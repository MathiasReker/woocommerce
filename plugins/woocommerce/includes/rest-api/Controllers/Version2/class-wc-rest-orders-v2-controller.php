<?php
/**
 * REST API Orders controller
 *
 * Handles requests to the /orders endpoint.
 *
 * @package WooCommerce\RestApi
 * @since    2.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API Orders controller class.
 *
 * @package WooCommerce\RestApi
 * @extends WC_REST_CRUD_Controller
 */
class WC_REST_Orders_V2_Controller extends WC_REST_CRUD_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v2';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'shop_order';

	/**
	 * If object is hierarchical.
	 *
	 * @var bool
	 */
	protected $hierarchical = true;

	/**
	 * Stores the request.
	 *
	 * @var array
	 */
	protected $request = [];

	/**
	 * Register the routes for orders.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				'args'   => [
					'id' => [
						'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'context' => $this->get_context_param( [ 'default' => 'view' ] ),
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => [
						'force' => [
							'default'     => false,
							'type'        => 'boolean',
							'description' => __( 'Whether to bypass trash and force deletion.', 'woocommerce' ),
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'batch_items' ],
					'permission_callback' => [ $this, 'batch_items_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				'schema' => [ $this, 'get_public_batch_schema' ],
			]
		);
	}

	/**
	 * Get object. Return false if object is not of required type.
	 *
	 * @since  3.0.0
	 * @param  int $id Object ID.
	 * @return WC_Data|bool
	 */
	protected function get_object( $id ) {
		$order = wc_get_order( $id );
		// In case id is a refund's id (or it's not an order at all), don't expose it via /orders/ path.
		if ( ! $order || 'shop_order_refund' === $order->get_type() ) {
			return false;
		}

		return $order;
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		$object = $this->get_object( (int) $request['id'] );

		if ( ( ! $object || 0 === $object->get_id() ) && ! wc_rest_check_post_permissions( $this->post_type, 'read' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'woocommerce' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return parent::get_item_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to update an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function update_item_permissions_check( $request ) {
		$object = $this->get_object( (int) $request['id'] );

		if ( ( ! $object || 0 === $object->get_id() ) && ! wc_rest_check_post_permissions( $this->post_type, 'read' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_edit', __( 'Sorry, you are not allowed to edit this resource.', 'woocommerce' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return parent::update_item_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to delete an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$object = $this->get_object( (int) $request['id'] );

		if ( ( ! $object || 0 === $object->get_id() ) && ! wc_rest_check_post_permissions( $this->post_type, 'read' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_delete', __( 'Sorry, you are not allowed to delete this resource.', 'woocommerce' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return parent::delete_item_permissions_check( $request );
	}

	/**
	 * Expands an order item to get its data.
	 *
	 * @param WC_Order_item $item Order item data.
	 * @return array
	 */
	protected function get_order_item_data( $item ) {
		$data           = $item->get_data();
		$format_decimal = [ 'subtotal', 'subtotal_tax', 'total', 'total_tax', 'tax_total', 'shipping_tax_total' ];

		// Format decimal values.
		foreach ( $format_decimal as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = wc_format_decimal( $data[ $key ], $this->request['dp'] );
			}
		}

		// Add SKU, PRICE, and IMAGE to products.
		if ( is_callable( [ $item, 'get_product' ] ) ) {
			$data['sku']       = $item->get_product() ? $item->get_product()->get_sku() : null;
			$data['price']     = $item->get_quantity() ? $item->get_total() / $item->get_quantity() : 0;

			$image_id = $item->get_product() ? $item->get_product()->get_image_id() : 0;
			$data['image'] = [
				'id'  => $image_id,
				'src' => $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '',
			];
		}

		// Add parent_name if the product is a variation.
		if ( is_callable( [ $item, 'get_product' ] ) ) {
			$product = $item->get_product();

			if ( is_callable( [ $product, 'get_parent_data' ] ) ) {
				$data['parent_name'] = $product->get_title();
			} else {
				$data['parent_name'] = null;
			}
		}

		// Format taxes.
		if ( ! empty( $data['taxes']['total'] ) ) {
			$taxes = [];

			foreach ( $data['taxes']['total'] as $tax_rate_id => $tax ) {
				$taxes[] = [
					'id'       => $tax_rate_id,
					'total'    => $tax,
					'subtotal' => isset( $data['taxes']['subtotal'][ $tax_rate_id ] ) ? $data['taxes']['subtotal'][ $tax_rate_id ] : '',
				];
			}
			$data['taxes'] = $taxes;
		} elseif ( isset( $data['taxes'] ) ) {
			$data['taxes'] = [];
		}

		// Remove names for coupons, taxes and shipping.
		if ( isset( $data['code'] ) || isset( $data['rate_code'] ) || isset( $data['method_title'] ) ) {
			unset( $data['name'] );
		}

		// Remove props we don't want to expose.
		unset( $data['order_id'] );
		unset( $data['type'] );

		// Expand meta_data to include user-friendly values.
		$formatted_meta_data = $item->get_all_formatted_meta_data( null );

		// Filter out product variations.
		if ( isset( $product ) && 'true' === $this->request['order_item_display_meta'] ) {
			$order_item_name   = $data['name'];
			$data['meta_data'] = array_filter(
				$data['meta_data'],
				function( $meta ) use ( $product, $order_item_name ) {
					$display_value = wp_kses_post( rawurldecode( (string) $meta->value ) );

					// Skip items with values already in the product details area of the product name.
					if ( $product && $product->is_type( 'variation' ) && wc_is_attribute_in_product_name( $display_value, $order_item_name ) ) {
						return false;
					}

					return true;
				}
			);
		}

		$data['meta_data'] = array_map(
			[ $this, 'merge_meta_item_with_formatted_meta_display_attributes' ],
			$data['meta_data'],
			array_fill( 0, count( $data['meta_data'] ), $formatted_meta_data )
		);

		return $data;
	}

	/**
	 * Merge the `$formatted_meta_data` `display_key` and `display_value` attribute values into the corresponding
	 * {@link WC_Meta_Data}. Returns the merged array.
	 *
	 * @param WC_Meta_Data $meta_item           An object from {@link WC_Order_Item::get_meta_data()}.
	 * @param array        $formatted_meta_data An object result from {@link WC_Order_Item::get_all_formatted_meta_data}.
	 * The keys are the IDs of {@link WC_Meta_Data}.
	 *
	 * @return array
	 */
	private function merge_meta_item_with_formatted_meta_display_attributes( $meta_item, $formatted_meta_data ) {
		$result = [
			'id'            => $meta_item->id,
			'key'           => $meta_item->key,
			'value'         => $meta_item->value,
			'display_key'   => $meta_item->key,   // Default to original key, in case a formatted key is not available.
			'display_value' => $meta_item->value, // Default to original value, in case a formatted value is not available.
		];

		if ( array_key_exists( $meta_item->id, $formatted_meta_data ) ) {
			$formatted_meta_item = $formatted_meta_data[ $meta_item->id ];

			$result['display_key'] = wc_clean( $formatted_meta_item->display_key );
			$result['display_value'] = wc_clean( $formatted_meta_item->display_value );
		}

		return $result;
	}

	/**
	 * Get formatted item data.
	 *
	 * @since 3.0.0
	 * @param WC_Order $order WC_Data instance.
	 *
	 * @return array
	 */
	protected function get_formatted_item_data( $order ) {
		$extra_fields      = [ 'meta_data', 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines', 'refunds', 'payment_url', 'is_editable', 'needs_payment', 'needs_processing' ];
		$format_decimal    = [ 'discount_total', 'discount_tax', 'shipping_total', 'shipping_tax', 'shipping_total', 'shipping_tax', 'cart_tax', 'total', 'total_tax' ];
		$format_date       = [ 'date_created', 'date_modified', 'date_completed', 'date_paid' ];
		// These fields are dependent on other fields.
		$dependent_fields = [
			'date_created_gmt'   => 'date_created',
			'date_modified_gmt'  => 'date_modified',
			'date_completed_gmt' => 'date_completed',
			'date_paid_gmt'      => 'date_paid',
		];

		$format_line_items = [ 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines' ];

		// Only fetch fields that we need.
		$fields = $this->get_fields_for_response( $this->request );
		foreach ( $dependent_fields as $field_key => $dependency ) {
			if ( in_array( $field_key, $fields ) && ! in_array( $dependency, $fields ) ) {
				$fields[] = $dependency;
			}
		}

		$extra_fields      = array_intersect( $extra_fields, $fields );
		$format_decimal    = array_intersect( $format_decimal, $fields );
		$format_date       = array_intersect( $format_date, $fields );

		$format_line_items = array_intersect( $format_line_items, $fields );

		$data = $order->get_base_data();

		// Add extra data as necessary.
		foreach ( $extra_fields as $field ) {
			switch ( $field ) {
				case 'meta_data':
					$data['meta_data'] = $order->get_meta_data();
					break;
				case 'line_items':
					$data['line_items'] = $order->get_items( 'line_item' );
					break;
				case 'tax_lines':
					$data['tax_lines'] = $order->get_items( 'tax' );
					break;
				case 'shipping_lines':
					$data['shipping_lines'] = $order->get_items( 'shipping' );
					break;
				case 'fee_lines':
					$data['fee_lines'] = $order->get_items( 'fee' );
					break;
				case 'coupon_lines':
					$data['coupon_lines'] = $order->get_items( 'coupon' );
					break;
				case 'refunds':
					$data['refunds'] = [];
					foreach ( $order->get_refunds() as $refund ) {
						$data['refunds'][] = [
							'id'     => $refund->get_id(),
							'reason' => $refund->get_reason() ? $refund->get_reason() : '',
							'total'  => '-' . wc_format_decimal( $refund->get_amount(), $this->request['dp'] ),
						];
					}
					break;
				case 'payment_url':
					$data['payment_url'] = $order->get_checkout_payment_url();
					break;
				case 'is_editable':
					$data['is_editable'] = $order->is_editable();
					break;
				case 'needs_payment':
					$data['needs_payment'] = $order->needs_payment();
					break;
				case 'needs_processing':
					$data['needs_processing'] = $order->needs_processing();
					break;
			}
		}

		// Format decimal values.
		foreach ( $format_decimal as $key ) {
			$data[ $key ] = wc_format_decimal( $data[ $key ], $this->request['dp'] );
		}

		// Format date values.
		foreach ( $format_date as $key ) {
			$datetime              = $data[ $key ];
			$data[ $key ]          = wc_rest_prepare_date_response( $datetime, false );
			$data[ $key . '_gmt' ] = wc_rest_prepare_date_response( $datetime );
		}

		// Format the order status.
		$data['status'] = 'wc-' === substr( $data['status'], 0, 3 ) ? substr( $data['status'], 3 ) : $data['status'];

		// Format line items.
		foreach ( $format_line_items as $key ) {
			$data[ $key ] = array_values( array_map( [ $this, 'get_order_item_data' ], $data[ $key ] ) );
		}

		$allowed_fields = [
			'id',
			'parent_id',
			'number',
			'order_key',
			'created_via',
			'version',
			'status',
			'currency',
			'date_created',
			'date_created_gmt',
			'date_modified',
			'date_modified_gmt',
			'discount_total',
			'discount_tax',
			'shipping_total',
			'shipping_tax',
			'cart_tax',
			'total',
			'total_tax',
			'prices_include_tax',
			'customer_id',
			'customer_ip_address',
			'customer_user_agent',
			'customer_note',
			'billing',
			'shipping',
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'date_paid',
			'date_paid_gmt',
			'date_completed',
			'date_completed_gmt',
			'cart_hash',
			'meta_data',
			'line_items',
			'tax_lines',
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			'refunds',
			'payment_url',
			'is_editable',
			'needs_payment',
			'needs_processing',
		];

		$data = array_intersect_key( $data, array_flip( $allowed_fields ) );

		return $data;
	}

	/**
	 * Prepare a single order output for response.
	 *
	 * @since  3.0.0
	 * @param  WC_Data         $object  Object data.
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_object_for_response( $object, $request ) {
		$this->request       = $request;
		$this->request['dp'] = is_null( $this->request['dp'] ) ? wc_get_price_decimals() : absint( $this->request['dp'] );
		$request['context']  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data                = $this->get_formatted_item_data( $object );
		$data                = $this->add_additional_fields_to_object( $data, $request );
		$data                = $this->filter_response_by_context( $data, $request['context'] );
		$response            = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $object, $request ) );

		/**
		 * Filter the data for a response.
		 *
		 * The dynamic portion of the hook name, $this->post_type,
		 * refers to object type being prepared for the response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Data          $object   Object data.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( "woocommerce_rest_prepare_{$this->post_type}_object", $response, $object, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WC_Data         $object  Object data.
	 * @param WP_REST_Request $request Request object.
	 * @return array                   Links for the given post.
	 */
	protected function prepare_links( $object, $request ) {
		$links = [
			'self'       => [
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $object->get_id() ) ),
			],
			'collection' => [
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			],
		];

		if ( 0 !== (int) $object->get_customer_id() ) {
			$links['customer'] = [
				'href' => rest_url( sprintf( '/%s/customers/%d', $this->namespace, $object->get_customer_id() ) ),
			];
		}

		if ( 0 !== (int) $object->get_parent_id() ) {
			$links['up'] = [
				'href' => rest_url( sprintf( '/%s/orders/%d', $this->namespace, $object->get_parent_id() ) ),
			];
		}

		return $links;
	}

	/**
	 * Prepare objects query.
	 *
	 * @since  3.0.0
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	protected function prepare_objects_query( $request ) {
		global $wpdb;

		$args = parent::prepare_objects_query( $request );

		// Set post_status.
		if ( in_array( $request['status'], $this->get_order_statuses(), true ) ) {
			$args['post_status'] = 'wc-' . $request['status'];
		} elseif ( 'any' === $request['status'] ) {
			$args['post_status'] = 'any';
		} else {
			$args['post_status'] = $request['status'];
		}

		if ( isset( $request['customer'] ) ) {
			if ( ! empty( $args['meta_query'] ) ) {
				$args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}

			$args['meta_query'][] = [
				'key'   => '_customer_user',
				'value' => $request['customer'],
				'type'  => 'NUMERIC',
			];
		}

		// Search by product.
		if ( ! empty( $request['product'] ) ) {
			$order_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT order_id
					FROM {$wpdb->prefix}woocommerce_order_items
					WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
					AND order_item_type = 'line_item'",
					$request['product']
				)
			);

			// Force WP_Query return empty if don't found any order.
			$order_ids = ! empty( $order_ids ) ? $order_ids : [ 0 ];

			$args['post__in'] = $order_ids;
		}

		// Search.
		if ( ! empty( $args['s'] ) ) {
			$order_ids = wc_order_search( $args['s'] );

			if ( ! empty( $order_ids ) ) {
				unset( $args['s'] );
				$args['post__in'] = array_merge( $order_ids, [ 0 ] );
			}
		}

		/**
		 * Filter the query arguments for a request.
		 *
		 * Enables adding extra arguments or setting defaults for an order collection request.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 */
		$args = apply_filters( 'woocommerce_rest_orders_prepare_object_query', $args, $request );

		return $args;
	}

	/**
	 * Only return writable props from schema.
	 *
	 * @param  array $schema Schema.
	 * @return bool
	 */
	protected function filter_writable_props( $schema ) {
		return empty( $schema['readonly'] );
	}

	/**
	 * Prepare a single order for create or update.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @param  bool            $creating If is creating a new object.
	 * @return WP_Error|WC_Data
	 */
	protected function prepare_object_for_database( $request, $creating = false ) {
		$id        = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$order     = new WC_Order( $id );
		$schema    = $this->get_item_schema();
		$data_keys = array_keys( array_filter( $schema['properties'], [ $this, 'filter_writable_props' ] ) );

		// Handle all writable props.
		foreach ( $data_keys as $key ) {
			$value = $request[ $key ];

			if ( ! is_null( $value ) ) {
				switch ( $key ) {
					case 'status':
						// Status change should be done later so transitions have new data.
						break;
					case 'billing':
					case 'shipping':
						$this->update_address( $order, $value, $key );
						break;
					case 'line_items':
					case 'shipping_lines':
					case 'fee_lines':
					case 'coupon_lines':
						if ( is_array( $value ) ) {
							foreach ( $value as $item ) {
								if ( is_array( $item ) ) {
									if ( $this->item_is_null( $item ) || ( isset( $item['quantity'] ) && 0 === $item['quantity'] ) ) {
										$order->remove_item( $item['id'] );
									} else {
										$this->set_item( $order, $key, $item );
									}
								}
							}
						}
						break;
					case 'meta_data':
						if ( is_array( $value ) ) {
							foreach ( $value as $meta ) {
								$order->update_meta_data( $meta['key'], $meta['value'], isset( $meta['id'] ) ? $meta['id'] : '' );
							}
						}
						break;
					default:
						if ( is_callable( [ $order, "set_{$key}" ] ) ) {
							$order->{"set_{$key}"}( $value );
						}
						break;
				}
			}
		}

		/**
		 * Filters an object before it is inserted via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`,
		 * refers to the object type slug.
		 *
		 * @param WC_Data         $order    Object object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating If is creating a new object.
		 */
		return apply_filters( "woocommerce_rest_pre_insert_{$this->post_type}_object", $order, $request, $creating );
	}

	/**
	 * Save an object data.
	 *
	 * @since  3.0.0
	 * @throws WC_REST_Exception But all errors are validated before returning any data.
	 * @param  WP_REST_Request $request  Full details about the request.
	 * @param  bool            $creating If is creating a new object.
	 * @return WC_Data|WP_Error
	 */
	protected function save_object( $request, $creating = false ) {
		try {
			$object = $this->prepare_object_for_database( $request, $creating );

			if ( is_wp_error( $object ) ) {
				return $object;
			}

			// Make sure gateways are loaded so hooks from gateways fire on save/create.
			WC()->payment_gateways();

			if ( ! is_null( $request['customer_id'] ) && 0 !== $request['customer_id'] ) {
				// Make sure customer exists.
				if ( false === get_user_by( 'id', $request['customer_id'] ) ) {
					throw new WC_REST_Exception( 'woocommerce_rest_invalid_customer_id', __( 'Customer ID is invalid.', 'woocommerce' ), 400 );
				}

				// Make sure customer is part of blog.
				if ( is_multisite() && ! is_user_member_of_blog( $request['customer_id'] ) ) {
					add_user_to_blog( get_current_blog_id(), $request['customer_id'], 'customer' );
				}
			}

			if ( $creating ) {
				$object->set_created_via( 'rest-api' );
				$object->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
				$object->calculate_totals();
			} else {
				// If items have changed, recalculate order totals.
				if ( isset( $request['billing'] ) || isset( $request['shipping'] ) || isset( $request['line_items'] ) || isset( $request['shipping_lines'] ) || isset( $request['fee_lines'] ) || isset( $request['coupon_lines'] ) ) {
					$object->calculate_totals( true );
				}
			}

			// Set status.
			if ( ! empty( $request['status'] ) ) {
				$object->set_status( $request['status'] );
			}

			$object->save();

			// Actions for after the order is saved.
			if ( true === $request['set_paid'] ) {
				if ( $creating || $object->needs_payment() ) {
					$object->payment_complete( $request['transaction_id'] );
				}
			}

			return $this->get_object( $object->get_id() );
		} catch ( WC_Data_Exception $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		} catch ( WC_REST_Exception $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), [ 'status' => $e->getCode() ] );
		}
	}

	/**
	 * Update address.
	 *
	 * @param WC_Order $order  Order data.
	 * @param array    $posted Posted data.
	 * @param string   $type   Address type.
	 */
	protected function update_address( $order, $posted, $type = 'billing' ) {
		foreach ( $posted as $key => $value ) {
			if ( is_callable( [ $order, "set_{$type}_{$key}" ] ) ) {
				$order->{"set_{$type}_{$key}"}( $value );
			}
		}
	}

	/**
	 * Gets the product ID from the SKU or posted ID.
	 *
	 * @throws WC_REST_Exception When SKU or ID is not valid.
	 * @param array  $posted Request data.
	 * @param string $action 'create' to add line item or 'update' to update it.
	 * @return int
	 */
	protected function get_product_id( $posted, $action = 'create' ) {
		if ( ! empty( $posted['sku'] ) ) {
			$product_id = (int) wc_get_product_id_by_sku( $posted['sku'] );
		} elseif ( ! empty( $posted['product_id'] ) && empty( $posted['variation_id'] ) ) {
			$product_id = (int) $posted['product_id'];
		} elseif ( ! empty( $posted['variation_id'] ) ) {
			$product_id = (int) $posted['variation_id'];
		} elseif ( 'update' === $action ) {
			$product_id = 0;
		} else {
			throw new WC_REST_Exception( 'woocommerce_rest_required_product_reference', __( 'Product ID or SKU is required.', 'woocommerce' ), 400 );
		}
		return $product_id;
	}

	/**
	 * Maybe set an item prop if the value was posted.
	 *
	 * @param WC_Order_Item $item   Order item.
	 * @param string        $prop   Order property.
	 * @param array         $posted Request data.
	 */
	protected function maybe_set_item_prop( $item, $prop, $posted ) {
		if ( isset( $posted[ $prop ] ) ) {
			$item->{"set_$prop"}( $posted[ $prop ] );
		}
	}

	/**
	 * Maybe set item props if the values were posted.
	 *
	 * @param WC_Order_Item $item   Order item data.
	 * @param string[]      $props  Properties.
	 * @param array         $posted Request data.
	 */
	protected function maybe_set_item_props( $item, $props, $posted ) {
		foreach ( $props as $prop ) {
			$this->maybe_set_item_prop( $item, $prop, $posted );
		}
	}

	/**
	 * Maybe set item meta if posted.
	 *
	 * @param WC_Order_Item $item   Order item data.
	 * @param array         $posted Request data.
	 */
	protected function maybe_set_item_meta_data( $item, $posted ) {
		if ( ! empty( $posted['meta_data'] ) && is_array( $posted['meta_data'] ) ) {
			foreach ( $posted['meta_data'] as $meta ) {
				if ( isset( $meta['key'] ) ) {
					$value = isset( $meta['value'] ) ? $meta['value'] : null;
					$item->update_meta_data( $meta['key'], $value, isset( $meta['id'] ) ? $meta['id'] : '' );
				}
			}
		}
	}

	/**
	 * Create or update a line item.
	 *
	 * @param array  $posted Line item data.
	 * @param string $action 'create' to add line item or 'update' to update it.
	 * @param object $item Passed when updating an item. Null during creation.
	 * @return WC_Order_Item_Product
	 * @throws WC_REST_Exception Invalid data, server error.
	 */
	protected function prepare_line_items( $posted, $action = 'create', $item = null ) {
		$item    = is_null( $item ) ? new WC_Order_Item_Product( ! empty( $posted['id'] ) ? $posted['id'] : '' ) : $item;
		$product = wc_get_product( $this->get_product_id( $posted, $action ) );

		if ( $product && $product !== $item->get_product() ) {
			$item->set_product( $product );

			if ( 'create' === $action ) {
				$quantity = isset( $posted['quantity'] ) ? $posted['quantity'] : 1;
				$total    = wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] );
				$item->set_total( $total );
				$item->set_subtotal( $total );
			}
		}

		$this->maybe_set_item_props( $item, [ 'name', 'quantity', 'total', 'subtotal', 'tax_class' ], $posted );
		$this->maybe_set_item_meta_data( $item, $posted );

		return $item;
	}

	/**
	 * Create or update an order shipping method.
	 *
	 * @param array  $posted $shipping Item data.
	 * @param string $action 'create' to add shipping or 'update' to update it.
	 * @param object $item Passed when updating an item. Null during creation.
	 * @return WC_Order_Item_Shipping
	 * @throws WC_REST_Exception Invalid data, server error.
	 */
	protected function prepare_shipping_lines( $posted, $action = 'create', $item = null ) {
		$item = is_null( $item ) ? new WC_Order_Item_Shipping( ! empty( $posted['id'] ) ? $posted['id'] : '' ) : $item;

		if ( 'create' === $action ) {
			if ( empty( $posted['method_id'] ) ) {
				throw new WC_REST_Exception( 'woocommerce_rest_invalid_shipping_item', __( 'Shipping method ID is required.', 'woocommerce' ), 400 );
			}
		}

		$this->maybe_set_item_props( $item, [ 'method_id', 'method_title', 'total', 'instance_id' ], $posted );
		$this->maybe_set_item_meta_data( $item, $posted );

		return $item;
	}

	/**
	 * Create or update an order fee.
	 *
	 * @param array  $posted Item data.
	 * @param string $action 'create' to add fee or 'update' to update it.
	 * @param object $item Passed when updating an item. Null during creation.
	 * @return WC_Order_Item_Fee
	 * @throws WC_REST_Exception Invalid data, server error.
	 */
	protected function prepare_fee_lines( $posted, $action = 'create', $item = null ) {
		$item = is_null( $item ) ? new WC_Order_Item_Fee( ! empty( $posted['id'] ) ? $posted['id'] : '' ) : $item;

		if ( 'create' === $action ) {
			if ( empty( $posted['name'] ) ) {
				throw new WC_REST_Exception( 'woocommerce_rest_invalid_fee_item', __( 'Fee name is required.', 'woocommerce' ), 400 );
			}
		}

		$this->maybe_set_item_props( $item, [ 'name', 'tax_class', 'tax_status', 'total' ], $posted );
		$this->maybe_set_item_meta_data( $item, $posted );

		return $item;
	}

	/**
	 * Create or update an order coupon.
	 *
	 * @param array  $posted Item data.
	 * @param string $action 'create' to add coupon or 'update' to update it.
	 * @param object $item Passed when updating an item. Null during creation.
	 * @return WC_Order_Item_Coupon
	 * @throws WC_REST_Exception Invalid data, server error.
	 */
	protected function prepare_coupon_lines( $posted, $action = 'create', $item = null ) {
		$item = is_null( $item ) ? new WC_Order_Item_Coupon( ! empty( $posted['id'] ) ? $posted['id'] : '' ) : $item;

		if ( 'create' === $action ) {
			if ( empty( $posted['code'] ) ) {
				throw new WC_REST_Exception( 'woocommerce_rest_invalid_coupon_coupon', __( 'Coupon code is required.', 'woocommerce' ), 400 );
			}
		}

		$this->maybe_set_item_props( $item, [ 'code', 'discount' ], $posted );
		$this->maybe_set_item_meta_data( $item, $posted );

		return $item;
	}

	/**
	 * Wrapper method to create/update order items.
	 * When updating, the item ID provided is checked to ensure it is associated
	 * with the order.
	 *
	 * @param WC_Order $order order object.
	 * @param string   $item_type The item type.
	 * @param array    $posted item provided in the request body.
	 * @throws WC_REST_Exception If item ID is not associated with order.
	 */
	protected function set_item( $order, $item_type, $posted ) {
		global $wpdb;

		if ( ! empty( $posted['id'] ) ) {
			$action = 'update';
		} else {
			$action = 'create';
		}

		$method = 'prepare_' . $item_type;
		$item   = null;

		// Verify provided line item ID is associated with order.
		if ( 'update' === $action ) {
			$item = $order->get_item( absint( $posted['id'] ), false );

			if ( ! $item ) {
				throw new WC_REST_Exception( 'woocommerce_rest_invalid_item_id', __( 'Order item ID provided is not associated with order.', 'woocommerce' ), 400 );
			}
		}

		// Prepare item data.
		$item = $this->$method( $posted, $action, $item );

		do_action( 'woocommerce_rest_set_order_item', $item, $posted );

		// If creating the order, add the item to it.
		if ( 'create' === $action ) {
			$order->add_item( $item );
		} else {
			$item->save();
		}
	}

	/**
	 * Helper method to check if the resource ID associated with the provided item is null.
	 * Items can be deleted by setting the resource ID to null.
	 *
	 * @param array $item Item provided in the request body.
	 * @return bool True if the item resource ID is null, false otherwise.
	 */
	protected function item_is_null( $item ) {
		$keys = [ 'product_id', 'method_id', 'method_title', 'name', 'code' ];

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $item ) && is_null( $item[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get order statuses without prefixes.
	 *
	 * @return array
	 */
	protected function get_order_statuses() {
		$order_statuses = [ 'auto-draft' ];

		foreach ( array_keys( wc_get_order_statuses() ) as $status ) {
			$order_statuses[] = str_replace( 'wc-', '', $status );
		}

		return $order_statuses;
	}

	/**
	 * Get the Order's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->post_type,
			'type'       => 'object',
			'properties' => [
				'id'                   => [
					'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'parent_id'            => [
					'description' => __( 'Parent order ID.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'number'               => [
					'description' => __( 'Order number.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'order_key'            => [
					'description' => __( 'Order key.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'created_via'          => [
					'description' => __( 'Shows where the order was created.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'version'              => [
					'description' => __( 'Version of WooCommerce which last updated the order.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'status'               => [
					'description' => __( 'Order status.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => 'pending',
					'enum'        => $this->get_order_statuses(),
					'context'     => [ 'view', 'edit' ],
				],
				'currency'             => [
					'description' => __( 'Currency the order was created with, in ISO format.', 'woocommerce' ),
					'type'        => 'string',
					'default'     => get_woocommerce_currency(),
					'enum'        => array_keys( get_woocommerce_currencies() ),
					'context'     => [ 'view', 'edit' ],
				],
				'date_created'         => [
					'description' => __( "The date the order was created, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date_created_gmt'     => [
					'description' => __( 'The date the order was created, as GMT.', 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date_modified'        => [
					'description' => __( "The date the order was last modified, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date_modified_gmt'    => [
					'description' => __( 'The date the order was last modified, as GMT.', 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'discount_total'       => [
					'description' => __( 'Total discount amount for the order.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'discount_tax'         => [
					'description' => __( 'Total discount tax amount for the order.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'shipping_total'       => [
					'description' => __( 'Total shipping amount for the order.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'shipping_tax'         => [
					'description' => __( 'Total shipping tax amount for the order.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'cart_tax'             => [
					'description' => __( 'Sum of line item taxes only.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'total'                => [
					'description' => __( 'Grand total.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'total_tax'            => [
					'description' => __( 'Sum of all taxes.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'prices_include_tax'   => [
					'description' => __( 'True the prices included tax during checkout.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'customer_id'          => [
					'description' => __( 'User ID who owns the order. 0 for guests.', 'woocommerce' ),
					'type'        => 'integer',
					'default'     => 0,
					'context'     => [ 'view', 'edit' ],
				],
				'customer_ip_address'  => [
					'description' => __( "Customer's IP address.", 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'customer_user_agent'  => [
					'description' => __( 'User agent of the customer.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'customer_note'        => [
					'description' => __( 'Note left by customer during checkout.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'billing'              => [
					'description' => __( 'Billing address.', 'woocommerce' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'first_name' => [
							'description' => __( 'First name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'last_name'  => [
							'description' => __( 'Last name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'company'    => [
							'description' => __( 'Company name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_1'  => [
							'description' => __( 'Address line 1', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_2'  => [
							'description' => __( 'Address line 2', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'city'       => [
							'description' => __( 'City name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'state'      => [
							'description' => __( 'ISO code or name of the state, province or district.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'postcode'   => [
							'description' => __( 'Postal code.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'country'    => [
							'description' => __( 'Country code in ISO 3166-1 alpha-2 format.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'email'      => [
							'description' => __( 'Email address.', 'woocommerce' ),
							'type'        => [ 'string', 'null' ],
							'format'      => 'email',
							'context'     => [ 'view', 'edit' ],
						],
						'phone'      => [
							'description' => __( 'Phone number.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
				'shipping'             => [
					'description' => __( 'Shipping address.', 'woocommerce' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'first_name' => [
							'description' => __( 'First name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'last_name'  => [
							'description' => __( 'Last name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'company'    => [
							'description' => __( 'Company name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_1'  => [
							'description' => __( 'Address line 1', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_2'  => [
							'description' => __( 'Address line 2', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'city'       => [
							'description' => __( 'City name.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'state'      => [
							'description' => __( 'ISO code or name of the state, province or district.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'postcode'   => [
							'description' => __( 'Postal code.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'country'    => [
							'description' => __( 'Country code in ISO 3166-1 alpha-2 format.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
				'payment_method'       => [
					'description' => __( 'Payment method ID.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'payment_method_title' => [
					'description' => __( 'Payment method title.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'arg_options' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
				'transaction_id'       => [
					'description' => __( 'Unique transaction ID.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'date_paid'            => [
					'description' => __( "The date the order was paid, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date_paid_gmt'        => [
					'description' => __( 'The date the order was paid, as GMT.', 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date_completed'       => [
					'description' => __( "The date the order was completed, in the site's timezone.", 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'date_completed_gmt'   => [
					'description' => __( 'The date the order was completed, as GMT.', 'woocommerce' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'cart_hash'            => [
					'description' => __( 'MD5 hash of cart items to ensure orders are not modified.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'meta_data'            => [
					'description' => __( 'Meta data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'    => [
								'description' => __( 'Meta ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'key'   => [
								'description' => __( 'Meta key.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'value' => [
								'description' => __( 'Meta value.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
						],
					],
				],
				'line_items'           => [
					'description' => __( 'Line items data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'           => [
								'description' => __( 'Item ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'name'         => [
								'description' => __( 'Product name.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
							'parent_name'  => [
								'description' => __( 'Parent product name if the product is a variation.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'product_id'   => [
								'description' => __( 'Product ID.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
							'variation_id' => [
								'description' => __( 'Variation ID, if applicable.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
							],
							'quantity'     => [
								'description' => __( 'Quantity ordered.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
							],
							'tax_class'    => [
								'description' => __( 'Tax class of product.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'subtotal'     => [
								'description' => __( 'Line subtotal (before discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'subtotal_tax' => [
								'description' => __( 'Line subtotal tax (before discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'total'        => [
								'description' => __( 'Line total (after discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'total_tax'    => [
								'description' => __( 'Line total tax (after discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'taxes'        => [
								'description' => __( 'Line taxes.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'       => [
											'description' => __( 'Tax rate ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
										],
										'total'    => [
											'description' => __( 'Tax total.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'subtotal' => [
											'description' => __( 'Tax subtotal.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
							'meta_data'    => [
								'description' => __( 'Meta data.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'            => [
											'description' => __( 'Meta ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'key'           => [
											'description' => __( 'Meta key.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'value'         => [
											'description' => __( 'Meta value.', 'woocommerce' ),
											'type'        => 'mixed',
											'context'     => [ 'view', 'edit' ],
										],
										'display_key'   => [
											'description' => __( 'Meta key for UI display.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'display_value' => [
											'description' => __( 'Meta value for UI display.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
							'sku'          => [
								'description' => __( 'Product SKU.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'price'        => [
								'description' => __( 'Product price.', 'woocommerce' ),
								'type'        => 'number',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'image'        => [
								'description' => __( 'Properties of the main product image.', 'woocommerce' ),
								'type'        => 'object',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
								'properties'  => [
									'type'       => 'object',
									'properties' => [
										'id'                => [
											'description' => __( 'Image ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
										],
										'src'               => [
											'description' => __( 'Image URL.', 'woocommerce' ),
											'type'        => 'string',
											'format'      => 'uri',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
						],
					],
				],
				'tax_lines'            => [
					'description' => __( 'Tax lines data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'                 => [
								'description' => __( 'Item ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'rate_code'          => [
								'description' => __( 'Tax rate code.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'rate_id'            => [
								'description' => __( 'Tax rate ID.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'label'              => [
								'description' => __( 'Tax rate label.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'compound'           => [
								'description' => __( 'Show if is a compound tax rate.', 'woocommerce' ),
								'type'        => 'boolean',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'tax_total'          => [
								'description' => __( 'Tax total (not including shipping taxes).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'shipping_tax_total' => [
								'description' => __( 'Shipping tax total.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'meta_data'          => [
								'description' => __( 'Meta data.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'    => [
											'description' => __( 'Meta ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'key'   => [
											'description' => __( 'Meta key.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'value' => [
											'description' => __( 'Meta value.', 'woocommerce' ),
											'type'        => 'mixed',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
						],
					],
				],
				'shipping_lines'       => [
					'description' => __( 'Shipping lines data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'           => [
								'description' => __( 'Item ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'method_title' => [
								'description' => __( 'Shipping method name.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
							'method_id'    => [
								'description' => __( 'Shipping method ID.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
							'instance_id'  => [
								'description' => __( 'Shipping instance ID.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'total'        => [
								'description' => __( 'Line total (after discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'total_tax'    => [
								'description' => __( 'Line total tax (after discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'taxes'        => [
								'description' => __( 'Line taxes.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'    => [
											'description' => __( 'Tax rate ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'total' => [
											'description' => __( 'Tax total.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
									],
								],
							],
							'meta_data'    => [
								'description' => __( 'Meta data.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'    => [
											'description' => __( 'Meta ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'key'   => [
											'description' => __( 'Meta key.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'value' => [
											'description' => __( 'Meta value.', 'woocommerce' ),
											'type'        => 'mixed',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
						],
					],
				],
				'fee_lines'            => [
					'description' => __( 'Fee lines data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'         => [
								'description' => __( 'Item ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'name'       => [
								'description' => __( 'Fee name.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
							'tax_class'  => [
								'description' => __( 'Tax class of fee.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'tax_status' => [
								'description' => __( 'Tax status of fee.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'enum'        => [ 'taxable', 'none' ],
							],
							'total'      => [
								'description' => __( 'Line total (after discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'total_tax'  => [
								'description' => __( 'Line total tax (after discounts).', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'taxes'      => [
								'description' => __( 'Line taxes.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'       => [
											'description' => __( 'Tax rate ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'total'    => [
											'description' => __( 'Tax total.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'subtotal' => [
											'description' => __( 'Tax subtotal.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
									],
								],
							],
							'meta_data'  => [
								'description' => __( 'Meta data.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'    => [
											'description' => __( 'Meta ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'key'   => [
											'description' => __( 'Meta key.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'value' => [
											'description' => __( 'Meta value.', 'woocommerce' ),
											'type'        => 'mixed',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
						],
					],
				],
				'coupon_lines'         => [
					'description' => __( 'Coupons line data.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'           => [
								'description' => __( 'Item ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'code'         => [
								'description' => __( 'Coupon code.', 'woocommerce' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
							'discount'     => [
								'description' => __( 'Discount total.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
							],
							'discount_tax' => [
								'description' => __( 'Discount total tax.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'meta_data'    => [
								'description' => __( 'Meta data.', 'woocommerce' ),
								'type'        => 'array',
								'context'     => [ 'view', 'edit' ],
								'items'       => [
									'type'       => 'object',
									'properties' => [
										'id'    => [
											'description' => __( 'Meta ID.', 'woocommerce' ),
											'type'        => 'integer',
											'context'     => [ 'view', 'edit' ],
											'readonly'    => true,
										],
										'key'   => [
											'description' => __( 'Meta key.', 'woocommerce' ),
											'type'        => 'string',
											'context'     => [ 'view', 'edit' ],
										],
										'value' => [
											'description' => __( 'Meta value.', 'woocommerce' ),
											'type'        => 'mixed',
											'context'     => [ 'view', 'edit' ],
										],
									],
								],
							],
						],
					],
				],
				'refunds'              => [
					'description' => __( 'List of refunds.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'     => [
								'description' => __( 'Refund ID.', 'woocommerce' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'reason' => [
								'description' => __( 'Refund reason.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'total'  => [
								'description' => __( 'Refund total.', 'woocommerce' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
						],
					],
				],
				'payment_url'          => [
					'description' => __( 'Order payment URL.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'set_paid'             => [
					'description' => __( 'Define if the order is paid. It will set the status to processing and reduce stock items.', 'woocommerce' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => [ 'edit' ],
				],
				'is_editable'          => [
					'description' => __( 'Whether an order can be edited.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'needs_payment'        => [
					'description' => __( 'Whether an order needs payment, based on status and order total.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'needs_processing'     => [
					'description' => __( 'Whether an order needs processing before it can be completed.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
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
		$params = parent::get_collection_params();

		$params['status']   = [
			'default'           => 'any',
			'description'       => __( 'Limit result set to orders assigned a specific status.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => array_merge( [ 'any', 'trash' ], $this->get_order_statuses() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['customer'] = [
			'description'       => __( 'Limit result set to orders assigned a specific customer.', 'woocommerce' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['product']  = [
			'description'       => __( 'Limit result set to orders assigned a specific product.', 'woocommerce' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['dp']       = [
			'default'           => wc_get_price_decimals(),
			'description'       => __( 'Number of decimal points to use in each resource.', 'woocommerce' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order_item_display_meta'] = [
			'default' => false,
			'description' => __( 'Only show meta which is meant to be displayed for an order.', 'woocommerce' ),
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $params;
	}
}
