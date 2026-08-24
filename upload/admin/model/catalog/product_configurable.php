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

	public function updateVariantQuantity($variant_id, $quantity) {
		$this->lib()->updateVariantQuantity($variant_id, $quantity);
	}

	public function updateVariantPrice($variant_id, $price) {
		$this->lib()->updateVariantPrice($variant_id, $price);
	}

	public function updateVariantPricing($variant_id, $price, array $cg_prices = array()) {
		$this->lib()->updateVariantPricing($variant_id, $price, $cg_prices);
	}

	public function updateVariantDimensions($variant_id, $length, $width, $height, $weight) {
		$this->lib()->updateVariantDimensions($variant_id, $length, $width, $height, $weight);
	}

	public function updateVariantCodes($variant_id, array $codes) {
		$this->lib()->updateVariantCodes($variant_id, $codes);
	}

	public function updateVariantImage($variant_id, $image) {
		$this->lib()->updateVariantImage($variant_id, $image);
	}

	public function getAggregatedWeightRange($product_id) {
		return $this->lib()->getAggregatedWeightRange($product_id);
	}

	public function getAggregatedDimensionsRange($product_id) {
		return $this->lib()->getAggregatedDimensionsRange($product_id);
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

	public function findDuplicateVariant($product_id, $variant_hash, $exclude_variant_id = 0) {
		return $this->lib()->findDuplicateVariant($product_id, $variant_hash, $exclude_variant_id);
	}

	public function disableConfigurable($product_id, $purge_variants = true) {
		return $this->lib()->disableConfigurable($product_id, $purge_variants);
	}

	public function setConfigurable($product_id, $is_configurable) {
		return $this->lib()->setConfigurable($product_id, $is_configurable);
	}

	public function getVariantCustomerGroupPrices($product_id) {
		return $this->lib()->getVariantCustomerGroupPrices($product_id);
	}

	public function setVariantCustomerGroupPrice($variant_id, $customer_group_id, $price) {
		$this->lib()->setVariantCustomerGroupPrice($variant_id, $customer_group_id, $price);
	}

	public function deleteAllVariantCustomerGroupPrices($variant_id) {
		$this->lib()->deleteAllVariantCustomerGroupPrices($variant_id);
	}

	public function getVariantSpecials($variant_id) {
		return $this->lib()->getVariantSpecials($variant_id);
	}

	public function getVariantsSpecials($product_id) {
		return $this->lib()->getVariantsSpecials($product_id);
	}

	public function setVariantSpecials($variant_id, $specials) {
		$this->lib()->setVariantSpecials($variant_id, $specials);
	}

	public function deleteAllVariantSpecials($variant_id) {
		$this->lib()->deleteAllVariantSpecials($variant_id);
	}

	public function getVariantsDiscounts($product_id) {
		return $this->lib()->getVariantsDiscounts($product_id);
	}

	public function setVariantDiscounts($variant_id, $discounts) {
		$this->lib()->setVariantDiscounts($variant_id, $discounts);
	}

	public function deleteAllVariantDiscounts($variant_id) {
		$this->lib()->deleteAllVariantDiscounts($variant_id);
	}

	public function applyVariantPromotions($product_id, array $special_rows = array(), array $discount_rows = array()) {
		$this->lib()->applyVariantPromotions($product_id, $special_rows, $discount_rows);
	}
}
