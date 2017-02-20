<?php

namespace WBWPF\datatypes;

class Meta extends DataType {
	var $slug = "meta";

	function __construct() {
		$this->label = __("Product metas","waboot-woo-product-filters");
		$this->admin_description = __("Select one or more metas","waboot-woo-product-filters");
	}

	public function getData() {
		global $wpdb;
		$metas = [];
		$raw_metas = $wpdb->get_col("SELECT meta_key FROM $wpdb->postmeta as postmeta JOIN $wpdb->posts as posts ON postmeta.post_id = posts.ID WHERE post_type = 'product'");
		$raw_metas = array_unique($raw_metas);
		foreach ($raw_metas as $meta){
			$metas[$meta] = $meta;
		}
		return $metas;
	}

	public function getValueOf($product_id,$key){
		return get_post_meta($product_id,$key,true);
	}
}