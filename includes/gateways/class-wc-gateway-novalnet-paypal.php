<?php
/**
 * Novalnet PayPal Payment.
 *
 * This gateway is used for real time processing of paypal transaction of customers.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_Paypal
 * @extends Abstract_Novalnet_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_Paypal Class.
 */
class WC_Gateway_Novalnet_Paypal extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_paypal';


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Assign payment details.
		$this->assign_basic_payment_details();

		// Handle redirection payment response.
		add_action( 'woocommerce_api_response_novalnet_paypal', array( $this, 'check_novalnet_payment_response' ), 10 );

		// Add support tokenization.
		if ( wc_novalnet_check_isset( $this->settings, 'tokenization', 'yes' ) ) {

			$this->supports[] = 'tokenization';
			add_filter( 'woocommerce_payment_methods_list_item', array( 'WC_Payment_Token_Novalnet', 'saved_payment_methods_list_item' ), 10, 2 );
		}

		// Novalnet subscription supports.
		$this->supports = apply_filters( 'novalnet_subscription_supports', $this->supports, $this->id );
	}

	/**
	 * Returns the gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {

		return apply_filters( 'woocommerce_gateway_icon', $this->built_logo(), $this->id );
	}

	/**
	 * Validate payment fields on the frontend.
	 */
	public function validate_fields() {

		// Unset other payment session.
		$this->unset_other_payment_session();

		// Assigning post values in session.
		novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'wc-novalnet_paypal-new-payment-method',
				'wc-novalnet_paypal-payment-token',
			)
		);
	}

	/**
	 * Displays the payment form, payment description on checkout.
	 */
	public function payment_fields() {

		$this->test_mode_notification();

		// Display Tokenization.
		$tokenization = (bool) ( $this->supports( 'tokenization' ) && ! ( ! empty( novalnet()->request ['change_payment_method'] ) || is_wc_endpoint_url( 'order-pay' ) ) );

		if ( $tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		// Display save payement checkbox.
		if ( $tokenization ) {
			$this->save_payment_method_checkbox();
		}

		// Display payment description.
		$this->show_description();
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
	 * Check if the gateway is available for use.
	 *
	 * @return boolean
	 */
	public function is_available() {
		if ( is_admin() || ! wc_novalnet_check_session() ) {
			return parent::is_available();
		}
		return parent::is_available() && WC_Novalnet_Validation::is_payment_available( $this->settings, $this->id );
	}

	/**
	 * Form gateway parameters to process in the Novalnet server.
	 *
	 * @param WC_Order $wc_order   The order object.
	 * @param array    $parameters The basic parameters.
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {

		// Assigning post values in session.
		novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'wc-novalnet_paypal-new-payment-method',
				'wc-novalnet_paypal-payment-token',
			)
		);

		$this->set_payment_token( $parameters );

		if ( empty( $parameters ['transaction'] ['payment_data'] ['token'] ) ) {
			$this->redirect_payment_params( $wc_order, $parameters );
		}
	}

	/**
	 * Manage redirect process.
	 */
	public function check_novalnet_payment_response() {

		// Checks redirect response.
		if ( wc_novalnet_check_isset( novalnet()->request, 'wc-api', 'response_' . $this->id ) ) {

			// Process redirect response.
			$status = $this->process_redirect_payment_response();
			return wc_novalnet_safe_redirect( $status['redirect'] );
		}
	}

	/**
	 * Payment configurations in shop backend.
	 */
	public function init_form_fields() {

		$this->form_fields ['notice'] = array(
			/* translators: %1$s: anchor tag starts %2$s: anchor tag end */
			'title' => '<div class="updated inline notice"><p>' . sprintf( __( 'To accept PayPal transactions, configure your PayPal API info in the  %1$sNovalnet Admin Portal%2$s > PROJECT > "Project" Information > Payment Methods > Paypal > Configure.', 'woocommerce-novalnet-gateway' ), '<a href="https://admin.novalnet.de" target="_new">', '</a>' ) . '</p></div>',
			'type'  => 'title',
		);

		// Basic payment fields.
		WC_Novalnet_Configuration::basic( $this->form_fields, $this->id );

		// On-hold configurations.
		WC_Novalnet_Configuration::on_hold( $this->form_fields, $this->id );

		// Tokenization configuration.
		WC_Novalnet_Configuration::tokenization( $this->form_fields, $this->id );

		// Additional configuration.
		WC_Novalnet_Configuration::additional( $this->form_fields, $this->id );
	}

}
