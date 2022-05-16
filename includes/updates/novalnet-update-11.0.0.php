<?php
/**
 * Update Novalnet to 11.3.0
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
		'status',
		'auth_code',
		'active',
		'process_key',
	)
);
