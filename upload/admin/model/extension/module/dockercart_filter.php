<?php
/**
 * DockerCart Filter — Admin model
 *
 * Installs/uninstalls module events and DB indexes; used by the admin UI.
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 *
 * This module is distributed under a commercial/proprietary license.
 * Use, copying, modification, and distribution are permitted only under
 * the terms of a valid commercial license agreement with the copyright owner.
 *
 * For licensing inquiries contact: licensing@mathflow-bit.example
 */

class ModelExtensionModuleDockerCartFilter extends Model {
    private $logger;

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'filter');
    }

    public function install() {

        $this->load->model('setting/event');

        $event_codes = [
            'dockercart_filter',
            'dockercart_filter_seo_url',
            'dockercart_filter_meta_title',
            'dockercart_filter_model',
            'dockercart_filter_heading',
            'dockercart_filter_seo_category',
            'dockercart_filter_search',
            'dockercart_filter_seo_search',
            'dockercart_filter_dcf_filter',
            'dockercart_filter_modify_products'
        ];
        foreach ($event_codes as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }

        $this->model_setting_event->addEvent(
            'dockercart_filter_seo_url',
            'catalog/controller/startup/startup/before',
            'extension/module/dockercart_filter_event/handleSeoFilterUrl',
            1,
            -2000
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_meta_title',
            'catalog/controller/product/category/before',
            'extension/module/dockercart_filter_event/modifyMetaTitle',
            1,
            -1000
        );


        $this->model_setting_event->addEvent(
            'dockercart_filter_dcf_filter',
            'catalog/controller/product/category/before',
            'extension/module/dockercart_filter_event/applyDcfFilter',
            1,
            -1100
        );


        $this->model_setting_event->addEvent(
            'dockercart_filter_modify_products',
            'model/catalog/product/getProducts/before',
            'extension/module/dockercart_filter_event/modifyGetProducts',
            1,
            0
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_model',
            'catalog/controller/product/category/before',
            'extension/module/dockercart_filter_event/replaceProductModel'
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_heading',
            'catalog/view/product/category/before',
            'extension/module/dockercart_filter_event/modifyCategoryHeading'
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_meta_title_after',
            'catalog/controller/product/category/after',
            'extension/module/dockercart_filter_event/modifyMetaTitleAfter',
            1,
            1000
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_seo_category',
            'catalog/view/product/category/after',
            'extension/module/dockercart_filter_event/modifyCategoryPageSEO'
        );

        // Inject dcf parameter into category view links (pagination, product/category links)
        $this->model_setting_event->addEvent(
            'dockercart_filter_dcf_view',
            'catalog/view/product/category/after',
            'extension/module/dockercart_filter_event/injectDcfInCategoryView'
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_search',
            'catalog/controller/product/search/before',
            'extension/module/dockercart_filter_event/replaceProductModel'
        );

        $this->model_setting_event->addEvent(
            'dockercart_filter_seo_search',
            'catalog/view/product/search/after',
            'extension/module/dockercart_filter_event/modifySearchPageSEO'
        );

        $query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "product` WHERE Key_name = 'idx_product_status_price'");
        if (!$query->num_rows) {
            try {
                $this->db->query("CREATE INDEX idx_product_status_price ON `" . DB_PREFIX . "product` (`status`, `price`)");
            } catch (\Exception $e) {
                // If the DB user lacks privileges to create indexes, log a warning and continue.
                $this->logger->info('DockerCart Filter: failed to create index idx_product_status_price: ' . $e->getMessage());
            }
        }

        $query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "product` WHERE Key_name = 'idx_product_manufacturer'");
        if (!$query->num_rows) {
            try {
                $this->db->query("CREATE INDEX idx_product_manufacturer ON `" . DB_PREFIX . "product` (`manufacturer_id`, `status`)");
            } catch (\Exception $e) {
                $this->logger->info('DockerCart Filter: failed to create index idx_product_manufacturer: ' . $e->getMessage());
            }
        }

        $query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "product_attribute` WHERE Key_name = 'idx_product_attribute'");
        if (!$query->num_rows) {
            try {
                $this->db->query("CREATE INDEX idx_product_attribute ON `" . DB_PREFIX . "product_attribute` (`product_id`, `attribute_id`)");
            } catch (\Exception $e) {
                $this->logger->info('DockerCart Filter: failed to create index idx_product_attribute: ' . $e->getMessage());
            }
        }

        $query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "product_option_value` WHERE Key_name = 'idx_product_option'");
        if (!$query->num_rows) {
            try {
                $this->db->query("CREATE INDEX idx_product_option ON `" . DB_PREFIX . "product_option_value` (`product_id`, `option_id`, `option_value_id`)");
            } catch (\Exception $e) {
                $this->logger->info('DockerCart Filter: failed to create index idx_product_option: ' . $e->getMessage());
            }
        }
    }

    public function uninstall() {

        $this->load->model('setting/event');
        $event_codes = [
            'dockercart_filter',
            'dockercart_filter_seo_url',
            'dockercart_filter_meta_title',
            'dockercart_filter_model',
            'dockercart_filter_heading',
            'dockercart_filter_seo_category',
            'dockercart_filter_search',
            'dockercart_filter_seo_search',
            'dockercart_filter_dcf_filter',
            'dockercart_filter_modify_products',
            'dockercart_filter_meta_title_after',
            'dockercart_filter_dcf_view'
        ];
        foreach ($event_codes as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
    }

    public function clearExpiredCache() {

    }
}
