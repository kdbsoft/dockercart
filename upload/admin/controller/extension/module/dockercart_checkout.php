<?php
/**
 * DockerCart Checkout — Admin Controller
 *
 * One-Page Checkout Module for OpenCart 3.0.3.8+
 * Installation WITHOUT OCMOD - uses OpenCart Event System only
 *
 * License: GNU General Public License v3.0 (GPL-3.0)
 * Copyright (c) mathflow-bit
 */

class ControllerExtensionModuleDockercartCheckout extends Controller
{
    private $error = [];
    private $logger;

    // Configuration constants
    const CACHE_TTL_MIN = 0;
    const CACHE_TTL_MAX = 86400;
    const CACHE_TTL_DEFAULT = 3600;
    const LEGACY_NEWSLETTER_FIELD = "newsletter";
    const LEGACY_PAYMENT_AGREE_FIELD = "payment_agree";
    const MODULE_PREFIX = "module_dockercart_checkout_";

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        // Initialize centralized logger
        require_once DIR_SYSTEM . "library/dockercart_logger.php";
        $this->logger = new DockercartLogger($this->registry, "checkout");
    }

    /**
     * Main settings page
     */
    public function index()
    {
        $this->load->language("extension/module/dockercart_checkout");

        $module_heading_title = $this->language->get("heading_title");

        $this->document->setTitle($module_heading_title);

        $this->load->model("setting/setting");

        if (
            $this->request->server["REQUEST_METHOD"] == "POST" &&
            $this->validate()
        ) {
            // Process blocks structure if present
            if (
                isset($this->request->post["module_dockercart_checkout_blocks"])
            ) {
                $blocks =
                    $this->request->post["module_dockercart_checkout_blocks"];

                if (is_array($blocks)) {
                    $blocks = $this->processBlocksData($blocks);
                }

                $this->request->post[
                    "module_dockercart_checkout_blocks"
                ] = json_encode($blocks);
            }

            // Process shipping method overrides (convert to JSON for storage)
            if (
                isset(
                    $this->request->post[
                        "module_dockercart_checkout_shipping_override"
                    ],
                )
            ) {
                $shipping_overrides =
                    $this->request->post[
                        "module_dockercart_checkout_shipping_override"
                    ];
                if (is_array($shipping_overrides)) {
                    $this->request->post[
                        "module_dockercart_checkout_shipping_override"
                    ] = json_encode($shipping_overrides);
                }
            }

            // Process payment method overrides (convert to JSON for storage)
            if (
                isset(
                    $this->request->post[
                        "module_dockercart_checkout_payment_override"
                    ],
                )
            ) {
                $payment_overrides =
                    $this->request->post[
                        "module_dockercart_checkout_payment_override"
                    ];
                if (is_array($payment_overrides)) {
                    $this->request->post[
                        "module_dockercart_checkout_payment_override"
                    ] = json_encode($payment_overrides);
                }
            }

            $this->model_setting_setting->editSetting(
                "module_dockercart_checkout",
                $this->request->post,
            );

            $this->session->data["success"] = $this->language->get(
                "text_success",
            );

            $this->response->redirect(
                $this->url->link(
                    "marketplace/extension",
                    "user_token=" .
                        $this->session->data["user_token"] .
                        "&type=module",
                    true,
                ),
            );
        }

        // Error handling
        if (isset($this->error["warning"])) {
            $data["error_warning"] = $this->error["warning"];
        } else {
            $data["error_warning"] = "";
        }

        // Success message
        if (isset($this->session->data["success"])) {
            $data["success"] = $this->session->data["success"];
            unset($this->session->data["success"]);
        } else {
            $data["success"] = "";
        }

        // Breadcrumbs
        $data["breadcrumbs"] = [];

        $data["breadcrumbs"][] = [
            "text" => $this->language->get("text_home"),
            "href" => $this->url->link(
                "common/dashboard",
                "user_token=" . $this->session->data["user_token"],
                true,
            ),
        ];

        $data["breadcrumbs"][] = [
            "text" => $this->language->get("text_extension"),
            "href" => $this->url->link(
                "marketplace/extension",
                "user_token=" .
                    $this->session->data["user_token"] .
                    "&type=module",
                true,
            ),
        ];

        $data["breadcrumbs"][] = [
            "text" => $this->language->get("heading_title"),
            "href" => $this->url->link(
                "extension/module/dockercart_checkout",
                "user_token=" . $this->session->data["user_token"],
                true,
            ),
        ];

        $data["action"] = $this->url->link(
            "extension/module/dockercart_checkout",
            "user_token=" . $this->session->data["user_token"],
            true,
        );
        $data["cancel"] = $this->url->link(
            "marketplace/extension",
            "user_token=" . $this->session->data["user_token"] . "&type=module",
            true,
        );

        // AJAX URLs
        $data["save_blocks_ajax"] = $this->url->link(
            "extension/module/dockercart_checkout/saveBlocksOrder",
            "user_token=" . $this->session->data["user_token"],
            true,
        );
        $data["save_block_fields_ajax"] = $this->url->link(
            "extension/module/dockercart_checkout/saveBlockFieldsAjax",
            "user_token=" . $this->session->data["user_token"],
            true,
        );

        // Load settings with defaults
        $settings = [
            "status" => 0,
            "redirect_standard" => 1,
            "cache_ttl" => self::CACHE_TTL_DEFAULT,
            "theme" => "light",
            "custom_header_footer" => 1,
            "show_progress" => 1,
            "geo_detect" => 1,
            "guest_create_account" => 1,
            "show_company" => 0,
            "show_tax_id" => 0,
            "recaptcha_enabled" => 0,
            "recaptcha_site_key" => "",
            "recaptcha_secret_key" => "",
            "custom_css" => "",
            "custom_js" => "",
            "require_telephone" => 1,
            "require_address2" => 0,
            "require_postcode" => 1,
            "require_company" => 0,
            "journal3_compat" => 1,
            "debug" => 0,
            "default_country_id" => "",
            "default_zone_id" => "",
        ];

        foreach ($settings as $key => $default) {
            $fullKey = self::MODULE_PREFIX . $key;
            $data[$fullKey] = $this->getSettingValue($fullKey, $default);
        }

        // Load and merge checkout blocks configuration
        $blocks_data = $this->getSettingValue(
            "module_dockercart_checkout_blocks",
        );
        $default_blocks = $this->getDefaultBlocks();

        // Decode blocks if JSON string
        if (!empty($blocks_data) && is_string($blocks_data)) {
            $blocks_data = json_decode($blocks_data, true);
        }

        $data["blocks"] = $this->mergeBlocksWithDefaults(
            is_array($blocks_data) ? $blocks_data : [],
            $default_blocks,
        );

	// Remove legacy fields from admin UI
	$data["blocks"] = $this->cleanupBlocksForAdminUI($data["blocks"]);
	// Normalize localized labels/placeholders for existing saved blocks (including legacy raw keys like entry_comment)
	$data["blocks"] = $this->normalizeBlocksForAdminUI($data["blocks"]);

	// Load country model early — needed for address format reordering
	$this->load->model("localisation/country");

	// Reorder address fields based on default country's address_format
	$data["blocks"] = $this->reorderAddressFieldsByCountryFormat($data["blocks"]);

	// Pass ordered address field keys for Method Overrides tab
	$data["address_field_order"] = $this->getAddressFieldOrder();

	// Theme options
        $data["theme_options"] = [
            "light" => $this->language->get("text_theme_light"),
            "dark" => $this->language->get("text_theme_dark"),
            "custom" => $this->language->get("text_theme_custom"),
        ];

        // Load available shipping and payment methods for Method Overrides tab
        $data[
            "available_shipping_methods"
        ] = $this->getAvailableShippingMethods();
        $data[
            "available_payment_methods"
        ] = $this->getAvailablePaymentMethods();

        // getAvailable*Methods() loads languages of shipping/payment extensions and can overwrite common keys
        // (for example heading_title). Reload module language and lock explicit heading title for template.
        $this->load->language("extension/module/dockercart_checkout");
        $data["heading_title"] = $module_heading_title;

        // Load saved overrides
        $shipping_overrides_data = $this->getSettingValue(
            "module_dockercart_checkout_shipping_override",
            [],
        );
        if (is_string($shipping_overrides_data)) {
            $shipping_overrides_data = json_decode(
                $shipping_overrides_data,
                true,
            );
        }
        $data["shipping_overrides"] = is_array($shipping_overrides_data)
            ? $shipping_overrides_data
            : [];

        $payment_overrides_data = $this->getSettingValue(
            "module_dockercart_checkout_payment_override",
            [],
        );
        if (is_string($payment_overrides_data)) {
            $payment_overrides_data = json_decode(
                $payment_overrides_data,
                true,
            );
        }
        $data["payment_overrides"] = is_array($payment_overrides_data)
            ? $payment_overrides_data
            : [];

        // Ensure each shipping override has default field visibility (all visible)
        // country_id and zone_id are NOT togglable — they are always visible in the shipping_address block
        $default_fields = [
            "company" => 1,
            "address_1" => 1,
            "address_2" => 1,
            "city" => 1,
            "postcode" => 1,
        ];
        foreach ($data["available_shipping_methods"] as $code => $method_data) {
            if (!isset($data["shipping_overrides"][$code]["fields"])) {
                $data["shipping_overrides"][$code]["fields"] = $default_fields;
            } else {
                // Merge with defaults to ensure all keys exist
                $data["shipping_overrides"][$code]["fields"] = array_merge(
                    $default_fields,
                    $data["shipping_overrides"][$code]["fields"],
                );
            }
        }

	// Load available languages for multilingual support
	$this->load->model("localisation/language");
	$data["languages"] = $this->model_localisation_language->getLanguages();

	// Load countries for default country dropdown (model already loaded above)
	$data["admin_countries"] = $this->model_localisation_country->getCountries();

        $data["user_token"] = $this->session->data["user_token"];

        $data["header"] = $this->load->controller("common/header");
        $data["column_left"] = $this->load->controller("common/column_left");
        $data["footer"] = $this->load->controller("common/footer");

        $this->response->setOutput(
            $this->load->view("extension/module/dockercart_checkout", $data),
        );
    }

    /**
     * Get default checkout blocks configuration
     * New structure: rows with 1-3 columns per row
     * Each row contains: columns (1, 2, or 3) and fields array
     */
    private function getDefaultBlocks()
    {
        // Ensure language loaded when called from places that haven't loaded it yet
        $this->load->language("extension/module/dockercart_checkout");

        return [
            // LEFT COLUMN (60%)
            [
                "id" => "customer_details",
                "name" =>
                    $this->language->get("block_customer_details") ?:
                    "Customer Details",
                "column" => "left",
                "width" => 60,
                "enabled" => 1,
                "sort_order" => 1,
                "collapsible" => 0,
                "rows" => [
                    [
                        "columns" => 2,
                        "fields" => [
                            [
                                "id" => "firstname",
                                "label" => $this->language->get(
                                    "entry_firstname",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_firstname",
                                    ) ?:
                                    $this->language->get("entry_firstname"),
                            ],
                            [
                                "id" => "lastname",
                                "label" => $this->language->get(
                                    "entry_lastname",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_lastname",
                                    ) ?:
                                    $this->language->get("entry_lastname"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "email",
                                "label" => $this->language->get("entry_email"),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "email",
                                "placeholder" =>
                                    $this->language->get("placeholder_email") ?:
                                    $this->language->get("entry_email"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "telephone",
                                "label" => $this->language->get(
                                    "entry_telephone",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "tel",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_telephone",
                                    ) ?:
                                    $this->language->get("entry_telephone"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "fax",
                                "label" =>
                                    $this->language->get("entry_fax") ?: "Fax",
                                "visible" => 0,
                                "required" => 0,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get("placeholder_fax") ?:
                                    ($this->language->get("entry_fax") ?:
                                    "Fax"),
                            ],
                        ],
                    ],
                ],
            ],
            [
                "id" => "shipping_address",
                "name" =>
                    $this->language->get("block_shipping_address") ?:
                    "Shipping Address",
                "column" => "left",
                "width" => 60,
                "enabled" => 1,
                "sort_order" => 2,
                "collapsible" => 0,
                "rows" => [
                    [
                        "columns" => 2,
                        "fields" => [
                            [
                                "id" => "country_id",
                                "label" => $this->language->get(
                                    "entry_country",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "select",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_country",
                                    ) ?:
                                    $this->language->get("entry_country"),
                            ],
                            [
                                "id" => "zone_id",
                                "label" => $this->language->get("entry_zone"),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "select",
                                "placeholder" =>
                                    $this->language->get("placeholder_zone") ?:
                                    $this->language->get("entry_zone"),
                            ],
                        ],
                    ],
                ],
            ],
            [
                "id" => "payment_address",
                "name" =>
                    $this->language->get("block_payment_address") ?:
                    "Payment Address",
                "column" => "left",
                "width" => 60,
                "enabled" => 1,
                "sort_order" => 3,
                "collapsible" => 1,
                "rows" => [
                    [
                        "columns" => 2,
                        "fields" => [
                            [
                                "id" => "payment_firstname",
                                "label" => $this->language->get(
                                    "entry_firstname",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_firstname",
                                    ) ?:
                                    $this->language->get("entry_firstname"),
                            ],
                            [
                                "id" => "payment_lastname",
                                "label" => $this->language->get(
                                    "entry_lastname",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_lastname",
                                    ) ?:
                                    $this->language->get("entry_lastname"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "payment_company",
                                "label" => $this->language->get(
                                    "entry_company",
                                ),
                                "visible" => 0,
                                "required" => 0,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_company",
                                    ) ?:
                                    $this->language->get("entry_company"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "payment_address_1",
                                "label" => $this->language->get(
                                    "entry_address_1",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_address_1",
                                    ) ?:
                                    $this->language->get("entry_address_1"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "payment_address_2",
                                "label" => $this->language->get(
                                    "entry_address_2",
                                ),
                                "visible" => 0,
                                "required" => 0,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_address_2",
                                    ) ?:
                                    $this->language->get("entry_address_2"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 2,
                        "fields" => [
                            [
                                "id" => "payment_city",
                                "label" => $this->language->get("entry_city"),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_city",
                                    ) ?:
                                    $this->language->get("entry_city"),
                            ],
                            [
                                "id" => "payment_postcode",
                                "label" => $this->language->get(
                                    "entry_postcode",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "text",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_payment_postcode",
                                    ) ?:
                                    $this->language->get("entry_postcode"),
                            ],
                        ],
                    ],
                    [
                        "columns" => 2,
                        "fields" => [
                            [
                                "id" => "payment_country_id",
                                "label" => $this->language->get(
                                    "entry_country",
                                ),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "select",
                                "placeholder" =>
                                    $this->language->get(
                                        "placeholder_country",
                                    ) ?:
                                    $this->language->get("entry_country"),
                            ],
                            [
                                "id" => "payment_zone_id",
                                "label" => $this->language->get("entry_zone"),
                                "visible" => 1,
                                "required" => 1,
                                "type" => "select",
                                "placeholder" =>
                                    $this->language->get("placeholder_zone") ?:
                                    $this->language->get("entry_zone"),
                            ],
                        ],
                    ],
                ],
            ],
            [
                "id" => "payment_method",
                "name" =>
                    $this->language->get("block_payment_method") ?:
                    "Payment Method",
                "column" => "left",
                "width" => 60,
                "enabled" => 1,
                "sort_order" => 4,
                "collapsible" => 0,
                "rows" => [
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "payment_method",
                                "label" =>
                                    $this->language->get(
                                        "text_payment_method",
                                    ) ?:
                                    "Payment Method",
                                "visible" => 1,
                                "required" => 1,
                                "type" => "radio",
                            ],
                        ],
                    ],
                ],
            ],
            [
                "id" => "comment",
                "name" =>
                    $this->language->get("block_comment") ?: "Order Comment",
                "column" => "left",
                "width" => 60,
                "enabled" => 1,
                "sort_order" => 5,
                "collapsible" => 0,
                "rows" => [
                    [
                        "columns" => 1,
                        "fields" => [
                            [
                                "id" => "comment",
                                "label" =>
                                    $this->language->get("entry_comment") ?:
                                    "Order Comment",
                                "visible" => 1,
                                "required" => 0,
                                "type" => "textarea",
                                "placeholder" =>
                                    $this->language->get(
                                        "text_comment_placeholder",
                                    ) ?:
                                    "Notes about your order, e.g. special notes for delivery.",
                            ],
                        ],
                    ],
                ],
            ],
            [
                "id" => "terms",
                "name" =>
                    $this->language->get("block_agree") ?: "Terms & Conditions",
                "column" => "left",
                "width" => 60,
                "enabled" => 1,
                "sort_order" => 6,
                "collapsible" => 0,
                "rows" => [],
            ],
        ];
    }

    /**
     * AJAX: Save blocks order
     */
    public function saveBlocksOrder()
    {
        $json = [];
        $this->load->language("extension/module/dockercart_checkout");

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_checkout",
            )
        ) {
            $json["success"] = false;
            $json["error"] = $this->language->get("error_permission");
            $this->response->addHeader("Content-Type: application/json");
            $this->response->setOutput(json_encode($json));
            return;
        }

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (isset($data["blocks"]) && is_array($data["blocks"])) {
            try {
                $this->load->model("setting/setting");
                $this->model_setting_setting->editSettingValue(
                    "module_dockercart_checkout",
                    "module_dockercart_checkout_blocks",
                    json_encode($data["blocks"]),
                );
                $json["success"] = true;
            } catch (Exception $e) {
                $json["success"] = false;
                $json["error"] = sprintf(
                    $this->language->get("error_exception"),
                    $e->getMessage(),
                );
            }
        } else {
            $json["success"] = false;
            $json["error"] = $this->language->get("error_invalid_blocks_data");
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Install module - registers events, creates layout and SEO URL
     */
    public function install()
    {
        $this->load->model("setting/setting");
        $this->load->model("setting/event");
        $this->load->model("design/layout");

        // Default settings
        $defaults = [
            "module_dockercart_checkout_status" => 0,
            "module_dockercart_checkout_redirect_standard" => 1,
            "module_dockercart_checkout_cache_ttl" => 3600,
            "module_dockercart_checkout_theme" => "light",
            "module_dockercart_checkout_custom_header_footer" => 1,
            "module_dockercart_checkout_show_progress" => 1,
            "module_dockercart_checkout_geo_detect" => 1,
            "module_dockercart_checkout_guest_create_account" => 1,
            "module_dockercart_checkout_show_company" => 0,
            "module_dockercart_checkout_show_tax_id" => 0,
            "module_dockercart_checkout_recaptcha_enabled" => 0,
            "module_dockercart_checkout_journal3_compat" => 1,
            "module_dockercart_checkout_debug" => 0,
            "module_dockercart_checkout_default_country_id" => "",
            "module_dockercart_checkout_default_zone_id" => "",
            "module_dockercart_checkout_blocks" => json_encode(
                $this->getDefaultBlocks(),
            ),
        ];

        $this->model_setting_setting->editSetting(
            "module_dockercart_checkout",
            $defaults,
        );

        // Register events for checkout redirect
        $events = [
            // Redirect standard checkout to DockerCart checkout
            [
                "code" => "dockercart_checkout_redirect_checkout",
                "trigger" => "catalog/controller/checkout/checkout/before",
                "action" =>
                    "extension/module/dockercart_checkout/eventRedirectCheckout",
            ],
            // Redirect cart page to DockerCart checkout (optional, can be configured)
            [
                "code" => "dockercart_checkout_redirect_cart",
                "trigger" => "catalog/controller/checkout/cart/before",
                "action" =>
                    "extension/module/dockercart_checkout/eventRedirectCart",
            ],
            // Add custom scripts to header
            [
                "code" => "dockercart_checkout_header",
                "trigger" => "catalog/view/common/header/after",
                "action" =>
                    "extension/module/dockercart_checkout/eventHeaderAfter",
            ],
        ];

        foreach ($events as $event) {
            // Delete if exists (clean reinstall)
            $this->db->query(
                "DELETE FROM `" .
                    DB_PREFIX .
                    "event` WHERE `code` = '" .
                    $this->db->escape($event["code"]) .
                    "'",
            );

            // Add event
            $this->model_setting_event->addEvent(
                $event["code"],
                $event["trigger"],
                $event["action"],
            );
        }

        // Create layout for DockerCart Checkout
        $this->load->language("extension/module/dockercart_checkout");

        $layout_name =
            $this->language->get("text_layout_name") ?: "DockerCart Checkout";

        $layout_data = [
            "name" => $layout_name,
        ];

        // Check if layout exists
        $query = $this->db->query(
            "SELECT layout_id FROM `" .
                DB_PREFIX .
                "layout` WHERE `name` = '" .
                $this->db->escape($layout_name) .
                "'",
        );

        if (!$query->num_rows) {
            $this->db->query(
                "INSERT INTO `" .
                    DB_PREFIX .
                    "layout` SET `name` = '" .
                    $this->db->escape($layout_name) .
                    "'",
            );
            $layout_id = $this->db->getLastId();

            // Add layout route
            $this->db->query(
                "INSERT INTO `" .
                    DB_PREFIX .
                    "layout_route` SET
                `layout_id` = '" .
                    (int) $layout_id .
                    "',
                `store_id` = '0',
                `route` = 'checkout/dockercart_checkout'",
            );
        }

        // Add SEO URL if SEO URLs are enabled
        $seo_url_changed = false;
        if ($this->config->get("config_seo_url")) {
            // Check all languages
            $this->load->model("localisation/language");
            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                // Check if SEO URL already exists
                $query = $this->db->query(
                    "SELECT * FROM `" .
                        DB_PREFIX .
                        "seo_url`
                    WHERE `query` = 'checkout/dockercart_checkout'
                    AND `language_id` = '" .
                        (int) $language["language_id"] .
                        "'
                    AND `store_id` = '0'",
                );

                if (!$query->num_rows) {
                    $this->db->query(
                        "INSERT INTO `" .
                            DB_PREFIX .
                            "seo_url` SET
                        `store_id` = '0',
                        `language_id` = '" .
                            (int) $language["language_id"] .
                            "',
                        `query` = 'checkout/dockercart_checkout',
                        `keyword` = 'fast-checkout'",
                    );
                    $seo_url_changed = true;
                }
            }
        }

        if ($seo_url_changed) {
            $this->load->model("design/seo_url");
            $this->model_design_seo_url->invalidateSeoUrlCache();
        }

        $this->logger->info("Module installed successfully");
    }

    /**
     * Uninstall module - removes events and settings
     */
    public function uninstall()
    {
        $this->load->model("setting/setting");
        $this->load->model("setting/event");
        $layout_name = "DockerCart Checkout";

        // Remove events
        $this->db->query(
            "DELETE FROM `" .
                DB_PREFIX .
                "event` WHERE `code` LIKE 'dockercart_checkout_%'",
        );

        // Remove SEO URLs
        $this->db->query(
            "DELETE FROM `" .
                DB_PREFIX .
                "seo_url` WHERE `query` = 'checkout/dockercart_checkout'",
        );
        $this->load->model("design/seo_url");
        $this->model_design_seo_url->invalidateSeoUrlCache();

        // Remove layout
        $query = $this->db->query(
            "SELECT layout_id FROM `" .
                DB_PREFIX .
                "layout` WHERE `name` = '" .
                $this->db->escape($layout_name) .
                "'",
        );
        if ($query->num_rows) {
            $layout_id = $query->row["layout_id"];
            $this->db->query(
                "DELETE FROM `" .
                    DB_PREFIX .
                    "layout_route` WHERE `layout_id` = '" .
                    (int) $layout_id .
                    "'",
            );
            $this->db->query(
                "DELETE FROM `" .
                    DB_PREFIX .
                    "layout` WHERE `layout_id` = '" .
                    (int) $layout_id .
                    "'",
            );
        }

        // Remove settings
        $this->model_setting_setting->deleteSetting(
            "module_dockercart_checkout",
        );

        $this->logger->info("Module uninstalled successfully");
    }

    /**
     * AJAX: Save individual block fields without form submit
     */
    public function saveBlockFieldsAjax()
    {
        $json = ["success" => false, "error" => ""];
        $this->load->language("extension/module/dockercart_checkout");

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_checkout",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
            $this->response->addHeader("Content-Type: application/json");
            $this->response->setOutput(json_encode($json));
            return;
        }

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (!isset($data["block_index"]) || !isset($data["fields"])) {
            $this->load->language("extension/module/dockercart_checkout");
            $json["error"] =
                $this->language->get("error_missing_block_index_or_fields") .
                " Received: " .
                json_encode($data);
            $this->response->addHeader("Content-Type: application/json");
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $this->load->model("setting/setting");

            // Get current blocks
            $blocks_data = $this->config->get(
                "module_dockercart_checkout_blocks",
            );
            if (is_string($blocks_data)) {
                $blocks = json_decode($blocks_data, true);
            } else {
                $blocks = is_array($blocks_data) ? $blocks_data : [];
            }

            // Ensure it's an array
            if (!is_array($blocks)) {
                $blocks = [];
            }

            // Update the specific block's fields
            $block_index = intval($data["block_index"]);
            if (isset($blocks[$block_index])) {
                // Ensure fields is an array
                $fields = $data["fields"];
                if (is_string($fields)) {
                    $fields = json_decode($fields, true);
                }
                if (!is_array($fields)) {
                    $fields = [];
                }

                // Sanitize: remove any 'newsletter' fields — module does not manage newsletter subscriptions
                $fields = array_values(
                    array_filter($fields, function ($f) {
                        return !isset($f["id"]) || $f["id"] !== "newsletter";
                    }),
                );

                $blocks[$block_index]["fields"] = $fields;

                // Save back to settings
                $this->model_setting_setting->editSettingValue(
                    "module_dockercart_checkout",
                    "module_dockercart_checkout_blocks",
                    json_encode($blocks),
                );

                $json["success"] = true;
                $json["message"] = $this->language->get(
                    "text_block_fields_saved",
                );
            } else {
                $json["error"] = $this->language->get(
                    "error_block_index_not_found",
                );
            }
        } catch (Exception $e) {
            $json["error"] = sprintf(
                $this->language->get("error_exception"),
                $e->getMessage(),
            );
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Validate form
     */
    protected function validate()
    {
        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_checkout",
            )
        ) {
            $this->error["warning"] = $this->language->get("error_permission");
        }

        // Validate cache TTL
        if (
            isset($this->request->post["module_dockercart_checkout_cache_ttl"])
        ) {
            $ttl =
                (int) $this->request->post[
                    "module_dockercart_checkout_cache_ttl"
                ];
            if ($ttl < self::CACHE_TTL_MIN || $ttl > self::CACHE_TTL_MAX) {
                $this->error["warning"] = $this->language->get(
                    "error_cache_ttl",
                );
            }
        }

        return !$this->error;
    }

    /**
     * Merge saved blocks with default blocks
     */
    private function mergeBlocksWithDefaults($savedBlocks, $defaultBlocks)
    {
        $blocksById = [];
        foreach ($savedBlocks as $block) {
            if (isset($block["id"])) {
                $blocksById[$block["id"]] = $block;
            }
        }

        $finalBlocks = [];
        foreach ($defaultBlocks as $defaultBlock) {
            $blockId = $defaultBlock["id"];

            if (isset($blocksById[$blockId])) {
                $block = $this->migrateBlockStructure(
                    $blocksById[$blockId],
                    $defaultBlock,
                );
                $finalBlocks[] = $block;
            } else {
                $finalBlocks[] = $defaultBlock;
            }
        }

        return $finalBlocks;
    }

    /**
     * Migrate block from old structure to new and merge with defaults
     */
    private function migrateBlockStructure($savedBlock, $defaultBlock)
    {
        // Migration: convert old 'fields' to 'rows'
        if (isset($savedBlock["fields"]) && !isset($savedBlock["rows"])) {
            $fields = is_array($savedBlock["fields"])
                ? $savedBlock["fields"]
                : [];
            $savedBlock["rows"] = [];

            foreach ($fields as $field) {
                $savedBlock["rows"][] = [
                    "columns" => 1,
                    "fields" => [$field],
                ];
            }
            unset($savedBlock["fields"]);
        }

        // Use default rows if empty
        if (empty($savedBlock["rows"]) || !is_array($savedBlock["rows"])) {
            $savedBlock["rows"] = $defaultBlock["rows"];
            return $savedBlock;
        }

        // NOTE: Intentionally NOT restoring missing fields from defaults.
        // The default configuration has been updated to have fewer fields
        // (e.g., shipping_address now only has country_id and zone_id).
        // We respect the user's saved configuration and the updated defaults.
        // Only restore rows if the saved block has NO fields at all.
        $savedFieldIds = $this->extractFieldIds($savedBlock["rows"]);

        if (empty($savedFieldIds)) {
            // Block has no fields at all, use defaults
            $savedBlock["rows"] = $defaultBlock["rows"];
        }

        return $savedBlock;
    }

    /**
     * Extract all field IDs from rows
     */
    private function extractFieldIds($rows)
    {
        $fieldIds = [];

        foreach ($rows as $row) {
            if (isset($row["fields"]) && is_array($row["fields"])) {
                foreach ($row["fields"] as $field) {
                    if (isset($field["id"])) {
                        $fieldIds[$field["id"]] = true;
                    }
                }
            }
        }

        return $fieldIds;
    }

    /**
     * Find rows with missing fields
     */
    private function findMissingRows($defaultRows, $existingFieldIds)
    {
        $missingRows = [];

        foreach ($defaultRows as $row) {
            if (!isset($row["fields"]) || !is_array($row["fields"])) {
                continue;
            }

            foreach ($row["fields"] as $field) {
                if (
                    isset($field["id"]) &&
                    !isset($existingFieldIds[$field["id"]])
                ) {
                    $missingRows[] = $row;
                    break;
                }
            }
        }

        return $missingRows;
    }

    /**
     * Clean up blocks for admin UI (remove legacy fields)
     */
    private function cleanupBlocksForAdminUI($blocks)
    {
        $legacyFields = [
            self::LEGACY_NEWSLETTER_FIELD,
            self::LEGACY_PAYMENT_AGREE_FIELD,
        ];

        foreach ($blocks as &$block) {
            if (!isset($block["rows"]) || !is_array($block["rows"])) {
                continue;
            }

            foreach ($block["rows"] as &$row) {
                if (isset($row["fields"]) && is_array($row["fields"])) {
                    $row["fields"] = array_values(
                        array_filter($row["fields"], function ($field) use (
                            $legacyFields,
                        ) {
                            return !isset($field["id"]) ||
                                !in_array($field["id"], $legacyFields);
                        }),
                    );
                }
            }
        }

        return $blocks;
    }

    /**
     * Normalize block/field labels and placeholders for admin UI.
     * This keeps old saved configs in sync with current localization keys.
     */
    private function normalizeBlocksForAdminUI($blocks)
    {
        $blockTranslations = [
            "customer_details" => "block_customer_details",
            "shipping_address" => "block_shipping_address",
            "payment_address" => "block_payment_address",
            "shipping_method" => "block_shipping_method",
            "payment_method" => "block_payment_method",
            "coupon" => "block_coupon",
            "comment" => "block_comment",
            "terms" => "block_agree",
            "agree" => "block_agree",
            "cart" => "block_cart",
        ];

        $fieldLabelTranslations = [
            "firstname" => "entry_firstname",
            "lastname" => "entry_lastname",
            "email" => "entry_email",
            "telephone" => "entry_telephone",
            "fax" => "entry_fax",
            "company" => "entry_company",
            "address_1" => "entry_address_1",
            "address_2" => "entry_address_2",
            "city" => "entry_city",
            "postcode" => "entry_postcode",
            "country_id" => "entry_country",
            "zone_id" => "entry_zone",
            "payment_firstname" => "entry_firstname",
            "payment_lastname" => "entry_lastname",
            "payment_company" => "entry_company",
            "payment_address_1" => "entry_address_1",
            "payment_address_2" => "entry_address_2",
            "payment_city" => "entry_city",
            "payment_postcode" => "entry_postcode",
            "payment_country_id" => "entry_country",
            "payment_zone_id" => "entry_zone",
            "comment" => "entry_comment",
            "payment_method" => "text_payment_method",
        ];

        $fieldPlaceholderTranslations = [
            "firstname" => "placeholder_firstname",
            "lastname" => "placeholder_lastname",
            "email" => "placeholder_email",
            "telephone" => "placeholder_telephone",
            "fax" => "placeholder_fax",
            "company" => "placeholder_company",
            "address_1" => "placeholder_address_1",
            "address_2" => "placeholder_address_2",
            "city" => "placeholder_city",
            "postcode" => "placeholder_postcode",
            "country_id" => "placeholder_country",
            "zone_id" => "placeholder_zone",
            "payment_firstname" => "placeholder_payment_firstname",
            "payment_lastname" => "placeholder_payment_lastname",
            "payment_company" => "placeholder_payment_company",
            "payment_address_1" => "placeholder_payment_address_1",
            "payment_address_2" => "placeholder_payment_address_2",
            "payment_city" => "placeholder_payment_city",
            "payment_postcode" => "placeholder_payment_postcode",
            "payment_country_id" => "placeholder_country",
            "payment_zone_id" => "placeholder_zone",
            "comment" => "text_comment_placeholder",
        ];

        foreach ($blocks as &$block) {
            if (
                isset($block["id"]) &&
                isset($blockTranslations[$block["id"]])
            ) {
                $translatedBlockName = $this->language->get(
                    $blockTranslations[$block["id"]],
                );

                if (
                    $translatedBlockName &&
                    $translatedBlockName !== $blockTranslations[$block["id"]]
                ) {
                    $block["name"] = $translatedBlockName;
                }
            }

            if (!isset($block["rows"]) || !is_array($block["rows"])) {
                continue;
            }

            foreach ($block["rows"] as &$row) {
                if (!isset($row["fields"]) || !is_array($row["fields"])) {
                    continue;
                }

                foreach ($row["fields"] as &$field) {
                    if (empty($field["id"])) {
                        continue;
                    }

                    $field_id = (string) $field["id"];

                    if (isset($fieldLabelTranslations[$field_id])) {
                        $translatedLabel = $this->language->get(
                            $fieldLabelTranslations[$field_id],
                        );

                        if (
                            $translatedLabel &&
                            $translatedLabel !==
                                $fieldLabelTranslations[$field_id]
                        ) {
                            $field["label"] = $translatedLabel;
                        }
                    } elseif (
                        isset($field["label"]) &&
                        preg_match(
                            "/^(entry_|text_)/",
                            (string) $field["label"],
                        )
                    ) {
                        $translatedLabel = $this->language->get(
                            (string) $field["label"],
                        );

                        if (
                            $translatedLabel &&
                            $translatedLabel !== (string) $field["label"]
                        ) {
                            $field["label"] = $translatedLabel;
                        }
                    }

                    if (isset($fieldPlaceholderTranslations[$field_id])) {
                        $translatedPlaceholder = $this->language->get(
                            $fieldPlaceholderTranslations[$field_id],
                        );

                        if (
                            $translatedPlaceholder &&
                            $translatedPlaceholder !==
                                $fieldPlaceholderTranslations[$field_id]
                        ) {
                            $currentPlaceholder = isset($field["placeholder"])
                                ? trim((string) $field["placeholder"])
                                : "";

                            if (
                                $currentPlaceholder === "" ||
                                preg_match(
                                    "/^(entry_|text_|placeholder_)/",
                                    $currentPlaceholder,
                                )
                            ) {
                                $field["placeholder"] = $translatedPlaceholder;
                            }
                        }
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * Process blocks data: decode JSON strings and sanitize fields
     */
    private function processBlocksData($blocks)
    {
        foreach ($blocks as $idx => $block) {
            // Decode rows if JSON string
            if (isset($block["rows"]) && is_string($block["rows"])) {
                $blocks[$idx]["rows"] = $this->decodeJsonField($block["rows"]);
            }

            // Sanitize rows: remove legacy fields
            if (
                isset($blocks[$idx]["rows"]) &&
                is_array($blocks[$idx]["rows"])
            ) {
                $blocks[$idx]["rows"] = $this->sanitizeBlockRows(
                    $blocks[$idx]["rows"],
                );
            }

            // Legacy: handle old 'fields' structure
            if (isset($block["fields"]) && is_string($block["fields"])) {
                $blocks[$idx]["fields"] = $this->decodeJsonField(
                    $block["fields"],
                );
            }
        }

        return $blocks;
    }

    /**
     * Decode JSON field with HTML entity handling
     */
    private function decodeJsonField($jsonString)
    {
        if (empty($jsonString) || $jsonString === "null") {
            return [];
        }

        // Try HTML-decoded JSON first
        $decoded = json_decode(html_entity_decode($jsonString), true);

        // Fallback to plain JSON
        if ($decoded === null) {
            $decoded = json_decode($jsonString, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sanitize block rows: remove legacy/unsupported fields
     */
    private function sanitizeBlockRows($rows)
    {
        $legacyFields = [
            self::LEGACY_NEWSLETTER_FIELD,
            self::LEGACY_PAYMENT_AGREE_FIELD,
        ];

        foreach ($rows as $rowIdx => $row) {
            if (isset($row["fields"]) && is_array($row["fields"])) {
                $rows[$rowIdx]["fields"] = array_values(
                    array_filter($row["fields"], function ($field) use (
                        $legacyFields,
                    ) {
                        return !isset($field["id"]) ||
                            !in_array($field["id"], $legacyFields);
                    }),
                );
            }
        }

        return $rows;
    }

    /**
     * Get setting value with default fallback
     */
    private function getSettingValue($key, $default = null)
    {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }

        $value = $this->config->get($key);
        return $value !== null ? $value : $default;
    }

	/**
	 * Get ordered address field keys based on default country's address_format
	 */
	private function getAddressFieldOrder(): array
	{
		$defaultKeys = ["company", "address_1", "address_2", "city", "postcode"];

		$defaultCountryId = $this->config->get(
			"module_dockercart_checkout_default_country_id",
		);
		if (empty($defaultCountryId)) {
			$defaultCountryId = $this->config->get("config_country_id");
		}
		if (empty($defaultCountryId)) {
			return $defaultKeys;
		}

		$countryInfo = $this->model_localisation_country->getCountry(
			(int) $defaultCountryId,
		);
		if (empty($countryInfo["address_format"])) {
			return $defaultKeys;
		}

		$tokenToField = [
			"company" => "company",
			"address_1" => "address_1",
			"address_2" => "address_2",
			"city" => "city",
			"postcode" => "postcode",
		];

		$format = $countryInfo["address_format"];
		preg_match_all("/\{(\w+)\}/", $format, $matches);
		$fieldOrder = [];
		foreach ($matches[1] as $token) {
			if (
				isset($tokenToField[$token]) &&
				!in_array($tokenToField[$token], $fieldOrder, true)
			) {
				$fieldOrder[] = $tokenToField[$token];
			}
		}

		// Append any remaining default keys not found in format
		foreach ($defaultKeys as $key) {
			if (!in_array($key, $fieldOrder, true)) {
				$fieldOrder[] = $key;
			}
		}

		return $fieldOrder;
	}

	/**
	 * Reorder address fields in shipping_address block based on country's address_format
	 */
	private function reorderAddressFieldsByCountryFormat(array $blocks): array
	{
		$tokenToField = [
			"firstname" => "firstname",
			"lastname" => "lastname",
			"company" => "company",
			"address_1" => "address_1",
			"address_2" => "address_2",
			"city" => "city",
			"postcode" => "postcode",
			"zone" => "zone_id",
			"country" => "country_id",
		];

		$defaultCountryId = $this->config->get(
			"module_dockercart_checkout_default_country_id",
		);
		if (empty($defaultCountryId)) {
			$defaultCountryId = $this->config->get("config_country_id");
		}
		if (empty($defaultCountryId)) {
			return $blocks;
		}

		$this->load->model("localisation/country");
		$countryInfo = $this->model_localisation_country->getCountry(
			(int) $defaultCountryId,
		);
		if (empty($countryInfo["address_format"])) {
			return $blocks;
		}

		// Parse field order from address_format tokens
		$format = $countryInfo["address_format"];
		preg_match_all("/\{(\w+)\}/", $format, $matches);
		$fieldOrder = [];
		foreach ($matches[1] as $token) {
			if (isset($tokenToField[$token])) {
				$fieldOrder[] = $tokenToField[$token];
			}
		}
		if (empty($fieldOrder)) {
			return $blocks;
		}

		// Find shipping_address block and reorder after_shipping rows
		foreach ($blocks as &$block) {
			if (
				!isset($block["id"]) ||
				$block["id"] !== "shipping_address" ||
				empty($block["rows"])
			) {
				continue;
			}

			$beforeRows = [];
			$afterRows = [];
			foreach ($block["rows"] as $row) {
				if (!empty($row["after_shipping"])) {
					$afterRows[] = $row;
				} else {
					$beforeRows[] = $row;
				}
			}

			if (empty($afterRows)) {
				continue;
			}

			// Map each after_shipping row to its position in address_format
			$rowPositions = [];
			foreach ($afterRows as $idx => $row) {
				$position = count($fieldOrder);
				if (
					isset($row["fields"]) &&
					is_array($row["fields"]) &&
					!empty($row["fields"][0]["id"])
				) {
					$fieldId = $row["fields"][0]["id"];
					$pos = array_search($fieldId, $fieldOrder, true);
					if ($pos !== false) {
						$position = (int) $pos;
					}
				}
				$rowPositions[$idx] = $position;
			}

			// Stable sort afterRows by position
			$indices = array_keys($afterRows);
			usort($indices, function ($a, $b) use ($rowPositions) {
				return $rowPositions[$a] <=> $rowPositions[$b];
			});
			$sortedAfterRows = [];
			foreach ($indices as $idx) {
				$sortedAfterRows[] = $afterRows[$idx];
			}

			$block["rows"] = array_merge($beforeRows, $sortedAfterRows);
		}

		return $blocks;
	}

	/**
	 * Get available shipping methods from installed extensions
     * @return array Array of shipping methods with their default titles
     */
    private function getAvailableShippingMethods()
    {
        $methods = [];

        $this->load->model("setting/extension");
        $extensions = $this->model_setting_extension->getInstalled("shipping");

        foreach ($extensions as $code) {
            // Check if extension is enabled
            $status = $this->config->get("shipping_" . $code . "_status");

            if ($status) {
                // Load language file to get default title
                $this->load->language("extension/shipping/" . $code);

                $default_title = $this->language->get("heading_title");
                if (
                    empty($default_title) ||
                    $default_title == "heading_title"
                ) {
                    $default_title = ucfirst(str_replace("_", " ", $code));
                }

                // Try to get individual methods within this module
                $sub_methods = $this->getShippingModuleMethods($code);

                if (!empty($sub_methods)) {
                    // Module has multiple methods - add each one
                    foreach ($sub_methods as $sub_code => $sub_data) {
                        $methods[$sub_code] = [
                            "code" => $sub_code,
                            "default_title" => $sub_data["title"],
                            "module_title" => $default_title,
                            "module_code" => $code,
                            "status" => $status,
                        ];
                    }
                } else {
                    // Module has no sub-methods - use module-level config
                    $methods[$code] = [
                        "code" => $code,
                        "default_title" => $default_title,
                        "module_title" => $default_title,
                        "module_code" => $code,
                        "status" => $status,
                    ];
                }
            }
        }

        return $methods;
    }

    /**
     * Get individual methods within a shipping module
     *
     * @param string $code Shipping module code
     * @return array Array of methods with their codes as keys
     */
    private function getShippingModuleMethods(string $code): array
    {
        $methods = [];

        // Handle dockercart_universal - methods stored in database
        if ($code === "dockercart_universal") {
            $this->load->model("extension/shipping/dockercart_universal");
            $db_methods = $this->model_extension_shipping_dockercart_universal->getMethods();

            foreach ($db_methods as $method) {
                $method_code =
                    "dockercart_universal.dockercart_universal_" .
                    $method["method_id"];
                $methods[$method_code] = [
                    "title" =>
                        $method["name"] ?? "Method " . $method["method_id"],
                    "method_id" => $method["method_id"],
                ];
            }
        }

        // For other modules that support multiple methods:
        // - royal_mail: Generates methods based on weight (1st_class, 2nd_class, etc.)
        // - auspost: Returns available services from API
        // - fedex: Returns available services from API
        // - ec_ship: Returns available services from API
        // These typically generate methods dynamically based on API responses or
        // calculated rates, so we can't enumerate them without a sample address.
        // For now, these modules will use module-level configuration.
        // Future enhancement: Add database tables to store method configurations
        // for these modules, similar to dockercart_universal.

        return $methods;
    }

    private function getAvailablePaymentMethods()
    {
        $methods = [];

        $this->load->model("setting/extension");
        $extensions = $this->model_setting_extension->getInstalled("payment");

        foreach ($extensions as $code) {
            // Check if extension is enabled
            $status = $this->config->get("payment_" . $code . "_status");

            if ($status) {
                // Load language file to get default title
                $this->load->language("extension/payment/" . $code);

                $default_title = $this->language->get("heading_title");
                if (
                    empty($default_title) ||
                    $default_title == "heading_title"
                ) {
                    $default_title = ucfirst(str_replace("_", " ", $code));
                }

                $methods[$code] = [
                    "code" => $code,
                    "default_title" => $default_title,
                    "status" => $status,
                ];
            }
        }

        return $methods;
    }
}
