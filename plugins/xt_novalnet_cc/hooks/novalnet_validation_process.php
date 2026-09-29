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

if (!empty($_REQUEST['selected_payment']) && $_REQUEST['selected_payment'] == 'xt_novalnet_cc') {

    // Assign posted values in SESSION
    $_SESSION['xt_novalnet_cc_data']       = $_REQUEST;
    $_SESSION['novalnet_selected_payment'] = $_REQUEST['selected_payment'];

    // Include required file
    include_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'xt_novalnet_config/classes/class.novalnet.php';

    // Validate Payment form values
    if(empty($_SESSION['xt_novalnet_cc_data']['xt_novalnet_cc_one_click_process'])) {

        // Throw server error message
        if(!empty($_SESSION['xt_novalnet_cc_data']['xt_novalnet_cc_server_error_message'])) {
            $error_message = $_SESSION['xt_novalnet_cc_data']['xt_novalnet_cc_server_error_message'];
            unset($_SESSION['xt_novalnet_cc_data']['xt_novalnet_cc_server_error_message']);
            Novalnet::checkout_redirect_process($error_message);
        }

        // Throw empty payment details error message
        if(empty($_SESSION['xt_novalnet_cc_data']['cc_pan_hash']) || empty($_SESSION['xt_novalnet_cc_data']['nn_unique_id'])) {
            Novalnet::checkout_redirect_process(XT_NOVALNET_CARD_DETAILS_INVALID_TEXT);
        }
    }

}

