<?php
class ModelCatalogProductConfigurable extends Model {
	private $pc_lib = null;

	private function lib() {
		if ($this->pc_lib === null) {
			$this->pc_lib = new ProductConfigurable($this->registry);
		}

		return $this->pc_lib;
	}

	public function getVariants($product_id) {
		return $this->lib()->getVariants($product_id);
	}

	public function getVariant($variant_id) {
		return $this->lib()->getVariant($variant_id);
	}

	public function addVariant($product_id, $data) {
		return $this->lib()->addVariant($product_id, $data);
	}

	public function updateVariant($variant_id, $data) {
		return $this->lib()->updateVariant($variant_id, $data);
	}

	public function deleteVariant($variant_id) {
		return $this->lib()->deleteVariant($variant_id);
	}

	public function setDefaultVariant($variant_id) {
		return $this->lib()->setDefaultVariant($variant_id);
	}

	public function setConfigurableOptions($product_id, $option_ids) {
		return $this->lib()->setConfigurableOptions($product_id, $option_ids);
	}

	public function getConfigurableOptions($product_id) {
		return $this->lib()->getConfigurableOptions($product_id);
	}

	public function getConfigurable($product_id) {
		return $this->lib()->getConfigurable($product_id);
	}

	public function isConfigurable($product_id) {
		return $this->lib()->isConfigurable($product_id);
	}

	public function deleteAllVariants($product_id) {
		return $this->lib()->deleteAllVariants($product_id);
	}

	public function disableConfigurable($product_id) {
		return $this->lib()->disableConfigurable($product_id);
	}

	public function setConfigurable($product_id, $is_configurable) {
		return $this->lib()->setConfigurable($product_id, $is_configurable);
	}
}
