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


if ($_REQUEST['selected_payment'] == 'xt_novalnet_instalment_invoice') {

	$code = 'xt_novalnet_instalment_invoice';

	// Assign posted values in SESSION
	$_SESSION['xt_novalnet_instalment_invoice_data'] = $_REQUEST;
	$_SESSION['novalnet_selected_payment'] = $_REQUEST['selected_payment'];

	// Include required file
	include_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'xt_novalnet_config/classes/class.novalnet.php';

	// Get selected instalment cycle
	$selected_cycle = isset(
		$_REQUEST['xt_novalnet_instalment_invoice_cycles']
	)
		? (int)$_REQUEST['xt_novalnet_instalment_invoice_cycles']
		: 0;

	// Get configured instalment cycles
	$configured_cycles = array_filter(
		array_map(
			'intval',
			array_map('trim', explode(',', XT_NOVALNET_INSTALMENT_INVOICE_INSTALMENT_CYCLES))
		)
	);

	// Validate selected cycle
	if (
		empty($selected_cycle) ||
		empty($configured_cycles) ||
		!in_array(
			$selected_cycle,
			$configured_cycles,
			true
		)
	) {
		$err_text = defined('XT_NOVALNET_INSTALMENT_INVOICE_INVALID_CYCLE_ERROR_TEXT')
			? XT_NOVALNET_INSTALMENT_INVOICE_INVALID_CYCLE_ERROR_TEXT
			: 'Select the available instalment cycles';
		Novalnet::checkout_redirect_process($err_text);
	}

	// Check and validate guarantee payment
	$error = Novalnet::validate_guarantee_process($code);
	if ($error != '') {
		Novalnet::checkout_redirect_process($error);
	}
}