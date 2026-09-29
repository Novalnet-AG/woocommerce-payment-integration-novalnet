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
$_convertedstring = implode(',', array_keys($_SESSION['debug']));
if(!empty($_REQUEST['load_section']) && $_REQUEST['load_section'] == 'payment' && $_REQUEST['gridHandle'] == 'paymentgridForm' && strpos($_convertedstring, 'XT_NOVALNET_INVOICE') !== false && defined('XT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_TITLE') && defined('XT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_HINT')) {
    echo '<script type="text/javascript" integrity="sha384-pIQAGc2bpOAYMur0ouctU9Vty/iWLj6G74bOwGCAqmd3v58xzpnxo7u2PFafiQMj" src="../plugins/xt_novalnet_config/javascript/xt_novalnet_add_description.min.js">
    </script>
    <input type="hidden" id="xt_novalnet_invoice_guarantee_payment_title" value="'. XT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_TITLE .'">
    <input type="hidden" id="xt_novalnet_invoice_guarantee_payment_hint" value="'. XT_NOVALNET_INVOICE_PAYMENT_GUARANTEE_HINT .'">
    ';
}

if(!empty($_REQUEST['load_section']) && $_REQUEST['load_section'] == 'payment' && $_REQUEST['gridHandle'] == 'paymentgridForm' && strpos($_convertedstring, 'XT_NOVALNET_SEPA') !== false && defined('XT_NOVALNET_SEPA_PAYMENT_GUARANTEE_TITLE') && defined('XT_NOVALNET_SEPA_PAYMENT_GUARANTEE_HINT')) {
    echo '<script type="text/javascript" integrity="sha384-pIQAGc2bpOAYMur0ouctU9Vty/iWLj6G74bOwGCAqmd3v58xzpnxo7u2PFafiQMj" src="../plugins/xt_novalnet_config/javascript/xt_novalnet_add_description.min.js">
    </script>
    <input type="hidden" id="xt_novalnet_sepa_guarantee_payment_title" value="'. XT_NOVALNET_SEPA_PAYMENT_GUARANTEE_TITLE .'">
    <input type="hidden" id="xt_novalnet_sepa_guarantee_payment_hint" value="'. XT_NOVALNET_SEPA_PAYMENT_GUARANTEE_HINT .'">
    ';
}

if(!empty($_REQUEST['load_section']) && $_REQUEST['load_section'] == 'payment' && $_REQUEST['gridHandle'] == 'paymentgridForm'  && strpos($_convertedstring, 'XT_NOVALNET_PAYPAL') !== false ) {
    echo '<script type="text/javascript" integrity="sha384-pIQAGc2bpOAYMur0ouctU9Vty/iWLj6G74bOwGCAqmd3v58xzpnxo7u2PFafiQMj" src="../plugins/xt_novalnet_config/javascript/xt_novalnet_add_description.min.js">
    </script>
    <input type="hidden" id="xt_novalnet_paypal_reference_transaction_hint" value="'. XT_NOVALNET_PAYPAL_REFERENCE_TRANSACTION_HINT .'">
    ';
}