<?php
/**
 * Backward compatibility wrapper.
 * Legacy feed route proxies to module implementation.
 */
class ModelExtensionFeedDockercartImportYml extends Model {

    public function getProfile($profile_id) {
        $this->load->model('extension/module/dockercart_import_yml');
        return $this->model_extension_module_dockercart_import_yml->getProfile($profile_id);
    }

    public function runImport($profile_id) {
        $this->load->model('extension/module/dockercart_import_yml');
        return $this->model_extension_module_dockercart_import_yml->runImport($profile_id);
    }
}
