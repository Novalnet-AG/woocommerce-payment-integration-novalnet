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
 * xt_novalnet_cc Class
 */

class xt_novalnet_cc
{
    /**
     * Code for the gateway.
     *
     * @var string
     */
    public $code = 'xt_novalnet_cc';

    /**
     * Gateway shows Sub-Payments inside the Payment on the checkout.
     *
     * @var bool
     */
    public $subpayments = false;

    /**
     * Gateway shows default iframe on the checkout.
     *
     * @var bool
     */
    public $iframe = false;

    /**
     * Assign value for gateway post process
     *
     * @var bool
     */
    public $external = false;

    /**
     * Gateway shows payment form on the checkout.
     *
     * @var bool
     */
    public $post_form = false;

    /**
     * Paygate URL.
     *
     * @var string
     */

    public $TARGET_URL = 'https://payport.novalnet.de/pci_payport';
    /**
     * Settings of the gateway template.
     *
     * @var array
     */
    public $data = array();

    /**
     * Constructor
     *
     */
    function __construct()
    {
        global $currency;

        // Assign basic details to template
        $this->data = array_merge($this->data, Novalnet::get_basic_template_details($this->code));
        // Assign Credit card configuration
        $this->data['amex_logo'] = XT_NOVALNET_CC_AMEX_CARD_ENABLE;
        $this->data['maestro_logo'] = XT_NOVALNET_CC_MAESTRO_CARD_ENABLE;

        // Assign Iframe CSS style configuartions
        $this->data['nn_standard_label'] = XT_NOVALNET_CC_STANDARD_STYLE_LABEL_CONFIGURATION;
        $this->data['nn_standard_input'] = XT_NOVALNET_CC_STANDARD_STYLE_INPUT_CONFIGURATION;
        $this->data['nn_standard_css'] = XT_NOVALNET_CC_STANDARD_STYLE_CSS_CONFIGURATION;
        $this->data['nn_card_details_error_text'] = XT_NOVALNET_CC_IFRAME_CARD_DETAILS_ERROR_TEXT;

        $this->data['shop_lang'] = !empty($_SESSION['selected_language']) ? strtolower($_SESSION['selected_language']) : 'en';
        $this->data['card_holder_name'] = $_SESSION['customer']->customer_payment_address['customers_firstname'] . ' ' . $_SESSION['customer']->customer_payment_address['customers_lastname'];

        $this->data['nn_client_key'] = trim(XT_NOVALNET_PAYMENT_CLIENT_KEY);
        $this->data['nn_first_name'] = $_SESSION['customer']->customer_payment_address['customers_firstname'];
        $this->data['nn_last_name'] = $_SESSION['customer']->customer_payment_address['customers_lastname'];
        $this->data['nn_email'] = $_SESSION['customer']->customer_info['customers_email_address'];
        $this->data['nn_billing_street'] = $_SESSION['customer']->customer_payment_address['customers_street_address'];
        $this->data['nn_billing_city'] = $_SESSION['customer']->customer_payment_address['customers_city'];
        $this->data['nn_billing_zip'] = $_SESSION['customer']->customer_payment_address['customers_postcode'];
        $this->data['nn_billing_country_code'] = $_SESSION['customer']->customer_payment_address['customers_country_code'];

        $billing_address = [
            'street' => $_SESSION['customer']->customer_payment_address['customers_street_address'],
            'city' => $_SESSION['customer']->customer_payment_address['customers_city'],
            'zip' => $_SESSION['customer']->customer_payment_address['customers_postcode'],
            'country_code' => $_SESSION['customer']->customer_payment_address['customers_country_code'],
        ];

        $shipping_address = [
            'street' => $_SESSION['customer']->customer_shipping_address['customers_street_address'],
            'city' => $_SESSION['customer']->customer_shipping_address['customers_city'],
            'zip' => $_SESSION['customer']->customer_shipping_address['customers_postcode'],
            'country_code' => $_SESSION['customer']->customer_shipping_address['customers_country_code'],
        ];


        $this->data['nn_same_as_billing'] = ($shipping_address === $billing_address) ? 1 : 0;

        $this->data['nn_same_as_billing'] = 0;
        $this->data['nn_shiiping_first_name'] = $_SESSION['customer']->customer_shipping_address['customers_firstname'];
        $this->data['nn_shiiping_last_name'] = $_SESSION['customer']->customer_shipping_address['customers_lastname'];
        $this->data['nn_shiiping_enail'] = $_SESSION['customer']->customer_info['customers_email_address'];
        $this->data['nn_shipping_street'] = $_SESSION['customer']->customer_shipping_address['customers_street_address'];
        $this->data['nn_shipping_city'] = $_SESSION['customer']->customer_shipping_address['customers_city'];
        $this->data['nn_shipping_zip'] = $_SESSION['customer']->customer_shipping_address['customers_postcode'];
        $this->data['nn_shipping_country_code'] = $_SESSION['customer']->customer_shipping_address['customers_country_code'];

        $this->data['shop_currency'] = $currency->code;

        $payment_amount = (int) Novalnet::get_server_amount_format($_SESSION['cart']->total['plain']);

        $shopping_type = constant(strtoupper($this->code) . '_SHOPPING_TYPE');

        if ($shopping_type == 'ZEROAMOUNT') {

            $payment_amount = 0;

        }
        $this->data['transaction_amount'] = $payment_amount;
        $this->data['nn_test_mode'] = (int) XT_NOVALNET_CC_TEST_MODE;

        $this->data['css_path'] = _SRV_WEB_PLUGINS . 'xt_novalnet_cc/css/xt_novalnet_cc.css';
        // Javascript path
        $this->data['js_path'] = _SRV_WEB_PLUGINS . 'xt_novalnet_cc/javascript/xt_novalnet_cc.min.js';
        $this->data['checkout_js_url'] = 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js';

        // Assign One click process configuration

        Novalnet::get_shopping_type_details($this->data, $this->code);
        if (XT_NOVALNET_CC_ENABLE_3D_ENFORE == 'true' || $_SESSION['xt_novalnet_cc_data']['nn_cc_do_redirect'] == 1) {
            $this->external = true;
            $this->post_form = true;
        }
       
        if (XT_NOVALNET_CC_SHOPPING_TYPE == 'ONECLICK' && empty($_SESSION['xt_novalnet_cc_data']['nn_unique_id']) && empty($_SESSION['xt_novalnet_cc_data']['cc_pan_hash'])) {
			$this->external = false;
			$this->post_form =false;
		}
		if (!empty($_REQUEST['tid']) && $_REQUEST['novalnet_payment'] == $this->code) {
            $this->external = true;
            $this->post_form = true;
        }

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
        // Assign the on-hold parameter
        $xt_novalnet_config->onhold_param($parameters, $this->code, $process_type);

        // Check whether the Zero amount booking is enabled or not for set the amount to 0
        $xt_novalnet_config->zero_booking_param($parameters, $this->code, $process_type);

        // Assign the one-click shopping parameters
        $xt_novalnet_config->form_one_click_params($parameters, $this->code, $process_type);

        // Add payment form parameters
        if (empty($parameters['payment_ref'])) {
            $parameters['nn_it'] = 'iframe';
            $parameters['pan_hash'] = $_SESSION['xt_novalnet_cc_data']['cc_pan_hash'];
            $parameters['unique_id'] = $_SESSION['xt_novalnet_cc_data']['nn_unique_id'];
        }
        if (XT_NOVALNET_CC_ENABLE_3D_ENFORE == 'true' || $_SESSION['xt_novalnet_cc_data']['nn_cc_do_redirect'] == 1) {
            Novalnet::redirect_parameters($parameters, $this->code);
            $_SESSION[$this->code]['redirect_parameters'] = $parameters;
            $_SESSION[$this->code]['redirect_url'] = $this->TARGET_URL;
        } 
        if($this->external == false){
			 $xt_novalnet_config->proceed_payment($parameters, $this->code);
		 }
    }

    /**
     * Form the payment params and send to server for lower versions
     *
     * @return none
     */
    function pspRedirect()
    {
        global $xtLink;
        return ($xtLink->_link(array('page' => 'checkout', 'paction' => 'novalnet_redirect')));
    }

    /**
     * Complete the order after response from novalnet
     *
     * @return boolean
     */
    function pspSuccess()
    {
        // Unset payment parameters SESSION
        if (!empty($_SESSION[$this->code]['redirect_parameters'])) {
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
