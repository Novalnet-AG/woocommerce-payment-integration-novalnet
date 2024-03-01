<?php
/**
 * Plugin Name: Novalnet payment plugin - WooCommerce
 * Plugin URI:  https://www.novalnet.de/modul/woocommerce
 * Description: PCI compliant payment solution, covering a full scope of payment services and seamless integration for easy adaptability
 * Author:      Novalnet AG
 * Author URI:  https://www.novalnet.de
 * Version:     12.6.4
 * Requires at least: 5.0.0
 * Tested up to: 6.4.3
 * WC requires at least: 4.0.0
 * WC tested up to: 8.6.1
 * Text Domain: woocommerce-novalnet-gateway
 * Domain Path: /i18n/languages/
 * License:     GPLv2
 *
 * @package Novalnet payment plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Novalnet' ) ) :

	ob_start();

	// Define constants.
	if ( ! defined( 'NOVALNET_VERSION' ) ) {
		define( 'NOVALNET_VERSION', '12.6.4' );
	}
	if ( ! defined( 'NN_PLUGIN_FILE' ) ) {
		define( 'NN_PLUGIN_FILE', __FILE__ );
	}

	// Including main class.
	include_once 'class-wc-novalnet.php';
endif;


/**
 * Returns the main instance of novalnet.
 *
 * @since 12.0.0
 *
 * @return WC_Novalnet
 */
function novalnet() {

	// Initiate WC_Novalnet.
	return WC_Novalnet::instance();
}


/**
 * Initiate the novalnet function.
 */
add_action( 'plugins_loaded', 'novalnet' );

if ( ! function_exists( 'woocommerce_gateway_novalnet_woocommerce_block_support' ) ) {

	/**
	 * Register Novalnet Payment for WooCommerce Blocks
	 *
	 * @since 12.6.2
	 */
	function woocommerce_gateway_novalnet_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			// Including available Novalnet payment gateway files.
			foreach ( glob( dirname( __FILE__ ) . '/includes/wc-blocks/payments/*.php' ) as $filename ) {
				include_once $filename;
			}
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					foreach ( array_keys( novalnet()->get_payment_types() ) as $payment_id ) {
						$class_name = ucwords( $payment_id, '_' );
						if ( class_exists( $class_name ) ) {
							$payment_method_registry->register(
								new $class_name()
							);
						}
					}
				},
			);
		}
	}

	// Hook in Blocks integration. This action is called in a callback on plugins loaded.
	add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_novalnet_woocommerce_block_support' );
}
