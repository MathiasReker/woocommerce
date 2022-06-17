<?php
/**
 * REST API Reports revenue stats controller
 *
 * Handles requests to the /reports/revenue/stats endpoint.
 */

namespace Automattic\WooCommerce\Admin\API\Reports\Revenue\Stats;

defined( 'ABSPATH' ) || exit;

use \Automattic\WooCommerce\Admin\API\Reports\Revenue\Query as RevenueQuery;
use \Automattic\WooCommerce\Admin\API\Reports\ExportableInterface;
use \Automattic\WooCommerce\Admin\API\Reports\ExportableTraits;
use \Automattic\WooCommerce\Admin\API\Reports\ParameterException;

/**
 * REST API Reports revenue stats controller class.
 *
 * @internal
 * @extends WC_REST_Reports_Controller
 */
class Controller extends \WC_REST_Reports_Controller implements ExportableInterface {
	/**
	 * Exportable traits.
	 */
	use ExportableTraits;

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
	protected $rest_base = 'reports/revenue/stats';

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
		$args['segmentby']           = $request['segmentby'];
		$args['fields']              = $request['fields'];
		$args['force_cache_refresh'] = $request['force_cache_refresh'];

		return $args;
	}

	/**
	 * Get all reports.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$query_args      = $this->prepare_reports_query( $request );
		$reports_revenue = new RevenueQuery( $query_args );
		try {
			$report_data = $reports_revenue->get_data();
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
	 * Get report items for export.
	 *
	 * Returns only the interval data.
	 *
	 * @param WP_REST_Request $request Request data.
	 * @return WP_REST_Response
	 */
	public function get_export_items( $request ) {
		$response  = $this->get_items( $request );
		$data      = $response->get_data();
		$intervals = $data['intervals'];

		$response->set_data( $intervals );

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
		return apply_filters( 'woocommerce_rest_prepare_report_revenue_stats', $response, $report, $request );
	}

	/**
	 * Get the Report's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$data_values = [
			'total_sales'    => [
				'description' => __( 'Total sales.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
			],
			'net_revenue'    => [
				'description' => __( 'Net sales.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
			],
			'coupons'        => [
				'description' => __( 'Amount discounted by coupons.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'coupons_count'  => [
				'description' => __( 'Unique coupons count.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'format'      => 'currency',
			],
			'shipping'       => [
				'title'       => __( 'Shipping', 'woocommerce' ),
				'description' => __( 'Total of shipping.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
			],
			'taxes'          => [
				'description' => __( 'Total of taxes.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'format'      => 'currency',
			],
			'refunds'        => [
				'title'       => __( 'Returns', 'woocommerce' ),
				'description' => __( 'Total of returns.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
			],
			'orders_count'   => [
				'description' => __( 'Number of orders.', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'num_items_sold' => [
				'description' => __( 'Items sold.', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'products'       => [
				'description' => __( 'Products sold.', 'woocommerce' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'gross_sales'    => [
				'description' => __( 'Gross sales.', 'woocommerce' ),
				'type'        => 'number',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'indicator'   => true,
				'format'      => 'currency',
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
			'title'      => 'report_revenue_stats',
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
		$params              = [];
		$params['context']   = $this->get_context_param( [ 'default' => 'view' ] );
		$params['page']      = [
			'description'       => __( 'Current page of the collection.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];
		$params['per_page']  = [
			'description'       => __( 'Maximum number of items to be returned in result set.', 'woocommerce' ),
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['after']     = [
			'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['before']    = [
			'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woocommerce' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order']     = [
			'description'       => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => [ 'asc', 'desc' ],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['orderby']   = [
			'description'       => __( 'Sort collection by object attribute.', 'woocommerce' ),
			'type'              => 'string',
			'default'           => 'date',
			'enum'              => [
				'date',
				'total_sales',
				'coupons',
				'refunds',
				'shipping',
				'taxes',
				'net_revenue',
				'orders_count',
				'items_sold',
				'gross_sales',
			],
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['interval']  = [
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
		$params['segmentby'] = [
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
		return [
			'date'         => __( 'Date', 'woocommerce' ),
			'orders_count' => __( 'Orders', 'woocommerce' ),
			'gross_sales'  => __( 'Gross sales', 'woocommerce' ),
			'total_sales'  => __( 'Total sales', 'woocommerce' ),
			'refunds'      => __( 'Returns', 'woocommerce' ),
			'coupons'      => __( 'Coupons', 'woocommerce' ),
			'taxes'        => __( 'Taxes', 'woocommerce' ),
			'shipping'     => __( 'Shipping', 'woocommerce' ),
			'net_revenue'  => __( 'Net Revenue', 'woocommerce' ),
		];
	}

	/**
	 * Get the column values for export.
	 *
	 * @param array $item Single report item/row.
	 * @return array Key value pair of Column ID => Row Value.
	 */
	public function prepare_item_for_export( $item ) {
		$subtotals = (array) $item['subtotals'];

		return [
			'date'         => $item['date_start'],
			'orders_count' => $subtotals['orders_count'],
			'gross_sales'  => self::csv_number_format( $subtotals['gross_sales'] ),
			'total_sales'  => self::csv_number_format( $subtotals['total_sales'] ),
			'refunds'      => self::csv_number_format( $subtotals['refunds'] ),
			'coupons'      => self::csv_number_format( $subtotals['coupons'] ),
			'taxes'        => self::csv_number_format( $subtotals['taxes'] ),
			'shipping'     => self::csv_number_format( $subtotals['shipping'] ),
			'net_revenue'  => self::csv_number_format( $subtotals['net_revenue'] ),
		];
	}
}
