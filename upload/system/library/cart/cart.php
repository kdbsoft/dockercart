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

        $customer_group_discount = (float) $this->config->get(
            "config_customer_group_discount",
        );
        $customer_group_markup = (float) $this->config->get(
            "config_customer_group_markup",
        );
        $customer_group_id = (int) $this->config->get(
            "config_customer_group_id",
        );

        // Fallback: in some request flows startup config may not preload group pricing.
        // Read values directly from customer_group so cart/checkout prices stay consistent.
        if (
            $customer_group_id > 0 &&
            $customer_group_discount <= 0 &&
            $customer_group_markup <= 0
        ) {
            $has_markup_percent = false;
            $markup_column_query = $this->db->query(
                "SHOW COLUMNS FROM " .
                    DB_PREFIX .
                    "customer_group LIKE 'markup_percent'",
            );

            if ($markup_column_query->num_rows) {
                $has_markup_percent = true;
            }

            if ($has_markup_percent) {
                $customer_group_query = $this->db->query(
                    "SELECT discount_percent, markup_percent FROM " .
                        DB_PREFIX .
                        "customer_group WHERE customer_group_id = '" .
                        $customer_group_id .
                        "'",
                );
            } else {
                $customer_group_query = $this->db->query(
                    "SELECT discount_percent FROM " .
                        DB_PREFIX .
                        "customer_group WHERE customer_group_id = '" .
                        $customer_group_id .
                        "'",
                );
            }

            if ($customer_group_query->num_rows) {
                $customer_group_discount =
                    (float) $customer_group_query->row["discount_percent"];

                if ($customer_group_discount < 0) {
                    $customer_group_discount = 0;
                } elseif ($customer_group_discount > 100) {
                    $customer_group_discount = 100;
                }

                if ($has_markup_percent) {
                    $customer_group_markup =
                        (float) $customer_group_query->row["markup_percent"];

                    if ($customer_group_markup < 0) {
                        $customer_group_markup = 0;
                    } elseif ($customer_group_markup > 100) {
                        $customer_group_markup = 100;
                    }
                }
            }
        }

        if ($customer_group_discount > 0 && $customer_group_markup > 0) {
            $customer_group_markup = 0;
        }

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

        // Bulk pricing/reward/download lookup maps for all cart product ids.
        $cg_price_map = [];
        $product_discount_map = [];
        $product_special_map = [];
        $reward_map = [];
        $download_map = [];
        $variant_rows = [];
        $variant_cg_price_map = [];
        $variant_special_map = [];
        $variant_discount_map = [];

        if ($cart_query->num_rows) {
            $price_product_ids = [];

            foreach ($cart_query->rows as $cart_row) {
                $price_product_ids[(int) $cart_row["product_id"]] = true;
            }

            $price_product_ids = array_keys($price_product_ids);
            $cg_id = (int) $this->config->get("config_customer_group_id");

            if (!empty($price_product_ids)) {
                $in = implode(",", $price_product_ids);

                $cg_query = $this->db->query(
                    "SELECT product_id, price FROM " .
                        DB_PREFIX .
                        "dockercart_product_customer_group_price WHERE product_id IN (" .
                        $in .
                        ") AND customer_group_id = '" .
                        $cg_id .
                        "'",
                );

                foreach ($cg_query->rows as $row) {
                    $cg_price_map[(int) $row["product_id"]] = (float) $row["price"];
                }

                $discount_query = $this->db->query(
                    "SELECT product_id, price, quantity, priority FROM " .
                        DB_PREFIX .
                        "product_discount WHERE product_id IN (" .
                        $in .
                        ") AND customer_group_id = '" .
                        $cg_id .
                        "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC",
                );

                foreach ($discount_query->rows as $row) {
                    $product_discount_map[(int) $row["product_id"]][] = $row;
                }

                $special_query = $this->db->query(
                    "SELECT product_id, price, priority FROM " .
                        DB_PREFIX .
                        "product_special WHERE product_id IN (" .
                        $in .
                        ") AND customer_group_id = '" .
                        $cg_id .
                        "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC",
                );

                foreach ($special_query->rows as $row) {
                    $product_special_map[(int) $row["product_id"]][] = $row;
                }

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

                $variant_query = $this->db->query(
                    "SELECT pv.*, cgp.price AS cg_price FROM " .
                        DB_PREFIX .
                        "product_variant pv LEFT JOIN " .
                        DB_PREFIX .
                        "dockercart_product_variant_customer_group_price cgp ON (cgp.variant_id = pv.variant_id AND cgp.customer_group_id = '" .
                        $cg_id .
                        "') WHERE pv.variant_id IN (" .
                        $in_v .
                        ") AND pv.status = '1'",
                );

                foreach ($variant_query->rows as $row) {
                    $variant_rows[(int) $row["variant_id"]] = $row;

                    if ($row["cg_price"] !== null && (float) $row["cg_price"] > 0) {
                        $variant_cg_price_map[(int) $row["variant_id"]] = (float) $row["cg_price"];
                    }
                }

                // Default variant per product: the product-level special only
                // falls back to the default variant (mirrors the product page).
                $default_variant_map = [];

                $dv_query = $this->db->query(
                    "SELECT product_id, default_variant_id FROM " .
                        DB_PREFIX .
                        "product_configurable WHERE product_id IN (" .
                        $in .
                        ") AND default_variant_id IS NOT NULL AND default_variant_id > 0",
                );

                foreach ($dv_query->rows as $row) {
                    $default_variant_map[(int) $row["product_id"]] = (int) $row["default_variant_id"];
                }

                $vs_query = $this->db->query(
                    "SELECT variant_id, price FROM " .
                        DB_PREFIX .
                        "dockercart_product_variant_special WHERE variant_id IN (" .
                        $in_v .
                        ") AND customer_group_id = '" .
                        $cg_id .
                        "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC",
                );

                foreach ($vs_query->rows as $row) {
                    if (!isset($variant_special_map[(int) $row["variant_id"]])) {
                        $variant_special_map[(int) $row["variant_id"]] = (float) $row["price"];
                    }
                }

                $vd_query = $this->db->query(
                    "SELECT variant_id, price, quantity FROM " .
                        DB_PREFIX .
                        "dockercart_product_variant_discount WHERE variant_id IN (" .
                        $in_v .
                        ") AND customer_group_id = '" .
                        $cg_id .
                        "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC",
                );

                foreach ($vd_query->rows as $row) {
                    $variant_discount_map[(int) $row["variant_id"]][] = $row;
                }
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

                        if (isset($variant_cg_price_map[$variant_id]) && $variant_cg_price_map[$variant_id] > 0) {
                            $product_query["row"]["price"] = $variant_cg_price_map[$variant_id];
                        }
                    } else {
                        $stock = false;
                    }
                }

                $price = $product_query["row"]["price"];
                $has_variant_group_price = false;

                if ($variant_id > 0) {
                    if (isset($variant_special_map[$variant_id])) {
                        $best_special = $variant_special_map[$variant_id];

                        if ((float) $best_special < (float) $price) {
                            $price = (float) $best_special;
                        }
                    } elseif (
                        isset($product_special_map[(int) $cart["product_id"]])
                        && isset($default_variant_map[(int) $cart["product_id"]])
                        && (int) $default_variant_map[(int) $cart["product_id"]] === $variant_id
                    ) {
                        // The product-level special only applies to the default
                        // variant (mirrors the product page); other variants
                        // price by their own data.
                        $best_product_special = (float) $product_special_map[(int) $cart["product_id"]][0]["price"];

                        if ($best_product_special < (float) $price) {
                            $price = $best_product_special;
                        }
                    }

                    // Variant quantity discounts (DockerCart)
                    $variant_discount_quantity = 0;

                    foreach ($cart_query->rows as $cart_2) {
                        if ((int)$cart_2["product_id"] != (int)$cart["product_id"]) {
                            continue;
                        }

                        $cart_2_options = json_decode($cart_2["option"], true);
                        $cart_2_variant_id = isset($cart_2_options["variant_id"]) ? (int)$cart_2_options["variant_id"] : 0;

                        if ($cart_2_variant_id == $variant_id) {
                            $variant_discount_quantity += (float)$cart_2["quantity"];
                        }
                    }

                    if (isset($variant_discount_map[$variant_id])) {
                        foreach ($variant_discount_map[$variant_id] as $vd_row) {
                            if ((float) $vd_row["quantity"] <= $variant_discount_quantity) {
                                // Apply only when it beats the current price
                                // (special / group price), mirroring the plain
                                // product flow below.
                                if ((float) $vd_row["price"] < (float) $price) {
                                    $price = (float) $vd_row["price"];
                                }
                                break;
                            }
                        }
                    }

                    // DockerCart: variant-level customer group price override
                    if (isset($variant_cg_price_map[$variant_id]) && $variant_cg_price_map[$variant_id] > 0) {
                        $has_variant_group_price = true;
                    }

                    // Global % customer group discount/markup applies to variants
                    // the same way it applies to plain products below (and on
                    // the product page), unless a per-variant group price is set.
                    if ($has_variant_group_price) {
                        // Per-variant group price set — skip global % discount/markup
                    } elseif ($customer_group_discount > 0) {
                        $price *= (100 - $customer_group_discount) / 100;
                    } elseif ($customer_group_markup > 0) {
                        $price *= (100 + $customer_group_markup) / 100;
                    }
                }

                if (!$variant_id) {
                    // DockerCart: Per-product customer group price override
                    $has_customer_group_price = isset($cg_price_map[(int) $cart["product_id"]]) && $cg_price_map[(int) $cart["product_id"]] > 0;

                    if ($has_customer_group_price) {
                        $price = $cg_price_map[(int) $cart["product_id"]];
                    }

                    // Product Discounts
                    $discount_quantity = 0;

                    foreach ($cart_query->rows as $cart_2) {
                        if ($cart_2["product_id"] == $cart["product_id"]) {
                            $discount_quantity += $cart_2["quantity"];
                        }
                    }

                    if (isset($product_discount_map[(int) $cart["product_id"]])) {
                        foreach ($product_discount_map[(int) $cart["product_id"]] as $pd_row) {
                            if ((float) $pd_row["quantity"] <= $discount_quantity) {
                                $price = (float) $pd_row["price"];
                                break;
                            }
                        }
                    }

                    // Product Specials
                    if (isset($product_special_map[(int) $cart["product_id"]])) {
                        $best_special_price = (float) $product_special_map[(int) $cart["product_id"]][0]["price"];

                        if ($best_special_price < (float) $price) {
                            $price = $best_special_price;
                        }
                    }

                    if ($has_customer_group_price) {
                        // Per-product group price set — skip global % discount/markup
                    } elseif ($has_variant_group_price) {
                        // Variant-level group price set — skip global % discount/markup
                    } elseif ($customer_group_discount > 0) {
                        $price *= (100 - $customer_group_discount) / 100;
                    } elseif ($customer_group_markup > 0) {
                        $price *= (100 + $customer_group_markup) / 100;
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
