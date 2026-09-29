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

include_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'xt_novalnet_config/classes/class.novalnet.php';

/**
 * xt_novalnet_instalment_sepa Class
 */

class xt_novalnet_instalment_sepa
{
	/**
	 * Code for the gateway.
	 *
	 * @var string
	 */
	public $code         = 'xt_novalnet_instalment_sepa';

	/**
	 * Gateway shows Sub-Payments inside the Payment on the checkout.
	 *
	 * @var bool
	 */
	public $subpayments  = false;

	/**
	 * Gateway shows default iframe on the checkout.
	 *
	 * @var bool
	 */
	public $iframe       = false;

	/**
	 * Assign value for gateway post process
	 *
	 * @var bool
	 */
	public $external       = false;

	/**
	 * Settings of the gateway template.
	 *
	 * @var array
	 */
	public $data         = array();

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		// Assign basic details to template
		$this->data = array_merge($this->data, Novalnet::get_basic_template_details($this->code));

		// Assign One click process configuration
		Novalnet::get_shopping_type_details($this->data, $this->code);
		if (empty($this->data['user_masked_data'])) {
			$this->data['one_click_process_enabled'] = false;
			$this->data['given_details_style']  = 'display:none';
			$this->data['new_details_style']    = 'display:block';
		}

		$this->data['sepa_invalid_account_error'] = XT_NOVALNET_INSTALMENT_SEPA_INVALID_ACCOUNT_DETAILS_ERROR_TEXT;

		// Assign guarantee details to template
		Novalnet::get_guarantee_details($this->data, $this->code);

		// Assign Direct Debit SEPA form values
		$countries = new countries('true', 'store');
		$this->data['default_country']    = $_SESSION['customer']->customer_default_address['customers_country_code'];
		$this->data['account_holder'] = $_SESSION['customer']->customer_payment_address['customers_firstname'] . ' ' . $_SESSION['customer']->customer_payment_address['customers_lastname'];
		$this->data['country']            = $countries->countries_list_sorted;
		$this->data['js_path']            = _SRV_WEB_PLUGINS . 'xt_novalnet_instalment_sepa/javascript/xt_novalnet_instalment_sepa.min.js';


		// Assign available instalment cycles
		$cycles = trim(XT_NOVALNET_INSTALMENT_SEPA_INSTALMENT_CYCLES);
		$this->data['xt_novalnet_instalment_total_amount'] = $_SESSION['cart']->total['plain'];
		$this->data['xt_novalnet_instalment_total_period'] = $cycles;
        $this->data['selected_currency'] = $_SESSION['selected_currency'];
	}

	/**
	 * Form additional parameters
	 *
	 * @param array $xt_novalnet_config
	 * @param array $parameters
	 */
	function additional_parameters($xt_novalnet_config, &$parameters)
	{

		$process_type = constant(strtoupper($this->code) . '_SHOPPING_TYPE');
		// Assign the one-click shopping parameters
		$xt_novalnet_config->form_one_click_params($parameters, $this->code, $process_type);

		// Form guarantee payment parameters
		$xt_novalnet_config->form_guarantee_payment_params($parameters, $this->code, $process_type);

		// Assign the on-hold parameter
		$xt_novalnet_config->onhold_param($parameters, $this->code);

		// Set payment parameters
		if (empty($parameters['payment_ref'])) {

			$parameters['bank_account_holder'] = trim($_SESSION['xt_novalnet_instalment_sepa_data']['novalnet_instalment_sepa_account_holder']);
			$parameters['iban'] = $_SESSION['xt_novalnet_instalment_sepa_data']['novalnet_instalment_sepa_iban'];
			$parameters['bic'] = $_SESSION['xt_novalnet_instalment_sepa_data']['novalnet_instalment_sepa_bic'];
		}

		// Process the payment
		$xt_novalnet_config->proceed_payment($parameters, $this->code);
	}
}