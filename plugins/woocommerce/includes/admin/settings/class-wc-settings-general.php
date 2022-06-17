<?php
/**
 * WooCommerce General Settings
 *
 * @package WooCommerce\Admin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Settings_General', false ) ) {
	return new WC_Settings_General();
}

/**
 * WC_Admin_Settings_General.
 */
class WC_Settings_General extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'general';
		$this->label = __( 'General', 'woocommerce' );

		parent::__construct();
	}

	/**
	 * Get settings or the default section.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {

		$currency_code_options = get_woocommerce_currencies();

		foreach ( $currency_code_options as $code => $name ) {
			$currency_code_options[ $code ] = $name . ' (' . get_woocommerce_currency_symbol( $code ) . ')';
		}

		$settings =
			[

				[
					'title' => __( 'Store Address', 'woocommerce' ),
					'type'  => 'title',
					'desc'  => __( 'This is where your business is located. Tax rates and shipping rates will use this address.', 'woocommerce' ),
					'id'    => 'store_address',
				],

				[
					'title'    => __( 'Address line 1', 'woocommerce' ),
					'desc'     => __( 'The street address for your business location.', 'woocommerce' ),
					'id'       => 'woocommerce_store_address',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => true,
				],

				[
					'title'    => __( 'Address line 2', 'woocommerce' ),
					'desc'     => __( 'An additional, optional address line for your business location.', 'woocommerce' ),
					'id'       => 'woocommerce_store_address_2',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => true,
				],

				[
					'title'    => __( 'City', 'woocommerce' ),
					'desc'     => __( 'The city in which your business is located.', 'woocommerce' ),
					'id'       => 'woocommerce_store_city',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => true,
				],

				[
					'title'    => __( 'Country / State', 'woocommerce' ),
					'desc'     => __( 'The country and state or province, if any, in which your business is located.', 'woocommerce' ),
					'id'       => 'woocommerce_default_country',
					'default'  => 'US:CA',
					'type'     => 'single_select_country',
					'desc_tip' => true,
				],

				[
					'title'    => __( 'Postcode / ZIP', 'woocommerce' ),
					'desc'     => __( 'The postal code, if any, in which your business is located.', 'woocommerce' ),
					'id'       => 'woocommerce_store_postcode',
					'css'      => 'min-width:50px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => true,
				],

				[
					'type' => 'sectionend',
					'id'   => 'store_address',
				],

				[
					'title' => __( 'General options', 'woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'general_options',
				],

				[
					'title'    => __( 'Selling location(s)', 'woocommerce' ),
					'desc'     => __( 'This option lets you limit which countries you are willing to sell to.', 'woocommerce' ),
					'id'       => 'woocommerce_allowed_countries',
					'default'  => 'all',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width: 350px;',
					'desc_tip' => true,
					'options'  => [
						'all'        => __( 'Sell to all countries', 'woocommerce' ),
						'all_except' => __( 'Sell to all countries, except for&hellip;', 'woocommerce' ),
						'specific'   => __( 'Sell to specific countries', 'woocommerce' ),
					],
				],

				[
					'title'   => __( 'Sell to all countries, except for&hellip;', 'woocommerce' ),
					'desc'    => '',
					'id'      => 'woocommerce_all_except_countries',
					'css'     => 'min-width: 350px;',
					'default' => '',
					'type'    => 'multi_select_countries',
				],

				[
					'title'   => __( 'Sell to specific countries', 'woocommerce' ),
					'desc'    => '',
					'id'      => 'woocommerce_specific_allowed_countries',
					'css'     => 'min-width: 350px;',
					'default' => '',
					'type'    => 'multi_select_countries',
				],

				[
					'title'    => __( 'Shipping location(s)', 'woocommerce' ),
					'desc'     => __( 'Choose which countries you want to ship to, or choose to ship to all locations you sell to.', 'woocommerce' ),
					'id'       => 'woocommerce_ship_to_countries',
					'default'  => '',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'desc_tip' => true,
					'options'  => [
						''         => __( 'Ship to all countries you sell to', 'woocommerce' ),
						'all'      => __( 'Ship to all countries', 'woocommerce' ),
						'specific' => __( 'Ship to specific countries only', 'woocommerce' ),
						'disabled' => __( 'Disable shipping &amp; shipping calculations', 'woocommerce' ),
					],
				],

				[
					'title'   => __( 'Ship to specific countries', 'woocommerce' ),
					'desc'    => '',
					'id'      => 'woocommerce_specific_ship_to_countries',
					'css'     => '',
					'default' => '',
					'type'    => 'multi_select_countries',
				],

				[
					'title'    => __( 'Default customer location', 'woocommerce' ),
					'id'       => 'woocommerce_default_customer_address',
					'desc_tip' => __( 'This option determines a customers default location. The MaxMind GeoLite Database will be periodically downloaded to your wp-content directory if using geolocation.', 'woocommerce' ),
					'default'  => 'base',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => [
						''                 => __( 'No location by default', 'woocommerce' ),
						'base'             => __( 'Shop country/region', 'woocommerce' ),
						'geolocation'      => __( 'Geolocate', 'woocommerce' ),
						'geolocation_ajax' => __( 'Geolocate (with page caching support)', 'woocommerce' ),
					],
				],

				[
					'title'    => __( 'Enable taxes', 'woocommerce' ),
					'desc'     => __( 'Enable tax rates and calculations', 'woocommerce' ),
					'id'       => 'woocommerce_calc_taxes',
					'default'  => 'no',
					'type'     => 'checkbox',
					'desc_tip' => __( 'Rates will be configurable and taxes will be calculated during checkout.', 'woocommerce' ),
				],

				[
					'title'           => __( 'Enable coupons', 'woocommerce' ),
					'desc'            => __( 'Enable the use of coupon codes', 'woocommerce' ),
					'id'              => 'woocommerce_enable_coupons',
					'default'         => 'yes',
					'type'            => 'checkbox',
					'checkboxgroup'   => 'start',
					'show_if_checked' => 'option',
					'desc_tip'        => __( 'Coupons can be applied from the cart and checkout pages.', 'woocommerce' ),
				],

				[
					'desc'            => __( 'Calculate coupon discounts sequentially', 'woocommerce' ),
					'id'              => 'woocommerce_calc_discounts_sequentially',
					'default'         => 'no',
					'type'            => 'checkbox',
					'desc_tip'        => __( 'When applying multiple coupons, apply the first coupon to the full price and the second coupon to the discounted price and so on.', 'woocommerce' ),
					'show_if_checked' => 'yes',
					'checkboxgroup'   => 'end',
					'autoload'        => false,
				],

				[
					'type' => 'sectionend',
					'id'   => 'general_options',
				],

				[
					'title' => __( 'Currency options', 'woocommerce' ),
					'type'  => 'title',
					'desc'  => __( 'The following options affect how prices are displayed on the frontend.', 'woocommerce' ),
					'id'    => 'pricing_options',
				],

				[
					'title'    => __( 'Currency', 'woocommerce' ),
					'desc'     => __( 'This controls what currency prices are listed at in the catalog and which currency gateways will take payments in.', 'woocommerce' ),
					'id'       => 'woocommerce_currency',
					'default'  => 'USD',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'desc_tip' => true,
					'options'  => $currency_code_options,
				],

				[
					'title'    => __( 'Currency position', 'woocommerce' ),
					'desc'     => __( 'This controls the position of the currency symbol.', 'woocommerce' ),
					'id'       => 'woocommerce_currency_pos',
					'class'    => 'wc-enhanced-select',
					'default'  => 'left',
					'type'     => 'select',
					'options'  => [
						'left'        => __( 'Left', 'woocommerce' ),
						'right'       => __( 'Right', 'woocommerce' ),
						'left_space'  => __( 'Left with space', 'woocommerce' ),
						'right_space' => __( 'Right with space', 'woocommerce' ),
					],
					'desc_tip' => true,
				],

				[
					'title'    => __( 'Thousand separator', 'woocommerce' ),
					'desc'     => __( 'This sets the thousand separator of displayed prices.', 'woocommerce' ),
					'id'       => 'woocommerce_price_thousand_sep',
					'css'      => 'width:50px;',
					'default'  => ',',
					'type'     => 'text',
					'desc_tip' => true,
				],

				[
					'title'    => __( 'Decimal separator', 'woocommerce' ),
					'desc'     => __( 'This sets the decimal separator of displayed prices.', 'woocommerce' ),
					'id'       => 'woocommerce_price_decimal_sep',
					'css'      => 'width:50px;',
					'default'  => '.',
					'type'     => 'text',
					'desc_tip' => true,
				],

				[
					'title'             => __( 'Number of decimals', 'woocommerce' ),
					'desc'              => __( 'This sets the number of decimal points shown in displayed prices.', 'woocommerce' ),
					'id'                => 'woocommerce_price_num_decimals',
					'css'               => 'width:50px;',
					'default'           => '2',
					'desc_tip'          => true,
					'type'              => 'number',
					'custom_attributes' => [
						'min'  => 0,
						'step' => 1,
					],
				],

				[
					'type' => 'sectionend',
					'id'   => 'pricing_options',
				],
			];

		return apply_filters( 'woocommerce_general_settings', $settings );
	}

	/**
	 * Output a color picker input box.
	 *
	 * @param mixed  $name Name of input.
	 * @param string $id ID of input.
	 * @param mixed  $value Value of input.
	 * @param string $desc (default: '') Description for input.
	 */
	public function color_picker( $name, $id, $value, $desc = '' ) {
		echo '<div class="color_box">' . wc_help_tip( $desc ) . '
			<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="text" value="' . esc_attr( $value ) . '" class="colorpick" /> <div id="colorPickerDiv_' . esc_attr( $id ) . '" class="colorpickdiv"></div>
		</div>';
	}
}

return new WC_Settings_General();
