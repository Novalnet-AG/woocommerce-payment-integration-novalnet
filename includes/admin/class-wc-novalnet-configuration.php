<?php
/**
 * Novalnet Configuration Class
 *
 * @author   Novalnet
 * @category Configuration
 * @package  woocommerce-novalnet-gateway/includes/admin/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * WC_Novalnet_Configuration
 */
class WC_Novalnet_Configuration extends WC_Settings_API {

	/**
	 * The single instance of the class.
	 *
	 * @var   Novalnet_Helper The single instance of the class.
	 * @since 12.0.0
	 */
	protected static $instance = null;

	/**
	 * Main Novalnet_Helper Instance.
	 *
	 * Ensures only one instance of Novalnet_Helper is loaded or can be loaded.
	 *
	 * @since  12.0.0
	 * @static
	 * @return Novalnet_Api_Callback Main instance.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * To view Novalnet config page
	 *
	 * @since 12.0.0
	 */
	public static function novalnet_settings_page() {

		woocommerce_admin_fields( self::novalnet_settings_fields() );
	}


	/**
	 * Update the Novalnet configuration
	 *
	 * @since 12.0.0
	 */
	public static function update_novalnet_settings() {

		// Update Global configuraion fields.
		woocommerce_update_options( self::novalnet_settings_fields() );
	}

	/**
	 * Guarantee payment notification.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $form_fields The form fields.
	 * @param string $payment_type The Payment type.
	 */
	public static function guarantee_conditions_notification( &$form_fields, $payment_type ) {

		// Assign default minimum amount.
		$min_amount = '9.99';
		if ( novalnet()->get_supports( 'instalment', $payment_type ) ) {
			$min_amount = '19.98';
		}

		// Get country list.
		$countries         = WC()->countries->countries;
		$allowed_countries = array( 'DE', 'AT', 'CH' );
		foreach ( $allowed_countries as $country_code ) {
			$b2c_countries [] = $countries[ $country_code ];
		}
		if ( novalnet()->get_supports( 'instalment', $payment_type ) ) {
			// Payment requirements.
			$form_fields ['guarantee_payment_title'] = array(
				'title'       => ' ',
				'type'        => 'title',
				'description' => sprintf(
					'<div class="updated inline notice"><p><strong>%1$s</strong><br/>
                            <ul>
                                <li>%2$s</li>
                                <li>%3$s</li>
                                <li>%4$s</li>
                                <li>%5$s</li>
                                <li>%6$s</li>
                                <li>%7$s</li>
                                <li>%8$s</li>
                            </ul></p></div>',
					__( 'Basic requirements for payment guarantee:', 'woocommerce-novalnet-gateway' ),
					/* translators: %s: B2c countries */
					sprintf( __( 'Allowed B2C countries: %s', 'woocommerce-novalnet-gateway' ), implode( ', ', $b2c_countries ) ),
					sprintf( __( 'Allowed B2B countries: European Union', 'woocommerce-novalnet-gateway' ) ),
					/* translators: %s: currency */
					sprintf( __( 'Allowed currency: %s', 'woocommerce-novalnet-gateway' ), get_woocommerce_currency_symbol( 'EUR' ) ),
					/* translators: %s: amount */
					sprintf( __( 'Minimum order amount: %s or more', 'woocommerce-novalnet-gateway' ), wp_strip_all_tags( wc_price( $min_amount, array( 'currency' => 'EUR' ) ) ) ),
					__( 'Please note that the instalment cycle amount has to be a minimum of 9.99 EUR and the instalment cycles which do not meet this criteria will not be displayed in the instalment plan', 'woocommerce-novalnet-gateway' ),
					__( 'Age limit: 18 years or more', 'woocommerce-novalnet-gateway' ),
					__( 'The billing address must be the same as the shipping address', 'woocommerce-novalnet-gateway' )
				),
			);
		} else {
			// Payment requirements.
			$form_fields ['guarantee_payment_title'] = array(
				'title'       => ' ',
				'type'        => 'title',
				'description' => sprintf(
					'<div class="updated inline notice"><p><strong>%1$s</strong><br/>
                            <ul>
                                <li>%2$s</li>
                                <li>%3$s</li>
                                <li>%4$s</li>
                                <li>%5$s</li>
                                <li>%6$s</li>
                                <li>%7$s</li>
                            </ul></p></div>',
					__( 'Basic requirements for payment guarantee:', 'woocommerce-novalnet-gateway' ),
					/* translators: %s: B2c countries */
					sprintf( __( 'Allowed B2C countries: %s', 'woocommerce-novalnet-gateway' ), implode( ', ', $b2c_countries ) ),
					sprintf( __( 'Allowed B2B countries: European Union', 'woocommerce-novalnet-gateway' ) ),
					/* translators: %s: currency */
					sprintf( __( 'Allowed currency: %s', 'woocommerce-novalnet-gateway' ), get_woocommerce_currency_symbol( 'EUR' ) ),
					/* translators: %s: amount */
					sprintf( __( 'Minimum order amount: %s or more', 'woocommerce-novalnet-gateway' ), wp_strip_all_tags( wc_price( $min_amount, array( 'currency' => 'EUR' ) ) ) ),
					__( 'Age limit: 18 years or more', 'woocommerce-novalnet-gateway' ),
					__( 'The billing address must be the same as the shipping address', 'woocommerce-novalnet-gateway' )
				),
			);
		}
	}

	/**
	 * Guarantee payment configuration fields.
	 *
	 * @since 12.0.0
	 *
	 * @param array $form_fields  The form fields.
	 * @param bool  $payment_type The payment type.
	 * @param bool  $is_guarantee The type of the configuration.
	 */
	public static function guarantee( &$form_fields, $payment_type = '', $is_guarantee = false ) {

		if ( $is_guarantee ) {

			if ( 'novalnet_guaranteed_sepa' === $payment_type ) {
				$note = __( 'Make sure the Direct Debit SEPA payment is enabled to use this option.', 'woocommerce-novalnet-gateway' );
			} else {
				$note = __( 'Make sure the Invoice payment is enabled to use this option.', 'woocommerce-novalnet-gateway' );
			}

			// Non-Guarantee payment force field.
			$form_fields['force_normal_payment'] = array(
				'title'       => __( 'Force Non-Guarantee payment', 'woocommerce-novalnet-gateway' ),
				'type'        => 'checkbox',
				'label'       => ' ',
				'default'     => 'no',
				'description' => $note,
				'desc_tip'    => __( 'Even if payment guarantee is enabled, payments will still be processed as non-guarantee payments if the payment guarantee requirements are not met.', 'woocommerce-novalnet-gateway' ),
			);
		}

		// Allow B2B customers field.
		$form_fields ['allow_b2b'] = array(
			'title'       => __( 'Allow B2B Customers', 'woocommerce-novalnet-gateway' ),
			'type'        => 'checkbox',
			'default'     => 'yes',
			'description' => __( 'Allow B2B customers to place order', 'woocommerce-novalnet-gateway' ),
			'label'       => ' ',
			'desc_tip'    => true,
		);
	}

	/**
	 * Instalment payment configuration fields.
	 *
	 * @since 12.0.0
	 *
	 * @param array $form_fields The form fields.
	 */
	public static function instalment( &$form_fields ) {

		$form_fields['instalment_plan_on_product_detail_page'] = array(
			'title'       => __( 'Display Instalment Plan on Product Detail Page', 'woocommerce-novalnet-gateway' ),
			'type'        => 'checkbox',
			'description' => __( 'Control whether or not an instalment plan should be displayed in the product page', 'woocommerce-novalnet-gateway' ),
			'default'     => 'yes',
			'label'       => ' ',
			'desc_tip'    => true,
		);
		$instalment_cycles                                     = array();
		foreach ( array(
			'2',
			'3',
			'4',
			'5',
			'6',
			'7',
			'8',
			'9',
			'10',
			'11',
			'12',
			'15',
			'18',
			'21',
			'24',
		) as $cycle ) {
			/* translators: %d: cycle */
			$instalment_cycles [ $cycle ] = sprintf( __( '%d cycles', 'woocommerce-novalnet-gateway' ), $cycle );
		}
		$form_fields ['instalment_total_period'] = array(
			'title'       => __( 'Instalment cycles', 'woocommerce-novalnet-gateway' ),
			'type'        => 'multiselect',
			'class'       => 'chosen_select',
			'options'     => $instalment_cycles,
			'default'     => array(
				'2',
				'3',
				'4',
				'5',
				'6',
				'7',
				'8',
				'9',
				'10',
				'11',
				'12',
			),
			'description' => __( 'Select the instalment cycles that can be availed in the instalment plan', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
		);
	}

	/**
	 * On-hold payment configurations fields.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $form_fields The form fields.
	 * @param string $payment_type The Payment type.
	 */
	public static function on_hold( &$form_fields, $payment_type ) {

		// On-hold configuration fields.
		$form_fields ['payment_status'] = array(
			'title'             => __( 'Payment Action', 'woocommerce-novalnet-gateway' ),
			'class'             => 'chosen_select',
			'type'              => 'select',
			'desc_tip'          => __( 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'woocommerce-novalnet-gateway' ),
			'description'       => '<span id="novalnet_paypal_notice"></span>',
			'options'           => array(
				'capture'   => __( 'Capture', 'woocommerce-novalnet-gateway' ),
				'authorize' => __( 'Authorize', 'woocommerce-novalnet-gateway' ),
			),
			'custom_attributes' => array(
				'onchange' => 'return wc_novalnet_admin.toggle_onhold_limit(this, "' . $payment_type . '")',
			),
		);

		$form_fields ['limit'] = array(
			'title'             => __( 'Minimum transaction amount for authorization', 'woocommerce-novalnet-gateway' ),
			'type'              => 'number',
			'description'       => __( '(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'woocommerce-novalnet-gateway' ),
			'desc_tip'          => __( 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'woocommerce-novalnet-gateway' ),
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
				'min'          => '1',
			),
		);

		if ( in_array( $payment_type, novalnet()->get_supports( 'zero_amount_booking' ), true ) && WC_Novalnet_Validation::check_zero_amount_tariff_types() ) {
			$form_fields ['payment_status']['options']['zero_amount_booking'] = __( 'Authorize with zero amount', 'woocommerce-novalnet-gateway' );
		}

		wc_enqueue_js(
			"jQuery( '#woocommerce_" . $payment_type . "_payment_status' ).change();"
		);
	}

	/**
	 * Adds setting fields for Novalnet global configuration
	 *
	 * @since 12.0.0
	 */
	public static function novalnet_settings_fields() {

		foreach ( novalnet()->get_supports( 'subscription' ) as $subscription_supported_payment ) {
			$payment_text = self::get_payment_text( $subscription_supported_payment );
			$subscription_payments [ $subscription_supported_payment ] = wc_novalnet_get_payment_text( self::get_payment_settings( $subscription_supported_payment ), $payment_text, wc_novalnet_shop_language(), $subscription_supported_payment, 'admin_title' );
		}

		$subs_config_log_data = array(
			'enable_subs'      => get_option( 'novalnet_enable_subs' ),
			'enable_shop_subs' => get_option( 'novalnet_enable_shop_subs' ),
			'subs_tariff'      => get_option( 'novalnet_subs_tariff_id' ),
			'usr_subcl'        => get_option( 'novalnet_usr_subcl' ),
		);

		$json_subs_config = wc_novalnet_serialize_data( $subs_config_log_data );

		novalnet()->helper()->debug( "SUBSCRIPTION_CONFIGURATION: $json_subs_config", '', true );

		// Global configuration fields.
		return apply_filters(
			'woocommerce_novalnet_settings',
			array(
				array(
					'title' => __( 'Novalnet API Configuration', 'woocommerce-novalnet-gateway' ),
					'id'    => 'novalnet_additional_info',
					'type'  => 'title',
					/* translators: %1$s: anchor tag starts %2$s: anchor tag end */
					'desc'  => '<div class="error inline notice" id="novalnet_webhook_url_message"> <p>' . sprintf( __( 'You must add the following webhook endpoint <code>%s</code> to your <a href="https://admin.novalnet.de" target="_blank">Novalnet Admin portal</a>. This will allow you to receive notifications about the transaction status.', 'woocommerce-novalnet-gateway' ), WC()->api_request_url( 'novalnet_callback' ) ) . '</p></div>',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'novalnet_additional_info',
				),
				array(
					'id'   => 'novalnet_global_settings',
					'type' => 'title',

					/* translators: %1$s: anchor tag starts %2$s: anchor tag end */
					'desc' => '<div class="notice notice-warning" id="novalnet_test_mode_message"><p>' . __( 'Your project is in test mode', 'woocommerce-novalnet-gateway' ) . '</p></div><div class="updated inline notice"><p>' . sprintf( __( 'Please read the Installation Guide before you start and login to the %1$s Novalnet Admin Portal%2$s using your merchant account. To get a merchant account, mail to sales@novalnet.de or call +49 (089) 923068320.', 'woocommerce-novalnet-gateway' ), '<a href="https://admin.novalnet.de" target="_new">', '</a>' ) . '</p></div>',
				),
				array(
					'title'             => __( 'Product activation key', 'woocommerce-novalnet-gateway' ),
					'desc'              => '<br/>' . __( 'Get your Product activation key from the <a href="https://admin.novalnet.de" target="_blank">Novalnet Admin Portal</a> Projects > Choose your project > API credentials > API Signature (Product activation key) ', 'woocommerce-novalnet-gateway' ),
					'id'                => 'novalnet_public_key',
					'desc_tip'          => __( 'Your product activation key is a unique token for merchant authentication and payment processing. ', 'woocommerce-novalnet-gateway' ),
					'type'              => 'text',
					'css'               => 'min-width: 40%;',
					'custom_attributes' => array(
						'autocomplete' => 'OFF',
						'required'     => 'true',
					),
				),
				array(
					'title'             => __( 'Payment access key ', 'woocommerce-novalnet-gateway' ),
					'desc'              => '<br/>' . __( 'Get your Payment access key from the <a href="https://admin.novalnet.de" target="_blank">Novalnet Admin Portal</a> Projects > Choose your project > API credentials > Payment access key', 'woocommerce-novalnet-gateway' ),
					'id'                => 'novalnet_key_password',
					'desc_tip'          => __( 'Your secret key used to encrypt the data to avoid user manipulation and fraud.', 'woocommerce-novalnet-gateway' ),
					'type'              => 'text',
					'custom_attributes' => array(
						'autocomplete' => 'OFF',
						'required'     => 'true',
					),
				),
				array(
					'title'             => __( 'Select Tariff ID', 'woocommerce-novalnet-gateway' ),
					'desc'              => __( 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project', 'woocommerce-novalnet-gateway' ),
					'id'                => 'novalnet_tariff_id',
					'desc_tip'          => __( 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project', 'woocommerce-novalnet-gateway' ),
					'type'              => 'text',
					'desc_tip'          => true,
					'custom_attributes' => array(
						'readonly' => 'readonly',
						'required' => 'true',
					),
				),
				array(
					'id'   => 'novalnet_tariff_type',
					'type' => 'novalnet_hidden',
				),
				array(
					'id'   => 'novalnet_client_key',
					'type' => 'novalnet_hidden',
				),
				array(
					'title'    => __( 'Display payment logo', 'woocommerce-novalnet-gateway' ),
					'desc'     => __( 'The payment method logo(s) will be displayed on the checkout page', 'woocommerce-novalnet-gateway' ),
					'id'       => 'novalnet_payment_logo',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'desc_tip' => true,
				),
				array(
					'title'    => __( 'Debug log', 'woocommerce-novalnet-gateway' ),
					'title'    => __( 'Debug log', 'woocommerce-novalnet-gateway' ),
					'type'     => 'checkbox',
					'id'       => 'novalnet_debug_log',
					'default'  => 'no',

					/* translators: %s: File name */
					'desc'     => sprintf( __( 'Find the Novalnet payment events log in this path:  <code>%s.txt</code>', 'woocommerce-novalnet-gateway' ), wc_get_log_file_path( 'woocommerce-novalnet-gateway' ) ),
					'desc_tip' => true,
				),
				array(
					'type' => 'sectionend',
					'id'   => 'novalnet_global_settings',
				),
				array(
					'title' => __( 'Notification / Webhook URL Setup', 'woocommerce-novalnet-gateway' ),
					'id'    => 'novalnet_vendor_script',
					'type'  => 'title',
				),
				array(
					'title'    => __( 'Notification / Webhook URL', 'woocommerce-novalnet-gateway' ),
					'id'       => 'novalnet_webhook_url',
					'type'     => 'url',
					'css'      => 'min-width: 40%;',
					'default'  => WC()->api_request_url( 'novalnet_callback' ),
					'desc'     => '<button class="button" id="webhook_configure">' . __( 'Configure', 'woocommerce-novalnet-gateway' ) . '</button>',
					'desc_tip' => __( 'You must configure the webhook endpoint in your <a href="https://admin.novalnet.de" target="_blank">Novalnet Admin portal</a>. This will allow you to receive notifications about the transaction', 'woocommerce-novalnet-gateway' ),
				),
				array(
					'title'    => __( 'Allow manual testing of the Notification / Webhook URL', 'woocommerce-novalnet-gateway' ),
					'id'       => 'novalnet_callback_test_mode',
					'type'     => 'checkbox',
					'default'  => 'no',
					'desc_tip' => __( 'Enable this to test the Novalnet Notification / Webhook URL manually. Disable this before setting your shop live to block unauthorized calls from external parties', 'woocommerce-novalnet-gateway' ),
					'label'    => ' ',
				),
				array(
					'title'             => __( 'Send e-mail to ', 'woocommerce-novalnet-gateway' ),
					'desc'              => __( ' Notification / Webhook URL execution messages will be sent to this e-mail', 'woocommerce-novalnet-gateway' ),
					'id'                => 'novalnet_callback_emailtoaddr',
					'type'              => 'text',
					'desc_tip'          => true,
					'custom_attributes' => array(
						'autocomplete' => 'OFF',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'novalnet_vendor_script',
				),
				array(
					'title' => __( 'Subscription Management', 'woocommerce-novalnet-gateway' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'novalnet_subs_management',
				),
				array(
					'title'    => __( 'Enable subscription', 'woocommerce-novalnet-gateway' ),
					'id'       => 'novalnet_enable_subs',
					'type'     => 'checkbox',
					'default'  => WC_Novalnet_Validation::is_subscription_plugin_available() ? 'yes' : 'no',
					'desc_tip' => true,
				),
				array(
					'title'   => __( 'Subscription payments', 'woocommerce-novalnet-gateway' ),
					'id'      => 'novalnet_subs_payments',
					'type'    => 'multiselect',
					'class'   => 'wc-enhanced-select',
					'options' => $subscription_payments,
					'default' => array_keys( $subscription_payments ),

				),
				array(
					'title'             => __( 'Select Subscription Tariff ID', 'woocommerce-novalnet-gateway' ),
					'id'                => 'novalnet_subs_tariff_id',
					'desc'              => __( 'Select the preferred Novalnet subscription tariff ID available for your project. For more information, please refer the Installation Guide', 'woocommerce-novalnet-gateway' ),
					'desc_tip'          => true,
					'type'              => 'text',
					'custom_attributes' => array(
						'autocomplete' => 'OFF',
						'readonly'     => 'readonly',
					),
				),
				array(
					'title'   => __( 'Display Subscription Cancellation Option for End User', 'woocommerce-novalnet-gateway' ),
					'id'      => 'novalnet_usr_subcl',
					'type'    => 'checkbox',
					'default' => 'no',
				),
				array(
					'title'    => __( 'Enable Shop Based Subscriptions', 'woocommerce-novalnet-gateway' ),
					'id'       => 'novalnet_enable_shop_subs',
					'type'     => 'checkbox',
					'default'  => 'no',
					'desc'     => __( 'By enabling this option, you agree to switch the subscription management from Novalnet to shop based subscription for all upcoming transactions.', 'woocommerce-novalnet-gateway' ),
					'desc_tip' => true,
				),
				array(
					'type' => 'sectionend',
					'id'   => 'novalnet_subs_management',
				),
			)
		);
	}

	/**
	 * Return Tokenization configuration field.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $form_fields  The form fields.
	 * @param string $payment_type The Payment type.
	 */
	public static function tokenization( &$form_fields, $payment_type ) {
		$additional_info = '';
		$label           = '';
		$default         = 'yes';

		$label = __( 'Payment details stored during the checkout process can be used for future payments', 'woocommerce-novalnet-gateway' );

		$form_fields ['tokenization'] = array(
			'title'       => __( 'One-click shopping', 'woocommerce-novalnet-gateway' ),
			'type'        => 'checkbox',
			'default'     => $default,
			'desc_tip'    => $label,
			'label'       => ' ',
			'description' => $additional_info,
		);
	}

	/**
	 * Return additional configuration field.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $form_fields  The form fields.
	 * @param string $payment_type The Payment type.
	 */
	public static function additional( &$form_fields, $payment_type ) {
		$min_amount = '';
		if ( novalnet()->get_supports( 'guarantee', $payment_type ) ) {
			$min_amount = '999';
		} elseif ( novalnet()->get_supports( 'instalment', $payment_type ) ) {
			$min_amount = '1998';
		}

		$form_fields ['min_amount'] = array(
			'title'             => __( 'Minimum order amount', 'woocommerce-novalnet-gateway' ),
			'type'              => 'number',
			'desc_tip'          => __( 'Minimum order amount to display the selected payment method (s) at during checkout', 'woocommerce-novalnet-gateway' ),
			'default'           => $min_amount,
			'description'       => __(
				'(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)',
				'woocommerce-novalnet-gateway'
			),
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
				'min'          => $min_amount,
			),
		);
	}

	/**
	 * Return Callback order status configuration field.
	 *
	 * @since 12.0.0
	 *
	 * @param array $form_fields The form fields.
	 */
	public static function callback_order_status( &$form_fields ) {
		$form_fields ['callback_status'] = array(
			'title'       => __( 'Callback / webhook order status', 'woocommerce-novalnet-gateway' ),
			'class'       => 'chosen_select',
			'description' => __( 'Status to be used when callback script is executed for payment received by Novalnet', 'woocommerce-novalnet-gateway' ),
			'type'        => 'select',
			'default'     => 'wc-completed',
			'options'     => wc_get_order_statuses(),
			'desc_tip'    => true,
		);
	}

	/**
	 * Return payment duration configuration configuration field.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $form_fields The form fields.
	 * @param string $payment_type The Payment type.
	 */
	public static function due_date( &$form_fields, $payment_type ) {

		$title       = __( 'Payment due date (in days)', 'woocommerce-novalnet-gateway' );
		$description = __( 'Number of days after which the payment is debited (must be between 2 and 14 days).', 'woocommerce-novalnet-gateway' );
		$min         = '2';
		$max         = '14';
		if ( 'novalnet_invoice' === $payment_type ) {
			$description = __( 'Number of days given to the buyer to transfer the amount to Novalnet (must be greater than 7 days). If this field is left blank, 14 days will be set as due date by default.', 'woocommerce-novalnet-gateway' );
			$min         = '7';
			$max         = '';
		} elseif ( 'novalnet_prepayment' === $payment_type ) {
			$description = __( 'Number of days given to the buyer to transfer the amount to Novalnet (must be between 7 and 28 days). If this field is left blank, 14 days will be set as due date by default.', 'woocommerce-novalnet-gateway' );
			$min         = '7';
			$max         = '28';
		} elseif ( 'novalnet_barzahlen' === $payment_type ) {
			$title       = __( 'Slip expiry date (in days)', 'woocommerce-novalnet-gateway' );
			$description = __( 'Number of days given to the buyer to pay at a store. If this field is left blank, 14 days will be set as slip expiry date by default.', 'woocommerce-novalnet-gateway' );
			$min         = '1';
			$max         = '';
		}

		$form_fields ['payment_duration'] = array(
			'title'             => $title,
			'type'              => 'number',
			'description'       => $description,
			'desc_tip'          => true,
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
				'min'          => $min,
				'max'          => $max,
			),
		);
	}

	/**
	 * Return basic payment configurations fields.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $form_fields  The form fields.
	 * @param string $payment_type The Payment type.
	 *
	 * @return array
	 */
	public static function basic( &$form_fields, $payment_type ) {

		include_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$translations = wp_get_available_translations();

		$languages = array(
			'EN' => __( 'English' ),
		);
		foreach ( get_available_languages() as $language ) {
			$languages[ wc_novalnet_shop_language( $language ) ] = $translations [ $language ]['native_name'];
		}

		$payment_text           = self::get_payment_text( $payment_type );
		$payment_title_lang     = $payment_text [ 'title_' . strtolower( wc_novalnet_shop_language() ) ] ?? $payment_text ['title_en'];
		$form_fields['enabled'] = array(
			'title' => __( 'Enable payment method', 'woocommerce-novalnet-gateway' ),
			'type'  => 'checkbox',
			'label' => ' ',
		);

		$form_fields['test_mode'] = array(
			'title'       => __( 'Enable test mode', 'woocommerce-novalnet-gateway' ),
			'type'        => 'checkbox',
			'label'       => ' ',
			'default'     => 'no',
			'description' => __( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
		);

		if ( count( $languages ) > 1 ) {
			$form_fields['lang'] = array(
				'class'             => 'chosen_select',
				'type'              => 'select',
				'options'           => $languages,
				'title'             => __( 'Payment name & description language <span class="dashicons dashicons-translation" aria-hidden="true"></span>', 'woocommerce-novalnet-gateway' ),
				'custom_attributes' => array(
					'onchange' => 'wc_novalnet_admin.toggle_payment_name(this, "' . $payment_type . '")',
				),
				'default'           => wc_novalnet_shop_language(),
			);
		}
		foreach ( array_keys( $languages ) as $language ) {

			$language   = strtolower( wc_novalnet_shop_language( $language ) );
			$title_lang = $payment_text ['title_en'];
			if ( isset( $payment_text [ 'title_' . $language ] ) ) {
				$title_lang = $payment_text [ 'title_' . $language ];
			}
			$form_fields[ 'title_' . $language ] = array(
				'title'             => __( 'Title', 'woocommerce-novalnet-gateway' ),
				'type'              => 'text',
				'description'       => '<span id="novalnet_language_' . $payment_type . '"></span>',
				'default'           => $title_lang,
				'custom_attributes' => array(
					'autocomplete' => 'OFF',
				),
			);
			$desc_lang                           = $payment_text ['description_en'];
			if ( isset( $payment_text [ 'description_' . $language ] ) ) {
				$desc_lang = $payment_text [ 'description_' . $language ];
			}
			$form_fields[ 'description_' . $language ]   = array(
				'title'       => __( 'Description', 'woocommerce-novalnet-gateway' ),
				'type'        => 'textarea',
				'description' => '',
				'default'     => $desc_lang,
			);
			$form_fields [ 'instructions_' . $language ] = array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
				'desc_tip'    => true,
			);
		}

		$default_order_status = 'wc-completed';
		if ( in_array( $payment_type, array( 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_barzahlen', 'novalnet_multibanco' ), true ) ) {
			$default_order_status = 'wc-processing';
		}
		$form_fields ['order_success_status'] = array(
			'title'       => __( 'Completed order status', 'woocommerce-novalnet-gateway' ),
			'class'       => 'chosen_select',
			'type'        => 'select',
			'default'     => $default_order_status,
			'options'     => wc_get_order_statuses(),
			'description' => __( 'Status to be used for successful orders', 'woocommerce-novalnet-gateway' ),
			'desc_tip'    => true,
		);
		if ( ! in_array( $payment_type, array( 'novalnet_applepay', 'novalnet_googlepay' ), true ) ) {
			$form_fields ['enable_for_methods'] = array(
				'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				/* translators: %1$s: payment_en_title*/
				'description'       => sprintf( __( 'If %1$s is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce-novalnet-gateway' ), $payment_title_lang ),
				'options'           => load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __( 'Select shipping methods', 'woocommerce' ),
				),
			);
		}

		$form_fields ['enable_for_virtual'] = array(
			'title'   => __( 'Accept for virtual orders', 'woocommerce' ),
			/* translators: %1$s: payment_en_title*/
			'label'   => sprintf( __( 'Accept %1$s if the order is virtual', 'woocommerce-novalnet-gateway' ), $payment_title_lang ),
			'type'    => 'checkbox',
			'default' => 'yes',
		);

		wc_enqueue_js(
			"
            jQuery( document ).ready(function () {
                jQuery( '#woocommerce_" . $payment_type . "_lang' ).change();
            });
        "
		);
		return $form_fields;
	}

	/**
	 * Fetch the Novalnet global configuration values from the database.
	 *
	 * @since 12.0.0
	 *
	 * @param string $input The specific configuration name.
	 *
	 * @return mixed
	 */
	public static function get_global_settings( $input ) {
		if ( ! empty( $input ) ) {
			return get_option( 'novalnet_' . $input );
		}
		return '';
	}

	/**
	 * Main Novalnet_Helper Instance.
	 *
	 * Get wallet settings.
	 *
	 * @since  12.4.0
	 *
	 * @param string $form_fields The form fields.
	 * @param string $payment_type The payment type.
	 *
	 * @static
	 * @return void.
	 */
	public static function wallet_settings( &$form_fields, $payment_type ) {

		// Basic payment fields.
		$form_fields['seller_name'] = array(
			'title'             => __( 'Business name', 'woocommerce-novalnet-gateway' ),
			'type'              => 'text',
			'desc_tip'          => __( 'This is the text that appears as PAY SELLER NAME in the Apple Pay payment sheet.', 'woocommerce-novalnet-gateway' ),
			'custom_attributes' => array(
				'autocomplete' => 'OFF',
			),
			'description'       => __( 'The business name is rendered in the Google Pay payment sheet, and this text will appear as PAY "BUSINESS NAME" so that the customer knows where he is paying to.', 'woocommerce-novalnet-gateway' ),
			'desc_tip'          => true,
			'default'           => get_bloginfo( 'name' ),
		);

		if ( 'novalnet_googlepay' === $payment_type ) {
			// Enable 3d settings.
			$form_fields ['enforce_3d'] = array(
				'title'       => __( 'Enforce 3D secure payment outside EU', 'woocommerce-novalnet-gateway' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'label'       => ' ',
				'description' => __( 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.', 'woocommerce-novalnet-gateway' ),
				'desc_tip'    => true,
			);

			$form_fields['partner_id'] = array(
				'title'             => __( 'Google Merchant ID', 'woocommerce-novalnet-gateway' ),
				'type'              => 'text',
				'description'       => __( 'Please note that Googles merchant identifier is required for processing the payment method in the live environment. Googles merchant identifier is issued after registration with the <a href="https://pay.google.com/business/console/">Google Pay and Wallet Console</a>. See <a href="https://developers.google.com/pay/api/web/guides/test-and-deploy/request-prod-access">Request production access</a> for more information about the approval process and obtaining a Google merchant identifier. The registration also involves submitting the integration with sufficient screen-shots, so collect this information by enabling the payment method in test mode. To suppress the validation of this field while saving the configuration, use this test identifier BCR2DN4XXXTN7FSI for testing and submission of your integration to Google.', 'woocommerce-novalnet-gateway' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'OFF',
					'required'     => 'true',
				),
			);
		}
	}

	/**
	 * Get payment configuration.
	 *
	 * @since 12.0.0
	 * @param string $payment_type The payment type value.
	 *
	 * @return array
	 */
	public static function get_payment_settings( $payment_type ) {
		return get_option( 'woocommerce_' . $payment_type . '_settings' );
	}

	/**
	 * Returns payment name and description.
	 *
	 * @since 12.0.0
	 * @param string $payment_type The payment type value.
	 *
	 * @return array
	 */
	public static function get_payment_text( $payment_type ) {

		$output       = '';
		$payment_text = array(
			'novalnet_googlepay'            => array(
				'title_en'       => 'Google Pay',
				'title_de'       => 'Google Pay',
				'description_en' => 'Amount will be booked from your card after successful authentication',
				'description_de' => 'Ihre Karte wird nach Bestellabschluss sofort belastet',
				'admin_desc'     => __( 'Funds are withdrawn from the buyer\'s account using credit/debit card details', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_applepay'             => array(
				'title_en'       => 'Apple Pay',
				'title_de'       => 'Apple Pay',
				'description_en' => 'Amount will be booked from your card after successful authentication',
				'description_de' => 'Ihre Karte wird nach Bestellabschluss sofort belastet',
				'admin_desc'     => __( 'Funds are withdrawn from the buyer\'s account using credit/debit card details', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_cc'                   => array(
				'title_en'       => 'Credit/Debit Cards',
				'title_de'       => 'Kredit- / Debitkarte',
				'description_en' => 'Your credit/debit card will be charged immediately after the order is completed',
				'description_de' => 'Ihre Karte wird nach Bestellabschluss sofort belastet',
				'admin_desc'     => __( 'Funds are withdrawn from the buyer\'s account using credit/debit card details', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_sepa'                 => array(
				'title_en'       => 'Direct Debit SEPA',
				'title_de'       => 'SEPA-Lastschrift',
				'description_en' => 'The amount will be debited from your account by Novalnet',
				'description_de' => 'Der Betrag wird durch Novalnet von Ihrem Konto abgebucht',
				'admin_desc'     => __( 'Europe-wide Direct Debit system that allows you to collect Euro currencies from buyers in the 34 SEPA countries and associated regions', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_paypal'               => array(
				'title_en'       => 'PayPal',
				'title_de'       => 'PayPal',
				'description_en' => ' You will be redirected to PayPal. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu PayPal weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist',
				'admin_desc'     => __( 'Electronic wallet that alows buyers to pay using any payment modes they have added to their PayPal account', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_ideal'                => array(
				'title_en'       => 'iDEAL',
				'title_de'       => 'iDEAL',
				'description_en' => 'You will be redirected to iDEAL. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu iDEAL weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist.',
				'admin_desc'     => __( 'Dutch payment method that allow your buyers to make instant payments online through his own bank', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_alipay'               => array(
				'title_en'       => 'Alipay',
				'title_de'       => 'Alipay',
				'description_en' => 'You will be redirected to Alipay. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu Alipay weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist.',
				'admin_desc'     => __( 'Dutch payment method that allow your buyers to make instant payments online through his own bank', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_wechatpay'            => array(
				'title_en'       => 'WeChat Pay',
				'title_de'       => 'WeChat Pay',
				'description_en' => 'You will be redirected to WeChat Pay. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu WeChat Pay weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist.',
				'admin_desc'     => __( 'Dutch payment method that allow your buyers to make instant payments online through his own bank', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_trustly'              => array(
				'title_en'       => 'Trustly',
				'title_de'       => 'Trustly',
				'description_en' => 'You will be redirected to Trustly. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu Trustly weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist.',
				'admin_desc'     => __( 'Dutch payment method that allow your buyers to make instant payments online through his own bank', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_postfinance'          => array(
				'title_en'       => 'PostFinance E-Finance',
				'title_de'       => 'PostFinance',
				'description_en' => 'You will be redirected to PostFinance. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu PostFinance weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist ',
				'admin_desc'     => __( 'Swiss based online account system where buyers are redirected to login and pay using their PostFinance Card', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_postfinance_card'     => array(
				'title_en'       => 'PostFinance Card',
				'title_de'       => 'PostFinance Card',
				'description_en' => 'You will be redirected to PostFinance. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu PostFinance weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist',
				'admin_desc'     => __( 'Swiss based online card payment method which allows buyers to pay using PostFinance Card', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_giropay'              => array(
				'title_en'       => 'giropay',
				'title_de'       => 'giropay',
				'description_en' => 'You will be redirected to giropay. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu giropay weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist',
				'admin_desc'     => __( 'German based online payment method where funds are instantly transferred from buyer\'s account to your account', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_eps'                  => array(
				'title_en'       => 'eps',
				'title_de'       => 'Eps',
				'description_en' => 'You will be redirected to eps. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu eps weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist ',
				'admin_desc'     => __( 'Austria based online banking method that allows your buyers to pay using any form of electronic payments', 'woocommerce-novalnet-gateway' ),

			),
			'novalnet_instantbank'          => array(
				'title_en'       => 'Sofort',
				'title_de'       => 'Sofortüberweisung',
				'description_en' => 'You will be redirected to Sofort. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu Sofortüberweisung weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist',
				'admin_desc'     => __( 'Pan European payment method allows buyers to pay through their own internet banking system', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_online_bank_transfer' => array(
				'title_en'       => 'Online bank transfer',
				'title_de'       => 'Onlineüberweisung',
				'description_en' => 'You will be redirected to banking page. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden auf die Banking-Seite weitergeleitet. Bitte schließen oder aktualisieren Sie den Browser nicht, bis die Zahlung abgeschlossen ist.',
				'admin_desc'     => __( 'Pan European payment method allows buyers to pay through their own internet banking system', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_przelewy24'           => array(
				'title_en'       => 'Przelewy24',
				'title_de'       => 'Przelewy24',
				'description_en' => 'You will be redirected to Przelewy24. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu Przelewy24 weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist',
				'admin_desc'     => __( 'Poland based payment method which allows buyers pay using bank transfers or any other methods', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_prepayment'           => array(
				'title_en'   => 'Prepayment',
				'title_de'   => 'Vorkasse',
				'admin_desc' => __( 'Payment is debited after order confirmation and, the goods are then delivered', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_invoice'              => array(
				'title_en'   => 'Invoice',
				'title_de'   => 'Kauf auf Rechnung',
				'admin_desc' => __( 'A payable credit note with the order details', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_barzahlen'            => array(
				'title_en'       => 'Barzahlen/viacash',
				'title_de'       => 'Barzahlen/viacash',
				'description_en' => 'On successful checkout, you will receive a payment slip/SMS to pay your online purchase at one of our retail partners (e.g. supermarket).',
				'description_de' => 'Nach erfolgreichem Bestellabschluss erhalten Sie einen Zahlschein bzw. eine SMS. Damit können Sie Ihre Online-Bestellung bei einem unserer Partner im Einzelhandel (z.B. Drogerie, Supermarkt etc.) bezahlen',
				'admin_desc'     => __( 'Transaction is completed through cash payments using cash slips in countries like Germany and Austria', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_multibanco'           => array(
				'title_en'       => 'Multibanco',
				'title_de'       => 'Multibanco',
				'description_en' => 'On successful checkout, you will receive a payment reference. Using this payment reference, you can either pay in the Multibanco ATM or through your online bank account ',
				'description_de' => 'Nach erfolgreichem Bestellabschluss erhalten Sie eine Zahlungsreferenz. Damit können Sie entweder an einem Multibanco-Geldautomaten oder im Onlinebanking bezahlen.',
				'admin_desc'     => __( 'Voucher based payment method where buyer pays in ATM or at retail outlets using a reference ID', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_bancontact'           => array(
				'title_en'       => 'Bancontact',
				'title_de'       => 'Bancontact',
				'description_en' => 'You will be redirected to Bancontact. Please don’t close or refresh the browser until the payment is completed',
				'description_de' => 'Sie werden zu Bancontact weitergeleitet. Um eine erfolgreiche Zahlung zu gewährleisten, darf die Seite nicht geschlossen oder neu geladen werden, bis die Bezahlung abgeschlossen ist',
				'admin_desc'     => __( 'Belgium based online payment method where buyers are redirected to Bancontact site/app for payment authorization', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_guaranteed_invoice'   => array(
				'title_en'       => 'Invoice',
				'admin_title_en' => 'Novalnet Invoice with payment guarantee',
				'title_de'       => 'Kauf auf Rechnung',
				'admin_title_de' => 'Novalnet Rechnung mit Zahlungsgarantie',
				'admin_desc'     => __( 'Guaranteed payment made to you either from the buyer or from payment guarantee for the purchase made through invoice', 'woocommerce-novalnet-gateway' ),

			),
			'novalnet_guaranteed_sepa'      => array(
				'title_en'       => 'Direct Debit SEPA',
				'admin_title_en' => 'Novalnet Direct Debit SEPA with payment guarantee',
				'title_de'       => 'SEPA-Lastschrift',
				'admin_title_de' => 'Novalnet SEPA-Lastschrift mit Zahlungsgarantie',
				'description_en' => 'The amount will be debited from your account by Novalnet',
				'description_de' => 'Der Betrag wird durch Novalnet von Ihrem Konto abgebucht',
				'admin_desc'     => __( 'Guaranteed payment made to you either from the buyer or from payment guarantee for the purchase made through SEPA', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_instalment_invoice'   => array(
				'title_en'   => 'Instalment by Invoice',
				'title_de'   => 'Ratenzahlung per Rechnung',
				'admin_desc' => __( 'Buyer pays his bill as instalment payments through a single or recurring invoice(s)', 'woocommerce-novalnet-gateway' ),
			),
			'novalnet_instalment_sepa'      => array(
				'title_en'       => 'Instalment by Direct Debit SEPA',
				'title_de'       => 'Ratenzahlung per SEPA-Lastschrift',
				'description_en' => 'The amount will be debited from your account by Novalnet',
				'description_de' => 'Der Betrag wird durch Novalnet von Ihrem Konto abgebucht',
				'admin_desc'     => __( 'Through this payment method you can collect amount periodically like subscription or instalment payments through SEPA', 'woocommerce-novalnet-gateway' ),
			),
		);

		$payment_ids = array_keys( $payment_text );
		foreach ( $payment_ids as $payment ) {
			if ( empty( $payment_text[ $payment ]['admin_title_en'] ) ) {
				$payment_text[ $payment ]['admin_title_en'] = 'Novalnet ' . $payment_text[ $payment ]['title_en'];
			}
			if ( empty( $payment_text[ $payment ]['admin_title_de'] ) ) {
				$payment_text[ $payment ]['admin_title_de'] = 'Novalnet ' . $payment_text[ $payment ]['title_de'];
			}
			if ( empty( $payment_text[ $payment ]['admin_description_de'] ) ) {
				$payment_text[ $payment ]['admin_description_de'] = 'Novalnet ' . $payment_text[ $payment ]['title_de'];
			}

			if ( empty( $payment_text[ $payment ]['description_en'] ) && empty( $payment_text[ $payment ]['description_de'] ) ) {
				if ( in_array( $payment_type, array( 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_guaranteed_invoice', 'novalnet_instalment_invoice' ), true ) ) {
					$payment_text[ $payment ]['description_en'] = 'You will receive an e-mail with the Novalnet account details to complete the payment.';
					$payment_text[ $payment ]['description_de'] = 'Sie erhalten eine E-Mail mit den Bankdaten von Novalnet, um die Zahlung abzuschließen.';
				} else {
					$payment_text[ $payment ]['description_en'] = 'You will be redirected to the Novalnet secure payment page to complete the payment.';
					$payment_text[ $payment ]['description_de'] = 'Sie werden auf die sichere Zahlungsseite von Novalnet weitergeleitet, um die Zahlung abzuschließen.';
				}
			}
		}

		if ( isset( $payment_text [ $payment_type ] ) ) {
			$output = $payment_text [ $payment_type ];
		}
		return $output;
	}
}
