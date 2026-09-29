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

// Set payment code as uppercase
$payment_name = strtoupper($data['payment_code']);

if (!empty($data['payment_code']) && strpos($data['payment_code'], 'novalnet') !== false) {
    global $store_handler, $db;

    // Get stores ID
    $novalnet_stores = $store_handler->getStores();

    // Throw error message
    foreach ($novalnet_stores as $novalnet_store) {
        $novalnet_store_id = $novalnet_store['id'];
        if (isset($novalnet_error['shop_' . $novalnet_store_id . '_error'])) {
            $obj->success = false;
            $obj = new stdClass();
            $obj->failed = true;
            $obj->error_message = $novalnet_error['shop_' . $novalnet_store_id . '_error'];
            unset($novalnet_error, $payment_name);
            return $obj;
        }
    }
    unset($novalnet_error, $payment_name, $novalnet_store_id, $novalnet_stores);
}

if (!empty($data['code']) && $data['code'] == 'xt_novalnet_config') {
    global $store_handler, $db;

    // Get stores ID
    $novalnet_stores = $store_handler->getStores();

    foreach ($novalnet_stores as $novalnet_store) {
        $novalnet_store_id = $novalnet_store['id'];

        // Update callback URL
        $novalnet_url_value = $this->url_data['novalnet_callback_url_' . $novalnet_store_id];
        $db->Execute('UPDATE ' . TABLE_PLUGIN_CONFIGURATION . " SET config_value='" . $novalnet_url_value . "' WHERE config_key='XT_NOVALNET_CALLBACK_URL' AND shop_id='" . $novalnet_store_id . "'");
        unset($this->url_data['novalnet_callback_url_' . $novalnet_store_id]);

        // Show error message
        $novalnet_error = $this->url_data['novalnet_error_' . $novalnet_store_id];
        unset($this->url_data['novalnet_error_' . $novalnet_store_id]);
        if (!empty($novalnet_error)) {
            $obj = new stdClass();
            $obj->failed = true;
            $obj->error_message = $novalnet_error;
            unset($novalnet_error);
            return $obj;
        }
    }
    unset($novalnet_stores, $novalnet_store);
}
