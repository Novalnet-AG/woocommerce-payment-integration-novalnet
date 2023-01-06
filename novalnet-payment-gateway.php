<?php
/**
 * Plugin Name: Novalnet payment plugin - WooCommerce
 * Plugin URI:  https://www.novalnet.de/modul/woocommerce
 * Description: PCI compliant payment solution, covering a full scope of payment services and seamless integration for easy adaptability
 * Author:      Novalnet AG
 * Author URI:  https://www.novalnet.de
 * Version:     12.5.2
 * Requires at least: 5.0.0
 * Tested up to: 6.1.1
 * WC requires at least: 4.0.0
 * WC tested up to: 7.1.0
 * Text Domain: woocommerce-novalnet-gateway
 * Domain Path: /i18n/languages/
 * License:     GPLv2
 *
 * @package Novalnet payment plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Novalnet' ) ) :

	ob_start();

	// Define constants.
	if ( ! defined( 'NOVALNET_VERSION' ) ) {
		define( 'NOVALNET_VERSION', '12.5.2' );
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
 * @return Novalnet
 */
function novalnet() {

	// Initiate WC_Novalnet.
	return WC_Novalnet::instance();
}


/**
 * Initiate the novalnet function.
 */
add_action( 'plugins_loaded', 'novalnet' );
