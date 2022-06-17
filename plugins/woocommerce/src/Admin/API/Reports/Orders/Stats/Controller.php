<?php
/**
 * REST API Reports orders stats controller
 *
 * Handles requests to the /reports/orders/stats endpoint.
 */

namespace Automattic\WooCommerce\Admin\API\Reports\Orders\Stats;

defined( 'ABSPATH' ) || exit;

use \Automattic\WooCommerce\Admin\API\Reports\ParameterException;

/**
 * REST API Reports orders stats controller class.
 *
 * @internal
 * @extends \Automattic\WooCommerce\Admin\API\Reports\Controller
 */
class Controller extends \Automattic\WooCommerce\Admin\API\Reports\Controller {

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
	protected $rest_base = 'reports/orders/stats';

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
		$args['interval']            = $request['interval'];
		$args['page']                = $request['page'];
		$args['per_page']            = $request['per_page'];
		$args['orderby']             = $request['orderby'];
		$args['order']               = $request['order'];
		$args['fields']              = $request['fields'];
		$args['match']               = $request['match'];
		$args['status_is']           = (array) $request['status_is'];
		$args['status_is_not']       = (array) $request['status_is_not'];
		$args['product_includes']    = (array) $request['product_includes'];
		$args['product_excludes']    = (array) $request['product_excludes'];
		$args['variation_includes']  = (array) $request['variation_includes'];
		$args['variation_excludes']  = (array) $request['variation_excludes'];
		$args['coupon_includes']     = (array) $request['coupon_includes'];
		$args['coupon_excludes']     = (array) $request['coupon_excludes'];
		$args['tax_rate_includes']   = (array) $request['tax_rate_includes'];
		$args['tax_rate_excludes']   = (array) $request['tax_rate_excludes'];
		$args['customer_type']       = $request['customer_type'];
		$args['refunds']             = $request['refunds'];
		$args['attribute_is']        = (array) $request['attribute_is'];
		$args['attribute_is_not']    = (array) $request['attribute_is_not'];
		$args['category_includes']   = (array) $request['categories'];
		$args['segmentby']           = $request['segmentby'];
		$args['force_cache_refresh'] = $request['force_cache_refresh'];

		// For backwards compatibility, `customer` is aliased to `customer_type`.
		if ( empty( $request['customer_type'] ) && ! empty( $request['customer'] ) ) {
			$args['customer_type'] = $request['customer'];
		}

		return $args;
	}

	/**
	 * Get all reports.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$query_args   = $this->prepare_reports_query( $request );
		$orders_query = new Query( $query_args );
		try {
			$report_data = $orders_query->get_data();
		} catch ( ParameterException $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), [ 'status' => $e->getCode() ] );
		}

		$out_data = [
			'totals'    => get_object_vars( $report_data->totals ),
			'intervals' => [],
		];

		foreach ( $report_data->intervals as $interval_data ) {
			$item                    = $this->prepare_item_for_response( $interval_data, $request );
			$out_data['intervals'][] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $out_data );
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
	 * @param Array           $report  Report data.
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

		/**
		 * Filter a report returned from the API.
		 *
		 * Allows modification of the report data right before it is returned.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object           $report   The original report object.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'woocommerce_rest_prepare_report_orders_stats', $response, $report, $request );
	}

	/**
	 * Get the Report's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$data_values = [
			'net_revenue'         => [
				'description' => __( 'Net sales.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'format'      => 'currency',
			],
			'orders_count'        => [
				'title'       => __( 'Orders', 'woocommerce' ),
				'description' => __( 'Number of orders', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
			],
			'avg_order_value'     => [
				'description' => __( 'Average order value.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
			],
			'avg_items_per_order' => [
				'description' => __( 'Average items per order', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'num_items_sold'      => [
				'description' => __( 'Number of items sold', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'coupons'             => [
				'description' => __( 'Amount discounted by coupons.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'coupons_count'       => [
				'description' => __( 'Unique coupons count.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'total_customers'     => [
				'description' => __( 'Total distinct customers.', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'products'            => [
				'description' => __( 'Number of distinct products sold.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
		];

		$segments = [
			'segments' => [
				'description' => __( 'Reports data grouped by segment condition.', 'woocommerce' ),
				'type'        => 'array',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'segment_id' => [
							'description' => __( 'Segment identificator.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'subtotals'  => [
							'description' => __( 'Interval subtotals.', 'woocommerce' ),
							'type'        => 'object',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
							'properties'  => $data_values,
						],
					],
				],
			],
		];

		$totals = array_merge( $data_values, $segments );

		// Products is not shown in intervals.
		unset( $data_values['products'] );

		$intervals = array_merge( $data_values, $segments );

		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'report_orders_stats',
			'type'       => 'object',
			'properties' => [
				'totals'    => [
					'description' => __( 'Totals data.', 'woocommerce' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'properties'  => $totals,
				],
				'intervals' => [
					'description' => __( 'Reports data grouped by intervals.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'interval'       => [
								'description' => __( 'Type of interval.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
								'enum'        => [ 'day', 'week', 'month', 'year' ],
							],
							'date_start'     => [
								'description' => __( "The date the report start, in the site's timezone.", 'woocommerce' ),
								'type'        => 'date-time',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'date_start_gmt' => [
								'description' => __( 'The date the report start, as GMT.', 'woocommerce' ),
								'type'        => 'date-time',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'date_end'       => [
								'description' => __( "The date the report end, in the site's timezone.", 'woocommerce' ),
								'type'        => 'date-time',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'date_end_gmt'   => [
								'description' => __( 'The date the report end, as GMT.', 'woocommerce' ),
								'type'        => 'date-time',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'subtotals'      => [
								'description' => __( 'Interval subtotals.', 'woocommerce' ),
								'type'        => 'object',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
								'properties'  => $intervals,
							],
						],
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
		$params                     = [];
		$params['context']          = $this->get_context_param( [ 'default' => 'view' ] );
		$params['page']             = [
			'description'       => __( 'Current page of the collection.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];
		$params['per_page']         = [
			'description'       => __( 'Maximum number of items to be returned in result set.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['after']            = [
			'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['before']           = [
			'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order']            = [
			'description'       => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => [ 'asc', 'desc' ],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['orderby']          = [
			'description'       => __( 'Sort collection by object attribute.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'date',
			'enum'              => [
				'date',
				'net_revenue',
				'orders_count',
				'avg_order_value',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['interval']         = [
			'description'       => __( 'Time interval to use for buckets in the returned data.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'week',
			'enum'              => [
				'hour',
				'day',
				'week',
				'month',
				'quarter',
				'year',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['match']            = [
			'description'       => __( 'Indicates whether all the conditions should be true for the resulting set, or if any one of them is sufficient. Match affects the following parameters: status_is, status_is_not, product_includes, product_excludes, coupon_includes, coupon_excludes, customer, categories', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'all',
			'enum'              => [
				'all',
				'any',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['status_is']        = [
			'description'       => __( 'Limit result set to items that have the specified order status.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_slug_list',
			'validate_callback' => 'rest_validate_request_arg',
			'default'           => null,
			'items'             => [
				'enum' => self::get_order_statuses(),
				'type' => 'string',
			],
		];
		$params['status_is_not']    = [
			'description'       => __( 'Limit result set to items that don\'t have the specified order status.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_slug_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => [
				'enum' => self::get_order_statuses(),
				'type' => 'string',
			],
		];
		$params['product_includes'] = [
			'description'       => __( 'Limit result set to items that have the specified product(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',

		];
		$params['product_excludes']   = [
			'description'       => __( 'Limit result set to items that don\'t have the specified product(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['variation_includes'] = [
			'description'       => __( 'Limit result set to items that have the specified variation(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['variation_excludes'] = [
			'description'       => __( 'Limit result set to items that don\'t have the specified variation(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['coupon_includes']    = [
			'description'       => __( 'Limit result set to items that have the specified coupon(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['coupon_excludes']    = [
			'description'       => __( 'Limit result set to items that don\'t have the specified coupon(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['tax_rate_includes']  = [
			'description'       => __( 'Limit result set to items that have the specified tax rate(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['tax_rate_excludes']  = [
			'description'       => __( 'Limit result set to items that don\'t have the specified tax rate(s) assigned.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['customer']           = [
			'description'       => __( 'Alias for customer_type (deprecated).', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => [
				'new',
				'returning',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['customer_type']      = [
			'description'       => __( 'Limit result set to orders that have the specified customer_type', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => [
				'new',
				'returning',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['refunds']            = [
			'description'       => __( 'Limit result set to specific types of refunds.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => '',
			'enum'              => [
				'',
				'all',
				'partial',
				'full',
				'none',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['attribute_is']       = [
			'description'       => __( 'Limit result set to orders that include products with the specified attributes.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'array',
			],
			'default'           => [],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['attribute_is_not']   = [
			'description'       => __( 'Limit result set to orders that don\'t include products with the specified attributes.', 'woocommerce' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'array',
			],
			'default'           => [],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['segmentby']          = [
			'description'       => __( 'Segment the response by additional constraint.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => [
				'product',
				'category',
				'variation',
				'coupon',
				'customer_type', // new vs returning.
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['fields']             = [
			'description'       => __( 'Limit stats fields to the specified items.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_slug_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => [
				'type' => 'string',
			],
		];
		$params['force_cache_refresh'] = [
			'description'       => __( 'Force retrieval of fresh data instead of from the cache.', 'woocommerce' ),
			'type'              => 'boolean',
			'sanitize_callback' => 'wp_validate_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $params;
	}
}
