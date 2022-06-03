<?php
/**
 * Novalnet table creation for 12.0.4.
 *
 * @author   Novalnet
 * @category Admin
 * @package  woocommerce-novalnet-gateway/includes/updates/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Including upgrader file to perform table creation.
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

global $wpdb;

$collate = $wpdb->get_charset_collate();

// Creating transaction details table to maintain the transaction log.
novalnet()->db()->handle_query(
	dbDelta(
		"CREATE TABLE {$wpdb->prefix}novalnet_transaction_detail (
    id int(11) unsigned AUTO_INCREMENT COMMENT 'Auto increment ID',
    `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Transaction Date',
    order_no int(11) unsigned COMMENT 'Post ID for the order',
    payment_type varchar(64) COMMENT 'Payment id of the gateway',
    tid bigint(20) unsigned COMMENT 'Novalnet Transaction ID',
    subs_id int(11) unsigned COMMENT 'Novalnet subscription ID',
    amount int(11) unsigned COMMENT 'Transaction amount in minimum unit of currency',
    callback_amount int(11) unsigned DEFAULT '0' COMMENT 'Transaction paid amount in minimum unit of currency',
    refunded_amount int(11) unsigned DEFAULT '0' COMMENT 'Transaction refunded amount in minimum unit of currency',
    currency varchar(3) COMMENT 'Transaction currency in ISO-4217',
    gateway_status varchar(64) COMMENT 'Payment status',
    additional_info text COMMENT 'Additional information used in gateways',
    PRIMARY KEY  (id),
    KEY tid (tid),
    KEY payment_type (payment_type),
    KEY order_no (order_no)
    )$collate COMMENT='Novalnet Transaction History';"
	)
);

// Creating subscription table to maintain the subscription log.
novalnet()->db()->handle_query(
	dbDelta(
		"CREATE TABLE {$wpdb->prefix}novalnet_subscription_details (
    id int(11) unsigned AUTO_INCREMENT COMMENT 'Auto increment ID',
    order_no int(11) unsigned COMMENT 'Post ID for the order in shop',
    payment_type varchar(64) COMMENT 'Payment Type',
    recurring_payment_type varchar(64) COMMENT 'Recurring payment Type',
    recurring_amount int(11) unsigned COMMENT 'Amount in minimum unit of currency. E.g. enter 100 which is equal to 1.00',
    tid bigint(20) unsigned COMMENT 'Novalnet Transaction Reference ID',
    recurring_tid bigint(20) unsigned COMMENT 'Novalnet transaction reference ID',
    subs_id int(11) unsigned COMMENT 'Subscription ID in Novalnet',
    signup_date datetime COMMENT 'Subscription signup date',
    next_payment_date datetime COMMENT 'Subscription next cycle date',
    suspended_date datetime COMMENT 'Subscription suspended date',
    termination_reason varchar(255) COMMENT 'Subscription termination reason',
    termination_at datetime COMMENT 'Subscription terminated date',
    subscription_length int(11) unsigned COMMENT 'Length of the subscription',
    PRIMARY KEY  (id),
    KEY order_no (order_no),
    KEY tid (tid)
	)$collate COMMENT='Novalnet Subscription Payment Details'"
	)
);

// Creating webhook table to maintain the webhook log.
novalnet()->db()->handle_query(
	dbDelta(
		"CREATE TABLE {$wpdb->prefix}novalnet_webhook_history (
    id int(11) unsigned AUTO_INCREMENT COMMENT 'Auto increment ID',
     `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Webhook execution date and time',
    event_type varchar(64) COMMENT 'Webhook event type',
    payment_type varchar(64) COMMENT 'Webhook payment type',
    gateway_status varchar(64) COMMENT 'Gateway status',
    event_tid bigint(20) unsigned COMMENT 'Event transaction ID',
    parent_tid bigint(20) unsigned COMMENT 'Event parent transaction  ID',
    amount int(11) unsigned COMMENT 'Amount in minimum unit of currency',
    order_no int(11) unsigned COMMENT 'Post ID for the order in shop',
    PRIMARY KEY  (id)
    )$collate COMMENT='Novalnet webhook history';"
	)
);
