<?php
/**
 * Direct Debit SEPA Payment Form.
 *
 * @author  Novalnet
 * @package woocommerce-novalnet-gateway/templates/checkout
 */

if ( ! defined( 'ABSPATH' ) ) :
	exit; // Exit if accessed directly.
endif;

?>
	<div class="wc-payment-form">
	<?php

	$first_name = ( isset( $contents['customer']['first_name'] ) ) ? $contents['customer']['first_name'] : ( isset( WC()->session->customer['first_name'] ) ? WC()->session->customer['first_name'] : '' );
	$last_name  = ( isset( $contents['customer']['last_name'] ) ) ? $contents['customer']['last_name'] : ( isset( WC()->session->customer['last_name'] ) ? WC()->session->customer['last_name'] : '' );

	$account_holder_field = array(
		'required'          => true,
		'class'             => array(
			'form-row-wide',
		),
		'label'             => __( 'Account Holder', 'woocommerce-novalnet-gateway' ),
		'id'                => $payment_type . '_holder',
		'custom_attributes' => array(
			'onkeypress'   => 'return wc_novalnet.is_valid_name(event);',
			'onkeyup'      => 'return wc_novalnet.is_valid_name(event);',
			'onchange'     => 'return wc_novalnet.is_valid_name(event);',
			'class'        => 'input-text',
			'autocomplete' => 'OFF',
		),
	);

	if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
		$account_holder_field['default'] = $first_name . ' ' . $last_name;
	} else {
		$account_holder_field['placeholder'] = __( 'Account Holder Name', 'woocommerce-novalnet-gateway' );
	}

	woocommerce_form_field(
		$payment_type . '_holder',
		$account_holder_field,
	);

	woocommerce_form_field(
		$payment_type . '_account',
		array(
			'required'          => true,
			'class'             => array(
				'form-row-wide',
			),
			'label'             => __( 'Account Number', 'woocommerce-novalnet-gateway' ),
			'id'                => $payment_type . '_account',
			'placeholder'       => '123456789',
			'custom_attributes' => array(
				'onkeypress'   => 'return wc_novalnet.is_number(event);',
				'onkeyup'      => 'return wc_novalnet.is_number(event);',
				'onchange'     => 'return wc_novalnet.is_number(event);',
				'class'        => 'input-text',
				'autocomplete' => 'OFF',
			),
		)
	);

	woocommerce_form_field(
		$payment_type . '_routing',
		array(
			'required'          => true,
			'class'             => array(
				'form-row-wide',
			),
			'label'             => __( 'Routing Number (ABA)', 'woocommerce-novalnet-gateway' ),
			'id'                => $payment_type . '_routing',
			'placeholder'       => '123456789',
			'custom_attributes' => array(
				'onkeypress'   => 'return wc_novalnet.is_number(event);',
				'onkeyup'      => 'return wc_novalnet.is_number(event);',
				'onchange'     => 'return wc_novalnet.is_number(event);',
				'class'        => 'input-text',
				'autocomplete' => 'OFF',
			),
		)
	);
	?>
</div>
