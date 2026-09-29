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

global $db, $store_handler;
$db->Execute("ALTER TABLE " . DB_PREFIX . "_novalnet_transaction_detail
    ADD due_date date COMMENT 'Transaction Due Date in response',
    ADD booked enum('0','1') DEFAULT '1' COMMENT 'Transaction booked',
    ADD payment_params text COMMENT 'Payment params used for zero amount booking',
    ADD payment_ref enum('0','1') DEFAULT '0' COMMENT 'payment reference for the transaction',
    ADD payment_details text COMMENT 'Payment details',
    ADD instalment_cycle_details text COMMENT 'Payment params used for zero amount booking',
    DROP COLUMN status,
    DROP COLUMN active,
    DROP COLUMN amount,
    DROP COLUMN additional_note,
    DROP COLUMN callback_status,
    DROP COLUMN account_holder,
    DROP COLUMN process_key
");

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
