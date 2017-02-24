<?php


if(!function_exists("wbwpf_show_filters")):
	/**
	 * Display filters
	 *
	 * @param array $args the params for displaying the filters
	 */
	function wbwpf_show_filters($args){
		//Testing:
		$args = [
			'product_cat' => [
				'type' => "checkbox", //Come visualizzarli
				'dataType' => 'taxonomies' //Come prende i valori
			],
			'product_tag' => [
				'type' => "checkbox",
				'dataType' => 'taxonomies'
			],
		];

		$plugin = \WBWPF\Plugin::get_instance_from_global();

		$filters = [];

		foreach ($args as $filter_slug => $filter_params){
			if(!isset($filter_params['dataType']) || !isset($filter_params['type'])) continue;

			$dataType_slug = $filter_params['dataType'];
			$uiType_slug = $filter_params['type'];

			$f = \WBWPF\includes\Filter_Factory::build($filter_slug,$dataType_slug,$uiType_slug);

			if($f instanceof \WBWPF\includes\Filter){
				if(isset($filter_params['label'])){
					$f->set_label($filter_params['label']);
				}else{
					$f->set_label();
				}
				$filters[] = $f;
			}
		}

		$v = new \WBF\components\mvc\HTMLView("views/filters.php",$plugin);
		$v->display([
			'filters' => $filters,
			'has_filters' => is_array($filters) && !empty($filters),
			'textdomain' => $plugin->get_textdomain()
		]);
	}
endif;

if(!function_exists("wbwpf_filters_breadcrumb")):
	/**
	 * Display filters breadcrumb
	 */
	function wbwpf_filters_breadcrumb(){
		$filters = \WBWPF\includes\Filter_Factory::build_from_get_params(); //try from get
		if(!is_array($filters) || empty($filters)){
			$filters = \WBWPF\includes\Filter_Factory::build_from_post_params(); //try from post
		}
		if(is_array($filters) && !empty($filters)){
			$plugin = \WBWPF\Plugin::get_instance_from_global();
			$breadcrumb = [];
			$i = 0;
			foreach ($filters as $f){
				if(!is_array($f->current_values)) continue;
				foreach ($f->current_values as $current_value){
					$single_filter_params = [
						$f->slug => [
							'type' => $f->uiType->type_slug,
							'dataType' => $f->dataType->type_slug
						]
					];
					$single_filter_values = [
						$f->slug => $current_value
					];
					$strigified = \WBWPF\includes\Filter_Factory::stringify_from_params($single_filter_params,$single_filter_values);
					$breadcrumb[$i] = [
						'label' => $f->dataType->getPublicItemLabelOf($current_value,$f),
						'single_query_string' => $strigified,
						'cumulated_query_string' => $i > 0 ? $breadcrumb[$i-1]['cumulated_query_string']."-".$strigified : $strigified
					];
					$breadcrumb[$i]['link'] = add_query_arg(["wbwpf_query"=>$breadcrumb[$i]['cumulated_query_string']]);
					$i++;
				}
			}

			$v = new \WBF\components\mvc\HTMLView("views/filters-breadcrumb.php",$plugin);
			$v->display([
				'breadcrumb' => $breadcrumb,
				'has_items' => !empty($breadcrumb)
			]);
		}
	}
endif;