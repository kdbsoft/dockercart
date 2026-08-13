<?php
/** @property \DB $db
 * @property \Config $config
 * @property \Cart $cart
 * @property \Currency $currency
 * @property \Tax $tax
 * @property \Session $session
 * @property \Language $language */
/**
 * ProductPricingCalculator — единый источник формулы «цены товара для
 * покупателя». Раньше эта формула была продублирована в трёх местах:
 * каталог (ModelCatalogProduct), корзина (Cart::getProducts) и админка
 * заказов (ModelSaleOrder) — и уже разошлась в деталях (например, в
 * админке количественные скидки считались по количеству добавляемой
 * строки, а не по накопленному). Теперь все три места используют этот
 * класс.
 *
 * Два режима работы:
 *  - calculate()           — одиночный расчёт (страница товара, админка);
 *  - calculateForLines()   — bulk-расчёт по строкам (корзина): ~8-10
 *    запросов на всю корзину независимо от размера, как и раньше.
 *
 * BXGY и подарки — промо-слой ПОВЕРХ базовой цены: считаются после
 * базового расчёта через существующие Bxgy / catalog model и возвращаются
 * в секции 'promo'. Сама базовая цена — без опций (option_price отдельно),
 * чтобы вызывающие могли корректно пересчитывать BXGY.
 */
class ProductPricingCalculator
{
    private $registry;
    private $config;
    private $db;
    private $customer_group_id;
    private $cg_multiplier;
    private $cg_multiplier_loaded = false;

    public function __construct($registry, array $config = [])
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
        $this->customer_group_id = isset($config['customer_group_id'])
            ? (int) $config['customer_group_id']
            : (int) $this->config->get('config_customer_group_id');

        $discount = isset($config['customer_group_discount']) ? (float) $config['customer_group_discount'] : null;
        $markup = isset($config['customer_group_markup']) ? (float) $config['customer_group_markup'] : null;

        if ($discount !== null && $markup !== null) {
            $this->cg_multiplier = $this->buildMultiplier($discount, $markup);
            $this->cg_multiplier_loaded = true;
        }
    }

    public function __get($key)
    {
        return $this->registry->get($key);
    }

    /**
     * Одиночный расчёт цены единицы товара/варианта (без опций).
     *
     * @param int   $product_id
     * @param int   $variant_id
     * @param float $quantity    накопленное количество (для количественных скидок)
     * @param array $options     список product_option_value_id (не-axis)
     * @return array{price: float, base_price: float, special: ?float, special_date_end: int,
     *               option_price: float, reward: int, variant_id: int, variant_sku: string,
     *               model: string, is_configurable: bool, tax_class_id: int, quantity: float}
     */
    public function calculate($product_id, $variant_id = 0, $quantity = 1, array $options = [])
    {
        $product_id = (int) $product_id;
        $variant_id = (int) $variant_id;
        $quantity = max(1.0, (float) $quantity);

        $pc = new \ProductConfigurable($this->registry);
        $is_configurable = $variant_id > 0 ? true : $pc->isConfigurable($product_id);

        $variant = null;
        $variant_sku = '';
        $model = '';
        $tax_class_id = 0;

        if ($variant_id > 0) {
            $variant_query = $this->db->query("SELECT price, model, sku FROM `" . DB_PREFIX . "product_variant` WHERE variant_id = '" . (int) $variant_id . "' AND status = '1'");

            if (!$variant_query->num_rows) {
                // Вариант не найден/отключён — считаем как обычный товар.
                $variant_id = 0;
                $is_configurable = true;
            } else {
                $variant = $variant_query->row;
                $price = (float) $variant['price'];
                $variant_sku = isset($variant['sku']) ? (string) $variant['sku'] : '';
                $model = !empty($variant['model']) ? $variant['model'] : (!empty($variant['sku']) ? $variant['sku'] : '');
            }
        }

        if ($variant_id <= 0) {
            $product_query = $this->db->query("SELECT price, tax_class_id, model, sku FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int) $product_id . "'");

            if (!$product_query->num_rows) {
                return $this->emptyResult($product_id, 0, '', 0, 0, 0.0, $quantity);
            }

            $row = $product_query->row;
            $price = (float) $row['price'];
            $tax_class_id = (int) $row['tax_class_id'];
            $model = $row['model'];
            $variant_sku = '';
        }

        $product_special = $this->getProductSpecial($product_id);
        $product_special_date_end = 0;

        if ($product_special !== null && !empty($product_special['date_end'])) {
            $date_end = (string) $product_special['date_end'];

            if ($date_end !== '' && $date_end !== '0000-00-00' && $date_end !== '0000-00-00 00:00:00') {
                $product_special_date_end = (int) strtotime($date_end);
            }
        }

        $product_cg_price = $this->getProductCustomerGroupPrice($product_id);
        $has_product_cg_price = $product_cg_price !== null && $product_cg_price > 0;

        $variant_cg_price = null;
        $variant_special = null;

        if ($variant_id > 0) {
            $variant_cg_price = $this->getVariantCustomerGroupPrice($variant_id);

            if ($variant_cg_price !== null && $variant_cg_price > 0) {
                $price = $variant_cg_price;
            }
        } elseif ($has_product_cg_price) {
            $price = $product_cg_price;
        }

        $cg_multiplier = $this->getMultiplier();
        $applied_product_special = false;

        // Количественная скидка по накопленному количеству.
        if ($variant_id > 0) {
            $discount = $this->getVariantDiscount($variant_id, $quantity);

            if ($discount !== null && $discount < $price) {
                $price = $discount;
            }
        } else {
            $discount = $this->getProductDiscount($product_id, $quantity);

            if ($discount !== null && $discount < $price) {
                $price = $discount;
            }
        }

        $base_price = $price;

        // Спеццена.
        $applied_variant_special = false;

        if ($variant_id > 0) {
            $variant_special = $this->getVariantSpecialPrice($variant_id);

            if ($variant_special !== null) {
                // Глобальный % применяется к спеццене варианта только при
                // отсутствии групповой цены варианта (зеркалит корзину).
                if ($variant_cg_price === null || $variant_cg_price <= 0) {
                    $variant_special = $variant_special * $cg_multiplier;
                }

                if ($variant_special < $price) {
                    $price = $variant_special;
                    $applied_variant_special = true;
                }
            } elseif (
                $product_special !== null
                && $this->isDefaultVariant($product_id, $variant_id)
            ) {
                // Спеццена товара применяется только к дефолтному варианту
                // (зеркалит корзину и страницу товара).
                $special_price = $this->applyProductSpecialMultiplier($product_special['price'], $has_product_cg_price);

                if ($special_price < $price) {
                    $price = $special_price;
                    $applied_product_special = true;
                }
            }

            if ($variant_cg_price === null || $variant_cg_price <= 0) {
                if (!$applied_product_special && !$applied_variant_special) {
                    $price = $price * $cg_multiplier;
                }
            }
        } else {
            if ($product_special !== null) {
                $special_price = $this->applyProductSpecialMultiplier($product_special['price'], $has_product_cg_price);

                if ($special_price < $price) {
                    $price = $special_price;
                    $applied_product_special = true;
                }
            }

            if (!$has_product_cg_price && !$applied_product_special) {
                $price = $price * $cg_multiplier;
            }
        }

        // Спеццена отбрасывается, если >= цены.
        $special = null;
        $special_date_end = 0;

        if ($variant_id > 0) {
            if ($applied_variant_special) {
                $special = $price;

                // Спеццена варианта: дата окончания той же активной спеццены
                // (тот же ORDER BY, что и getVariantSpecialPrice).
                $pc = new \ProductConfigurable($this->registry);
                $variant_end = $pc->getVariantSpecialEndDate($variant_id, $this->customer_group_id);

                if ($variant_end !== null && $variant_end > 0) {
                    $special_date_end = (int) $variant_end;
                }
            } elseif ($applied_product_special && $price > 0) {
                // Спеццена товара применяется к дефолтному варианту — неси её
                // метаданные (таймер акции на странице товара).
                $special = $price;
                $special_date_end = $product_special_date_end;
            }
        } elseif ($applied_product_special && $price > 0) {
            $special = $price;
            $special_date_end = $product_special_date_end;
        }

        $option_price = $this->calculateOptionPrice($product_id, $options);

        $reward = 0;

        if ($this->customer_group_id) {
            $reward_query = $this->db->query("SELECT points FROM `" . DB_PREFIX . "product_reward` WHERE product_id = '" . $product_id . "' AND customer_group_id = '" . $this->customer_group_id . "'");

            if ($reward_query->num_rows) {
                $reward = (int) $reward_query->row['points'];
            }
        }

        return array(
            'price'             => round((float) $price, 4),
            'base_price'        => round((float) $base_price, 4),
            'special'           => $special !== null ? round((float) $special, 4) : null,
            'special_date_end'  => $special_date_end,
            'option_price'      => round((float) $option_price, 4),
            'reward'            => $reward,
            'variant_id'        => $variant_id,
            'variant_sku'       => $variant_sku,
            'model'             => $model,
            'is_configurable'   => $is_configurable,
            'tax_class_id'      => $tax_class_id,
            'quantity'          => $quantity,
        );
    }

    /**
     * Bulk-расчёт по строкам (корзина, заказ). Ключи — "product_id:variant_id".
     *
     * @param array $lines [['product_id'=>, 'variant_id'=>, 'quantity'=>, 'options'=>[]], ...]
     * @return array{pricing: array, promo: array}
     */
    public function calculateForLines(array $lines)
    {
        if (empty($lines)) {
            return array('pricing' => array(), 'promo' => array('bxgy' => array(), 'gifts' => array()));
        }

        // Накопленное количество по паре product_id:variant_id.
        $accum = array();

        foreach ($lines as $line) {
            $key = (int) $line['product_id'] . ':' . (int) ($line['variant_id'] ?? 0);
            $accum[$key] = ($accum[$key] ?? 0) + max(0.0, (float) ($line['quantity'] ?? 0));
        }

        $product_ids = array();

        foreach ($accum as $key => $qty) {
            [$pid] = explode(':', $key);
            $product_ids[(int) $pid] = true;
        }

        $product_ids = array_keys($product_ids);

        // Bulk-карты (по образцу Cart::getProducts()).
        $product_cg_price_map = $this->bulkProductCustomerGroupPrices($product_ids);
        $product_discount_map = $this->bulkProductDiscounts($product_ids);
        $product_special_map = $this->bulkProductSpecials($product_ids);

        $pc = new \ProductConfigurable($this->registry);
        $variant_pricing = $pc->getVariantPricingByProductIds($product_ids, $this->customer_group_id);
        $configurable_data = $pc->getConfigurableDataByProductIds($product_ids);
        $default_variants = $pc->getDefaultVariantsByProductIds($product_ids);

        $cg_multiplier = $this->getMultiplier();

        $pricing = array();

        foreach ($accum as $key => $quantity) {
            [$pid, $vid] = array_map('intval', explode(':', $key));
            $pricing[$key] = $this->priceForLine(
                $pid,
                $vid,
                $quantity,
                $product_cg_price_map,
                $product_discount_map,
                $product_special_map,
                $variant_pricing,
                $default_variants,
                $cg_multiplier,
                isset($configurable_data['configurable'][$pid])
            );
        }

        // Промо-слой поверх базовых цен: BXGY + подарки.
        $promo_lines = array();

        foreach ($lines as $line) {
            $key = (int) $line['product_id'] . ':' . (int) ($line['variant_id'] ?? 0);

            if (!isset($pricing[$key])) {
                continue;
            }

            $promo_lines[] = array(
                'product_id'   => (int) $line['product_id'],
                'variant_id'   => (int) ($line['variant_id'] ?? 0),
                'quantity'     => max(0.0, (float) ($line['quantity'] ?? 0)),
                'price'        => (float) $pricing[$key]['price'] + (float) $pricing[$key]['option_price'],
                'tax_class_id' => (int) $pricing[$key]['tax_class_id'],
            );
        }

        $bxgy = array();

        if (!empty($promo_lines)) {
            $bxgy_lib = new \Bxgy($this->registry);
            $bxgy = $bxgy_lib->getPerProductDiscountsFor($promo_lines);
        }

        return array(
            'pricing' => $pricing,
            'promo'   => array(
                'bxgy'  => $bxgy,
                'gifts' => array(),
            ),
        );
    }

    /* ────────────────────────── внутренняя лестница ────────────────────────── */

    private function priceForLine($product_id, $variant_id, $quantity, array $product_cg_price_map, array $product_discount_map, array $product_special_map, array $variant_pricing, array $default_variants, $cg_multiplier, $is_configurable)
    {
        $quantity = max(1.0, (float) $quantity);

        $tax_class_id = 0;
        $variant_sku = '';
        $model = '';

        // Базовая цена: вариант или товар.
        if ($variant_id > 0) {
            $variant_query = $this->db->query("SELECT price, model, sku, status FROM `" . DB_PREFIX . "product_variant` WHERE variant_id = '" . $variant_id . "'");

            if (!$variant_query->num_rows || !(int) $variant_query->row['status']) {
                $variant_id = 0;
                $is_configurable = true;
            } else {
                $row = $variant_query->row;
                $price = (float) $row['price'];
                $model = !empty($row['model']) ? $row['model'] : (!empty($row['sku']) ? $row['sku'] : '');
                $variant_sku = $row['sku'];
            }
        }

        if ($variant_id <= 0) {
            $product_query = $this->db->query("SELECT price, tax_class_id, model, sku FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $product_id . "'");

            if (!$product_query->num_rows) {
                return $this->emptyResult($product_id, 0, '', 0, 0, 0.0, $quantity);
            }

            $row = $product_query->row;
            $price = (float) $row['price'];
            $tax_class_id = (int) $row['tax_class_id'];
            $model = $row['model'];
            $variant_sku = '';
        }

        $product_special = isset($product_special_map[$product_id][0]) ? $product_special_map[$product_id][0] : null;
        $product_cg_price = isset($product_cg_price_map[$product_id]) ? $product_cg_price_map[$product_id] : null;
        $has_product_cg_price = $product_cg_price !== null && $product_cg_price > 0;
        $product_special_date_end = 0;

        if ($product_special !== null && !empty($product_special['date_end'])) {
            $date_end = (string) $product_special['date_end'];

            if ($date_end !== '' && $date_end !== '0000-00-00' && $date_end !== '0000-00-00 00:00:00') {
                $product_special_date_end = (int) strtotime($date_end);
            }
        }

        $variant_cg_price = null;
        $variant_special = null;

        if ($variant_id > 0) {
            $variant_cg_price = isset($variant_pricing['cg_prices'][$product_id][$variant_id])
                ? $variant_pricing['cg_prices'][$product_id][$variant_id]
                : null;

            if ($variant_cg_price !== null && $variant_cg_price > 0) {
                $price = $variant_cg_price;
            }
        } elseif ($has_product_cg_price) {
            $price = $product_cg_price;
        }

        $applied_product_special = false;

        // Количественная скидка по накопленному количеству.
        if ($variant_id > 0) {
            $discount = $this->pickVariantDiscount($variant_pricing, $variant_id, $quantity);

            if ($discount !== null && $discount < $price) {
                $price = $discount;
            }
        } else {
            $discount = $this->pickProductDiscount($product_discount_map, $product_id, $quantity);

            if ($discount !== null && $discount < $price) {
                $price = $discount;
            }
        }

        $base_price = $price;

        // Спеццена.
        $applied_variant_special = false;

        if ($variant_id > 0) {
            $variant_special = $this->pickVariantSpecial($variant_pricing, $variant_id);

            if ($variant_special !== null) {
                if ($variant_cg_price === null || $variant_cg_price <= 0) {
                    $variant_special = $variant_special * $cg_multiplier;
                }

                if ($variant_special < $price) {
                    $price = $variant_special;
                    $applied_variant_special = true;
                }
            } elseif (
                $product_special !== null
                && isset($default_variants[$product_id])
                && (int) $default_variants[$product_id]['variant_id'] === $variant_id
            ) {
                $special_price = $this->applyProductSpecialMultiplier($product_special['price'], $has_product_cg_price);

                if ($special_price < $price) {
                    $price = $special_price;
                    $applied_product_special = true;
                }
            }

            if ($variant_cg_price === null || $variant_cg_price <= 0) {
                if (!$applied_product_special && !$applied_variant_special) {
                    $price = $price * $cg_multiplier;
                }
            }
        } else {
            if ($product_special !== null) {
                $special_price = $this->applyProductSpecialMultiplier($product_special['price'], $has_product_cg_price);

                if ($special_price < $price) {
                    $price = $special_price;
                    $applied_product_special = true;
                }
            }

            if (!$has_product_cg_price && !$applied_product_special) {
                $price = $price * $cg_multiplier;
            }
        }

        $special = null;
        $special_date_end = 0;

        if ($variant_id > 0) {
            if ($applied_variant_special) {
                $special = $price;

                // Спеццена варианта: дата окончания той же активной спеццены.
                $variant_end = $this->pickVariantSpecialEnd($variant_pricing, $variant_id);

                if ($variant_end !== null && $variant_end > 0) {
                    $special_date_end = (int) $variant_end;
                }
            } elseif ($applied_product_special && $price > 0) {
                // Спеццена товара применяется к дефолтному варианту — неси её
                // метаданные (единая формула со страницей товара).
                $special = $price;
                $special_date_end = $product_special_date_end;
            }
        } elseif ($applied_product_special && $price > 0) {
            $special = $price;
            $special_date_end = $product_special_date_end;
        }

        $reward = 0;

        if ($this->customer_group_id) {
            $reward_query = $this->db->query("SELECT points FROM `" . DB_PREFIX . "product_reward` WHERE product_id = '" . $product_id . "' AND customer_group_id = '" . $this->customer_group_id . "'");

            if ($reward_query->num_rows) {
                $reward = (int) $reward_query->row['points'];
            }
        }

        return array(
            'price'             => round((float) $price, 4),
            'base_price'        => round((float) $base_price, 4),
            'special'           => $special !== null ? round((float) $special, 4) : null,
            'special_date_end'  => $special_date_end,
            'option_price'      => 0.0,
            'reward'            => $reward,
            'variant_id'        => $variant_id,
            'variant_sku'       => $variant_sku,
            'model'             => $model,
            'is_configurable'   => $is_configurable,
            'tax_class_id'      => $tax_class_id,
            'quantity'          => $quantity,
        );
    }

    /* ────────────────────────── данные (одиночные) ────────────────────────── */

    private function getProductSpecial($product_id)
    {
        if (!$this->customer_group_id) {
            return null;
        }

        $query = $this->db->query("SELECT price, date_end FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int) $product_id . "' AND customer_group_id = '" . $this->customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC, product_special_id ASC LIMIT 1");

        return $query->num_rows ? $query->row : null;
    }

    private function getProductCustomerGroupPrice($product_id)
    {
        if (!$this->customer_group_id) {
            return null;
        }

        $query = $this->db->query("SELECT price FROM `" . DB_PREFIX . "dockercart_product_customer_group_price` WHERE product_id = '" . (int) $product_id . "' AND customer_group_id = '" . $this->customer_group_id . "'");

        return $query->num_rows ? (float) $query->row['price'] : null;
    }

    private function getProductDiscount($product_id, $quantity)
    {
        if (!$this->customer_group_id) {
            return null;
        }

        $query = $this->db->query("SELECT price FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int) $product_id . "' AND customer_group_id = '" . $this->customer_group_id . "' AND quantity <= '" . (float) $quantity . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC LIMIT 1");

        return $query->num_rows ? (float) $query->row['price'] : null;
    }

    private function getVariantCustomerGroupPrice($variant_id)
    {
        if (!$this->customer_group_id) {
            return null;
        }

        $pc = new \ProductConfigurable($this->registry);

        return $pc->getVariantCustomerGroupPrice((int) $variant_id, $this->customer_group_id);
    }

    private function getVariantSpecialPrice($variant_id)
    {
        if (!$this->customer_group_id) {
            return null;
        }

        $pc = new \ProductConfigurable($this->registry);

        return $pc->getVariantSpecialPrice((int) $variant_id, $this->customer_group_id);
    }

    private function getVariantDiscount($variant_id, $quantity)
    {
        if (!$this->customer_group_id) {
            return null;
        }

        $pc = new \ProductConfigurable($this->registry);

        return $pc->getVariantDiscountPrice((int) $variant_id, $this->customer_group_id, $quantity);
    }

    private function isDefaultVariant($product_id, $variant_id)
    {
        $query = $this->db->query("SELECT default_variant_id FROM `" . DB_PREFIX . "product_configurable` WHERE product_id = '" . (int) $product_id . "' AND default_variant_id IS NOT NULL AND default_variant_id > 0");

        return $query->num_rows && (int) $query->row['default_variant_id'] === (int) $variant_id;
    }

    /* ────────────────────────── данные (bulk) ────────────────────────── */

    private function bulkProductCustomerGroupPrices(array $product_ids)
    {
        $map = array();

        if (empty($product_ids) || !$this->customer_group_id) {
            return $map;
        }

        $query = $this->db->query("SELECT product_id, price FROM `" . DB_PREFIX . "dockercart_product_customer_group_price` WHERE product_id IN (" . implode(',', $product_ids) . ") AND customer_group_id = '" . $this->customer_group_id . "'");

        foreach ($query->rows as $row) {
            $map[(int) $row['product_id']] = (float) $row['price'];
        }

        return $map;
    }

    private function bulkProductDiscounts(array $product_ids)
    {
        $map = array();

        if (empty($product_ids) || !$this->customer_group_id) {
            return $map;
        }

        $query = $this->db->query("SELECT product_id, price, quantity, priority FROM `" . DB_PREFIX . "product_discount` WHERE product_id IN (" . implode(',', $product_ids) . ") AND customer_group_id = '" . $this->customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC");

        foreach ($query->rows as $row) {
            $map[(int) $row['product_id']][] = $row;
        }

        return $map;
    }

    private function bulkProductSpecials(array $product_ids)
    {
        $map = array();

        if (empty($product_ids) || !$this->customer_group_id) {
            return $map;
        }

        $query = $this->db->query("SELECT product_id, price, priority, date_end FROM `" . DB_PREFIX . "product_special` WHERE product_id IN (" . implode(',', $product_ids) . ") AND customer_group_id = '" . $this->customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC, product_special_id ASC");

        foreach ($query->rows as $row) {
            $map[(int) $row['product_id']][] = $row;
        }

        return $map;
    }

    private function pickProductDiscount(array $map, $product_id, $quantity)
    {
        if (!isset($map[$product_id])) {
            return null;
        }

        foreach ($map[$product_id] as $row) {
            if ((float) $row['quantity'] <= $quantity) {
                return (float) $row['price'];
            }
        }

        return null;
    }

    private function pickVariantDiscount(array $variant_pricing, $variant_id, $quantity)
    {
        foreach ($variant_pricing['discounts'] as $vid => $rows) {
            if ((int) $vid !== (int) $variant_id) {
                continue;
            }

            // Первый подходящий тир в порядке строк (quantity DESC из bulk-запроса).
            foreach ($rows as $row) {
                if ((int) $row['customer_group_id'] !== $this->customer_group_id) {
                    continue;
                }

                if (!(($row['date_start'] === '0000-00-00' || $row['date_start'] < date('Y-m-d H:i:s')) && ($row['date_end'] === '0000-00-00' || $row['date_end'] > date('Y-m-d H:i:s')))) {
                    continue;
                }

                if ((float) $row['quantity'] <= $quantity) {
                    return (float) $row['price'];
                }
            }
        }

        return null;
    }

    private function pickVariantSpecial(array $variant_pricing, $variant_id)
    {
        foreach ($variant_pricing['specials'] as $vid => $rows) {
            if ((int) $vid !== (int) $variant_id) {
                continue;
            }

            // Низшая активная спеццена для текущей группы (как в корзине).
            $best = null;

            foreach ($rows as $row) {
                if ((int) $row['customer_group_id'] !== $this->customer_group_id) {
                    continue;
                }

                if (!(($row['date_start'] === '0000-00-00' || $row['date_start'] < date('Y-m-d H:i:s')) && ($row['date_end'] === '0000-00-00' || $row['date_end'] > date('Y-m-d H:i:s')))) {
                    continue;
                }

                if ($best === null || (float) $row['price'] < (float) $best) {
                    $best = (float) $row['price'];
                }
            }

            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * Дата окончания активной спеццены варианта (та же строка, что выбирает
     * pickVariantSpecial: низшая активная цена для текущей группы).
     */
    private function pickVariantSpecialEnd(array $variant_pricing, $variant_id)
    {
        foreach ($variant_pricing['specials'] as $vid => $rows) {
            if ((int) $vid !== (int) $variant_id) {
                continue;
            }

            $best = null;
            $best_end = null;

            foreach ($rows as $row) {
                if ((int) $row['customer_group_id'] !== $this->customer_group_id) {
                    continue;
                }

                if (!(($row['date_start'] === '0000-00-00' || $row['date_start'] < date('Y-m-d H:i:s')) && ($row['date_end'] === '0000-00-00' || $row['date_end'] > date('Y-m-d H:i:s')))) {
                    continue;
                }

                if ($best === null || (float) $row['price'] < (float) $best) {
                    $best = (float) $row['price'];
                    $best_end = $row['date_end'];
                }
            }

            if ($best !== null && $best_end !== null) {
                $date_end = (string) $best_end;

                if ($date_end !== '' && $date_end !== '0000-00-00' && $date_end !== '0000-00-00 00:00:00') {
                    return (int) strtotime($date_end);
                }
            }
        }

        return null;
    }

    /* ────────────────────────── опции и группа ────────────────────────── */

    /**
     * Сумма опционных цен (не-axis) для списка product_option_value_id.
     */
    public function calculateOptionPrice($product_id, array $option_value_ids)
    {
        $option_price = 0.0;

        if (empty($option_value_ids)) {
            return $option_price;
        }

        $ids = array_values(array_unique(array_map('intval', $option_value_ids)));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return $option_price;
        }

        $query = $this->db->query("SELECT pov.product_option_value_id, pov.option_id, COALESCE(cgp.price, pov.price) AS price, COALESCE(cgp.price_prefix, pov.price_prefix) AS price_prefix FROM `" . DB_PREFIX . "product_option_value` pov LEFT JOIN `" . DB_PREFIX . "dockercart_product_option_value_customer_group_price` cgp ON (cgp.product_option_value_id = pov.product_option_value_id AND cgp.customer_group_id = '" . $this->customer_group_id . "') WHERE pov.product_option_value_id IN (" . implode(',', $ids) . ") AND pov.product_id = '" . (int) $product_id . "'");

        // Axis-опции (варианты) в цену не входят.
        $axis_ids = array();
        $axis_query = $this->db->query("SELECT option_id FROM `" . DB_PREFIX . "product_configurable_option` WHERE product_id = '" . (int) $product_id . "'");

        foreach ($axis_query->rows as $row) {
            $axis_ids[(int) $row['option_id']] = true;
        }

        foreach ($query->rows as $row) {
            if (isset($axis_ids[(int) $row['option_id']])) {
                continue;
            }

            if ($row['price_prefix'] === '+') {
                $option_price += (float) $row['price'];
            } elseif ($row['price_prefix'] === '-') {
                $option_price -= (float) $row['price'];
            }
        }

        return $option_price;
    }

    /**
     * Глобальный % группы применяется к спеццене товара только при
     * отсутствии групповой цены товара (зеркалит корзину).
     */
    private function applyProductSpecialMultiplier($special_price, $has_product_cg_price)
    {
        $multiplier = $this->getMultiplier();

        if ($multiplier == 1.0) {
            return (float) $special_price;
        }

        if ($has_product_cg_price) {
            return (float) $special_price;
        }

        return (float) $special_price * $multiplier;
    }

    private function getMultiplier()
    {
        if (!$this->cg_multiplier_loaded) {
            $this->cg_multiplier = $this->loadGroupMultiplier();
            $this->cg_multiplier_loaded = true;
        }

        return $this->cg_multiplier;
    }

    private function loadGroupMultiplier()
    {
        if (!$this->customer_group_id) {
            return 1.0;
        }

        $query = $this->db->query("SELECT discount_percent, markup_percent FROM `" . DB_PREFIX . "customer_group` WHERE customer_group_id = '" . $this->customer_group_id . "'");

        if (!$query->num_rows) {
            return 1.0;
        }

        $discount = (float) $query->row['discount_percent'];
        $markup = (float) $query->row['markup_percent'];

        return $this->buildMultiplier($discount, $markup);
    }

    private function buildMultiplier($discount, $markup)
    {
        if ($discount < 0) {
            $discount = 0;
        } elseif ($discount > 100) {
            $discount = 100;
        }

        if ($markup < 0) {
            $markup = 0;
        } elseif ($markup > 100) {
            $markup = 100;
        }

        if ($discount > 0 && $markup > 0) {
            $markup = 0;
        }

        if ($discount > 0) {
            return (100 - $discount) / 100;
        }

        if ($markup > 0) {
            return (100 + $markup) / 100;
        }

        return 1.0;
    }

    private function emptyResult($product_id, $variant_id, $variant_sku, $tax_class_id, $reward, $option_price, $quantity)
    {
        return array(
            'price'             => 0.0,
            'base_price'        => 0.0,
            'special'           => null,
            'special_date_end'  => 0,
            'option_price'      => round((float) $option_price, 4),
            'reward'            => $reward,
            'variant_id'        => $variant_id,
            'variant_sku'       => $variant_sku,
            'model'             => '',
            'is_configurable'   => false,
            'tax_class_id'      => $tax_class_id,
            'quantity'          => $quantity,
        );
    }
}
