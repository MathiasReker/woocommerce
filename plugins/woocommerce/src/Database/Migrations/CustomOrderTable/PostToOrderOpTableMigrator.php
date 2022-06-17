<?php
/**
 * Class for WPPost to wc_order_operational_details migrator.
 */

namespace Automattic\WooCommerce\Database\Migrations\CustomOrderTable;

use Automattic\WooCommerce\Database\Migrations\MetaToCustomTableMigrator;

/**
 * Helper class to migrate records from the WordPress post table
 * to the custom order operations table.
 *
 * @package Automattic\WooCommerce\Database\Migrations\CustomOrderTable
 */
class PostToOrderOpTableMigrator extends MetaToCustomTableMigrator {

	/**
	 * Get schema config for wp_posts and wc_order_operational_detail table.
	 *
	 * @return array Config.
	 */
	protected function get_schema_config(): array {
		global $wpdb;
		// TODO: Remove hardcoding.
		$this->table_names = [
			'orders'    => $wpdb->prefix . 'wc_orders',
			'addresses' => $wpdb->prefix . 'wc_order_addresses',
			'op_data'   => $wpdb->prefix . 'wc_order_operational_data',
			'meta'      => $wpdb->prefix . 'wc_orders_meta',
		];

		return [
			'source'      => [
				'entity' => [
					'table_name'             => $this->table_names['orders'],
					'meta_rel_column'        => 'id',
					'destination_rel_column' => 'id',
					'primary_key'            => 'id',
				],
				'meta'   => [
					'table_name'        => $wpdb->postmeta,
					'meta_key_column'   => 'meta_key',
					'meta_value_column' => 'meta_value',
					'entity_id_column'  => 'post_id',
				],
			],
			'destination' => [
				'table_name'        => $this->table_names['op_data'],
				'source_rel_column' => 'order_id',
				'primary_key'       => 'id',
				'primary_key_type'  => 'int',
			],
		];
	}


	/**
	 * Get columns config.
	 *
	 * @return \string[][] Config.
	 */
	protected function get_core_column_mapping(): array {
		return [
			'id' => [
				'type'        => 'int',
				'destination' => 'order_id',
			],
		];
	}


	/**
	 * Get meta data config.
	 *
	 * @return \string[][] Config.
	 */
	public function get_meta_column_config(): array {
		return [
			'_created_via'                  => [
				'type'        => 'string',
				'destination' => 'created_via',
			],
			'_order_version'                => [
				'type'        => 'string',
				'destination' => 'woocommerce_version',
			],
			'_prices_include_tax'           => [
				'type'        => 'bool',
				'destination' => 'prices_include_tax',
			],
			'_recorded_coupon_usage_counts' => [
				'type'        => 'bool',
				'destination' => 'coupon_usages_are_counted',
			],
			'_download_permissions_granted' => [
				'type'        => 'bool',
				'destination' => 'download_permission_granted',
			],
			'_cart_hash'                    => [
				'type'        => 'string',
				'destination' => 'cart_hash',
			],
			'_new_order_email_sent'         => [
				'type'        => 'bool',
				'destination' => 'new_order_email_sent',
			],
			'_order_key'                    => [
				'type'        => 'string',
				'destination' => 'order_key',
			],
			'_order_stock_reduced'          => [
				'type'        => 'bool',
				'destination' => 'order_stock_reduced',
			],
			'_date_paid'                    => [
				'type'        => 'date_epoch',
				'destination' => 'date_paid_gmt',
			],
			'_date_completed'               => [
				'type'        => 'date_epoch',
				'destination' => 'date_completed_gmt',
			],
			'_order_shipping_tax'           => [
				'type'        => 'decimal',
				'destination' => 'shipping_tax_amount',
			],
			'_order_shipping'               => [
				'type'        => 'decimal',
				'destination' => 'shipping_total_amount',
			],
			'_cart_discount_tax'            => [
				'type'        => 'decimal',
				'destination' => 'discount_tax_amount',
			],
			'_cart_discount'                => [
				'type'        => 'decimal',
				'destination' => 'discount_total_amount',
			],
			'_recorded_sales'               => [
				'type'        => 'bool',
				'destination' => 'recorded_sales',
			],
		];
	}
}
