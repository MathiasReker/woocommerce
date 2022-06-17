<?php
/**
 * REST API Reports products stats controller
 *
 * Handles requests to the /reports/products/stats endpoint.
 */

namespace Automattic\WooCommerce\Admin\API\Reports\Products\Stats;

defined( 'ABSPATH' ) || exit;

use \Automattic\WooCommerce\Admin\API\Reports\ParameterException;

/**
 * REST API Reports products stats controller class.
 *
 * @internal
 * @extends WC_REST_Reports_Controller
 */
class Controller extends \WC_REST_Reports_Controller {

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
	protected $rest_base = 'reports/products/stats';

	/**
	 * Mapping between external parameter name and name used in query class.
	 *
	 * @var array
	 */
	protected $param_mapping = [
		'categories' => 'category_includes',
		'products'   => 'product_includes',
		'variations' => 'variation_includes',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_analytics_products_stats_select_query', [ $this, 'set_default_report_data' ] );
	}

	/**
	 * Get all reports.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$query_args = [
			'fields' => [
				'items_sold',
				'net_revenue',
				'orders_count',
				'products_count',
				'variations_count',
			],
		];

		$registered = array_keys( $this->get_collection_params() );
		foreach ( $registered as $param_name ) {
			if ( isset( $request[ $param_name ] ) ) {
				if ( isset( $this->param_mapping[ $param_name ] ) ) {
					$query_args[ $this->param_mapping[ $param_name ] ] = $request[ $param_name ];
				} else {
					$query_args[ $param_name ] = $request[ $param_name ];
				}
			}
		}

		$query = new Query( $query_args );
		try {
			$report_data = $query->get_data();
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
		return apply_filters( 'woocommerce_rest_prepare_report_products_stats', $response, $report, $request );
	}

	/**
	 * Get the Report's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$data_values = [
			'items_sold'   => [
				'title'       => __( 'Products sold', 'woocommerce' ),
				'description' => __( 'Number of product items sold.', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
			],
			'net_revenue'  => [
				'description' => __( 'Net sales.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'format'      => 'currency',
			],
			'orders_count' => [
				'description' => __( 'Number of orders.', 'woocommerce' ),
				'type'        => 'integer',
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
						'segment_id'    => [
							'description' => __( 'Segment identificator.', 'woocommerce' ),
							'type'        => 'integer',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'segment_label' => [
							'description' => __( 'Human readable segment label, either product or variation name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
							'enum'        => [ 'day', 'week', 'month', 'year' ],
						],
						'subtotals'     => [
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

		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'report_products_stats',
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
								'properties'  => $totals,
							],
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Set the default results to 0 if API returns an empty array
	 *
	 * @internal
	 * @param Mixed $results Report data.
	 * @return object
	 */
	public function set_default_report_data( $results ) {
		if ( empty( $results ) ) {
			$results                       = new \stdClass();
			$results->total                = 0;
			$results->totals               = new \stdClass();
			$results->totals->items_sold   = 0;
			$results->totals->net_revenue  = 0;
			$results->totals->orders_count = 0;
			$results->intervals            = [];
			$results->pages                = 1;
			$results->page_no              = 1;
		}
		return $results;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params               = [];
		$params['context']    = $this->get_context_param( [ 'default' => 'view' ] );
		$params['page']       = [
			'description'       => __( 'Current page of the collection.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];
		$params['per_page']   = [
			'description'       => __( 'Maximum number of items to be returned in result set.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['after']      = [
			'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['before']     = [
			'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order']      = [
			'description'       => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => [ 'asc', 'desc' ],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['orderby']    = [
			'description'       => __( 'Sort collection by object attribute.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'date',
			'enum'              => [
				'date',
				'net_revenue',
				'coupons',
				'refunds',
				'shipping',
				'taxes',
				'net_revenue',
				'orders_count',
				'items_sold',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['interval']   = [
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
		$params['categories'] = [
			'description'       => __( 'Limit result to items from the specified categories.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => [
				'type' => 'integer',
			],
		];
		$params['products']   = [
			'description'       => __( 'Limit result to items with specified product ids.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => [
				'type' => 'integer',
			],
		];
		$params['variations'] = [
			'description'       => __( 'Limit result to items with specified variation ids.', 'woocommerce' ),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
			'items'             => [
				'type' => 'integer',
			],
		];
		$params['segmentby']  = [
			'description'       => __( 'Segment the response by additional constraint.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => [
				'product',
				'category',
				'variation',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['fields']     = [
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
