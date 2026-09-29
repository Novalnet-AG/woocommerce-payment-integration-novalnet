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

/**
 * Novalnet Class
 */

class Novalnet
{
    private static $_allowed_countries = array('DE', 'AT', 'CH',);

    private static $_supports_shopping_type = array('xt_novalnet_cc', 'xt_novalnet_sepa');

    /**
     * Get vendor details
     *
     * @return array
     */
    public static function get_vendor_details()
    {
        global $db;

        // Get tariff type and value
        list($tariff_type, $tariff_id) = defined('XT_NOVALNET_TARIFF_ID') && XT_NOVALNET_TARIFF_ID != '' ? explode('-', XT_NOVALNET_TARIFF_ID) : array('', '');

        $parameters = array(
            'vendor'    => defined('XT_NOVALNET_VENDOR_ID') ? trim(XT_NOVALNET_VENDOR_ID) : '',
            'auth_code' => defined('XT_NOVALNET_AUTH_CODE') ? trim(XT_NOVALNET_AUTH_CODE) : '',
            'product'   => defined('XT_NOVALNET_PRODUCT_ID') ? trim(XT_NOVALNET_PRODUCT_ID) : '',
            'tariff'    => $tariff_id,
        );
        // Assign payment access key and tariff type in SESSION for later use
        $_SESSION['novalnet']['accesskey']   = defined('XT_NOVALNET_PAYMENT_ACCESS_KEY') ? trim(XT_NOVALNET_PAYMENT_ACCESS_KEY) : '';

        $_SESSION['novalnet']['tariff_type'] = $tariff_type;
        // Fetch affiliate  details
        if (!empty($_SESSION['nn_aff_id'])) {
            $query = $db->Execute("SELECT aff_authcode, aff_accesskey FROM " . DB_PREFIX . "_novalnet_affiliate_account_detail WHERE aff_id=" . $_SESSION['nn_aff_id']);
            if (is_array($query->fields)) {
                $parameters['vendor']    = $_SESSION['nn_aff_id'];
                $parameters['auth_code'] = $query->fields['aff_authcode'];
                $_SESSION['novalnet']['accesskey'] = $query->fields['aff_accesskey'];
            }
        } else if ($_SESSION['customer']->customers_status != _STORE_CUSTOMERS_STATUS_ID_GUEST && $_SESSION['customer']->customers_id) {
            $query = $db->Execute("SELECT aff_id, aff_authcode, aff_accesskey FROM " . DB_PREFIX . "_novalnet_affiliate_account_detail WHERE aff_id =(SELECT aff_id FROM " . DB_PREFIX . "_novalnet_affiliate_user_detail WHERE customer_id='" . $_SESSION['customer']->customers_id . "' ORDER BY id DESC LIMIT 1)");

            // Fetch existing affiliate user details
            if (!empty($query->fields['aff_id'])) {
                $parameters['vendor']    = $query->fields['aff_id'];
                $parameters['auth_code'] = $query->fields['aff_authcode'];
                $_SESSION['novalnet']['accesskey'] = $query->fields['aff_accesskey'];
            }
        }

        return $parameters;
    }


    /**
     * Return last successful transaction payment status
     *
     * @return string/void
     */
    public static function get_last_payment()
    {
        global $db;

        // Get the Current/ Selected payment
        if (!empty($_SESSION['novalnet_selected_payment'])) {
            return $_SESSION['novalnet_selected_payment'];
        } elseif (!empty($_SESSION['selected_payment'])) {
            return $_SESSION['selected_payment'];
        } elseif (!empty($_SESSION['payment'])) {
            return $_SESSION['payment'];
        } elseif (defined('XT_NOVALNET_SELECT_DEFAULT_PAYMENT') && XT_NOVALNET_SELECT_DEFAULT_PAYMENT == 'true' && isset($_SESSION['customer']->customers_id)) {
            $payment_type = $db->GetOne("SELECT payment_type FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE customer_id='" . $_SESSION['customer']->customers_id . "' ORDER BY id DESC LIMIT 1");
            return $payment_type;
        }
        return '';
    }


    /**
     * Fetch the masking details of user's previous order
     *
     * @return array
     */
    public static function get_masking_details($code)
    {
        global $db;
        $masked_data = array();

        if ($code == 'xt_novalnet_sepa') {
            $query = $db->Execute("SELECT payment_details, tid FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE customer_id = '" . $_SESSION['customer']->customers_id . "' AND payment_ref = '0' AND payment_type='" . $code . "' AND booked='1' AND payment_details LIKE '%iban%' AND payment_params='' ORDER BY id DESC LIMIT 1");
        } else {
            $query = $db->Execute("SELECT payment_details, tid FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE customer_id = '" . $_SESSION['customer']->customers_id . "' AND payment_ref = '0' AND payment_type='" . $code . "' AND booked='1' AND payment_details!='' AND payment_params='' ORDER BY id DESC LIMIT 1");
        }

        // Check for query result
        if (!empty($query->fields['payment_details'])) {
            $masked_data        = (array) json_decode($query->fields['payment_details']);
            $masked_data['tid'] = $query->fields['tid'];
        }

        return $masked_data;
    }

    /**
     * Encode the required params and generate hash
     *
     * @param array $data
     */
    public static function generate_hash_value(&$data)
    {
        $data['uniqid'] = self::novalnet_get_uniqid();
        foreach (
            array(
                'auth_code',
                'product',
                'tariff',
                'amount',
                'test_mode',
            ) as $value
        ) {
            // Form an encode values
            $data[$value] = self::generate_encode_data($data[$value], $data['uniqid']);
        }
        // Generate Hash value
        $data['hash'] = self::novalnet_hash_value($data);
    }


    /**
     * Genaerate HASH value
     *
     * @param array $data
     * @return string
     */
    public static function novalnet_hash_value($data)
    {
        return hash('sha256', $data['auth_code'] . $data['product'] . $data['tariff'] . $data['amount'] . $data['test_mode'] . $data['uniqid'] . strrev($_SESSION['novalnet']['accesskey']));
    }


    /**
     * Get the client ip address
     *
     * @return ip address
     */
    public static function get_client_ip()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }


    /**
     * Get the response message
     *
     * @param array $data
     * @return string
     */
    public static function set_response_message($data)
    {
        return (!empty($data['status_desc']) ? $data['status_desc'] : (!empty($data['status_text']) ? $data['status_text'] : (!empty($data['status_message']) ? $data['status_message'] : XT_NOVALNET_UNKNOWN_MESSAGE_TEXT)));
    }


    /**
     * Perform CURL execution process
     *
     * @param array  $parameters
     * @param string $url
     * @return array
     */
    public static function perform_curlrequest($parameters, $url = 'https://payport.novalnet.de/paygate.jsp')
    {
        // Initiate cURL
        $curl_process = curl_init($url);
        // Set cURL options
        curl_setopt($curl_process, CURLOPT_POST, 1);
        curl_setopt($curl_process, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($curl_process, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl_process, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl_process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_process, CURLOPT_RETURNTRANSFER, 1);

        // Execute cURL
        $response_data = curl_exec($curl_process);
        $error_code    = curl_errno($curl_process);
        // Handle cURL error
        if ($error_code) {
            $error_message   = curl_error($curl_process);
            return 'status=' . $error_code . '&status_message=' . $error_message;
        }
        // Close cURL
        curl_close($curl_process);
        return $response_data;
    }

    /**
     * Form payment parameters
     *
     * @param string $selected_payment
     * @return array
     */
    public static function form_payment_params($selected_payment)
    {
        global $order, $xtLink;

        $_SESSION['novalnet']['selected_payment'] = $selected_payment;
        $novalnet_payments = self::get_payment_key();
        // Unset other payment sessions
        foreach (array_keys($novalnet_payments) as $payment) {
            if ($selected_payment != $payment) {
                self::unset_novalnet_session(array(
                    $payment,
                    $payment . '_data',
                    'novalnet',
                    'response_data',
                ));
            }
        }

        // Get the global configuration data
        $parameters = self::get_vendor_details();
        // Total order amount
        $total_amount = $_SESSION['novalnet']['order_amount'] = self::get_server_amount_format($order->order_total['total']['plain']);
        // Assign language value
        $language_code = !empty($order->order_data['language_code']) ? strtoupper($order->order_data['language_code']) : 'EN';

        $notify_url        = defined('XT_NOVALNET_CALLBACK_URL') ? trim(XT_NOVALNET_CALLBACK_URL) : '';
        // Form additional parameters
        $additional_parameters = array(
            'company'     => ($order->order_data['delivery_company'] != '') ? trim($order->order_data['delivery_company']) : trim($order->order_data['billing_company']),
            'notify_url'  => $notify_url,
        );
        // Get order data
        $shop_order_data = $order->order_data;
        // Get street address
        $street_address = !empty($shop_order_data['billing_suburb']) ? $shop_order_data['billing_street_address'] . ',' . $shop_order_data['billing_suburb'] : $shop_order_data['billing_street_address'];
        $system_url = $xtLink->_link(array(
            'page'   => ' ',
            'params' => "",
            'conn'   => ''
        ));
        // Form customer and order parameters
        $parameters = array_merge(
            $parameters,
            array(
                'customer_no'      => ($shop_order_data['customers_id'] != '') ? $shop_order_data['customers_id'] : 'Guest',
                'first_name'       => $shop_order_data['billing_firstname'],
                'last_name'        => $shop_order_data['billing_lastname'],
                'email'            => $shop_order_data['customers_email_address'],
                'gender'           => $shop_order_data['billing_gender'],
                'street'           => $street_address,
                'search_in_street' => '1',
                'city'             => $shop_order_data['billing_city'],
                'zip'              => $shop_order_data['billing_postcode'],
                'tel'              => $shop_order_data['billing_phone'],
                'mobile'           => $shop_order_data['billing_mobile_phone'],
                'country'          => $shop_order_data['billing_country_code'],
                'country_code'     => $shop_order_data['billing_country_code'],
                'remote_ip'        => self::get_client_ip(),
                'test_mode'        => (int) (constant(strtoupper($selected_payment) . '_TEST_MODE') == 'true'),
                'amount'           => $total_amount,
                'lang'             => $language_code,
                'language'         => $language_code,
                'currency'         => $shop_order_data['currency_code'],
                'key'              => $novalnet_payments[$selected_payment]['key'],
                'payment_type'     => $novalnet_payments[$selected_payment]['payment_type'],
                'system_name'      => 'xtcommerce',
                'system_version'   => _SYSTEM_VERSION . '-NN 11.6.0',
                'system_url'       => $system_url,
                'system_ip'        => $_SERVER['SERVER_ADDR'],
                'order_no'         => $shop_order_data['orders_id'],
            ),
            array_filter($additional_parameters)
        );
        return $parameters;
    }


    /**
     * Perform the encoding process for redirection payment methods
     *
     * @param string $data
     * @return string
     */
    public static function generate_encode_data($data, $uniqid)
    {
        try {
            $openssl = openssl_encrypt($data, 'aes-256-cbc', $_SESSION['novalnet']['accesskey'], true, $uniqid);
            $data    = base64_encode($openssl);
            $data    = htmlentities($data);
        } catch (Exception $e) {
            echo ('Error: ' . $e);
        }
        return $data;
    }


    /**
     * Get the current order status from order table
     *
     * @param integer $order_id
     * @return integer
     */
    public static function get_order_status($order_id)
    {
        global $db;
        $orderInfo = $db->Execute("select orders_status from " . TABLE_ORDERS . " where orders_id = " . $order_id);
        return $orderInfo->fields['orders_status'];
    }


    /**
     * Perform the decoding process for redirection payment methods
     *
     * @param string $data
     * @return string
     */
    public static function generate_decode_data($data, $key, $uniqid)
    {
        try {
            $data  = base64_decode($data);
            $value = trim(openssl_decrypt($data, 'aes-256-cbc', $key, true, $uniqid));
            return $value;
        } catch (Exception $e) {
            echo ('Error: ' . $e);
        }
        return $data;
    }


    /**
     * Form transaction comments
     *
     * @param array $response
     * @param integer $test_mode
     * @return string
     */
    public static function form_transaction_comments($response, $test_mode, $payment_type = null)
    {
        global $price;
        $comments = '';
        if ($response['tid']) {
            if (in_array($response['payment_id'], array('40', '41')) && in_array($response['tid_status'], array('75', '91', '99', '100', '103'))) {
                $comments .= XT_NOVALNET_MENTION_PAYMENT_CATEGORY_TEXT . '<br><br>';
            }

            if (in_array($response['tid_status'], array('91', '99', '100')) && isset($response["amount"]) && $response["amount"] == 0) {
                $comments .= XT_NOVALNET_ZERO_AMOUNT_BOOKING_TEXT . '<br><br>';
            }


            $comments .= constant(strtoupper($payment_type) . '_TEXT') . '<br>';
            $comments .= XT_NOVALNET_TRANSACTION_ID_TEXT . ': ' . $response['tid'] . '<br>';
            if ($test_mode) {
                $comments .= XT_NOVALNET_TESTBESTELLUNG_TEXT . '<br>';
            }
            if ($response['tid_status'] == '100' && $response['payment_id'] == '97') {
                $price->_setCurrency($response['currency']);
                $formatted_amount = $price->_Format(
                    array(
                        'price'       => $response['instalment_cycle_amount'],
                        'format'      => true,
                        'format_type' => 'default'
                    )
                );
                $comments .= self::form_instalment_comments($response, $formatted_amount['formated']);
            }

            $comments .= '<br>';
            if ($response['tid_status'] == '75') {
                $comments .= ($response['payment_id'] == '41' ? XT_NOVALNET_MENTION_GUARANTEE_PENDING_PAYMENT_TEXT : XT_NOVALNET_MENTION_GUARANTEE_PENDING_SEPA_PAYMENT_TEXT) . '<br><br>';
            }
        }

        return $comments;
    }

    /**
     * Form the comments for Invoice/Prepayment
     *
     * @param array   $response
     * @param string  $product_id
     * @param string  $selected_payment
     * @return string
     */
    public static function form_invoice_prepayment_comments($response, $product_id, $selected_payment)
    {
        global $price, $currency;
        $trans_comments = '';
        if ($response['tid_status'] != '75') {
            $account_holder = (isset($response['invoice_account_holder']) && $response['invoice_account_holder'] != '') ? $response['invoice_account_holder'] : $response['account_holder'];
            $price->_setCurrency($response['currency']);

            $amt = $response['payment_id'] == '96'
                ? $response['instalment_cycle_amount']
                : $response['amount'];
            $formatted_amount = $price->_Format(
                array(
                    'price'       => $amt,
                    'format'      => true,
                    'format_type' => 'default'
                )
            );
            $order_amount = $formatted_amount['formated'];

            if ($response['payment_id'] != '96' && !empty($response['formated_amount'])) {
                $order_amount = $response['formated_amount'];
            }

            if ($response['tid_status'] == 91) {
                $trans_comments .= sprintf(XT_NOVALNET_TRANSFER_INFO_ONHOLD_MESSAGE, $order_amount) . '<br/>';
            }

            if (!empty($response['due_date']) && $response['tid_status'] == 100) {
                $trans_comments .= sprintf(XT_NOVALNET_TRANSFER_INFO_NORMAL_TEXT, $order_amount, date_short($response['due_date'])) . '<br/>';
            }

            $order_no     = !empty($response['order_no']) ? $response['order_no'] : $response['orders_id'];
            $trans_comments .= XT_NOVALNET_ACCOUNT_HOLDER_TEXT . ': ' . $account_holder . '<br/>';
            $trans_comments .= XT_NOVALNET_IBAN_TEXT . ': ' . $response['invoice_iban'] . '<br/>';
            $trans_comments .= XT_NOVALNET_BIC_TEXT . ': ' . $response['invoice_bic'] . '<br/>';
            $trans_comments .= XT_NOVALNET_BANK_TEXT . ': ' . $response['invoice_bankname'] . '<br/>';
            $trans_comments .= XT_NOVALNET_BANK_PLACE_TEXT . ': ' . $response['invoice_bankplace'] . '<br/>';

            // Form payment reference comments
            $references = array_filter(
                array(
                    $response['tid']                        => 'payment_reference1',
                    'BNR-' . $product_id . '-' . $order_no               => 'payment_reference2',
                )
            );

            $trans_comments .= XT_NOVALNET_MULTI_PAYMENT_REFERENCE_NOTIFY_TEXT . '<br/>';
            $i = 1;
            foreach ($references as $key => $value) {
                $trans_comments .= constant('XT_NOVALNET_PAYMENT_TRANSACTION_REFERENCE_' . $i++) . ': ' . $key . '<br/>';
            }

            if ($response['payment_id'] == '96' && $response['tid_status'] == 100) {
                $trans_comments .= self::form_instalment_comments($response, $formatted_amount['formated']);
            }

            if (!empty($response["invoice_qr_image"])) {
                $trans_comments .= "<br/><b>" . XT_NOVALNET_TRANSACTION_INVOICE_QR_MESSAGE . "</b><br/>";
                $trans_comments .= "<img src='{$response["invoice_qr_image"]}'>";
            }
        }
        return $trans_comments . '<br/>';
    }

    public static function form_instalment_comments($response, $amount)
    {
        $trans_comments = '<br/>' . XT_NOVALNET_INSTALMENT_INFORMATION_TEXT . '<br/>';
        $trans_comments .= XT_NOVALNET_INSTALMENT_PROCESSED_INSTALMENTS . $response['instalment_cycles_executed'] . '<br/>';
        $trans_comments .= XT_NOVALNET_INSTALMENT_DUE_INSTALMENTS . $response['due_instalment_cycles'] . '<br/>';
        $trans_comments .= XT_NOVALNET_INSTALMENT_CYCLE_INSTALMENT_AMOUNT . $amount . '<br/>';
        $trans_comments .= XT_NOVALNET_INSTALMENT_NEXT_INSTALMENT_DATE . $response['next_instalment_date'] . '<br/>';
        return $trans_comments;
    }

    public static function amount_convert_shop_format($amount, $currency)
    {
        global $price;
        $price->_setCurrency($currency);
        $amount = $price->_Format(
            array(
                'price'       => $amount,
                'format'      => true,
                'format_type' => 'default'
            )
        );
        return $amount['formated'];
    }

    public static function storeInstalmentdetails($response, $total_amount)
    {
        $requiredFields = [
            'instalment_cycle_amount',
            'instalment_cycles_executed',
            'due_instalment_cycles',
            'next_instalment_date',
            'future_instalment_dates'
        ];

        foreach ($requiredFields as $field) {
            if (empty($response[$field])) {
                return !empty($response['instalment_cycle_amount'])
                    ? $response['instalment_cycle_amount']
                    : '{}';
            }
        }

        $future_instalment_dates_array = array();
        $future_instalment_dates_array[1] = date('Y-m-d H:i:s');

        // Future instalments
        $cycle_number = 2;
        foreach (explode('|', $response['future_instalment_dates']) as $cycle) {
            $parts = explode('-', $cycle, 2);

            if (count($parts) === 2 && !empty(trim($parts[1]))) {
                $future_instalment_dates_array[$cycle_number] = $parts[1];
                $cycle_number++;
            }
        }

        $total_cycles = count($future_instalment_dates_array);

        $cycle_amount = self::get_server_amount_format($response['instalment_cycle_amount']);
        $last_cycle_amount = $total_amount - ($cycle_amount * ($total_cycles - 1));
        $cycles = $future_instalment_dates_array;
        $cycle_details = array();
        if (!empty($cycles)) {
            foreach ($cycles as $cycle => $cycle_date) {
                $cycle_details[$cycle - 1]['date'] = $cycle_date;
                if (!empty($cycles[$cycle + 1])) {
                    $cycle_details[$cycle - 1]['next_instalment_date'] = $cycles[$cycle + 1];
                }
                $cycle_details[$cycle - 1]['status'] = 'Pending';
                if (!empty($response['instalment_cycles_executed']) && $cycle == $response['instalment_cycles_executed']) {
                    $cycle_details[$cycle - 1]['reference_tid'] = $response['tid'];
                    $cycle_details[$cycle - 1]['status'] = 'Paid';
                    $cycle_details[$cycle - 1]['paid_date'] = date('Y-m-d H:i:s');
                }
                $cycle_details[$cycle - 1]['instalment_cycle_amount'] = ($cycle == $total_cycles) ? $last_cycle_amount : $cycle_amount;
                $cycle_details[$cycle - 1]['instalment_cycle_amount_orginal_amount'] = ($cycle == $total_cycles) ? $last_cycle_amount : $cycle_amount;
            }
        }
        return (!empty($cycle_details) ? json_encode($cycle_details) : '{}');
    }


    /**
     * Forming the comments to update the records in db
     *
     * @param string $selected_payment
     * @param array  $response
     */
    public static function complete_novalnet_order($selected_payment, $response)
    {
        global $xtLink, $order, $db;

        $vendor_details          = self::get_vendor_details();
        $total_amount            = $paid_amount = self::get_server_amount_format($order->order_total['total']['plain']);
        // Get payment key
        $payment_key = !empty($_SESSION[$selected_payment]['key']) ? $_SESSION[$selected_payment]['key'] : self::get_payment_key($selected_payment);

        if (isset($response['status']) && ($response['status'] == '100' || ($selected_payment == 'xt_novalnet_paypal' && $response['status'] == '90'))) {
            // Check the shop configured in test mode
            $testmode = (int) (constant(strtoupper($selected_payment) . '_TEST_MODE') == 'true');
            $test_mode_value         = ($response['test_mode'] == '1' || $testmode);
            $comments                = self::form_transaction_comments($response, $test_mode_value, $selected_payment);

            // Check for Invoice/ Prepayment
            if (in_array($selected_payment, array('xt_novalnet_invoice', 'xt_novalnet_prepayment', 'xt_novalnet_instalment_invoice'))) {
                $comments       .= self::form_invoice_prepayment_comments($response, $vendor_details['product'], $selected_payment);
                $paid_amount  = 0;

                // Form payment reference values
                $payment_details = json_encode(
                    array(
                        'account_holder'    => $response['invoice_account_holder'],
                        'invoice_account'   => $response['invoice_account'],
                        'invoice_bankcode'  => $response['invoice_bankcode'],
                        'invoice_iban'      => $response['invoice_iban'],
                        'invoice_bic'       => $response['invoice_bic'],
                        'invoice_bankname'  => $response['invoice_bankname'],
                        'invoice_bankplace' => $response['invoice_bankplace'],
                        'invoice_qr_image'   => $response["invoice_qr_image"],
                    )
                );
            }

            // Assign the comments to session for send the novalnet order comments via mail
            $_SESSION['novalnet_comments'] = $comments;

            // Get the payment order status
            $order_status_id = constant(strtoupper($selected_payment) . '_ORDER_STATUS');

            if (in_array($response['tid_status'], array('90', '86'))) {
                $paid_amount = '0';
                $order_status_id = constant(strtoupper($selected_payment) . '_PENDING_STATUS');
            } else if (in_array($response['payment_id'], array('40', '41', '96', '97')) && $response['tid_status'] == '75') {
                $order_status_id = constant(strtoupper($selected_payment) . '_GUARANTEE_ORDER_STATUS');
            } else if (in_array($response['tid_status'], array('91', '98', '99', '85'))) {
                $order_status_id = defined('XT_NOVALNET_ONHOLD_COMPLETE_STATUS') ? trim(XT_NOVALNET_ONHOLD_COMPLETE_STATUS) : '';
            } else if ($response['tid_status'] == '100' && in_array($response['payment_id'], array('40', '41'))) {
                $order_status_id = ($response['payment_id'] == '41') ? constant(strtoupper($selected_payment) . '_CALLBACK_ORDER_STATUS') : constant(strtoupper($selected_payment) . '_ORDER_STATUS');
            }

            $callback_table_values = array(
                'date'         => date('Y-m-d H:i:s'),
                'payment_type' => $selected_payment,
                'status'       => '100',
                'callback_tid' => $response['tid'],
                'org_tid'      => $response['tid'],
                'amount'       => $paid_amount,
                'order_no'     => $response['order_no'],
            );

            $booked          = '1';
            $payment_params  = '';

            // Handle for shopping type process
            if (in_array($selected_payment, self::$_supports_shopping_type)) {
                $shopping_type = constant(strtoupper($selected_payment) . '_SHOPPING_TYPE');
                // Handle zero amount booking process
                if ($shopping_type == 'ZEROAMOUNT' && $_SESSION['novalnet']['tariff_type'] == '2' && $payment_key != '40') {
                    $booked         = '0';
                    $total_amount   = '0';
                    $payment_params = json_encode($_SESSION[$selected_payment]['payment_params']);

                    if (!isset($_SESSION[$selected_payment]['payment_params'])) {
                        $response['amount'] = self::get_server_amount_format($order->order_total['total']['plain']);

                        $response_params = $response;
                        $removeValues = array('page_action', 'novalnet_payment', 'selected_shipping', 'accesskey', 'paypal_transaction_id', 'error_return_method', 'implementation', 'hash', 'return_url', 'error_return_url', 'return_method', 'user_variable_0', 'uniqid', 'payment_id', 'session_start_time', 'tid', 'status', 'nc_no', 'nc_id', 'status_text', 'tid_status', 'hash2', 'tmp_coupon');

                        $response_params = array_diff_key($response_params, array_flip($removeValues));
                        $payment_params = json_encode($response_params);
                    }

                    // Handle one click process
                } else if ($shopping_type == 'ONECLICK') {

                    // Handle Credit Card payment
                    if ($selected_payment == 'xt_novalnet_cc' && empty($_SESSION['xt_novalnet_cc_data']['xt_novalnet_cc_one_click_process'])) {
                        $payment_details = json_encode(
                            array(
                                'cc_holder'    => $response['cc_holder'],
                                'cc_no'        => $response['cc_no'],
                                'cc_exp_year'  => $response['cc_exp_year'],
                                'cc_exp_month' => $response['cc_exp_month'],
                                'cc_card_type' => $response['cc_card_type'],
                            )
                        );

                        // Handle Direct Debit SEPA payment
                    } elseif ($selected_payment == 'xt_novalnet_sepa') {
                        if (empty($_SESSION['xt_novalnet_sepa_data']['xt_novalnet_sepa_one_click_process'])) {
                            $payment_details = json_encode(array(
                                'iban' => $response['iban'],
                                'bankaccount_holder' => $response['bankaccount_holder'],
                            ));
                        }

                        // Handle PayPal payment
                    } elseif ($selected_payment == 'xt_novalnet_paypal' && empty($_SESSION['xt_novalnet_paypal_data']['xt_novalnet_paypal_one_click_process'])) {
                        $payment_details = json_encode(
                            array(
                                'paypal_transaction_id' => $response['paypal_transaction_id'],
                            )
                        );
                    }
                }
            }

            $instalmentdetails = self::storeInstalmentdetails($response, $total_amount);

            // Form values for Novalnet_transaction_details table
            $transaction_table_values = array(
                'tid'             => $response['tid'],
                'vendor_id'       => $vendor_details['vendor'],
                'authcode'        => $vendor_details['auth_code'],
                'tariff_id'       => $vendor_details['tariff'],
                'product_id'      => $vendor_details['product'],
                'payment_id'      => $payment_key,
                'payment_type'    => $selected_payment,
                'amount'          => $total_amount,
                'currency'        => $response['currency'],
                'gateway_status'  => $response['tid_status'],
                'test_mode'       => (int) $test_mode_value,
                'customer_id'     => $response['customer_no'],
                'order_no'        => $response['order_no'],
                'due_date'        => !empty($response['due_date']) ? $response['due_date'] : (!empty($response['cp_due_date']) ? $response['cp_due_date'] : ''),
                'date'            => date('Y-m-d H:i:s'),
                'org_total'       => $total_amount,
                'booked'          => $booked,
                'payment_ref'     => (int) (!empty($_SESSION[$selected_payment . '_data'][$selected_payment . '_one_click_process'])),
                'instalment_cycle_details' => $instalmentdetails,
                'payment_params'  => $payment_params,
                'payment_details' => $payment_details,
            );
            // Update the order status table
            $order->_updateOrderStatus($order_status_id, PHP_EOL . $comments, 'true', 'true');
            // Update the formed values to the tables
            self::update_tables($response, $callback_table_values, $transaction_table_values);

            // Unset Novalnet session
            self::unset_novalnet_session(array(
                $selected_payment,
                $selected_payment . '_data',
                'novalnet',
                'novalnet_selected_payment',
                'response_data',
            ));

            // Redirect to success page
            $xtLink->_link(array(
                'page' => 'checkout',
                'paction' => 'success?language=' . strtolower($response['lang']) . '&language=' . strtolower($response['lang']),
                'conn' => 'SSL'
            ));
        } else {
            $transaction_table_values = array(
                'tid'             => $response['tid'],
                'vendor_id'       => $vendor_details['vendor'],
                'authcode'        => $vendor_details['auth_code'],
                'tariff_id'       => $vendor_details['tariff'],
                'product_id'      => $vendor_details['product'],
                'payment_id'      => $payment_key,
                'payment_type'    => $selected_payment,
                'amount'          => $total_amount,
                'currency'        => $response['currency'],
                'gateway_status'  => $response['tid_status'],
                'test_mode'       => (int) $test_mode_value,
                'customer_id'     => $response['customer_no'],
                'order_no'        => $response['order_no'],
                'due_date'        => !empty($response['due_date']) ? $response['due_date'] : (!empty($response['cp_due_date']) ? $response['cp_due_date'] : ''),
                'date'            => date('Y-m-d H:i:s'),
                'org_total'       => $total_amount,
                'payment_ref'     => (int) (!empty($_SESSION[$selected_payment . '_data'][$selected_payment . '_one_click_process'])),
                'payment_params'  => '',
                'payment_details' => $payment_details,
            );
            // Update the formed values to the tables
            self::update_tables($response, $callback_table_values, $transaction_table_values);
            $test_mode = !empty($response['test_mode']) ? $response['test_mode'] : '';
            $comments = self::form_transaction_comments($response, $test_mode, $selected_payment);
            $comments .= self::set_response_message($response);
            $order_status_id = XT_NOVALNET_ONHOLD_CANCEL_STATUS;
            // Update the cancelled order status
            $order->_updateOrderStatus($order_status_id, PHP_EOL . $comments);
            $message = self::set_response_message($response);
            $message = in_array($selected_payment, array('xt_novalnet_eps')) ? utf8_decode($message) : $message;
            unset($_SESSION['last_order_id']);
            self::checkout_redirect_process($message);
        }
    }

    /**
     * Update the comments in db
     *
     * @param array   $response
     * @param array   $callback_table_values
     * @param array   $transaction_table_values
     */
    public static function update_tables($response, $callback_table_values, $transaction_table_values)
    {
        global $db, $order;
        // Insert the order details in Novalnet callback table
        $db->AutoExecute(DB_PREFIX . '_novalnet_callback_history', $callback_table_values);

        // Insert the order details in Novalnet transaction table
        $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', $transaction_table_values);

        // Insert the customer details in Affiliate details table
        if (!empty($_SESSION['nn_aff_id'])) {
            $db->AutoExecute(
                DB_PREFIX . '_novalnet_affiliate_user_detail',
                array(
                    'aff_id'       => $_SESSION['nn_aff_id'],
                    'customer_id'  => $_SESSION['customer']->customers_id,
                    'aff_order_no' => $response['order_no'],
                )
            );
        }
    }


    /**
     * Validates the server response hash value
     *
     * @param array $response
     */
    public static function check_hash_process($response)
    {
        if (self::novalnet_hash_value($response) != $response['hash2']) {
            self::checkout_redirect_process(XT_NOVALNET_CHECK_HASH_FAILED_TEXT);
        }
    }


    /**
     * Decodes the response data
     *
     * @param array $response
     */
    public static function decode_response_data(&$response)
    {

        foreach (
            array(
                'amount',
                'test_mode',
                'tariff',
                'auth_code',
                'product'
            ) as $value
        ) {
            $response[$value] = self::generate_decode_data($response[$value], $_SESSION['novalnet']['accesskey'], $response['uniqid']);
        }
    }

    /**
     * Generate unique id for payment call.
     *
     * @return int
     */
    public static function novalnet_get_uniqid()
    {
        $uniqid = explode(',', '8,7,6,5,4,3,2,1,9,0,9,7,6,1,2,3,4,5,6,7,8,9,0');
        shuffle($uniqid);
        return substr(implode('', $uniqid), 0, 16);
    }

    /**
     * Convert shop amount into server amount format
     *
     * @param float $amount
     * @return integer
     */
    public static function get_server_amount_format($amount)
    {
        return (sprintf('%0.2f', $amount) * 100);
    }


    /**
     * Assign redirect payment parameters
     *
     * @param array $parameters
     * @param string $code
     */
    public static function redirect_parameters(&$parameters, $code)
    {
        global $xtLink;
        $selected_shipping = $_SESSION['selected_shipping'];
        $accesskey = $_SESSION['novalnet']['accesskey'];

        // Set the redirect payment parameters
        $parameters['user_variable_0'] = $parameters['system_url'];
        $parameters['implementation']  = 'ENC';
        $parameters['return_method']   = $parameters['error_return_method'] = 'POST';
        $parameters['error_return_url'] = $parameters['return_url'] = str_replace(
            '&amp;',
            '&',
            $xtLink->_link(
                array(
                    'page'   => 'checkout',
                    'params' => "page_action=payment_process&novalnet_payment=$code&selected_shipping=$selected_shipping&accesskey=$accesskey",
                    'conn'   => 'SSL',
                )
            )
        );
        // Get the encoded params
        self::generate_hash_value($parameters);
    }

    /**
     * Redirect to checkout page with message
     *
     * @param array $message
     */
    public static function checkout_redirect_process($message)
    {
        global $xtLink;

        $_SESSION['info_handler']['data']['error'][] = array('info_message' => $message);

        $xtLink->_redirect(
            $xtLink->_link(
                array(
                    'page'   => 'checkout',
                    'params' => 'page_action=payment',
                    'conn'   => 'SSL',
                )
            )
        );
    }


    /**
     * Retrieve the Novalnet payment key
     *
     * @param string $payment_type
     * @param string $key
     * @return array
     */
    public static function get_payment_key($payment_type = '', $key = 'key')
    {
        $payment = array(
            'xt_novalnet_cc' => array(
                'payment_type' => 'CREDITCARD',
                'key' => '6',
            ),
            'xt_novalnet_eps' => array(
                'payment_type' => 'EPS',
                'key' => '50',
            ),
            'xt_novalnet_ideal' => array(
                'payment_type' => 'IDEAL',
                'key' => '49',
            ),
            'xt_novalnet_invoice' => array(
                'payment_type' => 'INVOICE',
                'key' => '27',
            ),
            'xt_novalnet_paypal' => array(
                'payment_type' => 'PAYPAL',
                'key' => '34',
            ),
            'xt_novalnet_prepayment' => array(
                'payment_type' => 'PREPAYMENT',
                'key' => '27',
            ),
            'xt_novalnet_sepa' => array(
                'payment_type' => 'DIRECT_DEBIT_SEPA',
                'key' => '37',
            ),
            'xt_novalnet_przelewy24' => array(
                'payment_type' => 'PRZELEWY24 ',
                'key' => '78',
            ),
            'xt_novalnet_invoice_guarantee' => array(
                'payment_type' => 'GUARANTEED_INVOICE',
                'key' => '41',
            ),
            'xt_novalnet_instalment_invoice' => array(
                'payment_type' => 'INSTALMENT_INVOICE',
                'key' => '96',
            ),
            'xt_novalnet_instalment_sepa' => array(
                'payment_type' => 'INSTALMENT_DIRECT_DEBIT_SEPA',
                'key' => '97',
            ),
            'xt_novalnet_sepa_guarantee' => array(
                'payment_type' => 'GUARANTEED_DIRECT_DEBIT_SEPA',
                'key' => '40',
            ),
            'xt_novalnet_twint' => array(
                'payment_type' => 'TWINT',
                'key' => '151',
            ),
            'xt_novalnet_onlinebanktransfer' => array(
                'payment_type' => 'ONLINE_BANK_TRANSFER',
                'key' => '113',
            ),
        );

        if ($payment_type != '') {
            return $payment[$payment_type][$key];
        }
        return $payment;
    }

    /**
     * Get basic template details
     *
     * @param string $code
     *
     * @return array
     */
    public static function get_basic_template_details($code)
    {
        $data = array(
            'payment_logo'     => defined('XT_NOVALNET_PAYMENT_LOGO_ENABLE') ? XT_NOVALNET_PAYMENT_LOGO_ENABLE : '',
            'test_mode'        => constant(strtoupper($code) . '_TEST_MODE'),
            'user_info'        => trim((strip_tags(constant(strtoupper($code) . '_INFORMATION_TO_USER')))),
            'payment_selected' => self::get_last_payment(),
            'payment_logo_src' => "plugins/$code/images/$code.png",
        );

        return $data;
    }

    /**
     * Get guarantee details
     *
     * @param array $data
     * @param string $code
     *
     */
    public static function get_guarantee_details(&$data, $code)
    {

        global $currency, $xtPlugin, $price;

        if ($code == 'xt_novalnet_instalment_invoice' || $code == 'xt_novalnet_instalment_sepa') {
            $data[$code . '_enable_guarantee_payment'] = true;
        } else {
            $data[$code . '_enable_guarantee_payment'] = (constant(strtoupper($code) . '_ENABLE_GUARANTEE_PAYMENT') == '1');
        }

        if (!empty($xtPlugin->active_modules['xt_novalnet_config']) && $data[$code . '_enable_guarantee_payment']) {

            $minimum_amount = (int) trim(constant(strtoupper($code) . '_GUARANTEE_PAYMENT_MIN_AMOUNT'));
            if ($minimum_amount === 0) {
                $minimum_amount = in_array($code, ['xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'], true) ? 1998 : 999;
            }

            $data['guarantee_minimum_amount_error_text'] = sprintf(XT_NOVALNET_BASIC_GUARANTEE_MINIMUM_AMOUNT_ERROR_TEXT, (str_replace('.', ',', $minimum_amount / 100)));

            $order_amount   = (int) self::get_server_amount_format($_SESSION['cart']->total['plain']);

            // Billing address
            $billing_address = array(
                'country'   => $_SESSION['customer']->customer_payment_address['customers_country_code'],
                'post_code' => $_SESSION['customer']->customer_payment_address['customers_postcode'],
                'city'      => $_SESSION['customer']->customer_payment_address['customers_city'],
                'address'   => $_SESSION['customer']->customer_payment_address['customers_street_address'],
                'address2'  => $_SESSION['customer']->customer_payment_address['customers_suburb'],
            );

            //company value
            $company1 = $_SESSION['customer']->customer_payment_address['customers_company'];
            $company2 = $_SESSION['customer']->customer_payment_address['customers_company_2'];
            $company3 = $_SESSION['customer']->customer_payment_address['customers_company_3'];
            $company_value = (!empty($company1) ? $company1 : (!empty($company2) ? $company2 : (!empty($company3) ? $company3 : '')));

            // Shipping address
            $shipping_address = array(
                'country'   => $_SESSION['customer']->customer_shipping_address['customers_country_code'],
                'post_code' => $_SESSION['customer']->customer_shipping_address['customers_postcode'],
                'city'      => $_SESSION['customer']->customer_shipping_address['customers_city'],
                'address'   => $_SESSION['customer']->customer_shipping_address['customers_street_address'],
                'address2'  => $_SESSION['customer']->customer_shipping_address['customers_suburb'],
            );

            $is_guarantee = (in_array($_SESSION['customer']->customer_default_address['customers_country_code'], self::$_allowed_countries) &&  $currency->code == 'EUR' && $order_amount >= $minimum_amount && $billing_address == $shipping_address);

            $force_guarantee = defined(strtoupper($code) . '_ENABLE_FORCE_NON_GUARANTEE_PAYMENT') && (constant(strtoupper($code) . '_ENABLE_FORCE_NON_GUARANTEE_PAYMENT') == '1');

            $_SESSION[$code]['force_guarantee'] = false;

            self::unset_guarantee_session($code);

            $check_guarantee_for_dob = (bool)false;
            if (
                $shipping_address != $billing_address ||
                $order_amount < $minimum_amount ||
                !in_array(
                    $_SESSION['customer']->customer_default_address['customers_country_code'],
                    self::$_allowed_countries
                ) ||
                $currency->code != 'EUR'
            ) {
                $check_guarantee_for_dob = (bool)true;
            }

            if (!$is_guarantee && !$force_guarantee) {

                $_SESSION[$code]['guarantee_payment'] = false;
                if ($shipping_address != $billing_address) {
                    $_SESSION[$code]['guarantee_payment_address_error'] = true;
                    $data[$code . '_guarantee_payment_address_error'] = true;
                }
                if ($order_amount < $minimum_amount) {
                    $_SESSION[$code]['guarantee_payment_mimimum_amount_error'] = true;
                    $data[$code . '_guarantee_payment_mimimum_amount_error'] = true;
                }
                if (!in_array($_SESSION['customer']->customer_default_address['customers_country_code'], self::$_allowed_countries)) {
                    $_SESSION[$code]['guarantee_payment_country_error'] = true;
                    $data[$code . '_guarantee_payment_country_error'] = true;
                }
                if ($currency->code != 'EUR') {
                    $_SESSION[$code]['guarantee_payment_currency_error'] = true;
                    $data[$code . '_guarantee_payment_currency_error'] = true;
                }
            } elseif ($is_guarantee && empty($company_value) && $force_guarantee && $check_guarantee_for_dob) {

                $_SESSION[$code]['guarantee_payment'] = true;
                $data['customers_dob']     = $_SESSION['customer']->customer_default_address['customers_dob'];
                $data['date_format_error'] = XT_NOVALNET_INVALID_DATE_FORMAT_ERROR_TEXT;
                $_SESSION[$code]['customer_company'] = false;
                $data[$code . '_show_dob_field'] = false;
            } elseif ($is_guarantee && empty($company_value) && $force_guarantee && !$check_guarantee_for_dob) {

                $_SESSION[$code]['guarantee_payment'] = true;
                $data['customers_dob']     = $_SESSION['customer']->customer_default_address['customers_dob'];
                $data['date_format_error'] = XT_NOVALNET_INVALID_DATE_FORMAT_ERROR_TEXT;
                $_SESSION[$code]['customer_company'] = true;
                $data[$code . '_show_dob_field'] = true;
            } elseif ($is_guarantee && empty($company_value) && !$force_guarantee && !$check_guarantee_for_dob) {

                $_SESSION[$code]['guarantee_payment'] = true;
                $data['customers_dob']     = $_SESSION['customer']->customer_default_address['customers_dob'];
                $data['date_format_error'] = XT_NOVALNET_INVALID_DATE_FORMAT_ERROR_TEXT;
                $_SESSION[$code]['customer_company'] = true;
                $data[$code . '_show_dob_field'] = true;
            } elseif ($is_guarantee && !empty($company_value)) {
                $_SESSION[$code]['guarantee_payment'] = true;
                $_SESSION[$code]['customer_company'] = false;
                $data[$code . '_show_dob_field'] = false;
                $data[$code . '_b2b_payment'] = true;
            } elseif (!$is_guarantee && $force_guarantee) {
                $_SESSION[$code]['force_guarantee'] = true;
            }
        } else {
            self::unset_guarantee_session($code);
        }
    }

    /**
     *Unset the sessions for force guarantee
     *
     */
    public static function unset_guarantee_session($payment)
    {
        unset($_SESSION[$payment]['guarantee_payment']);
        unset($_SESSION[$payment]['guarantee_payment_country_error']);
        unset($_SESSION[$payment]['guarantee_payment_currency_error']);
        unset($_SESSION[$payment]['guarantee_payment_address_error']);
        unset($_SESSION[$payment]['guarantee_payment_mimimum_amount_error']);
    }

    /**
     * Validate guarantee process
     *
     * @param string $code
     *
     */
    public static function validate_guarantee_process($code)
    {
        $message = '';
        $_SESSION[$code]['process_guarantee'] = false;
        if (isset($_SESSION[$code]['guarantee_payment'])) {
            if (!empty($_SESSION[$code]['guarantee_payment'])) {

                $_SESSION[$code]['process_guarantee'] = true;
                if (!empty($_SESSION[$code]['customer_company'])) {

                    $dob = $_SESSION[$code . '_data'][$code . '_user_dob'];

                    if (empty($dob)) {
                        $message = XT_NOVALNET_ENTER_DOB_ERROR_TEXT;
                    } elseif (time() < strtotime('+18 years', strtotime($dob))) {
                        $message = XT_NOVALNET_BELOW_DOB_ERROR_TEXT;
                    }
                    if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dob)) {
                        $message = XT_NOVALNET_ENTER_VALID_DOB_ERROR_TEXT;
                    }
                }
            } else {
                $message .= XT_NOVALNET_BASIC_GUARANTEE_REQUIREMENT_MISSING_ERROR_TEXT . PHP_EOL;
                if (!empty($_SESSION[$code]['guarantee_payment_country_error'])) {
                    $message .= XT_NOVALNET_BASIC_GUARANTEE_COUNTRY_MISMATCH_ERROR_TEXT . PHP_EOL;
                }
                if (!empty($_SESSION[$code]['guarantee_payment_currency_error'])) {
                    $message .= XT_NOVALNET_BASIC_GUARANTEE_CURRENCY_INVALID_ERROR_TEXT . PHP_EOL;
                }
                if (!empty($_SESSION[$code]['guarantee_payment_address_error'])) {
                    $message .= XT_NOVALNET_BASIC_GUARANTEE_ADDRESS_MISMATCH_ERROR_TEXT . PHP_EOL;
                }
                if (!empty($_SESSION[$code]['guarantee_payment_mimimum_amount_error'])) {
                    $minimum_amount = (int) trim(constant(strtoupper($code) . '_GUARANTEE_PAYMENT_MIN_AMOUNT')) != '' ? (int) trim(constant(strtoupper($code) . '_GUARANTEE_PAYMENT_MIN_AMOUNT')) : 999;
                    $message .= sprintf(XT_NOVALNET_BASIC_GUARANTEE_MINIMUM_AMOUNT_ERROR_TEXT, str_replace('.', ',', ($minimum_amount / 100))) . PHP_EOL;
                }
            }
        }

        if (defined(strtoupper($code) . '_ENABLE_FORCE_NON_GUARANTEE_PAYMENT') && constant(strtoupper($code) . '_ENABLE_FORCE_NON_GUARANTEE_PAYMENT') == '1' && $message !== '') {
            $_SESSION[$code]['guarantee_payment'] = $_SESSION[$code]['process_guarantee'] = false;
            $message = '';
        }

        return $message;
    }

    /**
     * Validate Alpha Numeric
     *
     * @param array $input
     * @return boolean
     *
     */
    public static function validate_alpha_numeric($input)
    {
        return empty($input) || preg_match('/[#%\^<>@$=*!]/', $input);
    }

    /**
     * Get shopping type details
     *
     * @param array $data
     * @param string $code
     *
     */
    public static function get_shopping_type_details(&$data, $code)
    {
        $shopping_type   = constant(strtoupper($code) . '_SHOPPING_TYPE');
        $data['one_click_process_enabled'] = false;
        $user_masked_data = array();

        // Check for on click process
        if ($shopping_type == 'ONECLICK') {
            $data['one_click_enabled_desc'] = true;
            $data['one_click_process_enabled'] = true;
            $data['given_details_style']  = 'display:block';
            $data['new_details_style']    = 'display:none';
            $user_masked_data               = self::get_masking_details($code);
            if (!empty($user_masked_data)) {

                // Assign masked TID in SESSION
                $_SESSION[$code]['masked_tid'] = $user_masked_data['tid'];
            } else {
                $data['one_click_process_enabled'] = false;
                $data['given_details_style']  = 'display:none';
                $data['new_details_style']    = 'display:block';
            }
        } else {
            $data['given_details_style']  = 'display:none';
            $data['new_details_style']    = 'display:block';
        }
        // Check for on click process
        if ($shopping_type == 'ZEROAMOUNT') {
            $data['zero_amount_process_enabled'] = true;
        }
        $data['user_masked_data'] = $user_masked_data;
    }

    /**
     * Get customer data
     *
     * @param array $data
     *
     */
    function novalnet_getCustomer_data($data)
    {
        global $db, $store_handler, $xtPlugin;
        if (!isset($_SESSION['registered_customer'])) {
            $sql = "SELECT customers_id FROM " . TABLE_CUSTOMERS . " where customers_email_address = ? and account_type = '0'";
            $sql_ar = array($data['email']);
            $check_shop_id = true;
            ($plugin_code = $xtPlugin->PluginCode('class.novalnet.php:checkCustomer_check_shop_id')) ? eval($plugin_code) : false;
            if ($check_shop_id) {
                $sql .= " AND shop_id=? ";
                $sql_ar[] = $store_handler->shop_id;
            }

            $record = $db->Execute($sql, $sql_ar);

            if ($record != false || $record->RecordCount() != 0) {
                $cID = $record->fields['customers_id'];
            }

            $_SESSION['registered_customer'] = $cID;
            $_SESSION['customer']->_customer($cID);

            if (empty($_SESSION['registered_customer'])) {
                if (!empty($data['customer_no'])) {
                    $_SESSION['registered_customer'] = $data['customer_no'];
                    $_SESSION['customer']->_customer($data['customer_no']);
                } else {
                    $sql = "SELECT customers_id FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = ? and account_type = '1'";
                    $sql_ar = array($data['email']);
                    $check_shop_id = true;
                    if ($check_shop_id) {
                        $sql .= " AND shop_id=?";
                        $sql_ar[] = $store_handler->shop_id;
                    }
                    $sql .= " ORDER BY customers_id DESC LIMIT 1";
                    $record = $db->Execute($sql, $sql_ar);

                    if ($record != false || $record->RecordCount() != 0) {
                        $guest_customer_id = $record->fields['customers_id'];
                        $_SESSION['registered_customer'] = $guest_customer_id;
                        $_SESSION['customer']->_customer($guest_customer_id);
                    }
                }
            }

            $_SESSION['cart']->_restore();

            ($plugin_code = $xtPlugin->PluginCode('novalnet:login_customer')) ? eval($plugin_code) : false;
        }
    }

    /**
     * Check and unset Novalnet session
     *
     * @param string|array $data
     */
    public static function unset_novalnet_session($data)
    {
        if (is_array($data)) {
            foreach ($data as $session_data) {
                if (isset($_SESSION[$session_data])) {
                    unset($_SESSION[$session_data]);
                }
            }
        } else {
            if (isset($_SESSION[$data])) {
                unset($_SESSION[$data]);
            }
        }
    }
}
