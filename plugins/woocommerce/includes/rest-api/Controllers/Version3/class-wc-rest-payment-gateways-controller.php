<?php
/**
 * REST API WC Payment gateways controller
 *
 * Handles requests to the /payment_gateways endpoint.
 *
 * @package WooCommerce\RestApi
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paymenga gateways controller class.
 *
 * @package WooCommerce\RestApi
 * @extends WC_REST_Payment_Gateways_V2_Controller
 */
class WC_REST_Payment_Gateways_Controller extends WC_REST_Payment_Gateways_V2_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Prepare a payment gateway for response.
	 *
	 * @param  WC_Payment_Gateway $gateway    Payment gateway object.
	 * @param  WP_REST_Request    $request    Request object.
	 * @return WP_REST_Response   $response   Response data.
	 */
	public function prepare_item_for_response( $gateway, $request ) {
		$order = (array) get_option( 'woocommerce_gateway_order' );
		$item  = [
			'id'                 => $gateway->id,
			'title'              => $gateway->title,
			'description'        => $gateway->description,
			'order'              => isset( $order[ $gateway->id ] ) ? $order[ $gateway->id ] : '',
			'enabled'            => ( 'yes' === $gateway->enabled ),
			'method_title'       => $gateway->get_method_title(),
			'method_description' => $gateway->get_method_description(),
			'method_supports'    => $gateway->supports,
			'settings'           => $this->get_settings( $gateway ),
		];

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $item, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $gateway, $request ) );

		/**
		 * Filter payment gateway objects returned from the REST API.
		 *
		 * @param WP_REST_Response   $response The response object.
		 * @param WC_Payment_Gateway $gateway  Payment gateway object.
		 * @param WP_REST_Request    $request  Request object.
		 */
		return apply_filters( 'woocommerce_rest_prepare_payment_gateway', $response, $gateway, $request );
	}

	/**
	 * Return settings associated with this payment gateway.
	 *
	 * @param WC_Payment_Gateway $gateway Gateway instance.
	 *
	 * @return array
	 */
	public function get_settings( $gateway ) {
		$settings = [];
		$gateway->init_form_fields();
		foreach ( $gateway->form_fields as $id => $field ) {
			// Make sure we at least have a title and type.
			if ( empty( $field['title'] ) || empty( $field['type'] ) ) {
				continue;
			}

			// Ignore 'enabled' and 'description' which get included elsewhere.
			if ( in_array( $id, [ 'enabled', 'description' ], true ) ) {
				continue;
			}

			$data = [
				'id'          => $id,
				'label'       => empty( $field['label'] ) ? $field['title'] : $field['label'],
				'description' => empty( $field['description'] ) ? '' : $field['description'],
				'type'        => $field['type'],
				'value'       => empty( $gateway->settings[ $id ] ) ? '' : $gateway->settings[ $id ],
				'default'     => empty( $field['default'] ) ? '' : $field['default'],
				'tip'         => empty( $field['description'] ) ? '' : $field['description'],
				'placeholder' => empty( $field['placeholder'] ) ? '' : $field['placeholder'],
			];
			if ( ! empty( $field['options'] ) ) {
				$data['options'] = $field['options'];
			}
			$settings[ $id ] = $data;
		}
		return $settings;
	}

	/**
	 * Get the payment gateway schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'payment_gateway',
			'type'       => 'object',
			'properties' => [
				'id'                 => [
					'description' => __( 'Payment gateway ID.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'title'              => [
					'description' => __( 'Payment gateway title on checkout.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'description'        => [
					'description' => __( 'Payment gateway description on checkout.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'order'              => [
					'description' => __( 'Payment gateway sort order.', 'woocommerce' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'arg_options' => [
						'sanitize_callback' => 'absint',
					],
				],
				'enabled'            => [
					'description' => __( 'Payment gateway enabled status.', 'woocommerce' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
				],
				'method_title'       => [
					'description' => __( 'Payment gateway method title.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'method_description' => [
					'description' => __( 'Payment gateway method description.', 'woocommerce' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'method_supports'    => [
					'description' => __( 'Supported features for this payment gateway.', 'woocommerce' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type' => 'string',
					],
				],
				'settings'           => [
					'description' => __( 'Payment gateway settings.', 'woocommerce' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'id'          => [
							'description' => __( 'A unique identifier for the setting.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'label'       => [
							'description' => __( 'A human readable label for the setting used in interfaces.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'description' => [
							'description' => __( 'A human readable description for the setting used in interfaces.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'type'        => [
							'description' => __( 'Type of setting.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'enum'        => [ 'text', 'email', 'number', 'color', 'password', 'textarea', 'select', 'multiselect', 'radio', 'image_width', 'checkbox' ],
							'readonly'    => true,
						],
						'value'       => [
							'description' => __( 'Setting value.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'default'     => [
							'description' => __( 'Default value for the setting.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'tip'         => [
							'description' => __( 'Additional help text shown to the user about the setting.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'placeholder' => [
							'description' => __( 'Placeholder text to be displayed in text inputs.', 'woocommerce' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}
}
