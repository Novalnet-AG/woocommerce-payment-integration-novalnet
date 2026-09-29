<?php
/*
 ###################################################
 #             Novalnet Payment file
 # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 # @author    Novalnet AG
 # @copyright 2019 Novalnet 
 # @license   https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 # @link      https://www.novalnet.de
 ###################################################
*/

defined('_VALID_CALL') or die('Direct Access is not allowed.');

if ($_REQUEST['selected_payment'] == 'xt_novalnet_paypal') {

	$code = 'xt_novalnet_paypal';

	$_SESSION['xt_novalnet_paypal_data'] = $_REQUEST;
}
