<?php

namespace WBWPF\uitypes;

/*
 * Manage and output a checkbox-type filter
 */
use WBF\components\mvc\HTMLView;

class Checkbox extends UIType {
	/**
	 * Generate the output HTML
	 *
	 * @param bool $input_name
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function generate_output($input_name = false) {
		$v = new HTMLView("src/views/uitypes/checkbox.php","waboot-woo-product-filters");
		$output = $v->get([
			"values" => $this->values,
			"input_name" => $this->input_name
		]);
		return $output;
	}
}