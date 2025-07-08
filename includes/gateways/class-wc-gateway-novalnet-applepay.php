<?php
/**
 * Novalnet Apple Pay Payment.
 *
 * This gateway is used for real time processing of Applepay data of customers.
 *
 * Copyright (c) Novalnet`
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_ApplePay
 * @extends Abstract_Novalnet_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_ApplePay Class.
 */
class WC_Gateway_Novalnet_ApplePay extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_applepay';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Assign payment details.
		$this->assign_basic_payment_details();

		// Novalnet subscription supports.
		$this->supports = apply_filters( 'novalnet_subscription_supports', $this->supports, $this->id );
	}

	/**
	 * Process payment flow of the gateway.
	 *
	 * @param int $order_id the order id.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		return $this->perform_payment_call( $order_id );
	}

	/**
	 * Refund process.
	 *
	 * @since 12.0.0.
	 *
	 * @param int    $order_id  The order number.
	 * @param double $amount    The total amount of refund.
	 * @param string $reason    The reason for refund.
	 *
	 * @return boolean
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		return WC_Novalnet_Amount_Refund::execute( $order_id, wc_novalnet_formatted_amount( $amount ), $reason );
	}

	/**
	 * Returns the payment description html string for block checkout.
	 *
	 * @since 12.6.2
	 *
	 * @return string.
	 */
	public function get_payment_description_html() {
		$icon = '';
		if ( is_admin() ) {
			$icon_url = novalnet()->plugin_url . '/assets/images/' . $this->id . '.svg';
			$icon     = "<img src='$icon_url' alt='" . $this->title . "' title='" . $this->title . "' />";
		}
		return $icon;
	}

	/**
	 * Forming gateway parameters.
	 *
	 * @param WC_Order $wc_order    the order object.
	 * @param int      $parameters  the parameters.
	 *
	 * @return void
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {
		novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'novalnet_applepay_amount',
				'novalnet_applepay_token',
			)
		);

		$applepay_token = WC()->session->get( 'novalnet_applepay_token' );

		if ( empty( $parameters ['transaction'] ['payment_data'] ['wallet_token'] ) && ! empty( $applepay_token ) ) {

			// Assign generated pan hash and unique_id.
			$parameters['transaction'] ['payment_data'] = array(
				'wallet_token' => $applepay_token,
			);
		}
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return boolean
	 */
	public function is_available() {
		if ( is_admin() || ! wc_novalnet_check_session() || ( ! is_checkout_pay_page() && is_checkout() && novalnet()->helper()->is_checkout_block_default() ) || ( is_cart() && novalnet()->helper()->is_cart_block_default() ) ) {
			return parent::is_available();
		}
		return false;
	}

	/**
	 * Payment configurations in shop backend
	 */
	public function init_form_fields() {

		// Basic payment fields.
		WC_Novalnet_Configuration::basic( $this->form_fields, $this->id );

		// On-hold configurations.
		WC_Novalnet_Configuration::on_hold( $this->form_fields, $this->id );

		// Get wallet settings.
		WC_Novalnet_Configuration::wallet_settings( $this->form_fields, $this->id );

		$this->form_fields ['apple_pay_configuration_setting'] = array(
			'title' => __( 'Button Design', 'woocommerce-novalnet-gateway' ),
			'type'  => 'title',
		);

		$this->form_fields ['apple_pay_button_type'] = array(
			'title'   => __( 'Button Type', 'woocommerce-novalnet-gateway' ),
			'class'   => 'chosen_select',
			'type'    => 'select',
			'default' => 'apple-pay-button-text-buy',
			'options' => array(
				'plain'      => 'Default',
				'buy'        => 'Buy',
				'donate'     => 'Donate',
				'book'       => 'Book',
				'contribute' => 'Contribute',
				'check-out'  => 'Check out',
				'order'      => 'Order',
				'subscribe'  => 'Subscribe',
				'tip'        => 'Tip',
				'reload'     => 'Reload',
				'rent'       => 'Rent',
				'support'    => 'Support',
			),
		);

		$this->form_fields ['apple_pay_button_theme'] = array(
			'title'   => __( 'Button Theme', 'woocommerce-novalnet-gateway' ),
			'class'   => 'chosen_select',
			'type'    => 'select',
			'default' => 'black',
			'options' => array(
				'black'         => 'Dark',
				'white'         => 'Light',
				'white-outline' => 'Light-Outline',
			),
		);

		$this->form_fields ['apple_pay_button_height'] = array(
			'title'             => __( 'Button Height', 'woocommerce-novalnet-gateway' ),
			'type'              => 'number',
			/* translators: %1$s: min range %2$s: max range */
			'description'       => sprintf( __( 'Range from %1$s to %2$s pixels', 'woocommerce-novalnet-gateway' ), 30, 64 ),
			'desc_tip'          => true,
			'default'           => 40,
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
				'min'          => 30,
				'max'          => 64,
			),
		);

		$this->form_fields ['apple_pay_button_corner_radius'] = array(
			'title'             => __( 'Button Corner Radius', 'woocommerce-novalnet-gateway' ),
			'type'              => 'number',
			/* translators: %1$s: min range %2$s: max range */
			'description'       => sprintf( __( 'Range from %1$s to %2$s pixels', 'woocommerce-novalnet-gateway' ), 0, 10 ),
			'default'           => 5,
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
				'min'          => 0,
				'max'          => 10,
			),
		);

		// Enable inline form.
		$this->form_fields ['display_applepay_button_on'] = array(
			'title'       => __( 'Display the Apple Pay Button on', 'woocommerce-novalnet-gateway' ),
			'type'        => 'multiselect',
			'class'       => 'wc-enhanced-select',
			'default'     => 'yes',
			'description' => __( 'The selected pages will display the Apple pay button to pay instantly as an express checkout option', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
			'options'     => array(
				'shopping_cart_page' => __( 'Shopping cart page', 'woocommerce-novalnet-gateway' ),
				'mini_cart_page'     => __( 'Mini cart page', 'woocommerce-novalnet-gateway' ),
				'product_page'       => __( 'Product page', 'woocommerce-novalnet-gateway' ),
				'checkout_page'      => __( 'Checkout page', 'woocommerce-novalnet-gateway' ),
			),
			'default'     => array(
				'shopping_cart_page',
				'mini_cart_page',
				'product_page',
				'checkout_page',
			),
		);
	}
}
