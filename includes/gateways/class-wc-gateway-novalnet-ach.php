<?php
/**
 * Novalnet Direct Debit ACH Payment.
 *
 * This gateway is used for real time processing of bank data of customers.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_Ach
 * @extends Abstract_Novalnet_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_Ach Class.
 */
class WC_Gateway_Novalnet_Ach extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_ach';


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Assign payment details.
		$this->assign_basic_payment_details();

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
	 * Displays the payment form, payment description on checkout.
	 */
	public function payment_fields() {

		// Show TESTMODE notification.
		$this->test_mode_notification();

		// Display Tokenization.
		$tokenization = (bool) ( $this->supports( 'tokenization' ) && is_checkout() && ( empty( novalnet()->request ['change_payment_method'] ) ) );

		if ( $tokenization && ! is_admin() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$total = WC()->cart->total;

		$customer = array();
		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) && ! empty( $wp->query_vars ) ) { // @codingStandardsIgnoreLine.
			$order    = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
			$total    = $order->get_total();
			$customer = novalnet()->helper()->get_customer_data( $order );
		}

		if ( ! empty( novalnet()->request ['change_payment_method'] ) ) {
			$total = 0;
		}

		// Display form fields.
		novalnet()->helper()->load_template(
			'render-ach-form.php',
			array_merge(
				$this->settings,
				array(
					'amount'   => wc_novalnet_formatted_amount( $total ),
					'currency' => get_woocommerce_currency(),
					'customer' => $customer,
				)
			),
			$this->id
		);

		// Display save payement checkbox.
		if ( $tokenization && ! is_admin() ) {
			$this->save_payment_method_checkbox();
		}

		// Display payment description.
		$this->show_description();
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
	 * Validate payment fields on the frontend.
	 */
	public function validate_fields() {
		if ( ! empty( WC()->session ) ) {
			// Unset other payment session.
			WC()->session->__unset( $this->id );
			$this->unset_other_payment_session();
			// Assigning post values in session.
			$session = novalnet()->helper()->set_post_value_session(
				$this->id,
				array(
					'novalnet_ach_holder',
					'novalnet_ach_account',
					'novalnet_ach_routing',
					'wc-novalnet_ach-new-payment-method',
					'wc-novalnet_ach-payment-token',
				)
			);
			// Check ACH details.
			if ( ! WC_Novalnet_Validation::validate_payment_input_field(
				$session,
				array(
					'novalnet_ach_holder',
					'novalnet_ach_account',
					'novalnet_ach_routing',
				)
			) && ( wc_novalnet_check_isset( $session, 'wc-novalnet_ach-payment-token', 'new' ) || empty( $session ['wc-novalnet_ach-payment-token'] ) ) ) {
				WC()->session->__unset( $this->id );
				// Display message.
				$this->display_info( __( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ) );
				// Redirect to checkout page.
				return $this->novalnet_redirect();
			}
			if ( empty( $session['novalnet_ach_holder'] ) && empty( $session['novalnet_ach_account'] ) && empty( $session['novalnet_ach_routing'] ) && ( wc_novalnet_check_isset( $session, 'wc-novalnet_ach-payment-token', 'new' ) || empty( $session ['wc-novalnet_ach-payment-token'] ) ) ) {
				// Display message.
				$this->display_info( __( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ) );
				// Redirect to checkout page.
				return $this->novalnet_redirect();
			}
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
	 * @param WC_Order $wc_order   the order object.
	 * @param int      $parameters the parameters.
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {

		$session = novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'novalnet_ach_holder',
				'novalnet_ach_account',
				'novalnet_ach_routing',
				'wc-novalnet_ach-new-payment-method',
				'wc-novalnet_ach-payment-token',
			)
		);

		$this->set_payment_token( $parameters );

		if ( empty( $parameters ['transaction'] ['payment_data'] ['token'] ) && ! empty( $session['novalnet_ach_holder'] ) && ! empty( $session['novalnet_ach_account'] ) && ! empty( $session['novalnet_ach_routing'] ) ) {

			// Assign account details.
			$parameters['transaction'] ['payment_data'] = array(
				'account_holder' => $session['novalnet_ach_holder'],
				'account_number' => $session ['novalnet_ach_account'],
				'routing_number' => $session ['novalnet_ach_routing'],
			);
		}
	}

	/**
	 * Payment configurations in shop backend.
	 */
	public function init_form_fields() {

		// Basic payment fields.
		WC_Novalnet_Configuration::basic( $this->form_fields, $this->id );

		if ( in_array( $this->id, novalnet()->get_supports( 'zero_amount_booking' ), true ) && WC_Novalnet_Validation::check_zero_amount_tariff_types() ) {
			// On-hold configuration fields.
			$this->form_fields ['payment_status'] = array(
				'title'       => __( 'Payment Action', 'woocommerce-novalnet-gateway' ),
				'class'       => 'chosen_select',
				'type'        => 'select',
				'desc_tip'    => __( 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'woocommerce-novalnet-gateway' ),
				'description' => '<span id="novalnet_paypal_notice"></span>',
				'options'     => array(
					'capture'             => __( 'Capture', 'woocommerce-novalnet-gateway' ),
					'zero_amount_booking' => __( 'Authorize with zero amount', 'woocommerce-novalnet-gateway' ),
				),
			);
		}

		// Tokenization configuration.
		WC_Novalnet_Configuration::tokenization( $this->form_fields, $this->id );

		// Additional configuration.
		WC_Novalnet_Configuration::additional( $this->form_fields, $this->id );

	}
}
