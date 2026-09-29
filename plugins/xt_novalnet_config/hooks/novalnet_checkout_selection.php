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

global $xtPlugin;
if (in_array($page->page_action, array('shipping', 'payment', 'confirmation'))) {
    unset($_SESSION['last_order_id']);
}
if (!isset($xtPlugin->active_modules['xt_novalnet_config']) || !ctype_digit(XT_NOVALNET_VENDOR_ID) || !ctype_digit(XT_NOVALNET_PRODUCT_ID) || XT_NOVALNET_TARIFF_ID == 'none' || trim(XT_NOVALNET_AUTH_CODE) == '' || trim(XT_NOVALNET_PAYMENT_ACCESS_KEY) == '' || !function_exists('base64_decode') || !function_exists('base64_encode') || !function_exists('hash') || !function_exists('curl_init') || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
    foreach ($payment_data as $key => $value) {
        if (strpos($key, 'xt_novalnet') !== false) {
            unset($payment_data[$key]);
        } else if (strpos($payment_data[$key]['payment'], 'xt_novalnet') !== false) {
            unset($payment_data[$key]['payment']);
        }
    }
}

if (!isset($xtPlugin->active_modules['xt_novalnet_config']) || trim(XT_NOVALNET_PAYMENT_CLIENT_KEY) == '') {
    foreach ($payment_data as $key => $value) {
        if ($key == 'xt_novalnet_cc') {
            unset($payment_data[$key]);
        } else if ($payment_data[$key]['payment'] == 'xt_novalnet_cc') {
            unset($payment_data[$key]['payment']);
        }
    }
}

// Get the selected payment.
if (!empty($_SESSION['novalnet_selected_payment'])) {
    $selected_payment = $_SESSION['novalnet_selected_payment'];
} else if (!empty($_SESSION['selected_payment'])) {
    $selected_payment = $_SESSION['selected_payment'];
} else if (!empty($_SESSION['payment'])) {
    $selected_payment = $_SESSION['payment'];
}
