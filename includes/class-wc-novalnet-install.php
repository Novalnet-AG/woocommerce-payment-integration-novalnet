<?php
/**
 * Novalnet Plugin installation process.
 *
 * This file is used for creating tables while installing the plugins.
 *
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Install Class.
 *
 * @class   WC_Novalnet_Install
 */
class WC_Novalnet_Install {

	/**
	 * The Novalnet module previous versions with DB updates.
	 *
	 * @var array released Novalnet version array
	 */
	private static $db_updates = array(
		'11.0.0' => 'updates/novalnet-update-11.0.0.php',
		'12.0.0' => 'updates/novalnet-update-12.0.0.php',
	);

	/**
	 * Install actions such as creating/ updating the tables while activate link is clicked.
	 *
	 * @since 12.0.0
	 */
	public static function install() {

		// Initialize the DB update.
		self::update();
	}

	/**
	 * Update actions such as updating the tables
	 * when reloading the page after update.
	 *
	 * @since 12.0.0
	 * @since 12.8.1 Remove the hold stock duration.
	 * Handled the pending order auto cancel prevention in hook.
	 */
	public static function update() {

		$current_db_version = get_option( 'novalnet_db_version' );

		if ( version_compare( $current_db_version, NOVALNET_VERSION, '!=' ) ) {

			$available_gateways = get_option( 'woocommerce_gateway_order' );
			$novalnet_gateway   = novalnet()->get_payment_types();
			if ( ! empty( $available_gateways ) ) {
				// Sort Novalnet payment gateways.
				foreach ( $available_gateways as $key => $value ) {
					if ( in_array( $value, $novalnet_gateway, true ) ) {
						unset( $available_gateways [ $key ] );
					}
				}
			}
			$available_gateways = array_merge( $novalnet_gateway, (array) $available_gateways );
			update_option( 'woocommerce_gateway_order', $available_gateways );

			if ( version_compare( $current_db_version, '12.0.0', '<' ) ) {
				// Initialize the DB update.
				foreach ( self::$db_updates as $version => $updater ) {

					// Updating existing Novalnet table.
					if ( ! empty( $current_db_version ) && version_compare( $current_db_version, $version, '!=' ) && version_compare( $current_db_version, $version, '<' ) ) {
						include_once $updater;

						// Remove the previous Novalnet values.
						if ( version_compare( $current_db_version, '11.1.0', '<' ) ) {
							self::uninstall();
						}
					}
				}
			}

			// Table creation file.
			include_once 'updates/create-table.php';

			// Update Novalnet version in $wp->options table.
			update_option( 'novalnet_db_version', NOVALNET_VERSION );

			wc_novalnet_safe_redirect(
				wc_novalnet_generate_admin_link(
					array(
						'page' => 'wc-settings',
						'tab'  => 'novalnet-settings',
					)
				)
			);

		}
	}

	/**
	 * Deleting actions when plugin deactivated.
	 *
	 * @since 12.0.0
	 */
	public static function uninstall() {
		// Delete the existing Novalnet values from $wpdb->options table.
		novalnet()->db()->delete_plugin_option();
	}
}
