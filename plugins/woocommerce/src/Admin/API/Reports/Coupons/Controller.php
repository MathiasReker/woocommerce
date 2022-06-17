<?php
/**
 * REST API Reports coupons controller
 *
 * Handles requests to the /reports/coupons endpoint.
 */

namespace Automattic\WooCommerce\Admin\API\Reports\Coupons;

defined( 'ABSPATH' ) || exit;

use \Automattic\WooCommerce\Admin\API\Reports\ExportableInterface;

/**
 * REST API Reports coupons controller class.
 *
 * @internal
 * @extends WC_REST_Reports_Controller
 */
class Controller extends \WC_REST_Reports_Controller implements ExportableInterface {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc-analytics';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reports/coupons';

	/**
	 * Maps query arguments from the REST request.
	 *
	 * @param array $request Request array.
	 * @return array
	 */
	protected function prepare_reports_query( $request ) {
		$args                        = [];
		$args['before']              = $request['before'];
		$args['after']               = $request['after'];
		$args['page']                = $request['page'];
		$args['per_page']            = $request['per_page'];
		$args['orderby']             = $request['orderby'];
		$args['order']               = $request['order'];
		$args['coupons']             = (array) $request['coupons'];
		$args['extended_info']       = $request['extended_info'];
		$args['force_cache_refresh'] = $request['force_cache_refresh'];
		return $args;
	}

	/**
	 * Get all reports.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$query_args    = $this->prepare_reports_query( $request );
		$coupons_query = new Query( $query_args );
		$report_data   = $coupons_query->get_data();

		$data = [];

		foreach ( $report_data->data as $coupons_data ) {
			$item   = $this->prepare_item_for_response( $coupons_data, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (int) $report_data->total );
		$response->header( 'X-WP-TotalPages', (int) $report_data->pages );

		$page      = $report_data->page_no;
		$max_pages = $report_data->pages;
		$base      = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );
		if ( $page > 1 ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Prepare a report object for serialization.
	 *
	 * @param stdClass        $report  Report data.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $report, $request ) {
		$data = $report;

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $report ) );

		/**
		 * Filter a report returned from the API.
		 *
		 * Allows modification of the report data right before it is returned.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object           $report   The original report object.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'woocommerce_rest_prepare_report_coupons', $response, $report, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WC_Reports_Query $object Object data.
	 * @return array
	 */
	protected function prepare_links( $object ) {
		$links = [
			'coupon' => [
				'href' => rest_url( sprintf( '/%s/coupons/%d', $this->namespace, $object['coupon_id'] ) ),
			],
		];

		return $links;
	}

	/**
	 * Get the Report's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'report_coupons',
			'type'       => 'object',
			'properties' => [
				'coupon_id'     => [
					'description' => __( 'Coupon ID.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'amount'        => [
					'description' => __( 'Net discount amount.', 'woocommerce' ),
					'type'        => 'number',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'orders_count'  => [
					'description' => __( 'Number of orders.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'extended_info' => [
					'code'             => [
						'type'        => 'string',
						'readonly'    => true,
						'context'     => [ 'view', 'edit' ],
						'description' => __( 'Coupon code.', 'woocommerce' ),
					],
					'date_created'     => [
						'type'        => 'date-time',
						'readonly'    => true,
						'context'     => [ 'view', 'edit' ],
						'description' => __( 'Coupon creation date.', 'woocommerce' ),
					],
					'date_created_gmt' => [
						'type'        => 'date-time',
						'readonly'    => true,
						'context'     => [ 'view', 'edit' ],
						'description' => __( 'Coupon creation date in GMT.', 'woocommerce' ),
					],
					'date_expires'     => [
						'type'        => 'date-time',
						'readonly'    => true,
						'context'     => [ 'view', 'edit' ],
						'description' => __( 'Coupon expiration date.', 'woocommerce' ),
					],
					'date_expires_gmt' => [
						'type'        => 'date-time',
						'readonly'    => true,
						'context'     => [ 'view', 'edit' ],
						'description' => __( 'Coupon expiration date in GMT.', 'woocommerce' ),
					],
					'discount_type'    => [
						'type'        => 'string',
						'readonly'    => true,
						'context'     => [ 'view', 'edit' ],
						'enum'        => array_keys( wc_get_coupon_types() ),
						'description' => __( 'Coupon discount type.', 'woocommerce' ),
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                  = [];
		$params['context']       = $this->get_context_param( [ 'default' => 'view' ] );
		$params['page']          = [
			'description'       => __( 'Current page of the collection.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];
		$params['per_page']      = [
			'description'       => __( 'Maximum number of items to be returned in result set.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['after']         = [
			'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['before']        = [
			'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order']         = [
			'description'       => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => [ 'asc', 'desc' ],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['orderby']       = [
			'description'       => __( 'Sort collection by object attribute.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'coupon_id',
			'enum'              => [
				'coupon_id',
				'code',
				'amount',
				'orders_count',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['coupons']       = [
			'description'       => __( 'Limit result set to coupons assigned specific coupon IDs.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => [
				'type' => 'integer',
			],
		];
		$params['extended_info'] = [
			'description'       => __( 'Add additional piece of info about each coupon to the report.', 'woocommerce' ),
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'wc_string_to_bool',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['force_cache_refresh'] = [
			'description'       => __( 'Force retrieval of fresh data instead of from the cache.', 'woocommerce' ),
			'type'              => 'boolean',
			'sanitize_callback' => 'wp_validate_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $params;
	}

	/**
	 * Get the column names for export.
	 *
	 * @return array Key value pair of Column ID => Label.
	 */
	public function get_export_columns() {
		$export_columns = [
			'code'         => __( 'Coupon code', 'woocommerce' ),
			'orders_count' => __( 'Orders', 'woocommerce' ),
			'amount'       => __( 'Amount discounted', 'woocommerce' ),
			'created'      => __( 'Created', 'woocommerce' ),
			'expires'      => __( 'Expires', 'woocommerce' ),
			'type'         => __( 'Type', 'woocommerce' ),
		];

		/**
		 * Filter to add or remove column names from the coupons report for
		 * export.
		 *
		 * @since 1.6.0
		 */
		return apply_filters(
			'woocommerce_report_coupons_export_columns',
			$export_columns
		);
	}

	/**
	 * Get the column values for export.
	 *
	 * @param array $item Single report item/row.
	 * @return array Key value pair of Column ID => Row Value.
	 */
	public function prepare_item_for_export( $item ) {
		$date_expires = empty( $item['extended_info']['date_expires'] )
			? __( 'N/A', 'woocommerce' )
			: $item['extended_info']['date_expires'];

		$export_item = [
			'code'         => $item['extended_info']['code'],
			'orders_count' => $item['orders_count'],
			'amount'       => $item['amount'],
			'created'      => $item['extended_info']['date_created'],
			'expires'      => $date_expires,
			'type'         => $item['extended_info']['discount_type'],
		];

		/**
		 * Filter to prepare extra columns in the export item for the coupons
		 * report.
		 *
		 * @since 1.6.0
		 */
		return apply_filters(
			'woocommerce_report_coupons_prepare_export_item',
			$export_item,
			$item
		);
	}
}
