<?php
/**
 * Credit Card Payment Form.
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

	// Enqueue script.
	wp_enqueue_script( 'woocommerce-novalnet-gateway-cc-script', novalnet()->plugin_url . '/assets/js/novalnet-cc.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
	wp_localize_script(
		'woocommerce-novalnet-gateway-cc-script',
		'wc_novalnet_cc_data',
		array(
			'standard_label' => $contents ['standard_label'],
			'standard_input' => $contents ['standard_input'],
			'standard_css'   => $contents ['standard_css'],
			'client_key'     => WC_Novalnet_Configuration::get_global_settings( 'client_key' ),
			'inline_form'    => (int) ( ! empty( $contents ['enable_iniline_form'] ) && 'yes' === $contents ['enable_iniline_form'] ),
			'lang'           => wc_novalnet_shop_language(),
			'test_mode'      => (int) ( ! empty( $contents ['test_mode'] ) && 'yes' === $contents ['test_mode'] ),
			'currency'       => $contents['currency'],
			'first_name'     => ( isset( $contents['customer']['first_name'] ) ) ? $contents['customer']['first_name'] : WC()->session->customer['first_name'],
			'last_name'      => ( isset( $contents['customer']['last_name'] ) ) ? $contents['customer']['last_name'] : WC()->session->customer['last_name'],
			'street'         => ( isset( $contents['customer']['billing']['street'] ) ) ? $contents['customer']['billing']['street'] : WC()->session->customer['address'],
			'city'           => ( isset( $contents['customer']['billing']['city'] ) ) ? $contents['customer']['billing']['city'] : WC()->session->customer['city'],
			'zip'            => ( isset( $contents['customer']['billing']['zip'] ) ) ? $contents['customer']['billing']['zip'] : WC()->session->customer['postcode'],
			'country_code'   => ( isset( $contents['customer']['billing']['country_code'] ) ) ? $contents['customer']['billing']['country_code'] : WC()->session->customer['country'],
			'email'          => ( isset( $contents['customer']['email'] ) ) ? $contents['customer']['email'] : WC()->session->customer['email'],
			'tel'            => ( isset( $contents['customer']['tel'] ) ) ? $contents['customer']['tel'] : WC()->session->customer['phone'],
		)
	);
	?>
	<div id="novalnet-psd2-notification"><?php esc_attr_e( 'More security with the new Payment Policy (PSD2) Info', 'woocommerce-novalnet-gateway' ); ?>
		<span class="novalnet-psd2-tooltip novalnet-tooltip" data-text="<?php esc_attr_e( 'European card issuing banks often requires a password or some other form of authentication (EU Payment Services Directive "PSD2") for secure payment. If the payment is not successful, you can try again. If you have any further questions, please contact your bank.', 'woocommerce-novalnet-gateway' ); ?>"><svg style="display:inline-block; fill: currentColor;" xmlns="http://www.w3.org/2000/svg" width="12px" viewBox="0 0 512 512"><path d="M256 8C119.043 8 8 119.083 8 256c0 136.997 111.043 248 248 248s248-111.003 248-248C504 119.083 392.957 8 256 8zm0 448c-110.532 0-200-89.431-200-200 0-110.495 89.472-200 200-200 110.491 0 200 89.471 200 200 0 110.53-89.431 200-200 200zm107.244-255.2c0 67.052-72.421 68.084-72.421 92.863V300c0 6.627-5.373 12-12 12h-45.647c-6.627 0-12-5.373-12-12v-8.659c0-35.745 27.1-50.034 47.579-61.516 17.561-9.845 28.324-16.541 28.324-29.579 0-17.246-21.999-28.693-39.784-28.693-23.189 0-33.894 10.977-48.942 29.969-4.057 5.12-11.46 6.071-16.666 2.124l-27.824-21.098c-5.107-3.872-6.251-11.066-2.644-16.363C184.846 131.491 214.94 112 261.794 112c49.071 0 101.45 38.304 101.45 88.8zM298 368c0 23.159-18.841 42-42 42s-42-18.841-42-42 18.841-42 42-42 42 18.841 42 42z"/></svg></span>
		</span>
	</div>
	<iframe frameBorder="0" width="100% ! important" scrolling="no" id = "novalnet_cc_iframe"></iframe>
	<input type="hidden" name="novalnet_cc_pan_hash" id="novalnet_cc_pan_hash"/>
	<input type="hidden" name="novalnet_cc_unique_id" id="novalnet_cc_unique_id"/>
	<input type="hidden" name="novalnet_checkout_amount" id="novalnet_checkout_amount" value="<?php echo esc_attr( $contents['amount'] ); ?>"/>
	<input type="hidden" name="novalnet_authenticated_amount" id="novalnet_authenticated_amount"/>
	<input type="hidden" name="novalnet_cc_force_redirect" id="novalnet_cc_force_redirect"/>
	<div class="clear"></div>
</div>
