<?php
/**
 * DockerCart SEO Generator Module
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2025 DockerCart
 * @license    MIT
 * @version    1.0.0
 *
 * Массовая генерация SEO URL и мета-тегов для товаров, категорий, производителей и информационных страниц
 */

class ControllerExtensionModuleDockercartSeoGenerator extends Controller
{
    private $logger;
    private $error = [];
    // Module version — update this when releasing new versions
    private $module_version = "1.0.0";

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        // Initialize centralized logger
        require_once DIR_SYSTEM . "library/dockercart_logger.php";
        $this->logger = new DockercartLogger($this->registry, "seo_generator");
    }

    /**
     * Главная страница модуля
     */
    public function index()
    {
        $this->load->language("extension/module/dockercart_seo_generator");

        $this->document->setTitle($this->language->get("heading_title"));

        // Проверка лицензии
        $this->validateLicense();

        // Загружаем модель
        $this->load->model("extension/module/dockercart_seo_generator");
        $this->load->model("setting/setting");

        // Сохранение настроек
        if (
            $this->request->server["REQUEST_METHOD"] == "POST" &&
            $this->validate()
        ) {
            $this->model_setting_setting->editSetting(
                "module_dockercart_seo_generator",
                $this->request->post,
            );

            $this->session->data["success"] = $this->language->get(
                "text_success",
            );

            $this->response->redirect(
                $this->url->link(
                    "extension/module/dockercart_seo_generator",
                    "user_token=" . $this->session->data["user_token"],
                    true,
                ),
            );
        }

        // Подготовка данных для шаблона
        $data = [];

        // Заголовки и breadcrumbs
        $data["heading_title"] = $this->language->get("heading_title");
        $data["text_edit"] = $this->language->get("text_edit");

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
                "extension/module/dockercart_seo_generator",
                "user_token=" . $this->session->data["user_token"],
                true,
            ),
        ];

        // Тексты
        foreach ($this->language->all() as $key => $value) {
            $data[$key] = $value;
        }

        // Ошибки
        if (isset($this->error["warning"])) {
            $data["error_warning"] = $this->error["warning"];
        } else {
            $data["error_warning"] = "";
        }

        if (isset($this->session->data["success"])) {
            $data["success"] = $this->session->data["success"];
            unset($this->session->data["success"]);
        } else {
            $data["success"] = "";
        }

        // Ссылки
        $data["action"] = $this->url->link(
            "extension/module/dockercart_seo_generator",
            "user_token=" . $this->session->data["user_token"],
            true,
        );
        $data["cancel"] = $this->url->link(
            "marketplace/extension",
            "user_token=" . $this->session->data["user_token"] . "&type=module",
            true,
        );
        $data["user_token"] = $this->session->data["user_token"];

        // Получаем все языки магазина
        $this->load->model("localisation/language");
        $data["languages"] = $this->model_localisation_language->getLanguages();

        // Статус модуля
        if (
            isset(
                $this->request->post["module_dockercart_seo_generator_status"],
            )
        ) {
            $data["module_dockercart_seo_generator_status"] =
                $this->request->post["module_dockercart_seo_generator_status"];
        } else {
            $data[
                "module_dockercart_seo_generator_status"
            ] = $this->config->get("module_dockercart_seo_generator_status");
        }

        // Настройка режима отладки (debug)
        if (
            isset($this->request->post["module_dockercart_seo_generator_debug"])
        ) {
            $data["module_dockercart_seo_generator_debug"] =
                $this->request->post["module_dockercart_seo_generator_debug"];
        } else {
            $data["module_dockercart_seo_generator_debug"] = $this->config->get(
                "module_dockercart_seo_generator_debug",
            );
        }

        // Размер пакета обработки
        if (
            isset(
                $this->request->post[
                    "module_dockercart_seo_generator_batch_size"
                ],
            )
        ) {
            $data["module_dockercart_seo_generator_batch_size"] =
                $this->request->post[
                    "module_dockercart_seo_generator_batch_size"
                ];
        } else {
            $data["module_dockercart_seo_generator_batch_size"] =
                $this->config->get(
                    "module_dockercart_seo_generator_batch_size",
                ) ?:
                50;
        }

        // Отключение языкового префикса
        if (
            isset(
                $this->request->post[
                    "module_dockercart_seo_generator_disable_language_prefix"
                ],
            )
        ) {
            $data["module_dockercart_seo_generator_disable_language_prefix"] =
                $this->request->post[
                    "module_dockercart_seo_generator_disable_language_prefix"
                ];
        } else {
            $data[
                "module_dockercart_seo_generator_disable_language_prefix"
            ] = $this->config->get(
                "module_dockercart_seo_generator_disable_language_prefix",
            );
        }

        // Лицензия
        if (
            isset(
                $this->request->post[
                    "module_dockercart_seo_generator_license_key"
                ],
            )
        ) {
            $data["module_dockercart_seo_generator_license_key"] =
                $this->request->post[
                    "module_dockercart_seo_generator_license_key"
                ];
        } else {
            $data[
                "module_dockercart_seo_generator_license_key"
            ] = $this->config->get(
                "module_dockercart_seo_generator_license_key",
            );
        }

        if (
            isset(
                $this->request->post[
                    "module_dockercart_seo_generator_public_key"
                ],
            )
        ) {
            $data["module_dockercart_seo_generator_public_key"] =
                $this->request->post[
                    "module_dockercart_seo_generator_public_key"
                ];
        } else {
            $data[
                "module_dockercart_seo_generator_public_key"
            ] = $this->config->get(
                "module_dockercart_seo_generator_public_key",
            );
        }

        // Шаблоны для каждого типа сущности и каждого языка
        // Note: seo_url is now auto-generated from name, not from template
        $entity_types = ["product", "category", "manufacturer", "information"];
        $meta_fields = ["meta_title", "meta_description", "meta_keyword"];

        foreach ($entity_types as $entity_type) {
            foreach ($data["languages"] as $language) {
                foreach ($meta_fields as $field) {
                    $key =
                        "module_dockercart_seo_generator_" .
                        $entity_type .
                        "_" .
                        $field .
                        "_" .
                        $language["language_id"];

                    if (isset($this->request->post[$key])) {
                        $data[$key] = $this->request->post[$key];
                    } else {
                        // Determine language code for this language
                        $lang_code = isset($language["code"])
                            ? $language["code"]
                            : "en-gb";
                        $data[$key] =
                            $this->config->get($key) ?:
                            $this->getDefaultTemplate(
                                $entity_type,
                                $field,
                                $lang_code,
                            );
                    }
                }
            }
        }

        // Статистика
        $data[
            "stats"
        ] = $this->model_extension_module_dockercart_seo_generator->getStatistics();

        // Expose module version to template. Prefer a global DOCKERCART_VERSION constant if defined.
        $data["module_version"] = defined("DOCKERCART_VERSION")
            ? DOCKERCART_VERSION
            : $this->module_version;

        // Загружаем шаблон
        $data["header"] = $this->load->controller("common/header");
        $data["column_left"] = $this->load->controller("common/column_left");
        $data["footer"] = $this->load->controller("common/footer");

        // Render entity tab fragments server-side to avoid Twig include issues
        $entity_types = ["product", "category", "manufacturer", "information"];

        // Check for manufacturer_description table presence (some OCStore variants don't have it)
        $query = $this->db->query(
            "SHOW TABLES LIKE '" . DB_PREFIX . "manufacturer_description'",
        );
        $data["manufacturer_description_missing"] = empty($query->rows);

        foreach ($entity_types as $etype) {
            $entity_data = $data;
            $entity_data["entity_type"] = $etype;
            $entity_data["user_token"] = $this->session->data["user_token"];

            // Ensure per-language template values are explicitly set for this entity fragment
            $meta_fields = [
                "seo_url",
                "meta_title",
                "meta_description",
                "meta_keyword",
            ];
            if (
                isset($entity_data["languages"]) &&
                is_array($entity_data["languages"])
            ) {
                foreach ($entity_data["languages"] as $language) {
                    $lang_id = $language["language_id"];
                    $lang_code = isset($language["code"])
                        ? $language["code"]
                        : "en-gb";

                    foreach ($meta_fields as $field) {
                        $key =
                            "module_dockercart_seo_generator_" .
                            $etype .
                            "_" .
                            $field .
                            "_" .
                            $lang_id;

                        if (isset($this->request->post[$key])) {
                            $entity_data[$key] = $this->request->post[$key];
                        } else {
                            $entity_data[$key] =
                                $this->config->get($key) ?:
                                $this->getDefaultTemplate(
                                    $etype,
                                    $field,
                                    $lang_code,
                                );
                        }
                    }
                }
            }

            // Render fragment and store HTML into main data array
            $data[$etype . "_entity_html"] = $this->load->view(
                "extension/module/dockercart_seo_generator_entity",
                $entity_data,
            );
        }

        $this->response->setOutput(
            $this->load->view(
                "extension/module/dockercart_seo_generator",
                $data,
            ),
        );
    }

    /**
     * Предпросмотр генерации (10 случайных примеров)
     */
    public function preview()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } else {
            // Handle JSON POST data (sent by fetch API with Content-Type: application/json)
            $input_json = file_get_contents("php://input");
            if (!empty($input_json)) {
                $input = json_decode($input_json, true);
                if (is_array($input)) {
                    // Merge JSON input into POST
                    foreach ($input as $key => $value) {
                        if (!isset($this->request->post[$key])) {
                            $this->request->post[$key] = $value;
                        }
                    }
                }
            }

            $entity_type = isset($this->request->post["entity_type"])
                ? $this->request->post["entity_type"]
                : "product";
            $language_id = isset($this->request->post["language_id"])
                ? (int) $this->request->post["language_id"]
                : 1;
            $templates = isset($this->request->post["templates"])
                ? $this->request->post["templates"]
                : [];

            // If templates were not supplied by the JS request, fall back to module configuration
            // and default templates so preview shows generated meta even when admin left inputs blank.
            if (empty($templates)) {
                $meta_fields = [
                    "seo_url",
                    "meta_title",
                    "meta_description",
                    "meta_keyword",
                ];

                // Single language preview: populate from config or default templates
                $this->load->model("localisation/language");
                $lang_info = $this->model_localisation_language->getLanguage(
                    $language_id,
                );
                $lang_code = $lang_info ? $lang_info["code"] : "en-gb";

                foreach ($meta_fields as $field) {
                    $key =
                        "module_dockercart_seo_generator_" .
                        $entity_type .
                        "_" .
                        $field .
                        "_" .
                        $language_id;
                    $templates[$field] =
                        $this->config->get($key) ?:
                        $this->getDefaultTemplate(
                            $entity_type,
                            $field,
                            $lang_code,
                        );
                }
            }

            $json[
                "previews"
            ] = $this->model_extension_module_dockercart_seo_generator->generatePreview(
                $entity_type,
                $language_id,
                $templates,
                10,
            );
            $json["success"] = true;
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX генерация SEO данных порциями
     */
    public function generate()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } elseif (!$this->checkLicenseForGeneration()) {
            $json["error"] =
                $this->language->get("error_license_required") ?:
                "License key is required to use generation feature. Please enter and verify your license key in General Settings.";
        } else {
            // Handle JSON POST data (sent by fetch API with Content-Type: application/json)
            $input_json = file_get_contents("php://input");
            if (!empty($input_json)) {
                $input = json_decode($input_json, true);
                if (is_array($input)) {
                    // Merge JSON input into POST
                    foreach ($input as $key => $value) {
                        if (!isset($this->request->post[$key])) {
                            $this->request->post[$key] = $value;
                        }
                    }
                }
            }

            $entity_type = isset($this->request->post["entity_type"])
                ? $this->request->post["entity_type"]
                : "product";
            $language_id = isset($this->request->post["language_id"])
                ? (int) $this->request->post["language_id"]
                : 0;
            $generate_type = isset($this->request->post["generate_type"])
                ? $this->request->post["generate_type"]
                : "all"; // url, meta, all
            // Backwards-compatible: controller accepted filter_empty_* previously (true => only empty are processed)
            // Newer JS sends explicit overwrite_* flags (true => overwrite existing). We prefer explicit flags when provided.
            $filter_empty_url = false;
            $filter_empty_meta = false;

            if (isset($this->request->post["overwrite_url"])) {
                $overwrite_url = (bool) $this->request->post["overwrite_url"];
                $filter_empty_url = !$overwrite_url;
                $this->logger->info(
                    "AJAX generate(): overwrite_url flag received = " .
                        ($overwrite_url ? "1" : "0"),
                );
            } else {
                $filter_empty_url = isset(
                    $this->request->post["filter_empty_url"],
                )
                    ? (bool) $this->request->post["filter_empty_url"]
                    : false;
            }

            if (isset($this->request->post["overwrite_meta"])) {
                $overwrite_meta = (bool) $this->request->post["overwrite_meta"];
                $filter_empty_meta = !$overwrite_meta;
                $this->logger->info(
                    "AJAX generate(): overwrite_meta flag received = " .
                        ($overwrite_meta ? "1" : "0"),
                );
            } else {
                $filter_empty_meta = isset(
                    $this->request->post["filter_empty_meta"],
                )
                    ? (bool) $this->request->post["filter_empty_meta"]
                    : false;
            }

            // If user requested only URL generation, don't apply meta filters — they can cause 0 results
            if ($generate_type === "url") {
                $this->logger->info(
                    "AJAX generate(): generate_type=url -> forcing filter_empty_meta = false",
                );
                $filter_empty_meta = false;
            }

            // If user requested only META generation, don't apply URL filters
            if ($generate_type === "meta") {
                $this->logger->info(
                    "AJAX generate(): generate_type=meta -> forcing filter_empty_url = false",
                );
                $filter_empty_url = false;
            }
            $offset = isset($this->request->post["offset"])
                ? (int) $this->request->post["offset"]
                : 0;
            $batch_size =
                $this->config->get(
                    "module_dockercart_seo_generator_batch_size",
                ) ?:
                50;
            $templates = isset($this->request->post["templates"])
                ? $this->request->post["templates"]
                : [];

            // Если templates пусто, заполнить из конфига/языков
            if (empty($templates)) {
                $meta_fields = [
                    "seo_url",
                    "meta_title",
                    "meta_description",
                    "meta_keyword",
                ];

                $this->load->model("localisation/language");

                if ($language_id > 0) {
                    // Single language: simple templates
                    $lang_info = $this->model_localisation_language->getLanguage(
                        $language_id,
                    );
                    $lang_code = $lang_info ? $lang_info["code"] : "en-gb";

                    foreach ($meta_fields as $field) {
                        $key =
                            "module_dockercart_seo_generator_" .
                            $entity_type .
                            "_" .
                            $field .
                            "_" .
                            $language_id;
                        $templates[$field] =
                            $this->config->get($key) ?:
                            $this->getDefaultTemplate(
                                $entity_type,
                                $field,
                                $lang_code,
                            );
                    }
                } else {
                    // All languages: prepare templates per language
                    $languages = $this->model_localisation_language->getLanguages();

                    foreach ($meta_fields as $field) {
                        $templates[$field] = [];
                        foreach ($languages as $language) {
                            $lang_id = $language["language_id"];
                            $lang_code = isset($language["code"])
                                ? $language["code"]
                                : "en-gb";
                            $key =
                                "module_dockercart_seo_generator_" .
                                $entity_type .
                                "_" .
                                $field .
                                "_" .
                                $lang_id;
                            $templates[$field][$lang_id] =
                                $this->config->get($key) ?:
                                $this->getDefaultTemplate(
                                    $entity_type,
                                    $field,
                                    $lang_code,
                                );
                        }
                    }
                }
            }

            // Генерация
            $this->logger->info(
                "AJAX generate() called with entity_type=" .
                    $entity_type .
                    ", language_id=" .
                    $language_id .
                    ", generate_type=" .
                    $generate_type .
                    ", offset=" .
                    $offset .
                    ", batch_size=" .
                    $batch_size .
                    ", filter_empty_url=" .
                    ($filter_empty_url ? 1 : 0) .
                    ", filter_empty_meta=" .
                    ($filter_empty_meta ? 1 : 0),
            );

            // When filtering by empty fields, dataset changes during generation.
            // To avoid skipping entities because of shrinking result sets,
            // always read from offset 0 and keep offset only as a progress counter.
            $query_offset =
                $filter_empty_url || $filter_empty_meta ? 0 : $offset;

            $result = $this->model_extension_module_dockercart_seo_generator->generateSeoData(
                $entity_type,
                $language_id,
                $generate_type,
                $templates,
                $query_offset,
                $batch_size,
                $filter_empty_url,
                $filter_empty_meta,
            );

            $this->logger->info(
                "AJAX generate() result processed=" .
                    $result["processed"] .
                    ", updated=" .
                    (isset($result["updated"]) ? $result["updated"] : 0) .
                    ", total=" .
                    $result["total"],
            );

            $json["processed"] = $result["processed"];
            $json["updated"] = isset($result["updated"])
                ? $result["updated"]
                : 0;
            $json["total"] = $result["total"];

            // For filtered runs we use dynamic datasets (rows leave the set after update).
            // Progress must advance by actually updated rows, not by fetched chunk size.
            $progress_increment =
                $filter_empty_url || $filter_empty_meta
                    ? $json["updated"]
                    : $result["processed"];
            $json["offset"] = $offset + $progress_increment;

            if ($filter_empty_url || $filter_empty_meta) {
                // For dynamic filtered sets total is shrinking on each request,
                // so relying on offset < total causes premature completion.
                // Continue while current batch produced at least one update.
                $json["has_more"] = $json["updated"] > 0;
            } else {
                $json["has_more"] = $json["offset"] < $result["total"];
            }

            $json["success"] = true;
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Получить общую статистику для прогресс-бара
     */
    public function getTotal()
    {
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        $entity_type = isset($this->request->get["entity_type"])
            ? $this->request->get["entity_type"]
            : "product";
        $language_id = isset($this->request->get["language_id"])
            ? (int) $this->request->get["language_id"]
            : 0;
        $generate_type = isset($this->request->get["generate_type"])
            ? $this->request->get["generate_type"]
            : "all";
        $filter_empty_url = isset($this->request->get["filter_empty_url"])
            ? (bool) $this->request->get["filter_empty_url"]
            : false;
        $filter_empty_meta = isset($this->request->get["filter_empty_meta"])
            ? (bool) $this->request->get["filter_empty_meta"]
            : false;

        // Keep getTotal filter semantics consistent with generate()
        if ($generate_type === "url") {
            $filter_empty_meta = false;
        }

        if ($generate_type === "meta") {
            $filter_empty_url = false;
        }

        $json[
            "total"
        ] = $this->model_extension_module_dockercart_seo_generator->getTotalCount(
            $entity_type,
            $language_id,
            $filter_empty_url,
            $filter_empty_meta,
        );

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Установка модуля
     */
    public function install()
    {
        $this->load->model("extension/module/dockercart_seo_generator");
        $this->model_extension_module_dockercart_seo_generator->install();

        // Регистрируем события для автогенерации
        $this->registerEvents();
    }

    /**
     * Регистрация событий для автогенерации
     *
     * Используем события router - они срабатывают для КАЖДОГО контроллера
     * Формат: admin/controller/catalog/product/add/before, admin/controller/catalog/product/add/after
     */
    private function registerEvents()
    {
        $this->load->model("setting/event");

        // События для товаров
        $this->model_setting_event->addEvent(
            "dockercart_seo_product_add_after",
            "admin/model/catalog/product/addProduct/after",
            "extension/module/dockercart_seo_generator/eventProductAddAfter",
        );
        $this->model_setting_event->addEvent(
            "dockercart_seo_product_edit_after",
            "admin/model/catalog/product/editProduct/after",
            "extension/module/dockercart_seo_generator/eventProductEditAfter",
        );

        // События для категорий
        $this->model_setting_event->addEvent(
            "dockercart_seo_category_add_after",
            "admin/model/catalog/category/addCategory/after",
            "extension/module/dockercart_seo_generator/eventCategoryAddAfter",
        );
        $this->model_setting_event->addEvent(
            "dockercart_seo_category_edit_after",
            "admin/model/catalog/category/editCategory/after",
            "extension/module/dockercart_seo_generator/eventCategoryEditAfter",
        );

        // События для производителей
        $this->model_setting_event->addEvent(
            "dockercart_seo_manufacturer_add_after",
            "admin/model/catalog/manufacturer/addManufacturer/after",
            "extension/module/dockercart_seo_generator/eventManufacturerAddAfter",
        );
        $this->model_setting_event->addEvent(
            "dockercart_seo_manufacturer_edit_after",
            "admin/model/catalog/manufacturer/editManufacturer/after",
            "extension/module/dockercart_seo_generator/eventManufacturerEditAfter",
        );

        // События для информационных страниц
        $this->model_setting_event->addEvent(
            "dockercart_seo_information_add_after",
            "admin/model/catalog/information/addInformation/after",
            "extension/module/dockercart_seo_generator/eventInformationAddAfter",
        );
        $this->model_setting_event->addEvent(
            "dockercart_seo_information_edit_after",
            "admin/model/catalog/information/editInformation/after",
            "extension/module/dockercart_seo_generator/eventInformationEditAfter",
        );
    }

    /**
     * Автоматическая генерация для одной сущности
     *
     * @param string $entity_type Тип сущности (product, category, manufacturer, information)
     * @param int $entity_id ID сущности
     * @param bool $force_update Перезаписывать ли существующие SEO данные
     */
    private function autoGenerateForEntity(
        $entity_type,
        $entity_id,
        $force_update = false,
    ) {
        // Логирование события для отладки
        $this->logger->info(
            "Event triggered for $entity_type ID: $entity_id, force_update: $force_update",
        );

        $this->load->model("extension/module/dockercart_seo_generator");
        $this->load->model("localisation/language");

        $languages = $this->model_localisation_language->getLanguages();
        $meta_fields = [
            "seo_url",
            "meta_title",
            "meta_description",
            "meta_keyword",
        ];

        foreach ($languages as $language) {
            $templates = [];

            // Получаем шаблоны из настроек
            foreach ($meta_fields as $field) {
                $key =
                    "module_dockercart_seo_generator_" .
                    $entity_type .
                    "_" .
                    $field .
                    "_" .
                    $language["language_id"];
                $lang_code = isset($language["code"])
                    ? $language["code"]
                    : "en-gb";
                $templates[$field] =
                    $this->config->get($key) ?:
                    $this->getDefaultTemplate($entity_type, $field, $lang_code);
            }

            // Генерируем данные только для этой сущности
            $this->model_extension_module_dockercart_seo_generator->generateSeoDataForSingleEntity(
                $entity_type,
                $entity_id,
                $language["language_id"],
                $templates,
                $force_update,
            );
        }
    }

    /**
     * Обработчики событий для автогенерации
     *
     * События срабатывают ПОСЛЕ выполнения действия (add/edit)
     * Parameters: $route (строка маршрута), $args (массив с данными)
     */

    public function eventProductAddAfter($route, $args)
    {
        $this->logger->info("Product add/after event triggered, route: $route");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            $this->logger->info("Module disabled, skipping");
            return;
        }

        // После добавления товара может произойти редирект
        // ID товара получим из последней вставки
        $this->load->model("extension/module/dockercart_seo_generator");

        // Проверим, был ли успешный POST и валидация прошла
        // Если был редирект, значит товар был создан успешно
        if (
            $this->request->server["REQUEST_METHOD"] == "POST" &&
            isset($this->session->data["success"])
        ) {
            // Получим последний добавленный товар
            $query = $this->db->query(
                "SELECT MAX(product_id) as max_id FROM " .
                    DB_PREFIX .
                    "product",
            );
            $product_id = $query->row["max_id"];

            if ($product_id) {
                $this->logger->info(
                    "Auto-generating for new product: $product_id",
                );
                $this->autoGenerateForEntity("product", $product_id, false);
            }
        }
    }

    public function eventProductEditAfter($route, $args)
    {
        $this->logger->info("Product edit/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        $product_id = isset($args[0]) ? (int) $args[0] : 0;

        if ($product_id > 0) {
            $this->logger->info("Auto-generating for product: $product_id");
            $this->autoGenerateForEntity("product", $product_id, false);
        }
    }

    public function eventCategoryAddAfter($route, $args)
    {
        $this->logger->info("Category add/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        if (
            $this->request->server["REQUEST_METHOD"] == "POST" &&
            isset($this->session->data["success"])
        ) {
            $query = $this->db->query(
                "SELECT MAX(category_id) as max_id FROM " .
                    DB_PREFIX .
                    "category",
            );
            $category_id = $query->row["max_id"];

            if ($category_id) {
                $this->logger->info(
                    "Auto-generating for new category: $category_id",
                );
                $this->autoGenerateForEntity("category", $category_id, false);
            }
        }
    }

    public function eventCategoryEditAfter($route, $args)
    {
        $this->logger->info("Category edit/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        $category_id = isset($this->request->get["category_id"])
            ? (int) $this->request->get["category_id"]
            : 0;

        if ($category_id > 0 && isset($this->session->data["success"])) {
            $this->logger->info("Auto-generating for category: $category_id");
            $this->autoGenerateForEntity("category", $category_id, false);
        }
    }

    public function eventManufacturerAddAfter($route, $args)
    {
        $this->logger->info("Manufacturer add/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        if (
            $this->request->server["REQUEST_METHOD"] == "POST" &&
            isset($this->session->data["success"])
        ) {
            $query = $this->db->query(
                "SELECT MAX(manufacturer_id) as max_id FROM " .
                    DB_PREFIX .
                    "manufacturer",
            );
            $manufacturer_id = $query->row["max_id"];

            if ($manufacturer_id) {
                $this->logger->info(
                    "Auto-generating for new manufacturer: $manufacturer_id",
                );
                $this->autoGenerateForEntity(
                    "manufacturer",
                    $manufacturer_id,
                    false,
                );
            }
        }
    }

    public function eventManufacturerEditAfter($route, $args)
    {
        $this->logger->info("Manufacturer edit/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        $manufacturer_id = isset($this->request->get["manufacturer_id"])
            ? (int) $this->request->get["manufacturer_id"]
            : 0;

        if ($manufacturer_id > 0 && isset($this->session->data["success"])) {
            $this->logger->info(
                "Auto-generating for manufacturer: $manufacturer_id",
            );
            $this->autoGenerateForEntity(
                "manufacturer",
                $manufacturer_id,
                false,
            );
        }
    }

    public function eventInformationAddAfter($route, $args)
    {
        $this->logger->info("Information add/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        if (
            $this->request->server["REQUEST_METHOD"] == "POST" &&
            isset($this->session->data["success"])
        ) {
            $query = $this->db->query(
                "SELECT MAX(information_id) as max_id FROM " .
                    DB_PREFIX .
                    "information",
            );
            $information_id = $query->row["max_id"];

            if ($information_id) {
                $this->logger->info(
                    "Auto-generating for new information: $information_id",
                );
                $this->autoGenerateForEntity(
                    "information",
                    $information_id,
                    false,
                );
            }
        }
    }

    public function eventInformationEditAfter($route, $args)
    {
        $this->logger->info("Information edit/after event triggered");

        if (!$this->config->get("module_dockercart_seo_generator_status")) {
            return;
        }

        $information_id = isset($this->request->get["information_id"])
            ? (int) $this->request->get["information_id"]
            : 0;

        if ($information_id > 0 && isset($this->session->data["success"])) {
            $this->logger->info(
                "Auto-generating for information: $information_id",
            );
            $this->autoGenerateForEntity("information", $information_id, false);
        }
    }

    /**
     * Валидация прав доступа
     */
    protected function validate()
    {
        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $this->error["warning"] = $this->language->get("error_permission");
        }

        return !$this->error;
    }

    /**
     * Удаление модуля
     */
    public function uninstall()
    {
        $this->load->model("extension/module/dockercart_seo_generator");
        $this->model_extension_module_dockercart_seo_generator->uninstall();

        // Удаляем события
        $this->db->query(
            "DELETE FROM " .
                DB_PREFIX .
                "event WHERE code LIKE 'dockercart_seo_%'",
        );
    }

    /**
     * Получить шаблоны по умолчанию из языковых файлов
     */
    private function getDefaultTemplate(
        $entity_type,
        $field,
        $language_code = "",
    ) {
        $lang_key = "template_" . $entity_type . "_" . $field;

        // Если задан код языка, попробуем загрузить языковой файл для него и взять ключ оттуда
        if (!empty($language_code)) {
            $lang_file =
                DIR_APPLICATION .
                "language/" .
                $language_code .
                "/extension/module/dockercart_seo_generator.php";

            if (is_file($lang_file)) {
                $_lang = [];
                include $lang_file;

                if (isset($_[$lang_key]) && !empty($_[$lang_key])) {
                    return $_[$lang_key];
                }
            }
        }

        // Попробуем взять из текущего загруженного языка
        $template = $this->language->get($lang_key);

        // Fallback если ключ не найден в языковом файле
        if (empty($template) || $template === $lang_key) {
            // Базовые значения на случай если языковые файлы не загружены
            $defaults = [
                "product" => [
                    "seo_url" => "{name}",
                    "meta_title" => "{name} {manufacturer}",
                    "meta_description" =>
                        "{name} from {manufacturer}. {description}",
                    "meta_keyword" => "{name}, {manufacturer}, {category}",
                ],
                "category" => [
                    "seo_url" => "{name}",
                    "meta_title" => "{name}",
                    "meta_description" => "{name}. {description}",
                    "meta_keyword" => "{name}",
                ],
                "manufacturer" => [
                    "seo_url" => "{name}",
                    "meta_title" => "{name}",
                    "meta_description" => "{name} products. {description}",
                    "meta_keyword" => "{name}",
                ],
                "information" => [
                    "seo_url" => "{name}",
                    "meta_title" => "{name}",
                    "meta_description" => "{description}",
                    "meta_keyword" => "{name}",
                ],
            ];

            return isset($defaults[$entity_type][$field])
                ? $defaults[$entity_type][$field]
                : "";
        }

        return $template;
    }

    /**
     * Check license before allowing generation (strict check for AJAX calls)
     */
    private function checkLicenseForGeneration()
    {
        $license_key = $this->config->get(
            "module_dockercart_seo_generator_license_key",
        );

        // Allow localhost/127.0.0.1 (including with port) without license
        $domain = $_SERVER["HTTP_HOST"] ?? "";
        // Strip port from domain for checking
        $domain_without_port = preg_replace('/:\d+$/', "", $domain);
        if (
            strpos($domain, "localhost") !== false ||
            strpos($domain, "127.0.0.1") !== false ||
            $domain_without_port === "localhost" ||
            $domain_without_port === "127.0.0.1"
        ) {
            return true;
        }

        // Block if license is empty
        if (empty($license_key)) {
            $this->logger->info("Generation blocked: License key is empty");
            return false;
        }

        // If license library doesn't exist, allow (backwards compatibility)
        if (!file_exists(DIR_SYSTEM . "library/dockercart_license.php")) {
            $this->logger->info(
                "Generation allowed: License library not found (backwards compatibility)",
            );
            return true;
        }

        require_once DIR_SYSTEM . "library/dockercart_license.php";

        if (!class_exists("DockercartLicense")) {
            $this->logger->info(
                "Generation allowed: DockercartLicense class not found",
            );
            return true;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify(
                $license_key,
                "dockercart_seo_generator",
            );

            if (!$result["valid"]) {
                $this->logger->info(
                    "Generation blocked: License validation failed - " .
                        (isset($result["error"])
                            ? $result["error"]
                            : "Unknown error"),
                );
                return false;
            }

            $this->logger->info(
                "Generation allowed: License validated successfully",
            );
            return true;
        } catch (Exception $e) {
            $this->logger->info(
                "Generation blocked: License verification exception - " .
                    $e->getMessage(),
            );
            return false;
        }
    }

    /**
     * Проверка лицензии (для UI warnings, не блокирует работу)
     */
    private function validateLicense()
    {
        $license_key = $this->config->get(
            "module_dockercart_seo_generator_license_key",
        );

        $domain = $_SERVER["HTTP_HOST"] ?? "";
        if (
            strpos($domain, "localhost") !== false ||
            strpos($domain, "127.0.0.1") !== false
        ) {
            return true;
        }

        if (empty($license_key)) {
            return true;
        }

        if (!file_exists(DIR_SYSTEM . "library/dockercart_license.php")) {
            return true;
        }

        require_once DIR_SYSTEM . "library/dockercart_license.php";

        if (!class_exists("DockercartLicense")) {
            return true;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify(
                $license_key,
                "dockercart_seo_generator",
            );

            if (!$result["valid"]) {
                $error_msg = $this->language->get("error_license_invalid");
                if (isset($result["error"])) {
                    $error_msg .= ": " . $result["error"];
                }
                $this->logger->info(
                    "WARNING: License validation failed in admin: " .
                        $error_msg,
                );
            }
        } catch (Exception $e) {
            $this->logger->info(
                "ERROR: License verification exception: " . $e->getMessage(),
            );
        }

        return true;
    }

    /**
     * AJAX проверка лицензии
     */
    public function verifyLicenseAjax()
    {
        $json = [];

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        $license_key = isset($data["license_key"]) ? $data["license_key"] : "";
        $public_key = isset($data["public_key"]) ? $data["public_key"] : "";

        $this->logger->info(
            "AJAX: verifyLicenseAjax() called with key: " .
                substr($license_key, 0, 20) .
                "...",
        );

        if (empty($license_key)) {
            $json["valid"] = false;
            $json["error"] = "License key is empty";
            $this->response->addHeader("Content-Type: application/json");
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!file_exists(DIR_SYSTEM . "library/dockercart_license.php")) {
            $json["valid"] = false;
            $json["error"] = "License library not found";
            $this->logger->info("AJAX: License library not found");
            $this->response->addHeader("Content-Type: application/json");
            $this->response->setOutput(json_encode($json));
            return;
        }

        require_once DIR_SYSTEM . "library/dockercart_license.php";

        if (!class_exists("DockercartLicense")) {
            $json["valid"] = false;
            $json["error"] = "DockercartLicense class not found";
            $this->logger->info("AJAX: DockercartLicense class not found");
            $this->response->addHeader("Content-Type: application/json");
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $license = new DockercartLicense($this->registry);

            if (!empty($public_key)) {
                $this->logger->info(
                    "AJAX: Using provided public key for verification",
                );
                $result = $license->verifyWithPublicKey(
                    $license_key,
                    $public_key,
                    "dockercart_seo_generator",
                    true,
                );
            } else {
                $this->logger->info(
                    "AJAX: Using saved public key from database",
                );
                $result = $license->verify(
                    $license_key,
                    "dockercart_seo_generator",
                    true,
                );
            }

            $this->logger->info(
                "AJAX: Verification result: " . json_encode($result),
            );

            $json = $result;

            if ($result["valid"]) {
                $this->logger->info("AJAX: License verified successfully");
            } else {
                $this->logger->info(
                    "AJAX: License verification failed - " .
                        (isset($result["error"])
                            ? $result["error"]
                            : "Unknown error"),
                );
            }
        } catch (Exception $e) {
            $json["valid"] = false;
            $json["error"] = "Error: " . $e->getMessage();
            $this->logger->info(
                "AJAX: Exception during verification - " . $e->getMessage(),
            );
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Сканирование контроллеров для поиска маршрутов с index методом без SEO URL
     */
    public function scanControllers()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } else {
            $controllers = $this->model_extension_module_dockercart_seo_generator->scanAvailableControllers();
            $json["controllers"] = $controllers;
            $json["success"] = true;
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Генерация SEO URL для выбранных контроллеров
     */
    public function generateControllers()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } elseif (!$this->checkLicenseForGeneration()) {
            $json["error"] =
                $this->language->get("error_license_required") ?:
                "License key is required to use generation feature.";
        } else {
            // Handle JSON POST data
            $input_json = file_get_contents("php://input");
            if (!empty($input_json)) {
                $input = json_decode($input_json, true);
                if (is_array($input)) {
                    foreach ($input as $key => $value) {
                        if (!isset($this->request->post[$key])) {
                            $this->request->post[$key] = $value;
                        }
                    }
                }
            }

            $language_id = isset($this->request->post["language_id"])
                ? (int) $this->request->post["language_id"]
                : 0;
            $controllers = isset($this->request->post["controllers"])
                ? $this->request->post["controllers"]
                : [];
            $overwrite = isset($this->request->post["overwrite"])
                ? (bool) $this->request->post["overwrite"]
                : false;

            if (empty($controllers)) {
                $json["error"] = "No controllers selected";
            } else {
                $result = $this->model_extension_module_dockercart_seo_generator->generateControllersSeoUrls(
                    $controllers,
                    $language_id,
                    $overwrite,
                );

                $json["processed"] = $result["processed"];
                $json["skipped"] = isset($result["skipped"])
                    ? $result["skipped"]
                    : 0;
                $json["total"] = $result["total"];
                $json["success"] = true;
            }
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Массовое удаление SEO URL для выбранных контроллеров
     */
    public function deleteControllers()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } else {
            // Handle JSON DELETE data
            $input_json = file_get_contents("php://input");
            $input = [];
            if (!empty($input_json)) {
                $input = json_decode($input_json, true);
            }

            $controllers = isset($input["controllers"])
                ? $input["controllers"]
                : [];

            if (empty($controllers)) {
                $json["error"] = "No controllers selected";
            } else {
                // Get all languages
                $this->load->model("localisation/language");
                $languages = $this->model_localisation_language->getLanguages();

                $deleted_count = 0;

                // Delete for each controller and all languages
                foreach ($controllers as $controller) {
                    $route = isset($controller["route"])
                        ? $controller["route"]
                        : "";

                    if (!empty($route)) {
                        foreach ($languages as $language) {
                            $success = $this->model_extension_module_dockercart_seo_generator->deleteControllerSeoUrl(
                                $route,
                                $language["language_id"],
                            );
                            if ($success) {
                                $deleted_count++;
                            }
                        }
                    }
                }

                $json["deleted"] = $deleted_count;
                $json["total"] = count($controllers);
                $json["success"] = true;
            }
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Обновление SEO URL для отдельного контроллера
     */
    public function updateControllerUrl()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } else {
            // Handle JSON POST data
            $input_json = file_get_contents("php://input");
            if (!empty($input_json)) {
                $input = json_decode($input_json, true);
                if (is_array($input)) {
                    foreach ($input as $key => $value) {
                        if (!isset($this->request->post[$key])) {
                            $this->request->post[$key] = $value;
                        }
                    }
                }
            }

            $route = isset($this->request->post["route"])
                ? $this->request->post["route"]
                : "";
            $seo_url = isset($this->request->post["seo_url"])
                ? $this->request->post["seo_url"]
                : "";
            $language_id = isset($this->request->post["language_id"])
                ? (int) $this->request->post["language_id"]
                : 0;

            if (empty($route)) {
                $json["error"] = "Route is required";
            } elseif (empty($seo_url)) {
                $json["error"] = "SEO URL is required";
            } elseif ($language_id <= 0) {
                $json["error"] = "Language ID is required";
            } else {
                // Обновляем SEO URL
                $success = $this->model_extension_module_dockercart_seo_generator->updateControllerSeoUrl(
                    $route,
                    $seo_url,
                    $language_id,
                );

                if ($success) {
                    $json["success"] = true;
                    $json["message"] = "SEO URL updated successfully";
                } else {
                    $json["error"] =
                        "This SEO URL is already in use by another controller";
                }
            }
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Удаление SEO URL для контроллера
     */
    public function deleteControllerUrl()
    {
        $this->load->language("extension/module/dockercart_seo_generator");
        $this->load->model("extension/module/dockercart_seo_generator");

        $json = [];

        if (
            !$this->user->hasPermission(
                "modify",
                "extension/module/dockercart_seo_generator",
            )
        ) {
            $json["error"] = $this->language->get("error_permission");
        } else {
            // Handle JSON POST data
            $input_json = file_get_contents("php://input");
            if (!empty($input_json)) {
                $input = json_decode($input_json, true);
                if (is_array($input)) {
                    foreach ($input as $key => $value) {
                        if (!isset($this->request->post[$key])) {
                            $this->request->post[$key] = $value;
                        }
                    }
                }
            }

            $route = isset($this->request->post["route"])
                ? $this->request->post["route"]
                : "";
            $language_id = isset($this->request->post["language_id"])
                ? (int) $this->request->post["language_id"]
                : 0;

            if (empty($route)) {
                $json["error"] = "Route is required";
            } elseif ($language_id <= 0) {
                $json["error"] = "Language ID is required";
            } else {
                $success = $this->model_extension_module_dockercart_seo_generator->deleteControllerSeoUrl(
                    $route,
                    $language_id,
                );
                if ($success) {
                    $json["success"] = true;
                    $json["message"] = "SEO URL deleted successfully";
                } else {
                    $json["error"] = "Failed to delete SEO URL";
                }
            }
        }

        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($json));
    }
}
