<?php
namespace Cart;
class Cart
{
    private $data = [];
    private $products_cache = null;
    private $config;
    private $customer;
    private $session;
    private $db;
    private $tax;
    private $weight;
    private $registry;
    /** @var \Cart\Currency */
    private $currency;

    public function __construct($registry)
    {
        $this->registry = $registry;
        $this->config = $registry->get("config");
        $this->customer = $registry->get("customer");
        $this->session = $registry->get("session");
        $this->db = $registry->get("db");
        $this->tax = $registry->get("tax");
        $this->weight = $registry->get("weight");
        $this->currency = $registry->get("currency");

        // Remove guest carts (customer_id = 0) that are older than 30 days to avoid accumulation when session IDs rotate
        $this->db->query(
            "DELETE FROM " .
                DB_PREFIX .
                "cart WHERE customer_id = '0' AND date_added < DATE_SUB(NOW(), INTERVAL 30 DAY)",
        );

        // Prune stale order-creation claims (abandoned checkouts) so a session
        // id that is never reused cannot accumulate rows forever.
        $this->db->query(
            "DELETE FROM " .
                DB_PREFIX .
                "order_claim WHERE date_added < DATE_SUB(NOW(), INTERVAL 1 DAY)",
        );

        // Try to recover guest carts when session id has changed by using a persistent cookie.
        // This helps when session storage or session IDs rotate but the user returns shortly after.
        if (isset($registry)) {
            // `request` may not always be in registry in some contexts, guard usage
            if ($registry->get("request")) {
                $request = $registry->get("request");

                if (isset($request->cookie["cart_session"])) {
                    $old_session = $this->db->escape(
                        $request->cookie["cart_session"],
                    );

                    if (
                        $old_session &&
                        $old_session !=
                            $this->db->escape($this->session->getId())
                    ) {
                        // Update any guest cart entries from the old session id to the current
                        $this->db->query(
                            "UPDATE " .
                                DB_PREFIX .
                                "cart SET session_id = '" .
                                $this->db->escape($this->session->getId()) .
                                "' WHERE session_id = '" .
                                $old_session .
                                "' AND customer_id = '0'",
                        );
                    }
                }

                // Ensure cookie is present for future requests. If missing or differs, set it for 30 days.
                if (
                    !isset($request->cookie["cart_session"]) ||
                    $request->cookie["cart_session"] != $this->session->getId()
                ) {
                    @setcookie(
                        "cart_session",
                        $this->session->getId(),
                        time() + 60 * 60 * 24 * 30,
                        "/",
                    );
                    // Update PHP superglobal so subsequent logic in this request can read it.
                    $_COOKIE["cart_session"] = $this->session->getId();
                }
            }
        }

        if ($this->customer->getId()) {
            // We want to change the session ID on all the old items in the customers cart
            $this->db->query(
                "UPDATE " .
                    DB_PREFIX .
                    "cart SET session_id = '" .
                    $this->db->escape($this->session->getId()) .
                    "' WHERE customer_id = '" .
                    (int) $this->customer->getId() .
                    "'",
            );

            // Once the customer is logged in we want to update the customers cart
            $cart_query = $this->db->query(
                "SELECT * FROM " .
                    DB_PREFIX .
                    "cart WHERE customer_id = '0' AND session_id = '" .
                    $this->db->escape($this->session->getId()) .
                    "'",
            );

            foreach ($cart_query->rows as $cart) {
                $this->db->query(
                    "DELETE FROM " .
                        DB_PREFIX .
                        "cart WHERE cart_id = '" .
                        (int) $cart["cart_id"] .
                        "'",
                );

                // The advantage of using $this->add is that it will check if the products already exist and increaser the quantity if necessary.
                $this->add(
                    $cart["product_id"],
                    $cart["quantity"],
                    json_decode($cart["option"]),
                );
            }

            $this->products_cache = null;
        }
    }

    private function normalizeQuantity($quantity, $default = 1.0)
    {
        $normalized = str_replace(",", ".", trim((string) $quantity));

        if (!is_numeric($normalized)) {
            return round((float) $default, 2);
        }

        return round((float) $normalized, 2);
    }

    public function getProducts()
    {
        if ($this->products_cache !== null) {
            return $this->products_cache;
        }

        $product_data = [];

        $cart_query = $this->db->query(
            "SELECT * FROM " .
                DB_PREFIX .
                "cart WHERE customer_id = '" .
                (int) $this->customer->getId() .
                "' AND session_id = '" .
                $this->db->escape($this->session->getId()) .
                "'",
        );

        // Bulk lookup maps (N+1 killer): built once per getProducts() call,
        // consumed inside the per-line loop below.
        $product_rows = [];
        $product_option_rows = [];
        $option_value_rows = [];

        if ($cart_query->num_rows) {
            $cart_product_ids = [];
            $cart_option_value_ids = [];

            foreach ($cart_query->rows as $cart_row) {
                $cart_product_ids[(int) $cart_row["product_id"]] = true;

                $decoded = json_decode($cart_row["option"], true);

                if (is_array($decoded)) {
                    foreach ($decoded as $poid => $value) {
                        if ($poid === 'variant_id') {
                            continue;
                        }

                        if (is_array($value)) {
                            foreach ($value as $pov_id) {
                                $cart_option_value_ids[(int) $pov_id] = true;
                            }
                        } else {
                            $cart_option_value_ids[(int) $value] = true;
                        }
                    }
                }
            }

            $cart_product_ids = array_keys($cart_product_ids);

            if (!empty($cart_product_ids)) {
                $product_query = $this->db->query(
                    "SELECT p.*, pd.*, pco.axis_ids FROM " .
                        DB_PREFIX .
                        "product_to_store p2s LEFT JOIN " .
                        DB_PREFIX .
                        "product p ON (p2s.product_id = p.product_id) LEFT JOIN " .
                        DB_PREFIX .
                        "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN (SELECT product_id, GROUP_CONCAT(option_id) AS axis_ids FROM " .
                        DB_PREFIX .
                        "product_configurable_option GROUP BY product_id) pco ON (pco.product_id = p.product_id) WHERE p2s.store_id = '" .
                        (int) $this->config->get("config_store_id") .
                        "' AND p2s.product_id IN (" .
                        implode(",", $cart_product_ids) .
                        ") AND pd.language_id = '" .
                        (int) $this->config->get("config_language_id") .
                        "' AND p.date_available <= NOW() AND p.status = '1'",
                );

                foreach ($product_query->rows as $row) {
                    $product_rows[(int) $row["product_id"]] = $row;
                }
            }

            if (!empty($cart_option_value_ids)) {
                $option_value_query = $this->db->query(
                    "SELECT pov.product_option_value_id, pov.product_option_id, pov.option_id, pov.option_value_id, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix, COALESCE(cgp.price, pov.price) AS price, COALESCE(cgp.price_prefix, pov.price_prefix) AS price_prefix, ov.color_code, ovd.name FROM " .
                        DB_PREFIX .
                        "product_option_value pov LEFT JOIN " .
                        DB_PREFIX .
                        "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " .
                        DB_PREFIX .
                        "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) LEFT JOIN " .
                        DB_PREFIX .
                        "dockercart_product_option_value_customer_group_price cgp ON (cgp.product_option_value_id = pov.product_option_value_id AND cgp.customer_group_id = '" .
                        (int) $this->config->get("config_customer_group_id") .
                        "') WHERE pov.product_option_value_id IN (" .
                        implode(",", array_keys($cart_option_value_ids)) .
                        ") AND ovd.language_id = '" .
                        (int) $this->config->get("config_language_id") .
                        "'",
                );

                foreach ($option_value_query->rows as $row) {
                    $option_value_rows[(int) $row["product_option_value_id"]] = $row;
                }

                $option_ids = [];

                foreach ($option_value_rows as $row) {
                    $option_ids[(int) $row["product_option_id"]] = true;
                }

                if (!empty($option_ids)) {
                    $option_query = $this->db->query(
                        "SELECT po.product_option_id, po.product_id, po.option_id, od.name, o.type FROM " .
                            DB_PREFIX .
                            "product_option po LEFT JOIN `" .
                            DB_PREFIX .
                            "option` o ON (po.option_id = o.option_id) LEFT JOIN " .
                            DB_PREFIX .
                            "option_description od ON (o.option_id = od.option_id) WHERE po.product_option_id IN (" .
                            implode(",", array_keys($option_ids)) .
                            ") AND od.language_id = '" .
                            (int) $this->config->get("config_language_id") .
                            "'",
                    );

                    foreach ($option_query->rows as $row) {
                        $product_option_rows[(int) $row["product_option_id"]] = $row;
                    }
                }
            }
        }

        // DockerCart: reservation-aware availability. When checkout holds are
        // enabled, stock quantities are reduced by active holds of other
        // sessions (and all order-bound holds) so hasStock()/stock flags
        // reflect quantities that are actually still available.
        $reserved_map = [];

        $stock_reservation = new \DockercartStockReservation($this->registry);

        if ($stock_reservation->isEnabled()) {
            $cart_product_ids = [];

            foreach ($cart_query->rows as $cart_row) {
                $cart_product_ids[(int) $cart_row["product_id"]] = true;
            }

            if (!empty($cart_product_ids)) {
                $reserved_map = $stock_reservation->getReservedByProductIds(
                    array_keys($cart_product_ids),
                );
            }
        }

        // Bulk reward/download lookup maps for all cart product ids.
        $reward_map = [];
        $download_map = [];
        $variant_rows = [];

        $cart_product_ids = [];

        foreach ($cart_query->rows as $cart_row) {
            $cart_product_ids[(int) $cart_row["product_id"]] = true;
        }

        if (!empty($cart_product_ids)) {
            $in = implode(",", array_keys($cart_product_ids));
            $cg_id = (int) $this->config->get("config_customer_group_id");

            $reward_query = $this->db->query(
                "SELECT product_id, points FROM " .
                    DB_PREFIX .
                    "product_reward WHERE product_id IN (" .
                    $in .
                    ") AND customer_group_id = '" .
                    $cg_id .
                    "'",
            );

            foreach ($reward_query->rows as $row) {
                $reward_map[(int) $row["product_id"]] = $row["points"];
            }

            $download_query = $this->db->query(
                "SELECT p2d.product_id, d.download_id, d.filename, d.mask, dd.name FROM " .
                    DB_PREFIX .
                    "product_to_download p2d LEFT JOIN " .
                    DB_PREFIX .
                    "download d ON (p2d.download_id = d.download_id) LEFT JOIN " .
                    DB_PREFIX .
                    "download_description dd ON (d.download_id = dd.download_id) WHERE p2d.product_id IN (" .
                    $in .
                    ") AND dd.language_id = '" .
                    (int) $this->config->get("config_language_id") .
                    "' AND d.status = '1'",
            );

            foreach ($download_query->rows as $row) {
                $download_map[(int) $row["product_id"]][] = $row;
            }
        }

        // DockerCart: единый калькулятор цены (ProductPricingCalculator).
        // Раньше ценовая лестница (групповая цена → количественная скидка →
        // спеццена → глобальный % группы) была реализована здесь напрямую;
        // теперь она живёт в одном месте и переиспользуется каталогом,
        // корзиной и админкой заказов.
        $pricing_lines = [];

        foreach ($cart_query->rows as $cart_row) {
            $decoded = json_decode($cart_row["option"], true);
            $vid = isset($decoded["variant_id"]) ? (int) $decoded["variant_id"] : 0;

            $pricing_lines[] = [
                "product_id" => (int) $cart_row["product_id"],
                "variant_id" => $vid,
                "quantity" => (float) $cart_row["quantity"],
            ];

            if ($vid > 0) {
                $variant_ids[$vid] = true;
            }
        }

        $pricing_result = [];
        $calculator = new \ProductPricingCalculator($this->registry);

        if (!empty($pricing_lines)) {
            $pricing_result = $calculator->calculateForLines($pricing_lines);
        }

        // Variant data for all cart lines that carry a variant_id
        $variant_ids = [];

        foreach ($cart_query->rows as $cart_row) {
            $decoded = json_decode($cart_row["option"], true);
            $vid = isset($decoded["variant_id"]) ? (int) $decoded["variant_id"] : 0;

            if ($vid > 0) {
                $variant_ids[$vid] = true;
            }
        }

        $variant_ids = array_keys($variant_ids);

        if (!empty($variant_ids)) {
            $in_v = implode(",", $variant_ids);
            $cg_id = (int) $this->config->get("config_customer_group_id");

            $variant_query = $this->db->query(
                "SELECT pv.* FROM " .
                    DB_PREFIX .
                    "product_variant pv WHERE pv.variant_id IN (" .
                    $in_v .
                    ") AND pv.status = '1'",
            );

            foreach ($variant_query->rows as $row) {
                $variant_rows[(int) $row["variant_id"]] = $row;
            }
        }

        foreach ($cart_query->rows as $cart) {
            $stock = true;
            $cart["quantity"] = (float) $cart["quantity"];

            $product_query = isset($product_rows[(int) $cart["product_id"]])
                ? ["row" => $product_rows[(int) $cart["product_id"]], "num_rows" => 1]
                : ["row" => [], "num_rows" => 0];

            if ($product_query["num_rows"] && $cart["quantity"] > 0) {
                $option_price = 0;
                $option_points = 0;
                $option_weight = 0;

                $option_data = [];

                $axis_option_ids = [];
                if (!empty($product_query["row"]["axis_ids"])) {
                    foreach (explode(',', $product_query["row"]["axis_ids"]) as $aid) {
                        $axis_option_ids[] = (int)$aid;
                    }
                }

                $axis_selection = [];

                foreach (
                    json_decode($cart["option"])
                    as $product_option_id => $value
                ) {
                    if ($product_option_id === 'variant_id') {
                        continue;
                    }
                    $option_query = isset($product_option_rows[(int) $product_option_id])
                        ? ["row" => $product_option_rows[(int) $product_option_id], "num_rows" => 1]
                        : ["row" => [], "num_rows" => 0];

                    if ($option_query["num_rows"]) {
                        $is_axis = !empty($axis_option_ids) && in_array((int)$option_query["row"]["option_id"], $axis_option_ids);

                        if (
                            $option_query["row"]["type"] == "select" ||
                            $option_query["row"]["type"] == "radio" ||
                            $option_query["row"]["type"] == "color"
                        ) {
                            $option_value_query = isset($option_value_rows[(int) $value])
                                ? ["row" => $option_value_rows[(int) $value], "num_rows" => 1]
                                : ["row" => [], "num_rows" => 0];

                            if ($option_value_query["num_rows"]) {
                                if ($is_axis) {
                                    $axis_selection[(int)$option_query["row"]["option_id"]] = (int)$option_value_query["row"]["option_value_id"];
                                }

                                if (!$is_axis) {
                                    if (
                                        $option_value_query["row"]["price_prefix"] ==
                                        "+"
                                    ) {
                                        $option_price +=
                                            $option_value_query["row"]["price"];
                                    } elseif (
                                        $option_value_query["row"]["price_prefix"] ==
                                        "-"
                                    ) {
                                        $option_price -=
                                            $option_value_query["row"]["price"];
                                    }

                                    if (
                                        $option_value_query["row"]["points_prefix"] ==
                                        "+"
                                    ) {
                                        $option_points +=
                                            $option_value_query["row"]["points"];
                                    } elseif (
                                        $option_value_query["row"]["points_prefix"] ==
                                        "-"
                                    ) {
                                        $option_points -=
                                            $option_value_query["row"]["points"];
                                    }

                                    if (
                                        $option_value_query["row"]["weight_prefix"] ==
                                        "+"
                                    ) {
                                        $option_weight +=
                                            $option_value_query["row"]["weight"];
                                    } elseif (
                                        $option_value_query["row"]["weight_prefix"] ==
                                        "-"
                                    ) {
                                        $option_weight -=
                                            $option_value_query["row"]["weight"];
                                    }
                                }

                                $option_data[] = [
                                    "product_option_id" => $product_option_id,
                                    "product_option_value_id" => $value,
                                    "option_id" =>
                                        $option_query["row"]["option_id"],
                                    "option_value_id" =>
                                        $option_value_query["row"][
                                            "option_value_id"
                                        ],
                                    "name" => $option_query["row"]["name"],
                                    "value" => $option_value_query["row"]["name"],
                                    "type" => $option_query["row"]["type"],
                                    "price" =>
                                        $option_value_query["row"]["price"],
                                    "price_prefix" =>
                                        $option_value_query["row"][
                                            "price_prefix"
                                        ],
                                    "points" =>
                                        $option_value_query["row"]["points"],
                                    "points_prefix" =>
                                        $option_value_query["row"][
                                            "points_prefix"
                                        ],
                                    "weight" =>
                                        $option_value_query["row"]["weight"],
                                    "weight_prefix" =>
                                        $option_value_query["row"][
                                            "weight_prefix"
                                        ],
                                ];
                            }
                        } elseif (
                            $option_query["row"]["type"] == "checkbox" &&
                            is_array($value)
                        ) {
                            foreach ($value as $product_option_value_id) {
                                $option_value_query = isset($option_value_rows[(int) $product_option_value_id])
                                    ? ["row" => $option_value_rows[(int) $product_option_value_id], "num_rows" => 1]
                                    : ["row" => [], "num_rows" => 0];

                                if ($option_value_query["num_rows"]) {
                                    if (!$is_axis) {
                                        if (
                                            $option_value_query["row"][
                                                "price_prefix"
                                            ] == "+"
                                        ) {
                                            $option_price +=
                                                $option_value_query["row"]["price"];
                                        } elseif (
                                            $option_value_query["row"][
                                                "price_prefix"
                                            ] == "-"
                                        ) {
                                            $option_price -=
                                                $option_value_query["row"]["price"];
                                        }

                                        if (
                                            $option_value_query["row"][
                                                "points_prefix"
                                            ] == "+"
                                        ) {
                                            $option_points +=
                                                $option_value_query["row"]["points"];
                                        } elseif (
                                            $option_value_query["row"][
                                                "points_prefix"
                                            ] == "-"
                                        ) {
                                            $option_points -=
                                                $option_value_query["row"]["points"];
                                        }

                                        if (
                                            $option_value_query["row"][
                                                "weight_prefix"
                                            ] == "+"
                                        ) {
                                            $option_weight +=
                                                $option_value_query["row"]["weight"];
                                        } elseif (
                                            $option_value_query["row"][
                                                "weight_prefix"
                                            ] == "-"
                                        ) {
                                            $option_weight -=
                                                $option_value_query["row"]["weight"];
                                        }
                                    }

                                    $option_data[] = [
                                        "product_option_id" => $product_option_id,
                                        "product_option_value_id" => $product_option_value_id,
                                        "option_id" =>
                                            $option_query["row"]["option_id"],
                                        "option_value_id" =>
                                            $option_value_query["row"][
                                                "option_value_id"
                                            ],
                                        "name" => $option_query["row"]["name"],
                                        "value" =>
                                            $option_value_query["row"]["name"],
                                        "type" => $option_query["row"]["type"],
                                        "price" =>
                                            $option_value_query["row"]["price"],
                                        "price_prefix" =>
                                            $option_value_query["row"][
                                                "price_prefix"
                                            ],
                                        "points" =>
                                            $option_value_query["row"]["points"],
                                        "points_prefix" =>
                                            $option_value_query["row"][
                                                "points_prefix"
                                            ],
                                        "weight" =>
                                            $option_value_query["row"]["weight"],
                                        "weight_prefix" =>
                                            $option_value_query["row"][
                                                "weight_prefix"
                                            ],
                                    ];
                                }
                            }
                        } elseif (
                            $option_query["row"]["type"] == "text" ||
                            $option_query["row"]["type"] == "textarea" ||
                            $option_query["row"]["type"] == "file" ||
                            $option_query["row"]["type"] == "date" ||
                            $option_query["row"]["type"] == "datetime" ||
                            $option_query["row"]["type"] == "time"
                        ) {
                            $option_data[] = [
                                "product_option_id" => $product_option_id,
                                "product_option_value_id" => "",
                                "option_id" => $option_query["row"]["option_id"],
                                "option_value_id" => "",
                                "name" => $option_query["row"]["name"],
                                "value" => $value,
                                "type" => $option_query["row"]["type"],
                                "price" => "",
                                "price_prefix" => "",
                                "points" => "",
                                "points_prefix" => "",
                                "weight" => "",
                                "weight_prefix" => "",
                            ];
                        }
                    }
                }

                $decoded_options = json_decode($cart["option"], true);
                $variant_id = isset($decoded_options['variant_id']) ? (int)$decoded_options['variant_id'] : 0;
                $variant_sku = '';

                if (!empty($axis_selection)) {
                    $pc = new \ProductConfigurable($this->registry);
                    $resolved = $pc->resolveVariant((int)$cart["product_id"], $axis_selection);

                    if (!empty($resolved)) {
                        $variant_id = (int)$resolved['variant_id'];
                    } elseif ($variant_id > 0) {
                        $variant_id = 0;
                        $stock = false;
                    }
                }

                if ($variant_id > 0) {
                    $variant_query = isset($variant_rows[$variant_id])
                        ? ["row" => $variant_rows[$variant_id], "num_rows" => 1]
                        : ["row" => [], "num_rows" => 0];

                    if ($variant_query["num_rows"]) {
                        $variant_sku = $variant_query["row"]["sku"];
                        $variant_model = $variant_query["row"]["model"];
                        $product_query["row"]["price"] = (float)$variant_query["row"]["price"];
                        $product_query["row"]["quantity"] = (float)$variant_query["row"]["quantity"];
                        $product_query["row"]["subtract"] = (int)$variant_query["row"]["subtract"];
                        $product_query["row"]["weight"] = (float)$variant_query["row"]["weight"];
                        $product_query["row"]["weight_class_id"] = (int)$variant_query["row"]["weight_class_id"];

                        if (!empty($variant_model)) {
                            $product_query["row"]["model"] = $variant_model;
                        } elseif (!empty($variant_sku)) {
                            $product_query["row"]["model"] = $variant_sku;
                        }
                    } else {
                        $stock = false;
                    }
                }

                // DockerCart: единый калькулятор цены. Цена единицы и опций
                // берутся из ProductPricingCalculator (bulk-расчёт выше), а не
                // пересчитываются здесь — формула едина с каталогом и админкой.
                $price_key = (int) $cart["product_id"] . ":" . $variant_id;
                $line_pricing = isset($pricing_result["pricing"][$price_key])
                    ? $pricing_result["pricing"][$price_key]
                    : null;

                if ($line_pricing !== null) {
                    $price = (float) $line_pricing["price"];
                } else {
                    $price = (float) $product_query["row"]["price"];
                }

                // DockerCart: BXGY per-item discount (pre-tax, в валюте товара).
                // Скидка применяется к полной цене строки (базовая цена + опции) —
                // единая формула с промо-слоем ProductPricingCalculator и
                // админкой заказов (calculateProductPricing). Уменьшенная цена
                // строки автоматически попадает в getSubTotal()/getTaxes() и в
                // итоговые суммы корзины/чекаута.
                $bxgy_applied = false;
                $bxgy_per_unit = 0.0;
                $bxgy_units = 0;
                $bxgy_text = '';
                $bxgy_original_price = '';

                if (isset($pricing_result["promo"]["bxgy"][$price_key])) {
                    $bxgy_discount = $pricing_result["promo"]["bxgy"][$price_key];
                    $per_unit_discount = (float) ($bxgy_discount["per_unit"] ?? 0);
                    $units = (int) ($bxgy_discount["units"] ?? 0);

                    if ($per_unit_discount > 0 && $units > 0 && $cart["quantity"] > 0) {
                        $line_discount = $per_unit_discount * min($units, (int) $cart["quantity"]);
                        $line_total = ($price + $option_price) * $cart["quantity"];
                        $discounted_total = max(0, $line_total - $line_discount);
                        $discounted_unit = $discounted_total / $cart["quantity"];

                        // Скидка распределяется на всю строку (как в админке), поэтому
                        // базовая цена пересчитывается так, чтобы price + option_price
                        // давали дисконтированную цену строки.
                        $price = max(0, $discounted_unit - $option_price);

                        $bxgy_applied = true;
                        $bxgy_per_unit = $per_unit_discount;
                        $bxgy_units = $units;
                        $bxgy_text = isset($bxgy_discount["text"]) ? $bxgy_discount["text"] : '';
                        $bxgy_original_price = isset($bxgy_discount["original_price_formatted"]) ? $bxgy_discount["original_price_formatted"] : '';
                    }
                }

                // Reward Points
                $reward = isset($reward_map[(int) $cart["product_id"]]) ? $reward_map[(int) $cart["product_id"]] : 0;

                // Downloads
                $download_data = [];

                foreach (isset($download_map[(int) $cart["product_id"]]) ? $download_map[(int) $cart["product_id"]] : [] as $download) {
                    $download_data[] = [
                        "download_id" => $download["download_id"],
                        "name" => $download["name"],
                        "filename" => $download["filename"],
                        "mask" => $download["mask"],
                    ];
                }
                $product_quantity = (float) $product_query["row"]["quantity"];

                if (!empty($reserved_map)) {
                    $reservation_key =
                        (int) $cart["product_id"] . ":" . $variant_id;
                    $product_quantity -= (float) ($reserved_map[
                        $reservation_key
                    ] ?? 0);
                }

                // Stock
                if (
                    ($product_quantity <= 0 && !(int)$product_query["row"]['preorder']) ||
                    ($product_quantity > 0 && $product_quantity < $cart["quantity"])
                ) {
                    $stock = false;
                }

                // DockerCart Multicurrency: Convert price from product currency to default currency
                $multicurrency_price = $price;
                $multicurrency_option_price = $option_price;

                // Product currency already loaded in the product row (p.* includes currency_id);
                // currency values are in-memory in Cart\Currency (loaded once in constructor).
                $product_currency_id = !empty($product_query["row"]["currency_id"])
                    ? (int) $product_query["row"]["currency_id"]
                    : 0;

                if ($product_currency_id > 0) {
                    $default_currency_code = $this->config->get("config_currency");

                    if (method_exists($this->currency, "getCurrencies")) {
                        // In-memory map from Cart\Currency (catalog)
                        $currencies_map = $this->currency->getCurrencies();
                        $product_currency_value = 0.0;
                        $default_currency_value = 0.0;

                        foreach ($currencies_map as $c) {
                            if ((int)$c["currency_id"] === $product_currency_id) {
                                $product_currency_value = (float) $c["value"];
                            }

                            if ($c["code"] === $default_currency_code) {
                                $default_currency_value = (float) $c["value"];
                            }
                        }
                    } else {
                        // Legacy catalog Currency (admin side): only getValue(code)
                        // is available — resolve the product currency by code from
                        // the currency table (single query, not per cart line).
                        static $currency_code_by_id = [];

                        if (!isset($currency_code_by_id[$product_currency_id])) {
                            $currency_code_by_id[$product_currency_id] = null;

                            $currency_code_query = $this->db->query(
                                "SELECT code FROM " .
                                    DB_PREFIX .
                                    "currency WHERE currency_id = '" .
                                    (int) $product_currency_id .
                                    "'",
                            );

                            if ($currency_code_query->num_rows) {
                                $currency_code_by_id[$product_currency_id] = $currency_code_query->row["code"];
                            }
                        }

                        $product_currency_code = $currency_code_by_id[$product_currency_id];
                        $product_currency_value = $product_currency_code
                            ? (float) $this->currency->getValue($product_currency_code)
                            : 0.0;
                        $default_currency_value = (float) $this->currency->getValue($default_currency_code);
                    }

                    if ($product_currency_value > 0 && $default_currency_value > 0) {
                        $conversion_rate =
                            $default_currency_value /
                            $product_currency_value;
                        $multicurrency_price = $price * $conversion_rate;
                        $multicurrency_option_price =
                            $option_price * $conversion_rate;
                    }
                }

                $minimum_quantity = (float) $product_query["row"]["minimum"];

                if ($minimum_quantity <= 0) {
                    $minimum_quantity = 1.0;
                }

                $quantity_step = isset($product_query["row"]["quantity_step"])
                    ? (float) $product_query["row"]["quantity_step"]
                    : 1.0;

                if ($quantity_step <= 0) {
                    $quantity_step = 1.0;
                }

                $product_data[] = [
                    "cart_id" => $cart["cart_id"],
                    "product_id" => $product_query["row"]["product_id"],
                    "variant_id" => $variant_id,
                    "variant_sku" => $variant_sku,
                    "name" => $product_query["row"]["name"],
                    "model" => $product_query["row"]["model"],
                    "shipping" => $product_query["row"]["shipping"],
                    "image" => $product_query["row"]["image"],
                    "option" => $option_data,
                    "download" => $download_data,
                    "quantity" => (float) $cart["quantity"],
                    "minimum" => $minimum_quantity,
                    "quantity_step" => $quantity_step,
                    "subtract" => $product_query["row"]["subtract"],
                    "stock" => $stock,
                    "preorder" => !empty($product_query["row"]['preorder']),
                    "price" =>
                        $multicurrency_price + $multicurrency_option_price,
                    "total" =>
                        ($multicurrency_price + $multicurrency_option_price) *
                        $cart["quantity"],
                    "bxgy_applied" => $bxgy_applied,
                    "bxgy_per_unit" => $bxgy_per_unit,
                    "bxgy_units" => $bxgy_units,
                    "bxgy_text" => $bxgy_text,
                    "bxgy_original_price" => $bxgy_original_price,
                    "reward" => $reward * $cart["quantity"],
                    "points" => $product_query["row"]["points"]
                        ? ($product_query["row"]["points"] + $option_points) *
                            $cart["quantity"]
                        : 0,
                    "tax_class_id" => $product_query["row"]["tax_class_id"],
                    "weight" =>
                        ($product_query["row"]["weight"] + $option_weight) *
                        $cart["quantity"],
                    "weight_class_id" => $product_query["row"]["weight_class_id"],
                    "length" => $product_query["row"]["length"],
                    "width" => $product_query["row"]["width"],
                    "height" => $product_query["row"]["height"],
                    "length_class_id" => $product_query["row"]["length_class_id"],
                ];
            } else {
                $this->remove($cart["cart_id"]);
            }
        }

        $this->products_cache = $product_data;

        return $product_data;
    }

    public function add(
        $product_id,
        $quantity = 1,
        $option = [],
    ) {
        $quantity = $this->normalizeQuantity($quantity, 1);

        if ($quantity <= 0) {
            $quantity = 1;
        }

        $query = $this->db->query(
            "SELECT COUNT(*) AS total FROM " .
                DB_PREFIX .
                "cart WHERE customer_id = '" .
                (int) $this->customer->getId() .
                "' AND session_id = '" .
                $this->db->escape($this->session->getId()) .
                "' AND product_id = '" .
                (int) $product_id .
                "' AND `option` = '" .
                $this->db->escape(json_encode($option)) .
                "'",
        );

        if (!$query->row["total"]) {
            $this->db->query(
                "INSERT INTO " .
                    DB_PREFIX .
                    "cart SET customer_id = '" .
                    (int) $this->customer->getId() .
                    "', session_id = '" .
                    $this->db->escape($this->session->getId()) .
                    "', product_id = '" .
                    (int) $product_id .
                    "', `option` = '" .
                    $this->db->escape(json_encode($option)) .
                    "', quantity = '" .
                    (float) $quantity .
                    "', date_added = NOW()",
            );
        } else {
            $this->db->query(
                "UPDATE " .
                    DB_PREFIX .
                    "cart SET quantity = (quantity + " .
                    (float) $quantity .
                    ") WHERE customer_id = '" .
                    (int) $this->customer->getId() .
                    "' AND session_id = '" .
                    $this->db->escape($this->session->getId()) .
                    "' AND product_id = '" .
                    (int) $product_id .
                    "' AND `option` = '" .
                    $this->db->escape(json_encode($option)) .
                    "'",
            );
        }

        $this->products_cache = null;
    }

    public function update($cart_id, $quantity)
    {
        $quantity = $this->normalizeQuantity($quantity, 0);

        $this->db->query(
            "UPDATE " .
                DB_PREFIX .
                "cart SET quantity = '" .
                (float) $quantity .
                    "' WHERE cart_id = '" .
                (int) $cart_id .
                "' AND customer_id = '" .
                (int) $this->customer->getId() .
                "' AND session_id = '" .
                $this->db->escape($this->session->getId()) .
                "'",
        );

        $this->products_cache = null;
    }

    public function remove($cart_id)
    {
        $this->db->query(
            "DELETE FROM " .
                DB_PREFIX .
                "cart WHERE cart_id = '" .
                (int) $cart_id .
                "' AND customer_id = '" .
                (int) $this->customer->getId() .
                "' AND session_id = '" .
                $this->db->escape($this->session->getId()) .
                "'",
        );
    }

    public function clear()
    {
        $this->db->query(
            "DELETE FROM " .
                DB_PREFIX .
                "cart WHERE customer_id = '" .
                (int) $this->customer->getId() .
                "' AND session_id = '" .
                $this->db->escape($this->session->getId()) .
                "'",
        );

        $this->products_cache = null;
    }

    public function getWeight()
    {
        $weight = 0;

        foreach ($this->getProducts() as $product) {
            if ($product["shipping"]) {
                $weight += $this->weight->convert(
                    $product["weight"],
                    $product["weight_class_id"],
                    $this->config->get("config_weight_class_id"),
                );
            }
        }

        return $weight;
    }

    public function getSubTotal()
    {
        $total = 0;

        foreach ($this->getProducts() as $product) {
            $total += $product["total"];
        }

        return $total;
    }

    public function getTaxes()
    {
        $tax_data = [];

        foreach ($this->getProducts() as $product) {
            if ($product["tax_class_id"]) {
                $tax_rates = $this->tax->getRates(
                    $product["price"],
                    $product["tax_class_id"],
                );

                foreach ($tax_rates as $tax_rate) {
                    if (!isset($tax_data[$tax_rate["tax_rate_id"]])) {
                        $tax_data[$tax_rate["tax_rate_id"]] =
                            $tax_rate["amount"] * $product["quantity"];
                    } else {
                        $tax_data[$tax_rate["tax_rate_id"]] +=
                            $tax_rate["amount"] * $product["quantity"];
                    }
                }
            }
        }

        return $tax_data;
    }

    public function getTotal()
    {
        $total = 0;

        foreach ($this->getProducts() as $product) {
            $total +=
                $this->tax->calculate(
                    $product["price"],
                    $product["tax_class_id"],
                    $this->config->get("config_tax"),
                ) * $product["quantity"];
        }

        return $total;
    }

    public function countProducts()
    {
        $product_total = 0;

        $products = $this->getProducts();

        foreach ($products as $product) {
            $product_total += $product["quantity"];
        }

        return $product_total;
    }

    public function hasProducts()
    {
        return count($this->getProducts());
    }

    public function hasStock()
    {
        foreach ($this->getProducts() as $product) {
            if (!$product["stock"]) {
                return false;
            }
        }

        return true;
    }

    public function hasShipping()
    {
        foreach ($this->getProducts() as $product) {
            if ($product["shipping"]) {
                return true;
            }
        }

        return false;
    }

    public function hasDownload()
    {
        foreach ($this->getProducts() as $product) {
            if ($product["download"]) {
                return true;
            }
        }

        return false;
    }
}
