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

include_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'xt_novalnet_config/classes/class.xt_novalnet_config.php';

if (strpos($_SESSION['selected_payment'], 'novalnet') !== false && is_data($_SESSION[$_SESSION['selected_payment'] . '_data'])) {

    $code = $_SESSION['selected_payment'];

    // Create instance
    $xt_novalnet_config = new xt_novalnet_config();

    // Form all common required parameters
    $parameters           = $xt_novalnet_config->form_payment_params($code);

    $payment_class = new $code();
    // Form additional parameters.
    $payment_class->additional_parameters($xt_novalnet_config, $parameters);
}
