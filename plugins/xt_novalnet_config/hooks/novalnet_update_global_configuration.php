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

if ($data['code'] == "xt_novalnet_config") {
    global $store_handler;

    $novalnet_stores = $store_handler->getStores();
    foreach ($novalnet_stores as $novalnet_store) {
        // Get callback notification URL
        $novalnet_store_id = $novalnet_store['id'];
        $this->url_data['novalnet_callback_url_' . $novalnet_store_id] = $data['conf_XT_NOVALNET_CALLBACK_URL_shop_' . $novalnet_store_id];
        $data['conf_XT_NOVALNET_CALLBACK_URL_shop_' . $novalnet_store_id] = '';

        // Validate Global configuration
        $product_activation_key = trim($data['conf_XT_NOVALNET_ACTIVATION_KEY_shop_' . $novalnet_store_id]);
        $vendor_id              = trim($data['conf_XT_NOVALNET_VENDOR_ID_shop_' . $novalnet_store_id]);
        $auth_code              = trim($data['conf_XT_NOVALNET_AUTH_CODE_shop_' . $novalnet_store_id]);
        $product_id             = trim($data['conf_XT_NOVALNET_PRODUCT_ID_shop_' . $novalnet_store_id]);
        $tariff_id              = $data['conf_XT_NOVALNET_TARIFF_ID_shop_' . $novalnet_store_id];
        $payment_access_key     = trim($data['conf_XT_NOVALNET_PAYMENT_ACCESS_KEY_shop_' . $novalnet_store_id]);
        $client_key         = trim($data['conf_XT_NOVALNET_PAYMENT_CLIENT_KEY_shop_' . $novalnet_store_id]);

        $this->url_data['novalnet_error_' . $novalnet_store_id] = '';

        // Validate PHP core functions
        if (!function_exists('base64_decode') || !function_exists('base64_encode') || !function_exists('hash') || !function_exists('curl_init') || !function_exists('openssl_encrypt')) {
            $this->url_data['novalnet_error_' . $novalnet_store_id] = XT_NOVALNET_PHP_CORE_ERROR_TEXT;

            // Validate global configuration value
        } else if (empty($product_activation_key) || !ctype_digit($vendor_id) || empty($auth_code) || !ctype_digit($product_id) || empty($tariff_id) || empty($payment_access_key) || empty($client_key)) {
            $this->url_data['novalnet_error_' . $novalnet_store_id] = XT_NOVALNET_MANDATORY_PARAMETER_MISSING_ERROR_TEXT;
        }
    }

    unset($novalnet_stores);
}
