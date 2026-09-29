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
 * xt_novalnet_eps Class
 */

class xt_novalnet_eps
{
	/**
	 * Code for the gateway.
	 *
	 * @var string
	 */
	public $code         = 'xt_novalnet_eps';
	
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
	public $external       = true;
	
	/**
	 * Gateway shows payment form on the checkout.
	 *
	 * @var bool
	 */
	public $post_form    = true;

	/**
	 * Settings of the gateway template.
	 *
	 * @var array
	 */
	public $data         = array();
	
	/**
	 * Paygate URL.
	 *
	 * @var string
	 */
	public $TARGET_URL   = 'https://payport.novalnet.de/giropay';
     
	/**
	 * Constructor
	 *
	 */
    function __construct()
    {
        // Assign basic details to template
        $this->data = array_merge($this->data, Novalnet::get_basic_template_details($this->code));

        // Assign values for post process
        if (!empty($_REQUEST['tid']) && $_REQUEST['novalnet_payment'] == $this->code) {
            $this->external = true;
        }
    }
    
    /**
     * Form additional parameters
     *
     * @param array $xt_novalnet_config
     * @param array $parameters
     */
    function additional_parameters($xt_novalnet_config, &$parameters) {
		
		// Form redirect parameters
		Novalnet::redirect_parameters($parameters, $this->code);
		$_SESSION[$this->code]['redirect_parameters'] = $parameters;
		$_SESSION[$this->code]['redirect_url'] = $this->TARGET_URL;
	}
	
	/**
     * Form the payment params and send to server for lower versions
     *
     * @return none
     */
    function pspRedirect()
    {
		global $xtLink;
		return ($xtLink->_link(array('page'=>'checkout', 'paction'=>'novalnet_redirect')));
    }

    /**
     * Complete the order after response from novalnet
     *
     * @return boolean
     */
    function pspSuccess()
    {
		// Unset payment parameters SESSION
		if(!empty($_SESSION[$this->code]['redirect_parameters'])) {
			unset($_SESSION[$this->code]['redirect_parameters']);
		}
		
		// Check the hash value from server
		Novalnet::check_hash_process($_REQUEST);

		// Decode the values from server
		Novalnet::decode_response_data($_REQUEST);

		// Complete novalnet order
		Novalnet::complete_novalnet_order($this->code, $_REQUEST);
		return true;
    }
}

