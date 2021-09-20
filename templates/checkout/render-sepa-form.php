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

	woocommerce_form_field(
		$payment_type . '_iban',
		array(
			'required'          => true,
			'class'             => array(
				'form-row-wide',
			),
			'label'             => __( 'IBAN', 'woocommerce-novalnet-gateway' ),
			'id'                => $payment_type . '_iban',
			'placeholder'       => 'DE00 0000 0000 0000 0000 00',
			'custom_attributes' => array(
				'onkeypress'   => 'return NovalnetUtility.formatIban(event);',
				'onchange'     => 'return NovalnetUtility.formatIban(event);',
				'class'        => 'input-text',
				'autocomplete' => 'OFF',
				'style'        => 'text-transform:uppercase;',
			),
		)
	);
	?>
</div>
