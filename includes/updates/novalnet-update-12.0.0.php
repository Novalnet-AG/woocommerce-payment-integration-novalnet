<?php
/**
 * Update Novalnet to 12.0.3.
 *
 * @author   Novalnet
 * @category Admin
 * @package  woocommerce-novalnet-gateway/includes/updates/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
global $wpdb;

// Update Novalnet Transaction table.
novalnet()->db()->alter_table(
	array(
		'vendor_id',
		'product_id',
		'tariff_id',
		'payment_id',
		'payment_params',
		'payment_ref',
		'customer_email',
		'booked',
	)
);
novalnet()->db()->rename_column(
	array(
		'bank_details' => "`additional_info` TEXT DEFAULT NULL COMMENT 'Additional information used in gateways'",
	)
);


$global_configurations = array(
	'payment_logo',
	'debug_log',
	'enable_subs',
	'usr_subcl',
	'callback_test_mode',
);

// Get pubic key.
$public_key = get_option( 'novalnet_public_key' );

if ( ! empty( $public_key ) ) {
	$request = array(
		'merchant' => array(
			'signature' => $public_key,
		),
		'custom'   => array(
			'lang' => wc_novalnet_shop_language(),
		),
	);
	// Get Merchant details.
	$response = novalnet()->helper()->submit_request( $request, novalnet()->helper()->get_action_endpoint( 'merchant_details' ) );

	if ( ! empty( $response['result']['status'] ) && 'SUCCESS' === $response['result']['status'] ) {
		// Update client key.
		update_option( 'novalnet_client_key', $response['merchant']['client_key'] );
	} else {
		// Delete access key.
		delete_option( 'novalnet_key_password' );
	}
}

// Delete enable mail send option.
$send_mail  = WC_Novalnet_Configuration::get_global_settings( 'callback_mail_send_option' );
$send_mail2 = get_option( 'novalnet_enable_callback' );

if ( ! $send_mail && ! $send_mail2 ) {
	delete_option( 'novalnet_callback_emailtoaddr' );
}

foreach ( $global_configurations as $global_configuration ) {
	$value = WC_Novalnet_Configuration::get_global_settings( $global_configuration );

	if ( empty( $value ) ) {
		update_option( 'novalnet_' . $global_configuration, 'no' );
	} elseif ( '1' === $value ) {
		update_option( 'novalnet_' . $global_configuration, 'yes' );
	}
}
$saved_payment_settings = array_diff( array_keys( novalnet()->get_payment_types() ), array( 'novalnet_postfinance_card', 'novalnet_postfinance', 'novalnet_bancontact', 'novalnet_multibanco', 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa' ) );

foreach ( $saved_payment_settings as $payment ) {
	$payment_configuration = 'woocommerce_' . $payment . '_settings';
	$option_value          = get_option( $payment_configuration );
	if ( ! empty( $option_value ) ) {
		$language                                    = strtolower( wc_novalnet_shop_language() );
		$option_value[ 'instructions_' . $language ] = $option_value['payment_instruction'];
		wc_novalnet_update_value( 'test_mode', $option_value );
		if ( in_array( $payment, array( 'novalnet_invoice', 'novalnet_sepa', 'novalnet_cc', 'novalnet_paypal', 'novalnet_instalment_sepa', 'novalnet_instalment_invoice', 'novalnet_barzahlen' ), true ) ) {

			if ( 'novalnet_cc' === $payment ) {
				$option_value ['enable_iniline_form'] = 'yes';
				$option_value ['accepted_card_logo']  = array( 'visa', 'mastercard', 'maestro', 'unionpay', 'discover', 'diners', 'jcb', 'cb', 'cartasi' );
				wc_novalnet_update_value( 'cc_secure_enabled', $option_value );
			}

			if ( 'novalnet_paypal' !== $payment ) {
				$option_value ['tokenization'] = 'yes';
			} elseif ( wc_novalnet_check_isset( $option_value, 'payment_process', 'one_click_shop' ) ) {
				$option_value ['tokenization'] = 'yes';
			}

			if ( ! empty( $option_value[ $payment . '_payment_duration' ] ) ) {
				$option_value['payment_duration'] = $option_value[ $payment . '_payment_duration' ];
			}

			if ( wc_novalnet_check_isset( $option_value, 'limit_control', '1' ) ) {
				$option_value['payment_status'] = 'authorize';
				$option_value['limit']          = $option_value['limit'];
			} else {
				$option_value['payment_status'] = 'capture';
			}
			if ( 'novalnet_barzahlen' === $payment ) {
				$option_value['payment_duration'] = $option_value['barzahlen_payment_duration'];
			}
			if ( in_array( $payment, array( 'novalnet_sepa', 'novalnet_invoice' ), true ) && wc_novalnet_check_isset( $option_value, 'guarantee_payment', 'yes' ) ) {
				$set_values            = $option_value;
				$set_values['enabled'] = $option_value['guarantee_payment'];
				if ( wc_novalnet_check_isset( $option_value, 'limit_control', '1' ) ) {
					$set_values['payment_status'] = 'authorize';
					$set_values['limit']          = $option_value['limit'];
				} else {
					$set_values['payment_status'] = 'capture';
				}
				$set_values['allow_b2b'] = 'yes';
				if ( ! empty( $option_value['guarantee_payment_minimum_order_amount'] ) ) {
					$set_values['min_amount'] = $option_value['guarantee_payment_minimum_order_amount'];
				}

				if ( 'novalnet_sepa' === $payment ) {
					$set_values['payment_duration']   = $option_value['sepa_payment_duration'];
					$option_value['payment_duration'] = $option_value['sepa_payment_duration'];

					update_option( 'woocommerce_novalnet_guaranteed_sepa_settings', $set_values );
				} else {
					$set_values['order_success_status'] = $option_value['callback_status'];
					update_option( 'woocommerce_novalnet_guaranteed_invoice_settings', $set_values );
				}
			}
		}
		update_option( $payment_configuration, $option_value );
	}
}
