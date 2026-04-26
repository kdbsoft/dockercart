<?php
/**
 * DockerCart SEO Generator Model
 * 
 * Основная логика генерации SEO URL и мета-тегов
 */

class ModelExtensionModuleDockercartSeoGenerator extends Model {
    private $logger;
    private $seo_log_schema_checked = false;
	private $seo_url_cache_dirty = false;

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'seo_generator');
    }

    private function markSeoUrlCacheDirty() {
        $this->seo_url_cache_dirty = true;
    }

    private function flushSeoUrlCacheInvalidation() {
        if (!$this->seo_url_cache_dirty) {
            return;
        }

        $this->load->model('design/seo_url');
        $this->model_design_seo_url->invalidateSeoUrlCache();
        $this->seo_url_cache_dirty = false;
    }

    /**
     * Установка модуля - создание таблиц
     */
    public function install() {
        $this->ensureSeoLogSchema();

        // Создаём таблицу для логов генерации
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_seo_log` (
                `log_id` int(11) NOT NULL AUTO_INCREMENT,
                `entity_type` varchar(50) NOT NULL,
                `entity_id` int(11) NOT NULL,
                `field_type` varchar(50) NOT NULL,
                `old_value` text,
                `new_value` text,
                `date_added` datetime NOT NULL,
                PRIMARY KEY (`log_id`),
                KEY `entity_type` (`entity_type`),
                KEY `entity_id` (`entity_id`),
                KEY `date_added` (`date_added`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // Set default settings (debug mode disabled by default)
        $this->load->model('setting/setting');
        $defaults = array(
            'module_dockercart_seo_generator_status' => 0,
            'module_dockercart_seo_generator_debug' => 0,
            'module_dockercart_seo_generator_batch_size' => 50
        );
        $this->model_setting_setting->editSetting('module_dockercart_seo_generator', $defaults);
    }
    
    /**
     * Удаление модуля
     */
    public function uninstall() {
        // При желании можно удалить таблицу логов
        // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_seo_log`");
    }
    
    /**
     * Получить статистику по всем сущностям и языкам
     */
    public function getStatistics() {
        $stats = array();
        
        // Получаем все языки
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        
        // Товары
        $stats['products'] = array(
            'total' => $this->getTotalProducts(),
            'empty_url' => $this->getTotalProductsWithEmptyUrl(),
            'empty_meta' => $this->getTotalProductsWithEmptyMeta(),
            'by_language' => array()
        );
        
        foreach ($languages as $language) {
            $stats['products']['by_language'][$language['language_id']] = array(
                'name' => $language['name'],
                'code' => $language['code'],
                'empty_url' => $this->getTotalProducts($language['language_id'], true, false),
                'empty_meta' => $this->getTotalProducts($language['language_id'], false, true)
            );
        }
        
        // Категории
        $stats['categories'] = array(
            'total' => $this->getTotalCategories(),
            'empty_url' => $this->getTotalCategoriesWithEmptyUrl(),
            'empty_meta' => $this->getTotalCategoriesWithEmptyMeta(),
            'by_language' => array()
        );
        
        foreach ($languages as $language) {
            $stats['categories']['by_language'][$language['language_id']] = array(
                'name' => $language['name'],
                'code' => $language['code'],
                'empty_url' => $this->getTotalCategories($language['language_id'], true, false),
                'empty_meta' => $this->getTotalCategories($language['language_id'], false, true)
            );
        }
        
        // Производители
        $stats['manufacturers'] = array(
            'total' => $this->getTotalManufacturers(),
            'empty_url' => $this->getTotalManufacturersWithEmptyUrl(),
            'empty_meta' => $this->getTotalManufacturersWithEmptyMeta(),
            'by_language' => array()
        );
        
        foreach ($languages as $language) {
            $stats['manufacturers']['by_language'][$language['language_id']] = array(
                'name' => $language['name'],
                'code' => $language['code'],
                'empty_url' => $this->getTotalManufacturers($language['language_id'], true, false),
                'empty_meta' => 0 // У производителей нет мета-тегов по умолчанию
            );
        }
        
        // Информационные страницы
        $stats['information'] = array(
            'total' => $this->getTotalInformation(),
            'empty_url' => $this->getTotalInformationWithEmptyUrl(),
            'empty_meta' => $this->getTotalInformationWithEmptyMeta(),
            'by_language' => array()
        );
        
        foreach ($languages as $language) {
            $stats['information']['by_language'][$language['language_id']] = array(
                'name' => $language['name'],
                'code' => $language['code'],
                'empty_url' => $this->getTotalInformation($language['language_id'], true, false),
                'empty_meta' => $this->getTotalInformation($language['language_id'], false, true)
            );
        }
        
        return $stats;
    }
    
    /**
     * Получить общее количество сущностей для обработки
     */
    public function getTotalCount($entity_type, $language_id = 0, $filter_empty_url = false, $filter_empty_meta = false) {
        // Always return the entity count without multiplying by language count.
        // The generateSeoData() loop handles iterating through all languages internally.
        
        switch ($entity_type) {
            case 'product':
                return $this->getTotalProducts($language_id, $filter_empty_url, $filter_empty_meta);
            case 'category':
                return $this->getTotalCategories($language_id, $filter_empty_url, $filter_empty_meta);
            case 'manufacturer':
                return $this->getTotalManufacturers($language_id, $filter_empty_url, $filter_empty_meta);
            case 'information':
                return $this->getTotalInformation($language_id, $filter_empty_url, $filter_empty_meta);
            default:
                return 0;
        }
    }
    
    /**
     * Генерация SEO данных порциями
     */
    public function generateSeoData($entity_type, $language_id, $generate_type, $templates, $offset = 0, $limit = 50, $filter_empty_url = false, $filter_empty_meta = false) {
        $result = array(
            'processed' => 0,
            'updated' => 0,
            'total' => $this->getTotalCount($entity_type, $language_id, $filter_empty_url, $filter_empty_meta)
        );
        $this->logger->info("generateSeoData called: type={$entity_type}, language_id={$language_id}, generate_type={$generate_type}, offset={$offset}, limit={$limit}, filter_empty_url=" . ($filter_empty_url?1:0) . ", filter_empty_meta=" . ($filter_empty_meta?1:0));
        
        // Если language_id = 0, работаем со всеми языками
        if ($language_id == 0) {
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
        } else {
            $languages = array(array('language_id' => $language_id));
        }
        
        // Получаем сущности для обработки (используем первый язык если language_id=0)
        $first_lang_id = $language_id == 0 ? array_values($languages)[0]['language_id'] : $language_id;
        $entities = $this->getEntities($entity_type, $first_lang_id, $offset, $limit, $filter_empty_url, $filter_empty_meta);

        $this->logger->info("Fetched " . count($entities) . " entities for processing.");
        
        foreach ($entities as $entity) {
            // Обрабатываем для каждого языка
            foreach ($languages as $language) {
                $lang_id = $language['language_id'];
                
                // Генерируем данные
                $generated = $this->generateEntityData($entity_type, $entity, $lang_id, $templates);
                
                // Сохраняем
                $didSave = false;

                if ($generate_type == 'url' || $generate_type == 'all') {
                    $this->logger->debug("Save SEO URL: type={$entity_type}, id={$entity['id']}, lang={$lang_id}, url={$generated['seo_url']}");

                    $savedUrl = $this->saveSeoUrl($entity_type, $entity['id'], $lang_id, $generated['seo_url']);
                    if ($savedUrl) $didSave = true;
                }

                if ($generate_type == 'meta' || $generate_type == 'all') {
                    $this->logger->debug("Save MetaTags: type={$entity_type}, id={$entity['id']}, lang={$lang_id}, meta_title=" . substr($generated['meta_title'],0,200));

                    $savedMeta = $this->saveMetaTags($entity_type, $entity['id'], $lang_id, array(
                        'meta_title' => $generated['meta_title'],
                        'meta_description' => $generated['meta_description'],
                        'meta_keyword' => $generated['meta_keyword']
                    ));

                    if ($savedMeta) $didSave = true;
                }

                if ($didSave) {
                    $result['updated']++;
                }
            }

            // Processed is number of handled entities in this chunk
            // (independent from whether any value changed)
            $result['processed']++;
            
            // Логирование
            if ($this->config->get('module_dockercart_seo_generator_logging')) {
                $this->logGeneration($entity_type, $entity['id'], $language_id, $generated);
            }
        }

        $this->logger->info("generateSeoData finished: processed={$result['processed']}, updated={$result['updated']}, total={$result['total']}");
		$this->flushSeoUrlCacheInvalidation();

        return $result;
    }
    
    /**
     * Генерация предпросмотра
     */
    public function generatePreview($entity_type, $language_id, $templates, $limit = 10) {
        $previews = array();
        
        $entities = $this->getEntities($entity_type, $language_id, 0, $limit, false, false);
        
        foreach ($entities as $entity) {
            // For preview do not enforce DB uniqueness suffixes — show the raw generated URL
            $generated = $this->generateEntityData($entity_type, $entity, $language_id, $templates, false);
            
            $previews[] = array(
                'id' => $entity['id'],
                'name' => $entity['name'],
                'seo_url' => $generated['seo_url'],
                'meta_title' => $generated['meta_title'],
                'meta_description' => $generated['meta_description'],
                'meta_keyword' => $generated['meta_keyword']
            );
        }
        
        return $previews;
    }
    
    /**
     * Генерация данных для конкретной сущности
     */
    private function generateEntityData($entity_type, $entity, $language_id, $templates, $check_unique = true) {
        // Получаем расширенные данные сущности
        $data = $this->getEntityExtendedData($entity_type, $entity['id'], $language_id);
        
        // Подготавливаем плейсхолдеры
        $placeholders = $this->preparePlaceholders($entity_type, $data);
        
        // Применяем шаблоны
        $result = array();
        
        foreach (array('seo_url', 'meta_title', 'meta_description', 'meta_keyword') as $field) {
            $template = isset($templates[$field]) ? $templates[$field] : '';

            // Support templates provided per-language (when generating for all languages)
            if (is_array($template)) {
                if (isset($template[$language_id]) && !empty($template[$language_id])) {
                    $template_value = $template[$language_id];
                } else {
                    // Fallback to the first available template in array
                    $template_value = reset($template);
                }
            } else {
                $template_value = $template;
            }

            // Use a copy of placeholders so we can adjust per-field behavior
            $placeholders_for_field = $placeholders;

            // Special handling for SEO URL - always generate from name, ignore template
            if ($field === 'seo_url') {
                // Generate SEO URL directly from entity name, bypass template system
                $result[$field] = $this->sanitizeSeoUrl($placeholders['{name}']);
                
                // Add language prefix if not disabled and not default language
                $disable_prefix = $this->config->get('module_dockercart_seo_generator_disable_language_prefix');
                
                if (!$disable_prefix) {
                    $this->load->model('localisation/language');
                    $languages = $this->model_localisation_language->getLanguages();

                    // Determine default language id from config (by code or id)
                    $default_lang_id = null;
                    $default_code = $this->config->get('config_language');
                    if ($default_code && is_array($languages)) {
                        foreach ($languages as $lg) {
                            if (isset($lg['code']) && $lg['code'] == $default_code) {
                                $default_lang_id = $lg['language_id'];
                                break;
                            }
                        }
                    }

                    if (!$default_lang_id && $this->config->get('config_language_id')) {
                        $default_lang_id = (int)$this->config->get('config_language_id');
                    }

                    // If we couldn't determine default, fallback to first language in list
                    if (!$default_lang_id && is_array($languages) && !empty($languages)) {
                        $first = reset($languages);
                        $default_lang_id = isset($first['language_id']) ? $first['language_id'] : null;
                    }

                    if ($default_lang_id !== null && (int)$language_id !== (int)$default_lang_id) {
                        $result[$field] = $this->addLanguageSuffixToSeoUrl($result[$field], $language_id, $entity_type, $entity['id']);
                    }
                }
            } else {
                // For meta fields, use template processing
                $result[$field] = $this->applyTemplate($template_value, $placeholders_for_field);

                // Ensure meta fields do not contain HTML tags — sanitize final output
                $result[$field] = $this->cleanHtml($result[$field]);
            }
        }
        
        return $result;
    }
    
    /**
     * Подготовка плейсхолдеров для замены в шаблонах
     */
    private function preparePlaceholders($entity_type, $data) {
        $placeholders = array();
        
        // Базовые плейсхолдеры (очищаем от HTML тегов)
        $placeholders['{name}'] = isset($data['name']) ? $this->cleanHtml($data['name']) : '';
        $placeholders['{description}'] = isset($data['description']) ? $this->truncateDescription($data['description'], 150) : '';
        
        // Плейсхолдеры для магазина (очищаем от HTML)
        $placeholders['{store}'] = $this->cleanHtml($this->config->get('config_name'));
        $placeholders['{city}'] = $this->cleanHtml($this->config->get('config_geocode')); // или другое поле с городом
        
        // Специфичные для типа сущности (очищаем от HTML)
        switch ($entity_type) {
            case 'product':
                $placeholders['{category}'] = isset($data['category_name']) ? $this->cleanHtml($data['category_name']) : '';
                $placeholders['{manufacturer}'] = isset($data['manufacturer_name']) ? $this->cleanHtml($data['manufacturer_name']) : '';
                $placeholders['{model}'] = isset($data['model']) ? $this->cleanHtml($data['model']) : '';
                $placeholders['{sku}'] = isset($data['sku']) ? $this->cleanHtml($data['sku']) : '';
                $placeholders['{price}'] = isset($data['price']) ? $this->currency->format($data['price'], $this->config->get('config_currency')) : '';
                $placeholders['{stock}'] = isset($data['quantity']) && $data['quantity'] > 0 ? 'В наличии' : 'Под заказ';
                break;
                
            case 'category':
                // Категории имеют только базовые плейсхолдеры
                break;
                
            case 'manufacturer':
                // Производители имеют только базовые плейсхолдеры
                break;
                
            case 'information':
                // Информационные страницы имеют только базовые плейсхолдеры
                break;
        }
        
        return $placeholders;
    }
    
    /**
     * Применение шаблона с заменой плейсхолдеров
     */
    private function applyTemplate($template, $placeholders) {
        if (empty($template)) {
            return '';
        }
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Очистка HTML тегов из текста
     */
    private function cleanHtml($text) {
        if (empty($text)) {
            return '';
        }
        
        // Удаляем HTML теги
        $text = strip_tags($text);
        
        // Декодируем HTML сущности
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Удаляем лишние пробелы
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }

    /**
     * Encode quotes to HTML entities to avoid breaking admin HTML attributes
     */
    private function escapeQuotes($text) {
        if ($text === null) {
            return '';
        }

        // Replace double and single quotes with HTML entities
        $text = str_replace(array('"', "'"), array('&quot;', '&#39;'), $text);

        return $text;
    }
    
    /**
     * Обрезка описания до нужной длины
     */
    private function truncateDescription($text, $length = 150) {
        // Сначала очищаем HTML
        $text = $this->cleanHtml($text);
        
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length) . '...';
        }
        
        return $text;
    }
    
    /**
     * Очистка и транслитерация SEO URL
     */
    private function sanitizeSeoUrl($url) {
        // Транслитерация
        $url = $this->transliterate($url);
        
        // Приводим к нижнему регистру
        $url = mb_strtolower($url, 'UTF-8');
        
        // Заменяем пробелы и недопустимые символы на дефис
        $url = preg_replace('/[^a-z0-9\-]/', '-', $url);
        
        // Убираем повторяющиеся дефисы
        $url = preg_replace('/-+/', '-', $url);
        
        // Убираем дефисы в начале и конце
        $url = trim($url, '-');
        
        return $url;
    }
    
    /**
     * Транслитерация текста (multi-language support)
     * Supports: ru-ru, uk-ua, de-de, fr-fr, es-es, pt-pt, ar-ar, zh-cn, ja-jp, id-id
     */
    private function transliterate($text) {
        // Multi-language transliteration map
        $transliteration = array(
            // Русский (Russian)
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            
            // Украинский (Ukrainian)
            'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
            'І' => 'I', 'Ї' => 'Yi', 'Є' => 'Ye', 'Ґ' => 'G',
            
            // Немецкий (German) - умляуты
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            
            // Французский (French)
            'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u',
            'ÿ' => 'y', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
            'À' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Ù' => 'U', 'Û' => 'U',
            'Ÿ' => 'Y', 'Ç' => 'C', 'Œ' => 'OE', 'Æ' => 'AE',
            
            // Испанский / Португальский (Spanish/Portuguese)
            'á' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'ã' => 'a', 'õ' => 'o',
            'Ã' => 'A', 'Õ' => 'O'
        );
        
        $result = strtr($text, $transliteration);
        
        // Fallback for Arabic, Chinese, Japanese and other complex scripts
        // Use iconv to transliterate remaining non-ASCII characters
        if (function_exists('iconv')) {
            $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $result);
            if ($result === false) {
                // If iconv fails, try removing non-ASCII
                $result = preg_replace('/[^\x20-\x7E]/', '', $text);
            }
        } else {
            // If no iconv, just remove non-ASCII characters
            $result = preg_replace('/[^\x20-\x7E]/', '', $result);
        }
        
        return $result;
    }
    
    /**
     * Обеспечение уникальности SEO URL
     */
    /**
     * Добавление суффикса языка к SEO URL для обеспечения уникальности
     */
    private function addLanguageSuffixToSeoUrl($url, $language_id, $entity_type, $entity_id) {
        // Получаем код языка
        $query = $this->db->query("SELECT code FROM `" . DB_PREFIX . "language` WHERE language_id = '" . (int)$language_id . "'");
        
        if (!$query->num_rows) {
            return $url;
        }
        
        $language_code = $query->row['code'];
        
        // Если URL уже начинается с кода языка, не добавляем еще раз
        if (strpos($url, $language_code . '-') === 0) {
            return $url;
        }
        
        // Добавляем код языка в начало URL
        return $language_code . '-' . $url;
    }
    
    /**
     * Обеспечение уникальности SEO URL
     */
    // Uniqueness check removed - URLs are now overwritten without numeric suffixes
    
    /**
     * Сохранение SEO URL
     */
    private function saveSeoUrl($entity_type, $entity_id, $language_id, $seo_url) {
        if (empty($seo_url)) {
            return false;
        }
        
        $query_field = $entity_type . '_id';
        $query_value = $query_field . '=' . (int)$entity_id;
        
        // Проверяем, существует ли уже запись
        $existing = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "seo_url` 
            WHERE `query` = '" . $this->db->escape($query_value) . "' 
            AND `language_id` = '" . (int)$language_id . "'
        ");
        
        if ($existing->num_rows) {
            // If keyword is the same, nothing to do
            if (isset($existing->row['keyword']) && $existing->row['keyword'] === $seo_url) {
                return false;
            }

            // Обновляем существующую запись
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "seo_url` 
                SET `keyword` = '" . $this->db->escape($seo_url) . "',
                    `store_id` = '0'
                WHERE `query` = '" . $this->db->escape($query_value) . "' 
                AND `language_id` = '" . (int)$language_id . "'
            ");
			$this->markSeoUrlCacheDirty();

            return true;
        } else {
            // Создаём новую запись
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "seo_url` 
                SET `query` = '" . $this->db->escape($query_value) . "',
                    `keyword` = '" . $this->db->escape($seo_url) . "',
                    `language_id` = '" . (int)$language_id . "',
                    `store_id` = '0'
            ");
			$this->markSeoUrlCacheDirty();

            return true;
        }
    }
    
    /**
     * Сохранение мета-тегов
     */
    private function saveMetaTags($entity_type, $entity_id, $language_id, $meta_tags) {
        switch ($entity_type) {
            case 'product':
                $table = 'product_description';
                $id_field = 'product_id';
                break;
            case 'category':
                $table = 'category_description';
                $id_field = 'category_id';
                break;
            case 'manufacturer':
                // Some OpenCart installations (vanilla) don't have a manufacturer_description table
                // (OCStore and some forks add it). Detect the table existence and skip saving
                // meta-tags for manufacturers when the table is missing to avoid fatal errors.
                $table = 'manufacturer_description';
                $id_field = 'manufacturer_id';

                // Check whether the description table exists
                $check = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . $table . "'");

                if (!$check || !$check->num_rows) {
                    // Log and skip saving meta-tags for manufacturers on installations without this table
                    $this->logger->debug('manufacturer_description table not found, skipping meta save for manufacturer_id=' . (int)$entity_id);
                    return;
                }

                break;
            case 'information':
                $table = 'information_description';
                $id_field = 'information_id';
                break;
            default:
                return;
        }
        
        // Fetch current values for comparison
        $current = $this->db->query("SELECT * FROM `" . DB_PREFIX . $table . "` WHERE `" . $id_field . "` = '" . (int)$entity_id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");
        
        // Log existing and incoming meta for debugging
        $this->logger->debug('[META] saveMetaTags called: type=' . $entity_type . ', id=' . $entity_id . ', lang=' . $language_id . ', incoming_meta_title=' . (isset($meta_tags['meta_title']) ? substr($meta_tags['meta_title'],0,200) : ''));
        
        if ($current && $current->num_rows) {
            $this->logger->debug('[META] current meta_title=' . (isset($current->row['meta_title']) ? substr($current->row['meta_title'],0,200) : '')); 
        } else {
            $this->logger->debug('[META] current row not found (will INSERT if update_fields present)');
        }

        // Обновляем мета-теги
        $update_fields = array();
        
        if (!empty($meta_tags['meta_title'])) {
            // Clean HTML and encode quotes to avoid breaking admin attribute rendering
            $meta_tags['meta_title'] = $this->escapeQuotes($this->cleanHtml($meta_tags['meta_title']));
            $update_fields[] = "`meta_title` = '" . $this->db->escape($meta_tags['meta_title']) . "'";
        }

        if (!empty($meta_tags['meta_description'])) {
            // Clean HTML and encode quotes
            $meta_tags['meta_description'] = $this->escapeQuotes($this->cleanHtml($meta_tags['meta_description']));
            $update_fields[] = "`meta_description` = '" . $this->db->escape($meta_tags['meta_description']) . "'";
        }

        if (!empty($meta_tags['meta_keyword'])) {
            // Keywords shouldn't contain HTML; clean and encode
            $meta_tags['meta_keyword'] = $this->escapeQuotes($this->cleanHtml($meta_tags['meta_keyword']));
            $update_fields[] = "`meta_keyword` = '" . $this->db->escape($meta_tags['meta_keyword']) . "'";
        }
        
        if (!empty($update_fields)) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . $table . "` 
                SET " . implode(', ', $update_fields) . "
                WHERE `" . $id_field . "` = '" . (int)$entity_id . "' 
                AND `language_id` = '" . (int)$language_id . "'
            ");

            return true;
        }

        return false;
    }
    
    /**
     * Логирование генерации
     */
    private function logGeneration($entity_type, $entity_id, $language_id, $generated_data) {
        // Проверяем, включено ли логирование (debug режим)
        if (!$this->config->get('module_dockercart_seo_generator_debug')) {
            return;
        }

        $this->ensureSeoLogSchema();
        
        foreach ($generated_data as $field_type => $new_value) {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "dockercart_seo_log` 
                SET `entity_type` = '" . $this->db->escape($entity_type) . "',
                    `entity_id` = '" . (int)$entity_id . "',
                    `field_type` = '" . $this->db->escape($field_type) . "',
                    `new_value` = '" . $this->db->escape($new_value) . "',
                    `date_added` = NOW()
            ");
        }
    }
    
    /**
     * Генерация SEO данных для одной сущности (для автогенерации через события)
     */
    public function generateSeoDataForSingleEntity($entity_type, $entity_id, $language_id, $templates, $force_update = false) {
        // Логирование для отладки
        $this->logger->debug("generateSeoDataForSingleEntity called: type=$entity_type, id=$entity_id, lang=$language_id, force_update=$force_update");
        
        // Получаем данные сущности
        $entity_data = $this->getEntityExtendedData($entity_type, $entity_id, $language_id);
        
        if (empty($entity_data)) {
            $this->logger->debug("No entity data found for $entity_type ID $entity_id");
            return false;
        }
        
        // Создаём массив с ID и именем для совместимости
        $entity = array(
            'id' => $entity_id,
            'name' => isset($entity_data['name']) ? $entity_data['name'] : ''
        );
        
        // Генерируем данные
        $generated = $this->generateEntityData($entity_type, $entity, $language_id, $templates);
        
        // Проверяем, пуст ли SEO URL перед сохранением
        // При force_update = true перезаписываем всегда
        $seo_url_empty = $this->checkSeoUrlEmpty($entity_type, $entity_id, $language_id);
        if ($seo_url_empty || $force_update) {
            $this->saveSeoUrl($entity_type, $entity_id, $language_id, $generated['seo_url']);
        }
        
        // Для мета-тегов проверяем, пусты ли они
        // При force_update = true перезаписываем всегда
        $meta_empty = $this->checkMetaTagsEmpty($entity_type, $entity_id, $language_id);
        
        if ($meta_empty || $force_update) {
            $this->saveMetaTags($entity_type, $entity_id, $language_id, array(
                'meta_title' => $generated['meta_title'],
                'meta_description' => $generated['meta_description'],
                'meta_keyword' => $generated['meta_keyword']
            ));
        }
        
        // Логирование
        if ($this->config->get('module_dockercart_seo_generator_debug')) {
            $this->logGeneration($entity_type, $entity_id, $language_id, $generated);
        }

		$this->flushSeoUrlCacheInvalidation();
        
        return true;
    }
    
    /**
     * Проверка, пуст ли SEO URL
     */
    private function checkSeoUrlEmpty($entity_type, $entity_id, $language_id) {
        $query_field = $entity_type . '_id';
        $query_value = $query_field . '=' . (int)$entity_id;
        
        $query = $this->db->query("
            SELECT keyword FROM `" . DB_PREFIX . "seo_url` 
            WHERE query = '" . $this->db->escape($query_value) . "'
            AND language_id = '" . (int)$language_id . "'
        ");
        
        if (!$query->num_rows) {
            return true; // Нет записи - значит пусто
        }
        
        $row = $query->row;
        return empty($row['keyword']);
    }
    
    /**
     * Проверка, пусты ли мета-теги
     */
    private function checkMetaTagsEmpty($entity_type, $entity_id, $language_id) {
        switch ($entity_type) {
            case 'product':
                $table = 'product_description';
                $id_field = 'product_id';
                break;
            case 'category':
                $table = 'category_description';
                $id_field = 'category_id';
                break;
            case 'manufacturer':
                return true; // У производителей нет мета-тегов
            case 'information':
                $table = 'information_description';
                $id_field = 'information_id';
                break;
            default:
                return false;
        }
        
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . $table . "` 
            WHERE `" . $id_field . "` = '" . (int)$entity_id . "' 
            AND `language_id` = '" . (int)$language_id . "'
        ");
        
        if (!$query->num_rows) {
            return true;
        }
        
        $row = $query->row;
        
        return (empty($row['meta_title']) && empty($row['meta_description']));
    }
    
    /**
     * Получение расширенных данных сущности
     */
    private function getEntityExtendedData($entity_type, $entity_id, $language_id) {
        switch ($entity_type) {
            case 'product':
                return $this->getProductExtendedData($entity_id, $language_id);
            case 'category':
                return $this->getCategoryExtendedData($entity_id, $language_id);
            case 'manufacturer':
                return $this->getManufacturerExtendedData($entity_id, $language_id);
            case 'information':
                return $this->getInformationExtendedData($entity_id, $language_id);
            default:
                return array();
        }
    }
    
    /**
     * Получение расширенных данных товара
     */
    private function getProductExtendedData($product_id, $language_id) {
        $query = $this->db->query("
            SELECT 
                p.*,
                pd.name,
                pd.description,
                pd.meta_title,
                pd.meta_description,
                pd.meta_keyword,
                m.name as manufacturer_name,
                (SELECT name FROM `" . DB_PREFIX . "category_description` cd 
                 WHERE cd.category_id = (SELECT category_id FROM `" . DB_PREFIX . "product_to_category` 
                                         WHERE product_id = p.product_id LIMIT 1) 
                 AND cd.language_id = '" . (int)$language_id . "') as category_name
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
            LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
            WHERE p.product_id = '" . (int)$product_id . "'
            AND pd.language_id = '" . (int)$language_id . "'
        ");
        
        return $query->row;
    }
    
    /**
     * Получение расширенных данных категории
     */
    private function getCategoryExtendedData($category_id, $language_id) {
        $query = $this->db->query("
            SELECT 
                c.*,
                cd.name,
                cd.description,
                cd.meta_title,
                cd.meta_description,
                cd.meta_keyword
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)
            WHERE c.category_id = '" . (int)$category_id . "'
            AND cd.language_id = '" . (int)$language_id . "'
        ");
        
        return $query->row;
    }
    
    /**
     * Получение расширенных данных производителя
     */
    private function getManufacturerExtendedData($manufacturer_id, $language_id) {
        // В OpenCart 3.x у производителя нет таблицы description, используем базовую таблицу
        $query = $this->db->query("
            SELECT 
                m.*,
                m.name
            FROM `" . DB_PREFIX . "manufacturer` m
            WHERE m.manufacturer_id = '" . (int)$manufacturer_id . "'
        ");
        
        $data = $query->row;
        
        // Добавляем пустые поля для совместимости
        $data['description'] = '';
        $data['meta_title'] = '';
        $data['meta_description'] = '';
        $data['meta_keyword'] = '';
        
        return $data;
    }
    
    /**
     * Получение расширенных данных информационной страницы
     */
    private function getInformationExtendedData($information_id, $language_id) {
        $query = $this->db->query("
            SELECT 
                i.*,
                id.title as name,
                id.description,
                id.meta_title,
                id.meta_description,
                id.meta_keyword
            FROM `" . DB_PREFIX . "information` i
            LEFT JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id)
            WHERE i.information_id = '" . (int)$information_id . "'
            AND id.language_id = '" . (int)$language_id . "'
        ");
        
        return $query->row;
    }
    
    /**
     * Получение списка сущностей для обработки
     */
    private function getEntities($entity_type, $language_id, $offset = 0, $limit = 50, $filter_empty_url = false, $filter_empty_meta = false) {
        switch ($entity_type) {
            case 'product':
                return $this->getProducts($language_id, $offset, $limit, $filter_empty_url, $filter_empty_meta);
            case 'category':
                return $this->getCategories($language_id, $offset, $limit, $filter_empty_url, $filter_empty_meta);
            case 'manufacturer':
                return $this->getManufacturers($language_id, $offset, $limit, $filter_empty_url, $filter_empty_meta);
            case 'information':
                return $this->getInformationPages($language_id, $offset, $limit, $filter_empty_url, $filter_empty_meta);
            default:
                return array();
        }
    }
    
    /**
     * Получение товаров
     */
    private function getProducts($language_id, $offset = 0, $limit = 50, $filter_empty_url = false, $filter_empty_meta = false) {
        // Если language_id = 0, работаем со всеми товарами (не привязано к языку)
        if ($language_id == 0) {
            $sql = "SELECT DISTINCT p.product_id as id, 
                    (SELECT pd.name FROM `" . DB_PREFIX . "product_description` pd WHERE pd.product_id = p.product_id LIMIT 1) as name
                    FROM `" . DB_PREFIX . "product` p";
            
            $where = array();
            $empty_url_condition = '';
            $empty_meta_condition = '';
            
            if ($filter_empty_url) {
                $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('product_id=', p.product_id))";
                $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
            }
            
            if ($filter_empty_meta) {
                $sql .= " LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)";
                $empty_meta_condition = "(pd.meta_title = '' OR pd.meta_title IS NULL OR pd.meta_description = '' OR pd.meta_description IS NULL)";
            }

            if ($filter_empty_url && $filter_empty_meta) {
                $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
            } else {
                if (!empty($empty_url_condition)) {
                    $where[] = $empty_url_condition;
                }
                if (!empty($empty_meta_condition)) {
                    $where[] = $empty_meta_condition;
                }
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY p.product_id ASC";
            $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
            
            $query = $this->db->query($sql);
            return $query->rows;
        }
        
        // Если language_id > 0, выбираем товары для конкретного языка
        $sql = "SELECT p.product_id as id, pd.name
                FROM `" . DB_PREFIX . "product` p
                LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)";
        
        $where = array();
        $empty_url_condition = '';
        $empty_meta_condition = '';
        
        if ($language_id > 0) {
            $where[] = "pd.language_id = '" . (int)$language_id . "'";
        }
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('product_id=', p.product_id) AND su.language_id = pd.language_id)";
            $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        if ($filter_empty_meta) {
            $empty_meta_condition = "(pd.meta_title = '' OR pd.meta_title IS NULL OR pd.meta_description = '' OR pd.meta_description IS NULL)";
        }

        if ($filter_empty_url && $filter_empty_meta) {
            $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
        } else {
            if (!empty($empty_url_condition)) {
                $where[] = $empty_url_condition;
            }

            if (!empty($empty_meta_condition)) {
                $where[] = $empty_meta_condition;
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY p.product_id ASC";
        $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }
    
    /**
     * Получение категорий
     */
    private function getCategories($language_id, $offset = 0, $limit = 50, $filter_empty_url = false, $filter_empty_meta = false) {
        // Если language_id = 0, работаем со всеми категориями
        if ($language_id == 0) {
            $sql = "SELECT DISTINCT c.category_id as id, 
                    (SELECT cd.name FROM `" . DB_PREFIX . "category_description` cd WHERE cd.category_id = c.category_id LIMIT 1) as name
                    FROM `" . DB_PREFIX . "category` c";
            
            $where = array();
            $empty_url_condition = '';
            $empty_meta_condition = '';
            
            if ($filter_empty_url) {
                $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('category_id=', c.category_id))";
                $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
            }
            
            if ($filter_empty_meta) {
                $sql .= " LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)";
                $empty_meta_condition = "(cd.meta_title = '' OR cd.meta_title IS NULL OR cd.meta_description = '' OR cd.meta_description IS NULL)";
            }

            if ($filter_empty_url && $filter_empty_meta) {
                $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
            } else {
                if (!empty($empty_url_condition)) {
                    $where[] = $empty_url_condition;
                }

                if (!empty($empty_meta_condition)) {
                    $where[] = $empty_meta_condition;
                }
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY c.category_id ASC";
            $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
            
            $query = $this->db->query($sql);
            return $query->rows;
        }
        
        // Для конкретного языка
        $sql = "SELECT c.category_id as id, cd.name
                FROM `" . DB_PREFIX . "category` c
                LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)";
        
        $where = array();
        $empty_url_condition = '';
        $empty_meta_condition = '';
        
        if ($language_id > 0) {
            $where[] = "cd.language_id = '" . (int)$language_id . "'";
        }
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('category_id=', c.category_id) AND su.language_id = cd.language_id)";
            $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        if ($filter_empty_meta) {
            $empty_meta_condition = "(cd.meta_title = '' OR cd.meta_title IS NULL OR cd.meta_description = '' OR cd.meta_description IS NULL)";
        }

        if ($filter_empty_url && $filter_empty_meta) {
            $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
        } else {
            if (!empty($empty_url_condition)) {
                $where[] = $empty_url_condition;
            }

            if (!empty($empty_meta_condition)) {
                $where[] = $empty_meta_condition;
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY c.category_id ASC";
        $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }
    
    /**
     * Получение производителей
     */
    private function getManufacturers($language_id, $offset = 0, $limit = 50, $filter_empty_url = false, $filter_empty_meta = false) {
        $sql = "SELECT m.manufacturer_id as id, m.name
                FROM `" . DB_PREFIX . "manufacturer` m";
        
        $where = array();
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('manufacturer_id=', m.manufacturer_id))";
            $where[] = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        // Производители не имеют мета-тегов в базовой таблице OpenCart
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY m.manufacturer_id ASC";
        $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }
    
    /**
     * Получение информационных страниц
     */
    private function getInformationPages($language_id, $offset = 0, $limit = 50, $filter_empty_url = false, $filter_empty_meta = false) {
        // Если language_id = 0, работаем со всеми информ. страницами
        if ($language_id == 0) {
            $sql = "SELECT DISTINCT i.information_id as id, 
                    (SELECT id.title FROM `" . DB_PREFIX . "information_description` id WHERE id.information_id = i.information_id LIMIT 1) as name
                    FROM `" . DB_PREFIX . "information` i";
            
            $where = array();
            $empty_url_condition = '';
            $empty_meta_condition = '';
            
            if ($filter_empty_url) {
                $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('information_id=', i.information_id))";
                $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
            }
            
            if ($filter_empty_meta) {
                $sql .= " LEFT JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id)";
                $empty_meta_condition = "(id.meta_title = '' OR id.meta_title IS NULL OR id.meta_description = '' OR id.meta_description IS NULL)";
            }

            if ($filter_empty_url && $filter_empty_meta) {
                $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
            } else {
                if (!empty($empty_url_condition)) {
                    $where[] = $empty_url_condition;
                }

                if (!empty($empty_meta_condition)) {
                    $where[] = $empty_meta_condition;
                }
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            $sql .= " ORDER BY i.information_id ASC";
            $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
            
            $query = $this->db->query($sql);
            return $query->rows;
        }
        
        // Для конкретного языка
        $sql = "SELECT i.information_id as id, COALESCE(id.title, CONCAT('Information ', i.information_id)) as name
                FROM `" . DB_PREFIX . "information` i
                LEFT JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id AND id.language_id = '" . (int)$language_id . "')";
        
        $where = array();
        $empty_url_condition = '';
        $empty_meta_condition = '';
        
        // No need to filter by language_id since it's already in the JOIN condition
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('information_id=', i.information_id) AND su.language_id = '" . (int)$language_id . "')";
            $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        if ($filter_empty_meta) {
            // Only filter if description exists AND meta is empty
            // If no description exists at all (id.information_id IS NULL), we still want to include it
            $empty_meta_condition = "(id.information_id IS NULL OR id.meta_title = '' OR id.meta_title IS NULL OR id.meta_description = '' OR id.meta_description IS NULL)";
        }

        if ($filter_empty_url && $filter_empty_meta) {
            $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
        } else {
            if (!empty($empty_url_condition)) {
                $where[] = $empty_url_condition;
            }

            if (!empty($empty_meta_condition)) {
                $where[] = $empty_meta_condition;
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY i.information_id ASC";
        $sql .= " LIMIT " . (int)$offset . "," . (int)$limit;
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }
    
    /**
     * Вспомогательные методы для статистики
     */
    private function getTotalProducts($language_id = 0, $filter_empty_url = false, $filter_empty_meta = false) {
        $sql = "SELECT COUNT(DISTINCT p.product_id) as total
                FROM `" . DB_PREFIX . "product` p
                LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)";
        
        $where = array();
        $empty_url_condition = '';
        $empty_meta_condition = '';
        
        if ($language_id > 0) {
            $where[] = "pd.language_id = '" . (int)$language_id . "'";
        }
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('product_id=', p.product_id) AND su.language_id = pd.language_id)";
            $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        if ($filter_empty_meta) {
            $empty_meta_condition = "(pd.meta_title = '' OR pd.meta_title IS NULL OR pd.meta_description = '' OR pd.meta_description IS NULL)";
        }

        if ($filter_empty_url && $filter_empty_meta) {
            $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
        } else {
            if (!empty($empty_url_condition)) {
                $where[] = $empty_url_condition;
            }

            if (!empty($empty_meta_condition)) {
                $where[] = $empty_meta_condition;
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $query = $this->db->query($sql);
        return $query->row['total'];
    }
    
    private function getTotalProductsWithEmptyUrl() {
        $query = $this->db->query("
            SELECT COUNT(DISTINCT p.product_id) as total
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('product_id=', p.product_id))
            WHERE su.keyword IS NULL OR su.keyword = ''
        ");
        return $query->row['total'];
    }
    
    private function getTotalProductsWithEmptyMeta() {
        $query = $this->db->query("
            SELECT COUNT(DISTINCT p.product_id) as total
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
            WHERE pd.meta_title = '' OR pd.meta_title IS NULL 
               OR pd.meta_description = '' OR pd.meta_description IS NULL
        ");
        return $query->row['total'];
    }
    
    private function getTotalCategories($language_id = 0, $filter_empty_url = false, $filter_empty_meta = false) {
        $sql = "SELECT COUNT(DISTINCT c.category_id) as total
                FROM `" . DB_PREFIX . "category` c
                LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)";
        
        $where = array();
        $empty_url_condition = '';
        $empty_meta_condition = '';
        
        if ($language_id > 0) {
            $where[] = "cd.language_id = '" . (int)$language_id . "'";
        }
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('category_id=', c.category_id) AND su.language_id = cd.language_id)";
            $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        if ($filter_empty_meta) {
            $empty_meta_condition = "(cd.meta_title = '' OR cd.meta_title IS NULL OR cd.meta_description = '' OR cd.meta_description IS NULL)";
        }

        if ($filter_empty_url && $filter_empty_meta) {
            $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
        } else {
            if (!empty($empty_url_condition)) {
                $where[] = $empty_url_condition;
            }

            if (!empty($empty_meta_condition)) {
                $where[] = $empty_meta_condition;
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $query = $this->db->query($sql);
        return $query->row['total'];
    }
    
    private function getTotalCategoriesWithEmptyUrl() {
        $query = $this->db->query("
            SELECT COUNT(DISTINCT c.category_id) as total
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('category_id=', c.category_id))
            WHERE su.keyword IS NULL OR su.keyword = ''
        ");
        return $query->row['total'];
    }
    
    private function getTotalCategoriesWithEmptyMeta() {
        $query = $this->db->query("
            SELECT COUNT(DISTINCT c.category_id) as total
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)
            WHERE cd.meta_title = '' OR cd.meta_title IS NULL 
               OR cd.meta_description = '' OR cd.meta_description IS NULL
        ");
        return $query->row['total'];
    }
    
    private function getTotalManufacturers($language_id = 0, $filter_empty_url = false, $filter_empty_meta = false) {
        $sql = "SELECT COUNT(*) as total FROM `" . DB_PREFIX . "manufacturer`";
        
        if ($filter_empty_url) {
            $sql = "SELECT COUNT(DISTINCT m.manufacturer_id) as total
                    FROM `" . DB_PREFIX . "manufacturer` m
                    LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('manufacturer_id=', m.manufacturer_id))
                    WHERE su.keyword IS NULL OR su.keyword = ''";
        }
        
        $query = $this->db->query($sql);
        return $query->row['total'];
    }
    
    private function getTotalManufacturersWithEmptyUrl() {
        return $this->getTotalManufacturers(0, true, false);
    }
    
    private function getTotalManufacturersWithEmptyMeta() {
        return 0; // Производители не имеют мета-тегов по умолчанию
    }
    
    private function getTotalInformation($language_id = 0, $filter_empty_url = false, $filter_empty_meta = false) {
        $sql = "SELECT COUNT(DISTINCT i.information_id) as total
                FROM `" . DB_PREFIX . "information` i
                LEFT JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id)";
        
        $where = array();
        $empty_url_condition = '';
        $empty_meta_condition = '';
        
        if ($language_id > 0) {
            $where[] = "id.language_id = '" . (int)$language_id . "'";
        }
        
        if ($filter_empty_url) {
            $sql .= " LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('information_id=', i.information_id) AND su.language_id = id.language_id)";
            $empty_url_condition = "(su.keyword IS NULL OR su.keyword = '')";
        }
        
        if ($filter_empty_meta) {
            $empty_meta_condition = "(id.meta_title = '' OR id.meta_title IS NULL OR id.meta_description = '' OR id.meta_description IS NULL)";
        }

        if ($filter_empty_url && $filter_empty_meta) {
            $where[] = '(' . $empty_url_condition . ' OR ' . $empty_meta_condition . ')';
        } else {
            if (!empty($empty_url_condition)) {
                $where[] = $empty_url_condition;
            }

            if (!empty($empty_meta_condition)) {
                $where[] = $empty_meta_condition;
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $query = $this->db->query($sql);
        return $query->row['total'];
    }
    
    private function getTotalInformationWithEmptyUrl() {
        $query = $this->db->query("
            SELECT COUNT(DISTINCT i.information_id) as total
            FROM `" . DB_PREFIX . "information` i
            LEFT JOIN `" . DB_PREFIX . "seo_url` su ON (su.query = CONCAT('information_id=', i.information_id))
            WHERE su.keyword IS NULL OR su.keyword = ''
        ");
        return $query->row['total'];
    }
    
    private function getTotalInformationWithEmptyMeta() {
        $query = $this->db->query("
            SELECT COUNT(DISTINCT i.information_id) as total
            FROM `" . DB_PREFIX . "information` i
            LEFT JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id)
            WHERE id.meta_title = '' OR id.meta_title IS NULL 
               OR id.meta_description = '' OR id.meta_description IS NULL
        ");
        return $query->row['total'];
    }
    
    /**
     * Сканирование доступных контроллеров с методом index()
     * 
     * @return array Список контроллеров с их маршрутами, заголовками и SEO URLs для всех языков
     */
    public function scanAvailableControllers() {
        $controllers = array();
        
        // Получаем все языки
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        
        // Каталожные контроллеры для сканирования
        $catalog_paths = array(
            DIR_APPLICATION . '../catalog/controller/information/',
            DIR_APPLICATION . '../catalog/controller/product/',
            DIR_APPLICATION . '../catalog/controller/account/',
            DIR_APPLICATION . '../catalog/controller/checkout/',
            DIR_APPLICATION . '../catalog/controller/error/',
            DIR_APPLICATION . '../catalog/controller/affiliate/',
        );
        
        foreach ($catalog_paths as $base_path) {
            if (!is_dir($base_path)) {
                continue;
            }
            
            // Рекурсивно ищем все PHP файлы в директории и подпапках
            $files = $this->scanDirectoryRecursive($base_path);
            
            foreach ($files as $file) {
                // Получаем путь файла относительно base_path
                $relative_path = str_replace($base_path, '', $file);
                
                // Удаляем расширение .php, но сохраняем структуру папок
                $relative_path_no_ext = preg_replace('/\.php$/', '', $relative_path);
                
                // Получаем префикс папки из base_path (например 'account', 'checkout')
                $prefix = basename(rtrim($base_path, '/'));
                
                // Формируем маршрут контроллера
                // Заменяем обратные слеши на прямые и убираем лишние пробелы
                $relative_part = str_replace('\\', '/', $relative_path_no_ext);
                $route = $prefix . '/' . $relative_part;
                
                // Проверяем наличие метода index() в контроллере
                if ($this->hasIndexMethod($file)) {
                    // Получаем SEO URLs для всех языков
                    $seo_urls = array();
                    foreach ($languages as $language) {
                        $seo_url = $this->getControllerSeoUrl($route, $language['language_id']);
                        $seo_urls[$language['language_id']] = array(
                            'language_id' => $language['language_id'],
                            'language_code' => $language['code'],
                            'language_name' => $language['name'],
                            'keyword' => $seo_url
                        );
                    }
                    
                    // Генерируем заголовок из названия файла и пути
                    $filename_only = basename($relative_path_no_ext);
                    $title = ucwords(str_replace('_', ' ', $filename_only));
                    // Генерируем заголовок из названия файла и пути
                    $filename_only = basename($relative_path_no_ext);
                    $title = ucwords(str_replace('_', ' ', $filename_only));
                    
                    // Если файл в подпапке, добавляем название папки
                    $parent_dir = dirname($relative_path_no_ext);
                    if ($parent_dir !== '.' && !empty($parent_dir)) {
                        $parent_name = ucwords(str_replace('_', ' ', str_replace('/', ' ', $parent_dir)));
                        $title = $parent_name . ' ' . $title;
                    }
                    
                    $controllers[] = array(
                        'route' => $route,
                        'title' => $title,
                        'file' => $file,
                        'seo_urls' => $seo_urls
                    );
                }
            }
        }
        
        return $controllers;
    }
    
    /**
     * Рекурсивное сканирование директории для поиска PHP файлов контроллеров
     * 
     * @param string $directory Директория для сканирования
     * @return array Массив путей к PHP файлам
     */
    private function scanDirectoryRecursive($directory) {
        $files = array();
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $items = scandir($directory);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $directory . $item;
            
            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                // Это PHP файл - добавляем его
                $files[] = $path;
            } elseif (is_dir($path)) {
                // Это директория - рекурсивно сканируем её
                $subdirectory_files = $this->scanDirectoryRecursive($path . '/');
                $files = array_merge($files, $subdirectory_files);
            }
        }
        
        return $files;
    }
    
    /**
     * Проверка наличия метода index() в контроллере
     * 
     * @param string $file Путь к файлу контроллера
     * @return bool
     */
    private function hasIndexMethod($file) {
        if (!file_exists($file)) {
            return false;
        }
        
        $content = file_get_contents($file);
        
        // Ищем публичный или protected метод index()
        if (preg_match('/\b(?:public|protected)\s+function\s+index\s*\(/i', $content)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Получить SEO URL контроллера для конкретного языка
     * 
     * @param string $route Маршрут контроллера
     * @param int $language_id ID языка
     * @return string SEO URL или пустая строка
     */
    public function getControllerSeoUrl($route, $language_id) {
        $query = $this->db->query("
            SELECT keyword
            FROM `" . DB_PREFIX . "seo_url`
            WHERE `query` = '" . $this->db->escape($route) . "'
            AND `language_id` = '" . (int)$language_id . "'
            LIMIT 1
        ");
        
        return !empty($query->row) ? $query->row['keyword'] : '';
    }
    
    /**
     * Проверка наличия SEO URL для маршрута
     * 
     * @param string $route Маршрут контроллера
     * @return bool
     */
    private function hasSeoUrl($route) {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "seo_url`
            WHERE `query` = '" . $this->db->escape($route) . "'
        ");
        
        return ($query->row['total'] > 0);
    }
    
    /**
     * Генерация SEO URL для контроллеров
     * 
     * @param array $controllers Массив контроллеров с route и title
     * @param int $language_id ID языка (0 = все языки)
     * @param bool $overwrite Перезаписывать ли существующие SEO URLs
     * @return array Результат генерации
     */
    public function generateControllersSeoUrls($controllers, $language_id = 0, $overwrite = false) {
        $result = array(
            'processed' => 0,
            'skipped' => 0,
            'total' => count($controllers)
        );
        
        // Получаем языки
        if ($language_id == 0) {
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
        } else {
            $this->load->model('localisation/language');
            $lang_info = $this->model_localisation_language->getLanguage($language_id);
            $languages = $lang_info ? array($lang_info) : array();
        }
        
        foreach ($controllers as $controller) {
            if (!isset($controller['route']) || !isset($controller['title'])) {
                continue;
            }
            
            $route = $controller['route'];
            $title = $controller['title'];
            
            foreach ($languages as $language) {
                $lang_id = $language['language_id'];
                
                // Проверяем существующий SEO URL
                $existing_url = $this->getControllerSeoUrl($route, $lang_id);
                
                // Пропускаем если URL уже существует и не нужно перезаписывать
                if (!empty($existing_url) && !$overwrite) {
                    $result['skipped']++;
                    continue;
                }
                
                // Генерируем SEO URL из title
                $seo_url = $this->generateSlug($title);
                
                // Проверяем уникальность SEO URL и добавляем префикс пути контроллера если нужно
                $seo_url = $this->ensureUniqueSeoUrl($seo_url, $route, $lang_id);
                
                // Сохраняем SEO URL
                $this->saveSeoUrlForController($route, $seo_url, $lang_id);
                $result['processed']++;
            }
        }

		$this->flushSeoUrlCacheInvalidation();
        
        return $result;
    }
    
    /**
     * Обеспечение уникальности SEO URL
     * Если URL уже существует, добавляет префикс пути контроллера
     * Примеры:
     *   - Существует 'login', добавляем 'checkout-login' (из checkout/login)
     *   - Существует 'cart', добавляем 'common-cart' (из common/cart)
     * 
     * @param string $seo_url Базовый SEO URL
     * @param string $route Путь контроллера (например, "checkout/login")
     * @param int $language_id ID языка
     * @return string Уникальный SEO URL
     */
    private function ensureUniqueSeoUrl($seo_url, $route, $language_id) {
        $original_seo_url = $seo_url;
        
        // Если URL существует, пытаемся добавить префикс пути контроллера
        if ($this->seoUrlExists($seo_url, $language_id, $route)) {
            // Извлекаем первую часть пути (например, 'checkout' из 'checkout/login')
            $parts = explode('/', $route);
            if (count($parts) >= 2) {
                $prefix = $parts[0]; // Первая часть пути контроллера
                $seo_url = $prefix . '-' . $original_seo_url;
            }
            
            // Если и с префиксом существует, добавляем цифры
            $counter = 1;
            $fallback_url = $seo_url;
            while ($this->seoUrlExists($seo_url, $language_id, $route)) {
                $seo_url = $fallback_url . '-' . $counter;
                $counter++;
            }
        }
        
        return $seo_url;
    }
    
    /**
     * Проверка существования SEO URL
     * 
     * @param string $seo_url SEO URL для проверки
     * @param int $language_id ID языка
     * @param string $exclude_query Query для исключения из проверки
     * @return bool
     */
    private function seoUrlExists($seo_url, $language_id, $exclude_query = '') {
        $sql = "SELECT COUNT(*) as total
                FROM `" . DB_PREFIX . "seo_url`
                WHERE `keyword` = '" . $this->db->escape($seo_url) . "'
                AND `language_id` = '" . (int)$language_id . "'";
        
        if (!empty($exclude_query)) {
            $sql .= " AND `query` != '" . $this->db->escape($exclude_query) . "'";
        }
        
        $query = $this->db->query($sql);
        
        return ($query->row['total'] > 0);
    }

    /**
     * Generate a URL-friendly slug from a string
     * Uses transliteration when available and falls back to iconv.
     *
     * @param string $text
     * @return string
     */
    public function generateSlug($text) {
        if ($text === null) {
            return '';
        }

        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Transliterate to Latin when possible
        if (function_exists('transliterator_transliterate')) {
            try {
                $trans = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
                if ($trans !== null) {
                    $text = $trans;
                }
            } catch (Exception $e) {
                // ignore and fallback
            }
        } else {
            $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($trans !== false) {
                $text = $trans;
            }
        }

        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^A-Za-z0-9]+/u', '-', $text);

        // Remove duplicate hyphens and trim
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');

        // Lowercase
        $text = strtolower($text);

        return $text;
    }
    
    /**
     * Сохранение SEO URL для контроллера в базу данных
     * 
     * @param string $query Query (например, "common/home")
     * @param string $seo_url SEO URL
     * @param int $language_id ID языка
     */
    private function saveSeoUrlForController($query, $seo_url, $language_id) {
        // Удаляем существующий SEO URL для этого query и языка
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "seo_url`
            WHERE `query` = '" . $this->db->escape($query) . "'
            AND `language_id` = '" . (int)$language_id . "'
        ");
        
        // Вставляем новый SEO URL
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "seo_url`
            SET `store_id` = '0',
                `language_id` = '" . (int)$language_id . "',
                `query` = '" . $this->db->escape($query) . "',
                `keyword` = '" . $this->db->escape($seo_url) . "'
        ");

		$this->markSeoUrlCacheDirty();
    }
    
    /**
     * Обновление SEO URL для контроллера
     * 
     * @param string $route Маршрут контроллера
     * @param string $seo_url Новый SEO URL
     * @param int $language_id ID языка
     * @return bool Успешность операции
     */
    public function updateControllerSeoUrl($route, $seo_url, $language_id) {
        // Проверяем уникальность SEO URL (исключая текущий маршрут)
        $check_query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "seo_url`
            WHERE `keyword` = '" . $this->db->escape($seo_url) . "'
            AND `language_id` = '" . (int)$language_id . "'
            AND `query` != '" . $this->db->escape($route) . "'
        ");
        
        if ($check_query->row['total'] > 0) {
            // SEO URL уже используется
            return false;
        }
        
        // Сохраняем SEO URL
        $this->saveSeoUrlForController($route, $seo_url, $language_id);
		$this->flushSeoUrlCacheInvalidation();
        
        return true;
    }

    /**
     * Удалить SEO URL для контроллера и языка
     *
     * @param string $route
     * @param int $language_id
     * @return bool
     */
    public function deleteControllerSeoUrl($route, $language_id) {
        if (empty($route) || (int)$language_id <= 0) {
            return false;
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($route) . "' AND `language_id` = '" . (int)$language_id . "'");
        $this->markSeoUrlCacheDirty();
        $this->flushSeoUrlCacheInvalidation();

        return true;
    }

    private function ensureSeoLogSchema() {
        if ($this->seo_log_schema_checked) {
            return;
        }

        $table = DB_PREFIX . 'dockercart_seo_log';

        $exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");

        if (!$exists->num_rows) {
            $this->seo_log_schema_checked = true;
            return;
        }

        $column = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table) . "` LIKE 'language_id'");

        if ($column->num_rows) {
            $this->db->query("ALTER TABLE `" . $this->db->escape($table) . "` DROP COLUMN `language_id`");
        }

        $this->seo_log_schema_checked = true;
    }
}
