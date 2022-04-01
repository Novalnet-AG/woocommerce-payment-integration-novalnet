<?php
/**
 * Novalnet Instalment Invoice Payment
 *
 * This gateway is used for real time processing of Instalment invoice payment of customers.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_Instalment_Invoice
 * @extends NN_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet AG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_Instalment_Invoice Class.
 */
class WC_Gateway_Novalnet_Instalment_Invoice extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_instalment_invoice';

	/**
	 * Settings of the gateway.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Assign payment details.
		$this->assign_basic_payment_details();

		// This payment method will not support default refunds.
		novalnet()->helper()->unset_supports( $this->supports, 'refunds' );

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

		// Display guaranteed/instalment fields.
		novalnet()->helper()->load_template( 'render-guaranteed-instalment-form.php', $this->settings, $this->id );

		// Display payment description.
		$this->show_description();
	}

	/**
	 * Validate payment fields on the frontend.
	 */
	public function validate_fields() {

		// Unset other payment session.
		$this->unset_other_payment_session();

		// Assigning post values in session.
		$session = novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'novalnet_instalment_invoice_dob',
				'novalnet_instalment_invoice_period',
			)
		);

		$error = WC_Novalnet_Validation::has_valid_guarantee_input( $session, $this->id );

		if ( '' !== $error ) {

			WC()->session->__unset( $this->id );
			// Display message.
			$this->display_info( $error );

			// Redirect to checkout page.
			return $this->novalnet_redirect();
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
		global $wp;

		if ( is_admin() || ! wc_novalnet_check_session() ) {
			return parent::is_available();
		}

		if ( is_admin() || ! wc_novalnet_check_session() ) {
			return parent::is_available();
		}

		$total = WC()->cart->total;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) && ! empty( $wp->query_vars ) ) { // @codingStandardsIgnoreLine.
			$order = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
			$total = $order->get_total();
		}

		return parent::is_available() && WC_Novalnet_Validation::is_payment_available( $this->settings, $this->id ) && WC_Novalnet_Validation::is_guarantee_available( $this->id, $this->settings ) && WC_Novalnet_Validation::has_valid_instalment_cycles( $this->settings, $total );
	}

	/**
	 * Form gateway parameters to process in the Novalnet server.
	 *
	 * @param WC_Order $wc_order    The order object.
	 * @param int      $parameters  The parameters.
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {

		// Assigning post values in session.
		$session                               = novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'novalnet_instalment_invoice_dob',
				'novalnet_instalment_invoice_period',
			)
		);
		$parameters ['instalment']['interval'] = '1m';
		$parameters ['instalment']['cycles']   = $session[ $this->id . '_period' ];

		if ( ! is_admin() ) {
			$session = WC()->session->get( $this->id );
			if ( ! empty( $session [ $this->id . '_dob' ] ) ) {
				$parameters ['customer']['birth_date'] = $session [ $this->id . '_dob' ];
			}
		}
		if ( wc_novalnet_check_isset( $this->settings, 'allow_b2b', 'no' ) && ! empty( $parameters ['customer'] ['billing']['company'] ) ) {
			unset( $parameters ['customer'] ['billing']['company'] );
		}
	}


	/**
	 * Payment configurations in shop backend.
	 */
	public function init_form_fields() {

		// Guaranteed configurations.
		WC_Novalnet_Configuration::guarantee_conditions_notification( $this->form_fields, $this->id );

		// Basic payment fields.
		WC_Novalnet_Configuration::basic( $this->form_fields, $this->id );

		// On-hold configurations.
		WC_Novalnet_Configuration::on_hold( $this->form_fields, $this->id );

		// On-hold configurations.
		WC_Novalnet_Configuration::instalment( $this->form_fields );

		// Guarantee configuration.
		WC_Novalnet_Configuration::guarantee( $this->form_fields );

		// Additional configuration.
		WC_Novalnet_Configuration::additional( $this->form_fields, $this->id );
	}
}
