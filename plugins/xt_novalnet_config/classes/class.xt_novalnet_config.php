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
 * xt_novalnet_config Class
 */

class xt_novalnet_config
{
    /**
     * Forming payment parameters
     *
     * @param string $selected_payment
     * @return array
     */
    public function form_payment_params($selected_payment)
    {
        return Novalnet::form_payment_params($selected_payment);
    }


    /**
     * Proceed the payment process
     *
     * @param array  $parameters
     * @param string $selected_payment
     */
    public function proceed_payment($parameters, $selected_payment)
    {
        // Post request to server
        $response_data = Novalnet::perform_curlrequest($parameters);
        parse_str($response_data, $response);
        // Complete the order
        Novalnet::complete_novalnet_order($selected_payment, $response);
    }

    /**
     * Assign on-hold to payment parameter
     *
     * @param array $parameters
     * @param string $payment_name
     * @param string $shopping_type
     */
    public function onhold_param(&$parameters, $payment_name, $shopping_type = '')
    {
        if (!empty($shopping_type) && $shopping_type == 'ZEROAMOUNT') {
            return TRUE;
        }
        $onhold_type = trim(constant(strtoupper($payment_name) . '_ENABLE_AUTHORIZATION'));

        // Check the Minimum transaction amount for authorization
        $onhold_limit_amount = trim(constant(strtoupper($payment_name) . '_ONHOLD_LIMIT_AMOUNT'));

        if ($onhold_type == 'AUTHORIZE' && ($parameters['amount'] >= $onhold_limit_amount || empty($onhold_limit_amount))) {
            $parameters['on_hold'] = '1';
        }
    }


    /* Check for zero amount booking
     *
     * @param array $parameters
     * @param string $zero_booking
     */
    public function zero_booking_param(&$parameters, $code, $zero_booking)
    {
        // Check the zero amount booking
        if ($zero_booking == 'ZEROAMOUNT' && $_SESSION['novalnet']['tariff_type'] == '2') {

            // Assign payment parameters in SESSION
            $_SESSION[$code]['payment_params'] = $parameters;
            $parameters['amount'] = 0;
            $parameters['create_payment_ref'] = 1;
        }
    }

    /**
     * Form Guarantee payment parameters
     *
     * @param array $parameters
     * @param string $code
     */
    public function form_guarantee_payment_params(&$parameters, $code)
    {
        if (!empty($_SESSION[$code]['process_guarantee'])) {
            $payment_details = Novalnet::get_payment_key();
            if (!in_array($code, array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
                $payment_code = $code . '_guarantee';
                $parameters['payment_type'] = $payment_details[$payment_code]['payment_type'];
                $parameters['key'] = $_SESSION[$code]['key'] = $payment_details[$payment_code]['key'];
            }
            if (!empty($_SESSION[$code]['customer_company'])) {
                $parameters['birth_date']   = date('Y-m-d', strtotime($_SESSION[$code . '_data'][$code . '_user_dob']));
            }
            if (in_array($code, array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
                $parameters['instalment_cycles']   = $_SESSION[$code . '_data'][$code . '_cycles'];
                $parameters['instalment_period'] = '1m';
            }
        }
    }

    /**
     * Form One click process parameters
     *
     * @param reference array $parameters
     * @param string $code
     * @param string $shopping_type
     */
    public function form_one_click_params(&$parameters, $code, $shopping_type)
    {
        if ($shopping_type == 'ONECLICK') {
            if (!empty($_SESSION[$code . '_data'][$code . '_one_click_process'])) {
                $parameters['payment_ref'] = $_SESSION[$code]['masked_tid'];
            } else {
                $parameters['create_payment_ref'] = '1';
            }
        }
    }
}
