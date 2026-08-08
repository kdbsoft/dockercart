<?php
class ModelExtensionTotalBxgy extends Model {
	public function getTotal($total) {
		// BXGY discount is now applied directly to product prices at order
		// creation and admin order editing (pre-tax). Keeping this total
		// extension active would double-count the discount, so it is a no-op.
	}
}
