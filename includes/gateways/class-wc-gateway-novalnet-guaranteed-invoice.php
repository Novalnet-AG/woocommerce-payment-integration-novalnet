<?php
/**
 * Novalnet Guaranteed Invoice Payment.
 *
 * This gateway is used for real time processing of bank data of customers.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_Guaranteed_Invoice
 * @extends Abstract_Novalnet_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_Invoice Class.
 */
class WC_Gateway_Novalnet_Guaranteed_Invoice extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_guaranteed_invoice';

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

		// Display guaranteed/instalment fields.
		novalnet()->helper()->load_template( 'render-guaranteed-instalment-form.php', $this->settings, $this->id );

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

		// Unset other payment session.
		$this->unset_other_payment_session();

		// Assigning post values in session.
		$session = novalnet()->helper()->set_post_value_session(
			$this->id,
			array(
				'novalnet_guaranteed_invoice_dob',
			)
		);

		$error = WC_Novalnet_Validation::has_valid_guarantee_input( $session, $this->id, $this->settings );

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
		if ( is_admin() || ! wc_novalnet_check_session() ) {
			return parent::is_available();
		}
		return ( parent::is_available() && WC_Novalnet_Validation::is_guarantee_available( $this->id, $this->settings ) );
	}

	/**
	 * Form gateway parameters to process in the Novalnet server.
	 *
	 * @param WC_Order $wc_order   the order object.
	 * @param int      $parameters the parameters.
	 *
	 * @return void
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {

		$this->handle_payment_switch( $wc_order, $parameters );

		if ( $wc_order->get_payment_method() === $this->id ) {
			if ( ! is_admin() || WC()->session->get( 'admin_add_shop_order' ) ) {
				$session = novalnet()->helper()->set_post_value_session(
					$this->id,
					array(
						'novalnet_guaranteed_invoice_dob',
					)
				);
				if ( ! empty( $session [ $this->id . '_dob' ] ) ) {
					$parameters ['customer']['birth_date'] = $session [ $this->id . '_dob' ];
				}
			}
			if ( wc_novalnet_check_isset( $this->settings, 'allow_b2b', 'no' ) && ! empty( $parameters ['customer'] ['billing']['company'] ) ) {
				unset( $parameters ['customer'] ['billing']['company'] );
			}
		}

		// Novalnet subscription supports.
		$this->supports = apply_filters( 'novalnet_subscription_supports', $this->supports, $this->id );
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

		// Guarantee configuration.
		WC_Novalnet_Configuration::guarantee( $this->form_fields, $this->id, true );

		// Additional configuration.
		WC_Novalnet_Configuration::additional( $this->form_fields, $this->id );
	}
}
