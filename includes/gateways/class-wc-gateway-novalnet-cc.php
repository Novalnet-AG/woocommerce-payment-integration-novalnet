<?php
/**
 * Novalnet Credit Card Payment.
 *
 * This gateway is used for real time processing of Credit card data of customers.
 *
 * Copyright (c) Novalnet`
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class   WC_Gateway_Novalnet_Cc
 * @extends Abstract_Novalnet_Payment_Gateways
 * @package woocommerce-novalnet-gateway/includes/gateways/
 * @author  Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Gateway_Novalnet_Cc Class.
 */
class WC_Gateway_Novalnet_Cc extends WC_Novalnet_Abstract_Payment_Gateways {


	/**
	 * Id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'novalnet_cc';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Assign payment details.
		$this->assign_basic_payment_details();

		// Handle redirection payment response.
		add_action( 'woocommerce_api_response_novalnet_cc', array( $this, 'check_novalnet_payment_response' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'load_novalnet_cc_scripts' ) );

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
		$icons = '';
		if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'payment_logo' ) && ! empty( $this->settings ['accepted_card_logo'] ) ) {
			foreach ( $this->settings ['accepted_card_logo'] as $logo ) {
				$icon_src = novalnet()->plugin_url . '/assets/images/novalnet_cc_' . $logo . '.png';
				$icons   .= "<img src='$icon_src' alt='" . $this->title . "' title='" . $this->title . "' />";
			}
		}
		// Built payment default logo.
		return apply_filters( 'woocommerce_gateway_icon', $icons, $this->id );
	}

	/**
	 * Displays the payment form, payment description on checkout.
	 */
	public function payment_fields() {
		global $wp;
		// Show TESTMODE notification.
		$this->test_mode_notification();

		// Display Tokenization.
		$tokenization = (bool) ( $this->supports( 'tokenization' ) && ! ( ! empty( novalnet()->request ['change_payment_method'] ) || is_wc_endpoint_url( 'order-pay' ) ) );

		if ( $tokenization ) {
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
			'render-cc-form.php',
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
		if ( $tokenization ) {
			$this->save_payment_method_checkbox();
		}

		// Show description.
		$this->show_description();

	}

	/**
	 * Validate payment fields on the frontend.
	 */
	public function validate_fields() {

		if ( ! empty( WC()->session ) ) {
			// Unset other payment session.
			$this->unset_other_payment_session();
			// Assigning post values in session.
			$session = novalnet()->helper()->set_post_value_session(
				$this->id,
				array(
					'novalnet_cc_pan_hash',
					'novalnet_cc_unique_id',
					'novalnet_authenticated_amount',
					'novalnet_checkout_amount',
					'novalnet_cc_force_redirect',
					'wc-novalnet_cc-new-payment-method',
					'wc-novalnet_cc-payment-token',
				)
			);
			if ( ( empty( $session ['wc-novalnet_cc-payment-token'] ) || wc_novalnet_check_isset( $session, 'wc-novalnet_cc-payment-token', 'new' ) ) && ! WC_Novalnet_Validation::validate_payment_input_field(
				$session,
				array(
					'novalnet_cc_pan_hash',
					'novalnet_cc_unique_id',
				)
			) ) {

				WC()->session->__unset( $this->id );

				// Display message.
				$this->display_info( __( 'Your card details are invalid', 'woocommerce-novalnet-gateway' ) );

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
				'novalnet_cc_pan_hash',
				'novalnet_cc_unique_id',
				'novalnet_cc_force_redirect',
				'wc-novalnet_cc-new-payment-method',
				'wc-novalnet_cc-payment-token',
			)
		);
		$this->set_payment_token( $parameters );

		if ( empty( $parameters ['transaction'] ['payment_data'] ['token'] ) && ! empty( $session ['novalnet_cc_pan_hash'] ) && ! empty( $session ['novalnet_cc_unique_id'] ) ) {

			// Assign generated pan hash and unique_id.
			$parameters['transaction'] ['payment_data'] = array(
				'pan_hash'  => $session ['novalnet_cc_pan_hash'],
				'unique_id' => $session ['novalnet_cc_unique_id'],
			);
		}

		// Add redirection related parameters.
		if ( wc_novalnet_check_isset( $session, 'novalnet_cc_force_redirect', '1' ) ) {
			if ( (int) ( 'yes' === $this->settings ['enforce_3d'] ) ) {
				// Assign enforce 3d value.
				$parameters['transaction']['enforce_3d'] = '1';
			}

			// Assign redirect payment params.
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

			// Redirect to checkout / success page.
			wc_novalnet_safe_redirect( $status ['redirect'] );
		}
	}

	/**
	 * Payment configurations in shop backend
	 */
	public function init_form_fields() {

		// Basic payment fields.
		WC_Novalnet_Configuration::basic( $this->form_fields, $this->id );

		// On-hold configurations.
		WC_Novalnet_Configuration::on_hold( $this->form_fields, $this->id );

		// Tokenization configuration.
		WC_Novalnet_Configuration::tokenization( $this->form_fields, $this->id );

		// Enable inline form.
		$this->form_fields ['enable_iniline_form'] = array(
			'title'       => __( 'Enable inline form', 'woocommerce-novalnet-gateway' ),
			'type'        => 'checkbox',
			'default'     => 'yes',
			'label'       => ' ',
			'description' => __( 'Inline form: The following fields will be shown in the checkout in two lines: card holder & credit card number / expiry date / CVC', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
		);

		// Enable inline form.
		$this->form_fields ['enforce_3d'] = array(
			'title'       => __( 'Enforce 3D secure payment outside EU', 'woocommerce-novalnet-gateway' ),
			'type'        => 'checkbox',
			'default'     => 'no',
			'label'       => ' ',
			'description' => __( 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
		);

		// Enable inline form.
		$this->form_fields ['accepted_card_logo'] = array(
			'title'   => __( 'Display Credit/Debit card logos', 'woocommerce-novalnet-gateway' ),
			'type'    => 'multiselect',
			'class'   => 'wc-enhanced-select',
			'default' => 'yes',
			'options' => array(
				'visa'       => __( 'Visa', 'woocommerce-novalnet-gateway' ),
				'mastercard' => __( 'Mastercard', 'woocommerce-novalnet-gateway' ),
				'maestro'    => __( 'Maestro', 'woocommerce-novalnet-gateway' ),
				'amex'       => __( 'American Express', 'woocommerce-novalnet-gateway' ),
				'unionpay'   => __( 'Union Pay', 'woocommerce-novalnet-gateway' ),
				'discover'   => __( 'Discover', 'woocommerce-novalnet-gateway' ),
				'diners'     => __( 'Diners Club', 'woocommerce-novalnet-gateway' ),
				'jcb'        => __( 'JCB', 'woocommerce-novalnet-gateway' ),
				'cb'         => __( 'Carte Bleue', 'woocommerce-novalnet-gateway' ),
				'cartasi'    => __( 'Cartasi', 'woocommerce-novalnet-gateway' ),
			),
			'default' => array(
				'visa',
				'mastercard',
				'maestro',
				'unionpay',
				'discover',
				'diners',
				'jcb',
				'cb',
				'cartasi',
			),
		);

		// Additional configuration.
		WC_Novalnet_Configuration::additional( $this->form_fields, $this->id );

		$this->form_fields ['standard_style_configuration_heading'] = array(
			'title'       => __( 'Custom CSS settings', 'woocommerce-novalnet-gateway' ),
			'type'        => 'title',
			'description' => sprintf( '<strong>%s</strong>', __( 'CSS settings for iframe form', 'woocommerce-novalnet-gateway' ) ),
		);

		$this->form_fields ['standard_label'] = array(
			'title' => __( 'Label', 'woocommerce-novalnet-gateway' ),
			'type'  => 'textarea',
		);

		$this->form_fields ['standard_input'] = array(
			'title' => __( 'Input', 'woocommerce-novalnet-gateway' ),
			'type'  => 'textarea',
		);

		$this->form_fields ['standard_css'] = array(
			'title'   => __( 'CSS Text', 'woocommerce-novalnet-gateway' ),
			'type'    => 'textarea',
			'default' => '.input-group{box-sizing: border-box;width: 100%;margin: 0;outline: 0;line-height: 1;padding:0.7em 0;}.label-group{font-size:.92em;}html{font-family:"Source Sans Pro", Helvetica, sans-serif}.form-group{position: relative;box-sizing: border-box;width: 100%;margin: 1em 0;font-size: .92em;border-radius: 2px;line-height: 1.5;color: #515151;}',
		);

	}

	/**
	 * Enqueue novalnet credit card script in checkout.
	 *
	 * @since 12.5.6
	 */
	public function load_novalnet_cc_scripts() {
		if ( ! did_action( 'before_woocommerce_init' ) ) {
			return;
		}
		if ( is_checkout() && $this->is_available() ) {
			wp_enqueue_script( 'woocommerce-novalnet-gateway-cc-script', novalnet()->plugin_url . '/assets/js/novalnet-cc.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		}
	}
}
