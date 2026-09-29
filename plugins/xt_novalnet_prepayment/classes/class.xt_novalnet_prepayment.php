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
 * xt_novalnet_prepayment Class
 */

class xt_novalnet_prepayment
{
    /**
	 * Code for the gateway.
	 *
	 * @var string
	 */
	public $code         = 'xt_novalnet_prepayment';
	
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
    }
    
    /**
     * Form additional parameters
     *
     * @param array $xt_novalnet_config
     * @param array $parameters
     */
    function additional_parameters($xt_novalnet_config, &$parameters) {
		global $order;

		// Form payment parameters
		$due_date = intval(trim(XT_NOVALNET_PREPAYMENT_PREPAYMENT_DUE_DATE));
		if (!empty($due_date)) {
			$parameters['due_date'] = date('Y-m-d', strtotime('+' . $due_date . 'days'));
		}
		
		$parameters['invoice_type']   = 'PREPAYMENT';
		$parameters['invoice_ref']    = 'BNR-' . trim(XT_NOVALNET_PRODUCT_ID) . '-' . $order->order_data['orders_id'];

		// Process the payment
		$xt_novalnet_config->proceed_payment($parameters, $this->code);
	}
}
