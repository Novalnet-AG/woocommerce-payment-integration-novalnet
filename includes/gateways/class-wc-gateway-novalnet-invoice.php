<?php
/**
 * Novalnet Invoice Payment
 *
 * This gateway is used for real time processing of invoice payment of customers.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_Invoice
 * @extends NN_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet AG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_Invoice Class.
 */
class WC_Gateway_Novalnet_Invoice extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_invoice';


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

		$guaranteed_settings  = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_guaranteed_invoice' );
		$suppports_guaranteed = WC_Novalnet_Validation::is_guarantee_available( 'novalnet_guaranteed_invoice', $guaranteed_settings, true );
		if ( parent::is_available() && WC_Novalnet_Validation::is_payment_available( $this->settings, $this->id ) && ! $suppports_guaranteed ) {
			if ( wc_novalnet_check_isset( $guaranteed_settings, 'enabled', 'yes' ) && WC_Novalnet_Validation::is_force_payment_disabled( $guaranteed_settings ) ) {
				return false;
			}
			return true;
		} elseif ( ! empty( novalnet()->request ['change_payment_method'] ) && $suppports_guaranteed ) {
			return true;
		}
		return false;
	}

	/**
	 * Form gateway parameters to process in the Novalnet server.
	 *
	 * @param WC_Order $wc_order    The order object.
	 * @param int      $parameters  The parameters.
	 */
	public function generate_payment_parameters( $wc_order, &$parameters ) {

		// Add Invoice due date.
		if ( ! empty( $this->settings ['payment_duration'] ) ) {
			$parameters ['transaction']['due_date'] = wc_novalnet_format_due_date( $this->settings ['payment_duration'] );
		}
	}


	/**
	 * Payment configurations in shop backend.
	 */
	public function init_form_fields() {

		// Basic payment fields.
		WC_Novalnet_Configuration::basic( $this->form_fields, $this->id );

		// On-hold configurations.
		WC_Novalnet_Configuration::on_hold( $this->form_fields, $this->id );

		// Payment due date field.
		WC_Novalnet_Configuration::due_date( $this->form_fields, $this->id );

		// Callback order status configuration.
		WC_Novalnet_Configuration::callback_order_status( $this->form_fields );

		// Additional configuration.
		WC_Novalnet_Configuration::additional( $this->form_fields, $this->id );
	}
}
