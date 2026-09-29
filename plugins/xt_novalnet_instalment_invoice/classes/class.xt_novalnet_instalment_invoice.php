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
 * xt_novalnet_instalment_invoice Class
 */

class xt_novalnet_instalment_invoice
{
    /**
     * Code for the gateway.
     *
     * @var string
     */
    public $code         = 'xt_novalnet_instalment_invoice';

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
    public $external     = false;

    /**
     * Settings of the gateway template.
     *
     * @var array
     */
    public $data         = array();

    function __construct()
    {
        global $order;

        // Assign basic details to template
        $this->data = array_merge(
            $this->data,
            Novalnet::get_basic_template_details($this->code)
        );

        // Assign guarantee details to template
        Novalnet::get_guarantee_details(
            $this->data,
            $this->code
        );
       
        
        // Assign available instalment cycles
        $cycles = trim(XT_NOVALNET_INSTALMENT_INVOICE_INSTALMENT_CYCLES);
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
        global $order;

        // Assign the on-hold parameter
        $xt_novalnet_config->onhold_param($parameters, $this->code);

        // Form guarantee payment parameters
        $xt_novalnet_config->form_guarantee_payment_params($parameters, $this->code);
        
        $parameters['invoice_type'] = 'INVOICE';
        $parameters['invoice_ref']  = 'BNR-' . trim(XT_NOVALNET_PRODUCT_ID) . '-' . $order->order_data['orders_id'];
        // Process the payment
        $xt_novalnet_config->proceed_payment($parameters, $this->code);
    }
}
