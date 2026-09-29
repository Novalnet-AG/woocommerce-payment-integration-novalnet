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
if (!empty($_REQUEST['selected_payment']) && strpos($_REQUEST['selected_payment'], 'novalnet') !== false && !in_array($_REQUEST['selected_payment'], array('xt_novalnet_cc', 'xt_novalnet_sepa', 'xt_novalnet_invoice'))) {
    $_SESSION[$_REQUEST['selected_payment'] . '_data'] = $_REQUEST;
}
