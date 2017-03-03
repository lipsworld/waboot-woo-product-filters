<?php

namespace WBWPF\db_backends;

use WBF\components\utils\DB;

class MYSQL implements Backend {
	/**
	 * @param $table_name
	 * @param $params
	 *
	 * @return array|bool
	 */
	public function create_index_table( $table_name, $params ) {
		global $wpdb;

		$r = false;

		if(self::table_exists( $table_name )){
			//Create table
			$wpdb->query("DROP TABLE ".$wpdb->prefix.$table_name);
			$dropped = true;
		}else{
			$dropped = false;
		}

		if(!self::table_exists( $table_name ) || $dropped){
			$table_name = $wpdb->prefix.$table_name;
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (\n";

			$fields = [
				"relation_id bigint(20) NOT NULL AUTO_INCREMENT",
				"product_id bigint(20) NOT NULL",
			];

			$default_extra_fields = [
				"post_type varchar(20) NOT NULL",
				"post_parent bigint(20) NOT NULL DEFAULT 0",
				"total_sales bigint(20)", //to order by popularity
				"price varchar(255)", //to order by price
				"post_date_gmt DATETIME NOT NULL", //to order by date:
				"post_modified_gmt DATETIME NOT NULL"
			];

			foreach ($params as $datatype_slug => $data_key){
				foreach ($data_key as $k => $v){
					$fields[] = "$v VARCHAR(255)";
				}
			}

			$fields = array_merge($fields,$default_extra_fields);

			$fields[] = "PRIMARY KEY (relation_id)";

			$sql.= implode(",\n",$fields);

			$sql.= ") $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			$r = dbDelta( $sql );
		}

		return $r;
	}

	public function create_support_table( $table_name, $params ) {
		global $wpdb;

		$r = false;

		if(self::table_exists( $table_name )){
			//Create table
			$wpdb->query("DROP TABLE ".$wpdb->prefix.$table_name);
			$dropped = true;
		}else{
			$dropped = false;
		}

		if(!self::table_exists( $table_name ) || $dropped){
			$table_name = $wpdb->prefix.$table_name;
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (\n";

			$fields = [
				"relation_id bigint(20) NOT NULL AUTO_INCREMENT",
				"product_id bigint(20) NOT NULL",
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
	 * @param $table_name
	 *
	 * @return bool
	 */
	public function table_exists( $table_name ) {
		return DB::table_exists($table_name);
	}

	/**
	 * @param $table_name
	 * @param $prop_name
	 * @param $prop_value
	 *
	 * @return array
	 */
	public function get_products_id_by_property( $table_name, $prop_name, $prop_value ) {
		global $wpdb;
		$r = $wpdb->get_col("SELECT product_id FROM ".$wpdb->prefix.self::CUSTOM_PRODUCT_INDEX_TABLE." WHERE $prop_name = '$prop_value'");
		return $r;
	}

	/**
	 * @param $table_name
	 * @param $id
	 * @param $data
	 *
	 * @return bool
	 */
	public function insert_product_data( $table_name, $id, $data ) {
		if(!isset($data['product_id'])){
			$data['product_id'] = $id;
		}

		global $wpdb;

		/*
		 * Above completion could be done by: fill_entry_with_default_data, otherwise all rows will have those values.
		 */

		//Get default extra fields values
		$post_data = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE ID = $id");
		$post_data = $post_data[0];

		$extra_fields = [
			'post_type' => $post_data->post_type,
			'post_parent' => $post_data->post_parent,
			'post_date_gmt' => $post_data->post_date_gmt,
			'post_modified_gmt' => $post_data->post_modified_gmt,
			'total_sales' => get_post_meta($id,"total_sales",true),
			'price' => get_post_meta($id,"_price",true)
		];

		$data = array_merge($data,$extra_fields);

		$r = $wpdb->insert($wpdb->prefix.$table_name,$data);

		return $r > 0;
	}

	/**
	 * @param $table_name
	 * @param $id
	 *
	 * @return bool
	 */
	public function erase_product_data($table_name, $id) {
		global $wpdb;

		$r = $wpdb->delete($wpdb->prefix.$table_name,['product_id' => $id]);

		return $r > 0;
	}

	/**
	 * Complete an entry array before insert it into the database
	 *
	 * @param int $id the product id
	 * @param array $entry
	 */
	public function fill_entry_with_default_data(&$entry,$id){
		//Get default extra fields values
		global $wpdb;
		$post_data = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE ID = $id");
		$post_data = $post_data[0];

		$extra_fields = [
			'post_type' => $post_data->post_type,
			'post_parent' => $post_data->post_parent,
			'post_date_gmt' => $post_data->post_date_gmt,
			'post_modified_gmt' => $post_data->post_modified_gmt,
			'total_sales' => get_post_meta($id,"total_sales",true),
			'price' => get_post_meta($id,"_price",true)
		];

		$entry = array_merge($entry,$extra_fields);
	}
}