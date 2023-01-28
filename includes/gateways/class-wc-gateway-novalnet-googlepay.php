<?php
/**
 * Novalnet Google Pay Payment.
 *
 * This gateway is used for real time processing of Googlepay data of customers.
 *
 * Copyright (c) Novalnet`
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_GooglePay
 * @extends Abstract_Novalnet_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_GooglePay Class.
 */
class WC_Gateway_Novalnet_GooglePay extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_googlepay';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Assign payment details.
		$this->assign_basic_payment_details();

		// Handle redirection payment response.
		add_action( 'woocommerce_api_response_novalnet_googlepay', array( $this, 'check_novalnet_payment_response' ) );

		// Novalnet subscription supports.
		$this->supports = apply_filters( 'novalnet_subscription_supports', $this->supports, $this->id );
	}

	/**
	 * Manage redirect process.
	 */
	public function check_novalnet_payment_response() {

		// Checks redirect response.
		if ( wc_novalnet_check_isset( novalnet()->request, 'wc-api', 'response_' . $this->id ) ) {

			// Process redirect response.
			$status = $this->process_redirect_payment_response();

			// Redirect to checkout / success page.
			wc_novalnet_safe_redirect( $status ['redirect'] );
		}
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
	 * Forming gateway parameters.
	 *
	 * @param WC_Order $wc_order    the order object.
	 * @param int      $parameters  the parameters.
	 *
	 * @return void
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {
		$session = novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'novalnet_googlepay_amount',
				'novalnet_googlepay_token',
			)
		);

		$googlepay_token = WC()->session->get( 'novalnet_googlepay_token' );
		$do_redirect     = WC()->session->get( 'googlepay_do_redirect' );

		if ( 'true' === (string) $do_redirect ) {
			// Assign redirect payment params.
			$this->redirect_payment_params( $wc_order, $parameters );
		}

		if ( empty( $parameters ['transaction'] ['payment_data'] ['wallet_token'] ) ) {

			if ( ! empty( $googlepay_token ) ) {

				// Assign generated pan hash and unique_id.
				$parameters['transaction'] ['payment_data'] = array(
					'wallet_token' => $googlepay_token,
				);
			}
		}
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return boolean
	 */
	public function is_available() {
		if ( is_admin() || ! wc_novalnet_check_session() ) {
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

		$this->form_fields ['google_pay_configuration_setting'] = array(
			'title'       => __( 'Button Design', 'woocommerce-novalnet-gateway' ),
			'type'        => 'title',
			'description' => sprintf( '<strong>%s</strong>', __( 'Style for Google pay button', 'woocommerce-novalnet-gateway' ) ),
		);

		$this->form_fields ['google_pay_button_type'] = array(
			'title'   => __( 'Button Type', 'woocommerce-novalnet-gateway' ),
			'class'   => 'chosen_select',
			'type'    => 'select',
			'default' => 'google-pay-button-text-buy',
			'options' => array(
				'book'      => 'Book',
				'buy'       => 'Buy',
				'checkout'  => 'Checkout',
				'donate'    => 'Donate',
				'order'     => 'Order',
				'pay'       => 'Pay',
				'plain'     => 'Plain',
				'subscribe' => 'Subscribe',
			),
		);

		$this->form_fields ['google_pay_button_height'] = array(
			'title'             => __( 'Button Height', 'woocommerce-novalnet-gateway' ),
			'type'              => 'number',
			'description'       => 'Range from 40 to 100 pixels',
			'desc_tip'          => true,
			'default'           => 40,
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
				'min'          => 40,
				'max'          => 100,
			),
		);

		// Enable inline form.
		$this->form_fields ['display_googlepay_button_on'] = array(
			'title'       => __( 'Display the Google Pay Button on', 'woocommerce-novalnet-gateway' ),
			'type'        => 'multiselect',
			'class'       => 'wc-enhanced-select',
			'default'     => 'yes',
			'description' => __( 'The selected pages will display the Google Pay button to pay instantly as an express checkout option', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
			'options'     => array(
				'shopping_cart_page'  => __( 'Shopping cart page', 'woocommerce-novalnet-gateway' ),
				'mini_cart_page'      => __( 'Mini cart page', 'woocommerce-novalnet-gateway' ),
				'product_page'        => __( 'Product page', 'woocommerce-novalnet-gateway' ),
				'guest_checkout_page' => __( 'Guest checkout page', 'woocommerce-novalnet-gateway' ),
				'checkout_page'       => __( 'Checkout page', 'woocommerce-novalnet-gateway' ),
			),
			'default'     => array(
				'shopping_cart_page',
				'mini_cart_page',
				'product_page',
				'guest_checkout_page',
				'checkout_page',
			),
		);
	}
}
