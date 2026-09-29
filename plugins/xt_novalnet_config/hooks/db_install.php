<?php

/**
 * Novalnet payment extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Novalnet End User License Agreement
 * that is bundled with this package in the file freeware_license_agreement.txt
 *
 * DISCLAIMER
 *
 * If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

/*
 * Creating Novalnet Tables
 */

global $db, $store_handler;

// Update Query.
$table = DB_PREFIX . '_novalnet_transaction_detail';
$result = $db->Execute("SHOW TABLES LIKE '" . $table . "'");

if ($result->RecordCount()) {
    $sql = $db->Execute("SHOW COLUMNS FROM $table LIKE 'booked'");
    if (empty($sql->fields)) {
        $db->Execute("ALTER TABLE " . DB_PREFIX . "_novalnet_transaction_detail
            ADD due_date date COMMENT 'Transaction Due Date in response',
            ADD booked enum('0','1') DEFAULT '1' COMMENT 'Transaction booked',
            ADD payment_params text COMMENT 'Payment params used for zero amount booking',
            ADD payment_ref enum('0','1') DEFAULT '0' COMMENT 'payment reference for the transaction',
            ADD payment_details text COMMENT 'Payment details',
            DROP COLUMN status,
            DROP COLUMN active,
            DROP COLUMN amount,
            DROP COLUMN additional_note,
            DROP COLUMN callback_status,
            DROP COLUMN account_holder,
            DROP COLUMN process_key
        ");
    }
}

// Create a novalnet_callback_history table
$db->Execute(
    "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "_novalnet_callback_history (
    id int(11) unsigned AUTO_INCREMENT COMMENT 'Auto Increment ID',
    `date` datetime COMMENT 'Callback DATE TIME',
    payment_type varchar(50) COMMENT 'Callback Payment Type',
    status int(5) COMMENT 'Callback Status',
    callback_tid bigint(20) unsigned COMMENT 'Callback Reference ID',
    org_tid bigint(20) unsigned COMMENT 'Original Transaction ID',
    amount int(11) COMMENT 'Amount in cents',
    order_no int(11) unsigned COMMENT 'Order ID from shop',
    PRIMARY KEY (id),
    KEY orders_no (order_no)
    ) COMMENT='Novalnet Callback History'"
);

// Create a novalnet_transaction_detail table
$db->Execute(
    "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "_novalnet_transaction_detail (
    id int(11) unsigned AUTO_INCREMENT COMMENT 'Auto Increment ID',
    tid bigint(20) unsigned COMMENT 'Novalnet Transaction Reference ID',
    vendor_id int(11) unsigned COMMENT 'Novalnet Vendor ID',
    authcode varchar(50) COMMENT 'Novalnet authorization code',
    tariff_id int(11) unsigned COMMENT 'Tariff ID',
    product_id int(11) unsigned COMMENT 'Product ID',
    payment_id int(8) unsigned COMMENT 'Payment ID',
    payment_type varchar(50) COMMENT 'Executed Payment type of this order',
    currency varchar(5) COMMENT 'Transaction currency',
    gateway_status int(11) COMMENT 'Novalnet transaction status',
    test_mode enum('0','1') DEFAULT '0' COMMENT 'Transaction test mode status',
    customer_id int(11) unsigned COMMENT 'Customer ID from shop',
    order_no int(11) unsigned COMMENT 'Order ID from shop',
    due_date date COMMENT 'Transaction Due Date in response',
    `date` datetime COMMENT 'Transaction Date for reference',
    org_total int(11) COMMENT 'original Transaction amount',
    refunded_amount int(11) DEFAULT '0' COMMENT 'Total refunded transaction amount',
    payment_ref enum('0','1') DEFAULT '1' COMMENT 'payment reference for the transaction',
    booked enum('0','1') DEFAULT '1' COMMENT 'Transaction booked',
    instalment_cycle_details text COMMENT 'Payment params used for zero amount booking',
    payment_params text COMMENT 'Payment params used for zero amount booking',
    payment_details text COMMENT 'Payment details',
    PRIMARY KEY (id),
    KEY tid (tid),
    KEY customer_id (customer_id),
    KEY order_no (order_no),
    KEY payment_ref (payment_ref),
    KEY booked (booked)
    ) COMMENT='Novalnet Transaction History'"
);

// Create a novalnet_affiliate_account_detail table
$db->Execute(
    "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "_novalnet_affiliate_account_detail (
    id mediumint(8) unsigned AUTO_INCREMENT,
    vendor_id int(11) unsigned,
    vendor_authcode varchar(50),
    product_id int(11) unsigned,
    product_url text,
    activation_date datetime,
    aff_id int(11) unsigned,
    aff_authcode varchar(40),
    aff_accesskey varchar(40),
    PRIMARY KEY (id),
    KEY vendor_id (vendor_id),
    KEY aff_id (aff_id)
    ) COMMENT='Novalnet merchant / affiliate account information'"
);

// Create a novalnet_affiliate_user_detail table
$db->Execute(
    "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "_novalnet_affiliate_user_detail(
    id int(11) unsigned AUTO_INCREMENT,
    aff_id int(11) unsigned,
    customer_id int(11) unsigned,
    aff_order_no int(11),
    PRIMARY KEY (id),
    KEY customer_id (customer_id)
    ) COMMENT='Novalnet merchant / affiliate order information'"
);

$plugin_id = $db->GetOne('SELECT plugin_id FROM ' . TABLE_PLUGIN_PRODUCTS . ' where code ="xt_novalnet_config"');

$stores = $store_handler->getStores();
foreach ($stores as $store) {
    $store_id = $store['id'];

    // Get store details
    $record = $db->Execute("SELECT shop_ssl, shop_ssl_domain FROM " . TABLE_MANDANT_CONFIG . " where shop_id =?", array($store_id));

    // Insert Dynamic Notfication URL
    $record2 = $db->Execute("SELECT id  FROM " . TABLE_PLUGIN_CONFIGURATION . " where shop_id =? AND plugin_id = ? AND config_key = ?", array($store_id, $plugin_id, 'XT_NOVALNET_CALLBACK_URL'));

    if ($record2->fields['id']) {
        $shop_url = $record->fields['shop_ssl'] != 0 ? $record->fields['shop_ssl_domain'] : '';
        $callback_url = 'https://' . $shop_url . '/index.php?page=callback&page_action=xt_novalnet_config';
        $db->Execute("update " . TABLE_PLUGIN_CONFIGURATION . " set config_value = ? where id = ? limit 1", array($callback_url, $record2->fields['id']));
    }
}

echo '<script>
    $.ajax({
        type: "POST",
        url: "adminHandler.php?load_section=plugin_installed&pg=overview&parentNode=node_plugin_installed&m_ids=' . $plugin_id . '&multiFlag_setStatus=true",
        dataType: "json",
        async: false,
        success: function(data) {
            return true;
        }
    });
</script>';
