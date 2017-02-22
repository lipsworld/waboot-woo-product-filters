<?php

namespace WBWPF;
use WBF\components\assets\AssetsManager;
use WBF\components\mvc\HTMLView;
use WBF\components\pluginsframework\BasePlugin;
use WBF\components\utils\DB;
use WBWPF\datatypes\DataType;
use WBWPF\filters\Filter;
use WBWPF\includes\Filter_Factory;
use WBWPF\includes\Filter_Query;
use WBWPF\includes\Query_Factory;

/**
 * The core plugin class.
 *
 * @package    WBSample
 * @subpackage WBSample/includes
 */
class Plugin extends BasePlugin {
	/*
	 * This is the name of the table that cointains all products id with their filterable values
	 */
	const CUSTOM_PRODUCT_INDEX_TABLE = "wbwpf_products_index";
	const SETTINGS_OPTION_NAME = "wpwpf_settings";

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		parent::__construct( "waboot-woo-product-filters", plugin_dir_path( dirname(  __FILE__  ) ) );

		$this->hooks();
	}

	/**
	 * Return the plugin instance
	 *
	 * @return Plugin
	 * @throws \Exception
	 */
	public static function get_instance_from_global(){
		$plugin = BasePlugin::get_instances_of("waboot-woo-product-filters");
		if(!isset($plugin['core'])) throw new \Exception("Unable to find the plugin during get_instance_from_global()");
		$plugin = $plugin['core'];
		if(!$plugin instanceof Plugin) throw new \Exception("get_instance_from_global() found an invalid plugin instance");

		return $plugin;
	}

	/**
	 * Define plugins hooks
	 */
	public function hooks(){
		$this->loader->add_action("admin_enqueue_scripts", $this, "admin_assets");
		$this->loader->add_action("admin_menu",$this,"display_admin_page");

		//$this->loader->add_ajax_action("create_products_index_table",$this,"ajax_create_products_index_table");
		$this->loader->add_action("wp_ajax_create_products_index_table",$this,"ajax_create_products_index_table");
		$this->loader->add_action("wp_ajax_nopriv_create_products_index_table",$this,"ajax_create_products_index_table");

		$this->loader->add_action("wp_ajax_wbwpf_get_products",$this,"get_filtered_products_callback");
		$this->loader->add_action("wp_ajax_nopriv_wbwpf_get_products",$this,"get_filtered_products_callback");

		$this->loader->add_action("query_vars",$this,"add_query_vars",1);
		$this->loader->add_action("woocommerce_product_query",$this,"alter_product_query",10,2);
		$this->loader->add_filter("woocommerce_pagination_args",$this,"alter_woocommerce_pagination_args",10,1);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @hooked 'admin_enqueue_scripts'
	 */
	public function admin_assets(){
		$assets = [
			'wbwpf-admin' => [
				'uri' => defined("SCRIPT_DEBUG") && SCRIPT_DEBUG ? $this->get_uri()."/assets/dist/js/dashboard.pkg.js" : $this->get_uri()."/assets/dist/js/dashboard.min.js",
				'path' => defined("SCRIPT_DEBUG") && SCRIPT_DEBUG ? $this->get_dir()."/assets/dist/js/dashboard.pkg.js" : $this->get_dir()."/assets/dist/js/dashboard.min.js",
				'type' => 'js',
				'i10n' => [
					'name' => "wbwpf",
					'params' => [
						'ajax_url' => admin_url('admin-ajax.php')
					]
				]
			]
		];

		(new AssetsManager($assets))->enqueue();
	}

	/**
	 * Adds query vars
	 *
	 * @hooked 'query_vars'
	 *
	 * @param $vars
	 *
	 * @return array
	 */
	public function add_query_vars($vars){
		$vars[] = "wbwpf_query";
		return $vars;
	}

	/**
	 * Alter the woocommerce product query
	 *
	 * @hooked 'woocommerce_product_query'
	 *
	 * @param $query
	 * @param $wc_query
	 */
	public function alter_product_query($query,$wc_query){
		if(!$query instanceof \WP_Query) return;

		if(isset($_GET['wbwpf_query'])){
			$filter_query = Query_Factory::build_from_get_params();
		}elseif(isset($_POST['wbwpf_search_by_filters'])){
			$filter_query = Query_Factory::build_from_post_params();
		}

		if(isset($filter_query)){
			$ids = $filter_query->get_results(Filter_Query::RESULT_FORMAT_IDS);
			if(is_array($ids) && count($ids) > 0){
				$query->set('post__in',$ids);
			}else{
				$query->set('post__in',[0]);
			}
		}
	}

	/**
	 * Adds out query string to woocommerce pagination
	 *
	 * @hooked 'woocommerce_pagination_args'
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	public function alter_woocommerce_pagination_args($args){
		if(isset($_POST['wbwpf_search_by_filters'])){
			$filter_string = Filter_Factory::stringify_from_post_params();
			$args['add_fragment'] = "?wbwpf_query=$filter_string";
		}
		return $args;
	}

	/**
	 * Displays the admin page
	 *
	 * @hooked 'admin_menu'
	 */
	public function display_admin_page(){
		add_submenu_page("woocommerce",__("Filters settings",$this->get_textdomain()),__("Filters settings",$this->get_textdomain()),"manage_woocommerce","wbwpf_settings",function(){
			global $wpdb;
			$v = new HTMLView($this->src_path."/views/admin/settings.php",$this,false);

			$datatypes_tree = [];

			$datatypes = $this->get_available_dataTypes();

			foreach ($datatypes as $name => $classname){
				if(class_exists($classname)){
					$o = new $classname();
					if($o instanceof DataType){
						$datatypes_tree[] = [
							'label' => $o->label,
							'slug' => $o->slug,
							'description' => $o->admin_description,
							'data' => $o->getData()
						];
					}
				}
			}

			$v->for_dashboard()->display([
				'page_title' => __("Filters settings",$this->get_textdomain()),
				'data' => $datatypes_tree,
				'has_data' => isset($datatypes_tree) && is_array($datatypes_tree) && !empty($datatypes_tree),
				'current_settings' => $this->get_plugin_settings(),
				'textdomain' => $this->get_textdomain()
			]);
		});
	}

	/**
	 * Get which class to use to parse which data type
	 */
	public function get_available_dataTypes(){
		$datatypes = [
			'meta' => __NAMESPACE__."\\datatypes\\Meta",
			'taxonomies' => __NAMESPACE__."\\datatypes\\Taxonomy"
		];
		$datatypes = apply_filters("wbwpf/datatypes/available",$datatypes);
		return $datatypes;
	}

	/**
	 * Get which class to use to display which ui type
	 */
	public function get_available_uiTypes(){
		$uitypes = [
			'checkbox' => __NAMESPACE__."\\uitypes\\Checkbox",
			'range' => __NAMESPACE__."\\uitypes\\Range"
		];
		$uitypes = apply_filters("wbwpf/uitypes/available",$uitypes);
		return $uitypes;
	}

	/**
	 * Get a list of data type object in an associative array with slugs as keys
	 *
	 * @return array
	 */
	public function get_available_dataTypes_by_slug(){
		$dt = $this->get_available_dataTypes();
		$slugs = [];
		foreach ($dt as $classname){
			if(class_exists($classname)){
				$o = new $classname();
				if($o instanceof DataType){
					$slugs[$o->slug] = $o;
				}
			}
		}
		return $slugs;
	}

	/**
	 * Get the default settings
	 *
	 * @return array
	 */
	public function get_plugin_default_settings(){
		$defaults = [
			'filters' => []
		];
		$defaults = apply_filters("wbwpf/settings/defaults",$defaults);
		return $defaults;
	}

	/**
	 * Save the plugin settings
	 *
	 * @param $settings
	 */
	public function save_plugin_settings($settings){
		$actual = $this->get_plugin_settings();
		$settings = wp_parse_args($settings,$actual);
		update_option(Plugin::SETTINGS_OPTION_NAME,$settings);
	}

	/**
	 * Get the plugin settings
	 *
	 * @return array
	 */
	public function get_plugin_settings(){
		$defaults = $this->get_plugin_default_settings();
		$settings = get_option(Plugin::SETTINGS_OPTION_NAME);
		$settings = wp_parse_args($settings,$defaults);
		return $settings;
	}

	/**
	 * Ajax callback to create the filters table
	 */
	public function ajax_create_products_index_table(){
		$params = $_POST['params'];
		$table_params = $params['table_params'];
		$offset = $params['offset'];
		$limit = $params['limit'];

		if($offset == 0){ //We just started, so create the table
			$this->save_plugin_settings(['filters' => $table_params]);
			$r = $this->create_products_index_table($table_params);
			if(!$r){
				wp_send_json_error([
					'status' => 'failed',
					'message' => __("Unable to create or update the product index table", $this->get_textdomain())
				]);
			}
		}

		//Then begin to fill the table
		global $wpdb;
		if(!isset($params['found_products'])){
			$found_products = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_type = 'product' and post_status = 'publish'");
		}else{
			$found_products = $params['found_products'];
		}

		$ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'product' and post_status = 'publish' LIMIT {$limit} OFFSET {$offset}");

		if(is_array($ids) && !empty($ids)){
			$this->fill_products_index_table($ids);
			wp_send_json_success([
				'offset' => $limit+$offset,
				'limit' => $limit,
				'found_products' => $found_products,
				'current_percentage' => ceil( ($limit+$offset)*(100/$found_products) ),
				'table_params' => $table_params,
				'status' => 'run'
			]);
		}else{
			wp_send_json_success([
				'status' => 'complete',
				'current_percentage' => 100,
				'found_products' => $found_products,
			]);
		}
	}

	/**
	 * Creates the filters table
	 */
	public function create_products_index_table(array $params){
		global $wpdb;

		$r = false;

		if(DB::table_exists(Plugin::CUSTOM_PRODUCT_INDEX_TABLE)){
			//Create table
			$wpdb->query("DROP TABLE ".$wpdb->prefix.Plugin::CUSTOM_PRODUCT_INDEX_TABLE);
			$dropped = true;
		}else{
			$dropped = false;
		}

		if(!DB::table_exists(Plugin::CUSTOM_PRODUCT_INDEX_TABLE) || $dropped){
			$table_name = $wpdb->prefix.Plugin::CUSTOM_PRODUCT_INDEX_TABLE;
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (\n";

			$fields = [
				"relation_id bigint(20) NOT NULL AUTO_INCREMENT",
				"product_id bigint(20) NOT NULL"
			];

			foreach ($params as $datatype_slug => $data_key){
				foreach ($data_key as $k => $v){
					$fields[] = "$v VARCHAR(255)";
				}
			}

			$fields[] = "PRIMARY KEY (relation_id)";

			$sql.= implode(",\n",$fields);

			$sql.= ") $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			$r = dbDelta( $sql );
		}

		return $r;
	}

	/**
	 * Fill filters table with data
	 *
	 * @param array $ids if EMPTY, then the function will get all the products before filling, otherwise it fills only the selected ids
	 */
	public function fill_products_index_table($ids = []){
		global $wpdb;
		if(empty($ids)){
			$ids = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_type = 'product' and post_status = 'publish'");
		}

		$datatypes = $this->get_available_dataTypes_by_slug();
		$filters_settings = $this->get_plugin_settings()['filters'];
		$rows = [];

		/*
		 * We are testing two method of indexing: multiple row per product (avoiding cols with comma separated item) and single row per product (otherwise)
		 */

		/*
		 * This cycle create one row per product, with some cols with multiple values separated by comma
		 */
		/*foreach ($ids as $product_id){
			$new_row = [
				'product_id' => $product_id
			];
			foreach ($filters_settings as $datatype_slug => $values){
				foreach ($values as $value){
					$new_row[$value] = $datatypes[$datatype_slug]->getValueOf($product_id,$value,DataType::VALUES_FOR_FORMAT_COMMA_SEPARATED); //get the value for that data type of the current product
				}
			}
			$rows[] = $new_row;
		}*/

		/*
		 * These cycle will create many rows per product, so there is no column with multiple values
		 */
		foreach ($ids as $product_id){
			$new_row = [];
			foreach ($filters_settings as $datatype_slug => $values){
				foreach ($values as $value){
					$product_values = $datatypes[$datatype_slug]->getValueOf($product_id,$value,DataType::VALUES_FOR_VALUES_FORMAT_ARRAY); //get the value for that data type of the current product
					if(is_array($product_values) && !empty($product_values)){
						/*
						 * We have multiple values for this data type (eg: multiple product_cat terms), so we need to create multiple, incomplete rows
						 */
						array_walk($product_values,function($el) use(&$rows,$product_id,$value,$new_row){
							$rows[] = [
								'product_id' => $product_id,
								$value => $el
							];
						});
					}elseif(is_array($product_values) && empty($product_values)){
						if(!isset($new_row['product_id'])){
							$new_row['product_id'] = $product_id;
						}
						$new_row[$value] = ""; //todo: change to null?
					}else{
						/*
						 * We have have a single value for this data type. So it's ok to inject it into the main row. We want only one row that contains all single-valued data types for one product
						 */
						if(!isset($new_row['product_id'])){
							$new_row['product_id'] = $product_id;
						}
						$new_row[$value] = $product_values;
					}
				}
			}
			if(!empty($new_row)){
				$rows[] = $new_row;
			}
		}

		foreach ($rows as $new_row){
			//Insert the value
			$r = $wpdb->insert($wpdb->prefix.Plugin::CUSTOM_PRODUCT_INDEX_TABLE,$new_row);
		}
	}

	/**
	 * Returns a JSON of products for the frontend
	 */
	public function get_filtered_products_callback(){
		/*
		 * Idea:
		 * - I vari "filtri" sono dei middleware che modificano l'oggetto query.
		 * - Quindi si crea un nuovo oggetto query tramite Query_Factory, passandogli tutti i filtri necessari
		 * - Questi filtri modificano la query
		 * - Viene restituito un oggetto query finale
		 * - Viene eseguita la query
		 * - Vengono restituiti gli ID dei post
		 */
		$search_params = isset($_POST['search_params']) ? $_POST['search_params'] : [];
		$current_page = isset($search_params['page']) ? intval($search_params['page']) : 1;
		$limit = apply_filters( 'loop_shop_per_page', get_option( 'posts_per_page' ) );
		$offset = $limit * $current_page;

		if(empty($search_params)){
			wp_send_json_error();
		}else{
			if($current_page == 1){
				$posts = [
					[
						'ID' => 1,
						'title' => "Hello World!"
					],
					[
						'ID' => 1,
						'title' => "Hello World!"
					],
					[
						'ID' => 1,
						'title' => "Hello World!"
					],
					[
						'ID' => 1,
						'title' => "Hello World!"
					]
				];
				$data = [
					'total_count' => 5,
					'current_page' => $current_page,
					'posts' => $posts
				];
				wp_send_json_success($posts);
			}elseif($current_page == 2){
				$posts = [
					[
						'ID' => 1,
						'title' => "Hello World 2!"
					]
				];
				$data = [
					'total_count' => 5,
					'current_page' => $current_page,
					'posts' => $posts
				];
				wp_send_json_success($posts);
			}else{
				$posts = [];
				wp_send_json_success($posts);
			}
		}
	}
}
