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
 * callback_xt_novalnet_config Class
 */

use Symfony\Component\HttpFoundation\Request;

class callback_xt_novalnet_config extends callback
{
    /**
     * @Array Type of payment available - Level: 0
     */
    protected $payments = array(
        'CREDITCARD',
        'INVOICE_START',
        'DIRECT_DEBIT_SEPA',
        'GUARANTEED_DIRECT_DEBIT_SEPA',
        'GUARANTEED_INVOICE',
        'PAYPAL',
        'ONLINE_TRANSFER',
        'IDEAL',
        'EPS',
        'GIROPAY',
        'PRZELEWY24',
        'CASHPAYMENT',
        'TWINT',
        'ONLINE_BANK_TRANSFER',
        'INSTALMENT_INVOICE',
        'INSTALMENT_DIRECT_DEBIT_SEPA',

    );

    /**
     * @Array Type of Chargebacks available - Level: 1
     */
    protected $chargebacks = array(
        'RETURN_DEBIT_SEPA',
        'CREDITCARD_BOOKBACK',
        'CREDITCARD_CHARGEBACK',
        'REFUND_BY_BANK_TRANSFER_EU',
        'PAYPAL_BOOKBACK',
        'REVERSAL',
        'PRZELEWY24_REFUND',
        'CASHPAYMENT_REFUND',
        'GUARANTEED_SEPA_BOOKBACK',
        'GUARANTEED_INVOICE_BOOKBACK',
        'TWINT_REFUND',
        'TWINT_CHARGEBACK',
        'PAYPAL_CHARGEBACK',
        'INSTALMENT_SEPA_BOOKBACK',
        'INSTALMENT_INVOICE_BOOKBACK'
    );

    /**
     * @Array Type of CreditEntry payment and Collections available - Level: 2
     */
    protected $collections = array(
        'INVOICE_CREDIT',
        'CASHPAYMENT_CREDIT',
        'CREDIT_ENTRY_CREDITCARD',
        'CREDIT_ENTRY_SEPA',
        'DEBT_COLLECTION_SEPA',
        'DEBT_COLLECTION_CREDITCARD',
        'ONLINE_TRANSFER_CREDIT',
        'CREDIT_ENTRY_DE',
        'DEBT_COLLECTION_DE',
    );

    /**
     * @Array Payment groups for particular payment type
     */
    protected $payment_groups = array(
        'xt_novalnet_cc'                => array(
            'CREDITCARD',
            'CREDITCARD_BOOKBACK',
            'CREDITCARD_CHARGEBACK',
            'CREDIT_ENTRY_CREDITCARD',
            'DEBT_COLLECTION_CREDITCARD',
        ),
        'xt_novalnet_sepa'               => array(
            'DIRECT_DEBIT_SEPA',
            'RETURN_DEBIT_SEPA',
            'DEBT_COLLECTION_SEPA',
            'CREDIT_ENTRY_SEPA',
            'REFUND_BY_BANK_TRANSFER_EU',
            'GUARANTEED_SEPA_BOOKBACK',
            'GUARANTEED_DIRECT_DEBIT_SEPA'
        ),
        'xt_novalnet_ideal'              => array(
            'IDEAL',
            'REVERSAL',
            'ONLINE_TRANSFER_CREDIT',
            'REFUND_BY_BANK_TRANSFER_EU',
            'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE',
        ),
        'xt_novalnet_instantbanktransfer' => array(
            'ONLINE_TRANSFER',
            'REFUND_BY_BANK_TRANSFER_EU',
            'ONLINE_TRANSFER_CREDIT',
            'REVERSAL',
            'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE',
        ),
        'xt_novalnet_onlinebanktransfer' => array(
            'ONLINE_BANK_TRANSFER',
            'REFUND_BY_BANK_TRANSFER_EU',
            'ONLINE_TRANSFER_CREDIT',
            'REVERSAL',
            'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE',
        ),
        'xt_novalnet_paypal'              => array(
            'PAYPAL',
            'PAYPAL_BOOKBACK',
            'PAYPAL_CHARGEBACK',
        ),
        'xt_novalnet_prepayment'          => array(
            'INVOICE_START',
            'INVOICE_CREDIT',
            'REFUND_BY_BANK_TRANSFER_EU'
        ),
        'xt_novalnet_cashpayment'          => array(
            'CASHPAYMENT',
            'CASHPAYMENT_CREDIT',
            'CASHPAYMENT_REFUND',
        ),
        'xt_novalnet_invoice'             => array(
            'INVOICE_START',
            'GUARANTEED_INVOICE',
            'INVOICE_CREDIT',
            'REFUND_BY_BANK_TRANSFER_EU',
            'GUARANTEED_INVOICE_BOOKBACK',
            'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE',
        ),
        'xt_novalnet_eps'                 => array(
            'EPS',
            'REFUND_BY_BANK_TRANSFER_EU',
            'ONLINE_TRANSFER_CREDIT',
            'REVERSAL',
            'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE',
        ),
        'xt_novalnet_giropay'             => array(
            'GIROPAY',
            'REFUND_BY_BANK_TRANSFER_EU',
            'ONLINE_TRANSFER_CREDIT',
            'REVERSAL',
            'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE',
        ),
        'xt_novalnet_przelewy24'          => array(
            'PRZELEWY24',
            'PRZELEWY24_REFUND'
        ),
        'xt_novalnet_twint' => array(
            'TWINT',
            'TWINT_REFUND',
            'TWINT_CHARGEBACK',
        ),
        'xt_novalnet_instalment_invoice' => array(
            'INSTALMENT_INVOICE',
            'INSTALMENT_INVOICE_BOOKBACK',
        ),
        'xt_novalnet_instalment_sepa' => array(
            'INSTALMENT_DIRECT_DEBIT_SEPA',
            'INSTALMENT_SEPA_BOOKBACK',
        )
    );

    /**
     * @Array Callback Capture parameters
     */
    protected $server_request = array();

    /**
     * @Object Order instance
     */
    protected $noval_order;

    /**
     * @String Received amount
     */
    protected $received_amount = '';

    /**
     * @Integer Order ID
     */
    protected $order_id;

    /**
     * @var Mail ID to be notify to technic
     */
    protected $technic_notify_mail = 'technic@novalnet.de';


    /**
     * @Array Required parameters
     */
    protected $params_required = array();

    /**
     * @Array Allowed transaction sttatus
     */
    protected $allowed_status = array(
        '100',
        '99',
        '98',
        '91',
        '90',
        '85',
        '86'
    );

    /**
     * Core initiative function
     *
     * @return array
     */
    function process()
    {
        global $test_mode, $price;
        $test_mode  = (XT_NOVALNET_CALLBACK_TESTMODE == 'true');

        $real_host_ip = gethostbyname('pay-nn.de');
        if (empty($real_host_ip)) {
            $this->display_message('Novalnet HOST IP missing');
        }

        // Get the client ip
        $remote_ip = self::get_client_ip($real_host_ip);

        // Validate Authenticated IP
        if (!$test_mode && $remote_ip != $real_host_ip) {
            $this->display_message('Novalnet callback received. Unauthorised access from the IP [' . $remote_ip . ']');
        }
        // Get request parameters
        $server_request = array_map('trim', $_REQUEST);

        // Sometimes order number can be available in order_id so we check this condition and set the order number
        $server_request['order_no'] = !empty($server_request['order_no']) ? $server_request['order_no'] : '';

        // Affiliate activation process
        if (isset($server_request['vendor_activation']) && $server_request['vendor_activation'] == '1') {
            $this->params_required = array(
                'vendor_id',
                'vendor_authcode',
                'product_id',
                'aff_id',
                'aff_authcode',
                'aff_accesskey',
            );

            $this->insert_affiliate_details($server_request);
        }

        $this->params_required = array(
            'vendor_id',
            'tid',
            'tid_status',
            'payment_type',
            'status',
        );
        $server_request['shop_tid'] = $server_request['tid'];
        if (in_array($server_request['payment_type'], array('INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA')) && $server_request['instalment_billing'] == 1 && !empty($server_request['instalment_tid'])) {
            $server_request['shop_tid'] = $server_request['instalment_tid'];
            array_push($this->params_required, 'instalment_tid');
        }

        if (in_array($server_request['payment_type'], array_merge($this->chargebacks, $this->collections))) {
            $server_request['shop_tid'] = $server_request['tid_payment'];
            array_push($this->params_required, 'tid_payment');
        }

        //Validate the request parameters
        $this->server_request      = $this->validate_capture_params($server_request);

        //Get the order reference
        $this->transaction_history = $this->get_order_reference();

        // Format amount as per shop structure
        if (isset($this->server_request['amount']) && isset($this->server_request['currency'])) {
            $price->_setCurrency($this->server_request['currency']);
            $formated_amount = $price->_Format(
                array(
                    'price'       => $this->server_request['amount'] / 100,
                    'format'      => true,
                    'format_type' => 'default'
                )
            );
            $this->received_amount = $formated_amount['formated'];
        }

        $this->order_id = $this->transaction_history['order_no'];
        $payment_type_level = $this->get_payment_type_level();

        if ($payment_type_level === 0) {
            // level 0 payments - Initial payments
            $this->zero_level_process();
        }

        if ($payment_type_level === 1) {
            // level 1 payments - Type of charge backs
            $this->first_level_process();
        }

        if ($payment_type_level === 2) {
            // level 2 payments - Type of credit entry
            $this->second_level_process();
        }
        // Validate the transaction status
        if ($server_request['payment_type'] == 'TRANSACTION_CANCELLATION' && !in_array($this->server_request['current_order_previous_status'], array('75', '91', '99', '85', '98'))) {
            $this->update_cancel_order_comments();
        }
        $error_text = (($server_request['status'] != 100 || $server_request['tid_status'] != 100) ? 'Novalnet callback received. Status is not valid.' : 'Novalnet callback received. Callback Script executed already.');
        $this->display_message($error_text);
    }

    /*
     * Get given payment_type level for process
     *
     * @return integer
     */
    function get_payment_type_level()
    {
        return in_array($this->server_request['payment_type'], $this->payments) ? 0 : (in_array($this->server_request['payment_type'], $this->chargebacks) ? 1 : (in_array($this->server_request['payment_type'], $this->collections) ? 2 : false));
    }

    /**
     * Callback API Level 1 process
     *
     */
    function zero_level_process()
    {

        global $db, $xtPlugin, $price;

        include_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'xt_novalnet_config/classes/class.novalnet.php';
        if (in_array($this->server_request['payment_type'], array('GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INVOICE_START', 'INSTALMENT_INVOICE', 'DIRECT_DEBIT_SEPA', 'INSTALMENT_DIRECT_DEBIT_SEPA', 'CREDITCARD', 'PAYPAL')) && in_array($this->server_request['tid_status'], array(91, 99, 100)) && $this->server_request['status'] == 100 && ((in_array($this->transaction_history['current_order_previous_status'], array(75, 91, 99, 85, 98))) || ($this->server_request['instalment_billing'] == 1) && $this->transaction_history['current_order_previous_status'] == 100)) {

            $price->_setCurrency($this->server_request['currency']);
            $formated_amt = $price->_Format(
                array(
                    'price'       => $this->server_request['amount'] / 100,
                    'format'      => true,
                    'format_type' => 'default'
                )
            );

            $this->server_request['formated_amount'] = $formated_amt['formated'];

            $comments = '';
            $test_mode_value         = ($this->server_request['test_mode'] == '1');
            if (in_array($this->server_request['tid_status'], array(99, 91)) && $this->transaction_history['current_order_previous_status'] == 75) {
                $order_status = defined('XT_NOVALNET_ONHOLD_COMPLETE_STATUS') ? trim(XT_NOVALNET_ONHOLD_COMPLETE_STATUS) : '';
                $callback_comments .= PHP_EOL . sprintf(XT_NOVALNET_GUARANTEE_PAYMENT_PENDING_TO_HOLD_MESSAGE, $this->server_request['shop_tid'], date_short(date('Y-m-d')), date('H:i:s')) . PHP_EOL;
                $callback_comments                .= self::form_transaction_comments($this->server_request, $test_mode_value);
                $comments .= $callback_comments;
                if (in_array($this->server_request['payment_type'], array('GUARANTEED_INVOICE', 'INSTALMENT_INVOICE'))) {
                    $comments .= Novalnet::form_invoice_prepayment_comments($this->server_request, $this->server_request['product_id'], $this->server_request['payment_type']);
                }
            } else if ($this->server_request['tid_status'] == 100 && in_array($this->transaction_history['current_order_previous_status'], array(75, 91, 99, 98, 85))) {
                $callback_comments .= PHP_EOL . sprintf(XT_NOVALNET_CALLBACK_SCRIPT_GUARANTEE_TRANS_CONFIRM_SUCCESSFUL_MESSAGE, date("Y-m-d"), date('H:i:s')) . '<br><br>';
                $callback_comments                .= self::form_transaction_comments($this->server_request, $test_mode_value);
                if ($this->server_request['payment_type'] == 'GUARANTEED_INVOICE' &&  in_array($this->transaction_history['current_order_previous_status'], array(75, 91))) {
                    $order_status = XT_NOVALNET_INVOICE_CALLBACK_ORDER_STATUS;
                } else {
                    $order_status = constant(strtoupper($this->transaction_history['payment_type']) . '_ORDER_STATUS');
                }
                $update_parameters = array();
                $order_mail_send = false;
                $comments .= $callback_comments;
                if (in_array($this->server_request['payment_type'], array('GUARANTEED_INVOICE', 'INVOICE_START', 'INSTALMENT_INVOICE')) && in_array($this->transaction_history['current_order_previous_status'], array(75, 91))) {
                    $payment_details   = !empty($this->transaction_history['payment_details']) ? (array) json_decode($this->transaction_history['payment_details']) : '';
                    // Get bank details lower version
                    if (empty($payment_details)) {
                        $table = DB_PREFIX . '_novalnet_preinvoice_transaction_detail';
                        $result = $db->Execute("SHOW TABLES LIKE '" . $table . "'");
                        if ($result->RecordCount() > 0) {
                            $sql = $db->Execute("SELECT account_number, bank_code, bank_name, bank_city, bank_iban, bank_bic FROM $table WHERE order_no='" . $this->orders_id . "'");

                            $payment_details = array(
                                'account_holder'    => $sql->fields['account_holder'],
                                'invoice_account'   => $sql->fields['account_number'],
                                'invoice_bankcode'  => $sql->fields['bank_code'],
                                'invoice_iban'      => $sql->fields['bank_iban'],
                                'invoice_bic'       => $sql->fields['bank_bic'],
                                'invoice_bankname'  => $sql->fields['bank_name'],
                                'invoice_bankplace' => $sql->fields['bank_city'],
                            );
                            $update_parameters['payment_details'] = json_encode($payment_details);
                        }
                    }
                    $price->_setCurrency($this->transaction_history['currency']);
                    $formated_amount = $price->_Format(
                        array(
                            'price'       => $this->transaction_history['org_total'] / 100,
                            'format'      => true,
                            'format_type' => 'default'
                        )
                    );
                    $request = array_merge(
                        is_array($payment_details) ? $payment_details : [],
                        is_array($this->transaction_history) ? $this->transaction_history : []
                    );
                    $update_parameters['due_date'] = $request['due_date']  = $this->server_request['due_date'];
                    $request['formated_amount'] = $formated_amount['formated'];
                    if (!empty($this->transaction_history['instalment_cycle_details'])) {
                        $this->server_request['instalment_cycle_amount'] = $this->transaction_history['instalment_cycle_details'];
                    }
                    $comments .= Novalnet::form_invoice_prepayment_comments($this->server_request, $this->server_request['product_id'], $this->server_request['payment_type'], false);
                    $order_mail_send = true;
                }
                if (in_array($this->server_request['payment_type'], array('INSTALMENT_DIRECT_DEBIT_SEPA', 'INSTALMENT_INVOICE')) && in_array($this->transaction_history['current_order_previous_status'], array(75, 91)) && $this->server_request['tid_status'] == 100) {
                    $Instalment_cycle_details = Novalnet::storeInstalmentdetails($this->server_request, $this->server_request['amount']);
                    $update_parameters['instalment_cycle_details'] = $Instalment_cycle_details;
                }
            } elseif (
                in_array($this->server_request['payment_type'], array('INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA')) && $this->server_request['instalment_billing'] == 1 &&
                $this->server_request['tid_status'] == '100' && $this->server_request['status'] == '100' && $this->transaction_history['current_order_previous_status'] == '100'
            ) {
                $instalment_details = json_decode($this->transaction_history['instalment_cycle_details'], true);
                $cycle_index = $this->server_request['instalment_cycles_executed'] - 1;
                $instalment_details[$cycle_index]['next_instalment_date'] = (!empty($this->server_request['next_instalment_date'])) ? $this->server_request['next_instalment_date'] : '-';
                if (!empty($this->server_request['tid'])) {
                    $instalment_details[$cycle_index]['reference_tid'] = $this->server_request['tid'];
                    $instalment_details[$cycle_index]['status'] = 'Paid';
                    $instalment_details[$cycle_index]['paid_date'] = date('Y-m-d H:i:s');
                }
                $format_amount = Novalnet::amount_convert_shop_format($this->server_request['amount'] / 100, $this->server_request['currency']);
                $comments .= PHP_EOL . sprintf(XT_NOVALNET_INSTALMENT_NEW_INSTALMENT_TEXT, $this->server_request['instalment_tid'], $this->server_request['tid'], $format_amount, date_short(date("Y-m-d H:i:s"))) . '<br><br>';
                if ($this->server_request['payment_type'] == 'INSTALMENT_INVOICE') {
                    $invoice_format = $this->server_request;
                    $invoice_format['amount'] = $invoice_format['amount'] / 100;
                    $invoice_format['instalment_cycle_amount'] = $invoice_format['amount'];
                    $comments .= Novalnet::form_invoice_prepayment_comments($invoice_format, $this->server_request['product_id'], $this->server_request['payment_type']);
                } else {
                    $comments .= Novalnet::form_instalment_comments($this->server_request, $format_amount);
                }
                $callback_comments = $comments;
                $order_status = constant(strtoupper($this->transaction_history['payment_type']) . '_ORDER_STATUS');
                $update_parameters['instalment_cycle_details'] = json_encode($instalment_details);
            }

            if ((!empty($callback_comments) || !empty($comments)) && $order_status) {
                $update_parameters['gateway_status'] = $this->server_request['tid_status'];
                $statumail = ($order_mail_send) ? 'true' : 'false';
                $novalnet_order = new order($this->order_id, $this->transaction_history['customer_id']);
                $novalnet_order->_updateOrderStatus($order_status, PHP_EOL . $comments, $statumail, 'true');
                $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', $update_parameters, 'UPDATE', "tid='" . $this->server_request['shop_tid'] . "'");
                $this->send_notify_mail(
                    array(
                        'comments' => $callback_comments,
                        'order_no' => '',
                    )
                );
            }
        } else if (in_array($this->server_request['payment_type'], array('PAYPAL', 'PRZELEWY24')) && $this->server_request['tid_status'] == '100' && $this->server_request['status'] == '100') {

            // Fetch the total amount paid for the order
            $fetch_total_paid_amount = $db->Execute('SELECT SUM(amount) as amount_total FROM ' . DB_PREFIX . '_novalnet_callback_history WHERE order_no =' . $this->order_id);

            if ($fetch_total_paid_amount->fields['amount_total'] <= $this->transaction_history['org_total']) {
                // Payment success
                $callback_comments = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_ZERO_LEVEL_EXECUTED_TEXT, $this->server_request['shop_tid'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'));

                // Update callback comments in order status history table
                $this->update_callback_comments(
                    array(
                        'order_no'         => $this->order_id,
                        'comments'         => $callback_comments,
                        'orders_status_id' => ($this->server_request['payment_type'] == 'PAYPAL') ? XT_NOVALNET_PAYPAL_ORDER_STATUS : XT_NOVALNET_PRZELEWY24_ORDER_STATUS,
                    )
                );

                // Update current gateway status
                $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', array(
                    'gateway_status' => $this->server_request['tid_status']
                ), 'UPDATE', 'order_no = ' . $this->order_id);

                // Update the invoice details if exists
                if (!empty($xtPlugin->active_modules['xt_orders_invoices']) && file_exists(_SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php')) {
                    include_once _SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php';
                    $xt_orders_invoices = new xt_orders_invoices();
                    $exists = $xt_orders_invoices->isExistByOrderId($this->order_id);
                    if ($exists) {
                        $db->AutoExecute(TABLE_ORDERS_INVOICES, array(
                            'invoice_paid'    => '1',
                        ), 'UPDATE', 'orders_id = ' . $this->order_id);
                    }
                }

                // Log callback process (for all types of payments default)
                $this->log_callback_process();

                // Send notification mail to Merchant
                $this->send_notify_mail(
                    array(
                        'comments' => $callback_comments,
                        'order_no' => $this->order_id,
                    )
                );
            }
            $this->display_message('Novalnet Callbackscript received. Order already Paid');
        } else if ($this->server_request['payment_type'] == 'PRZELEWY24' && ($this->server_request['status'] != 100 || !in_array($this->server_request['tid_status'], array(100, 86)))) {
            //Przelewy payment failure
            $callback_comments = XT_NOVALNET_CALLBACK_SCRIPT_CANCEL_TEXT;
            $callback_comments .= isset($this->server_request['status_text']) ? '<br/>' . $this->server_request['status_text'] : (isset($this->server_request['status_desc']) ? '<br/>' . $this->server_request['status_desc'] : (isset($this->server_request['status_message']) ? '<br/>' . $this->server_request['status_message'] : ''));

            // Update callback comments in order status history table
            $this->update_callback_comments(
                array(
                    'order_no'         => $this->order_id,
                    'comments'         => $callback_comments,
                    'orders_status_id' => XT_NOVALNET_ONHOLD_CANCEL_STATUS,
                )
            );
            $this->display_message($callback_comments);
        } else if (in_array($this->server_request['payment_type'], array('GUARANTEED_INVOICE', 'INSTALMENT_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INSTALMENT_DIRECT_DEBIT_SEPA')) && $this->server_request['tid_status'] != 100 && $this->server_request['status'] != 100 && in_array($this->transaction_history['current_order_previous_status'], array(75, 91, 99))) {
            //payment failure

            $callback_comments .= sprintf(XT_NOVALNET_CALLBACK_SCRIPT_GUARANTEE_TRANS_CANCEL_SUCCESSFUL_MESSAGE,  date_short(date('Y-m-d')), date('H:i:s')) . '<br/>';
            // Update callback comments in order status history table

            $this->noval_order->_updateOrderStatus(XT_NOVALNET_ONHOLD_CANCEL_STATUS, PHP_EOL . $callback_comments . '<br/><br/>', 'false', 'true', 'callback', '0', '');
            $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', array(
                'gateway_status' => $this->server_request['tid_status']
            ), 'UPDATE', 'order_no = ' . $this->order_id);
            $this->display_message($callback_comments);
        } else {
            $this->display_message('Novalnet Callbackscript received. Payment type (' . $this->server_request['payment_type'] . ') is not applicable for this process!');
        }
    }

    /**
     * Callback API Level 1 process
     *
     */
    function first_level_process()
    {
        global $db;

        if (in_array($this->server_request['payment_type'], $this->chargebacks) && $this->server_request['status'] == '100' && $this->server_request['tid_status'] == '100') {

            // Charge back & Book back text

            $message = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_CHARGEBACK_TEXT, $this->server_request['tid_payment'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'), $this->server_request['tid']);

            if ($this->server_request['payment_type'] == 'REVERSAL') {
                $message = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_REVERSAL_TEXT,  $this->server_request['tid_payment'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'), $this->server_request['tid']);
            }

            if ($this->server_request['payment_type'] == 'RETURN_DEBIT_SEPA') {
                $message = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_RETURN_DEBIT_TEXT,  $this->server_request['tid_payment'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'), $this->server_request['tid']);
            }

            if (in_array($this->server_request['payment_type'], array('CREDITCARD_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'CASHPAYMENT_REFUND', 'GUARANTEED_SEPA_BOOKBACK', 'GUARANTEED_INVOICE_BOOKBACK', 'TWINT_REFUND', 'INSTALMENT_SEPA_BOOKBACK', 'INSTALMENT_INVOICE_BOOKBACK'))) {
                $message = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_BOOKBACK_TEXT,  $this->server_request['tid_payment'], $this->received_amount, $this->server_request['tid']);
                $is_instalment_bookback = in_array($this->server_request['payment_type'], array('INSTALMENT_SEPA_BOOKBACK', 'INSTALMENT_INVOICE_BOOKBACK'));
                $old_value = $db->Execute('SELECT org_total, refunded_amount FROM ' . DB_PREFIX . '_novalnet_transaction_detail WHERE tid="' . $this->server_request['shop_tid'] . '"' . ' or order_no = ' . $this->server_request['order_no']);
                $update_details = [];
                if ($is_instalment_bookback) {
                    $instalment_details = json_decode($this->transaction_history['instalment_cycle_details'], true);
                    $parent_tid = !empty($this->server_request['tid_payment']) ? $this->server_request['tid_payment'] : $this->server_request['instalment_tid'];
                    if (!empty($instalment_details)) {
                        foreach ($instalment_details as $cycle => $cycle_details) {
                            if (!empty($cycle_details['reference_tid']) && ($cycle_details['reference_tid'] == $parent_tid)) {
                                $instalment_amount = $instalment_details[$cycle]['instalment_cycle_amount'] - $this->server_request['amount'];
                                $instalment_details[$cycle]['instalment_cycle_amount'] = $instalment_amount;
                                if ($instalment_details[$cycle]['instalment_cycle_amount'] <= 0) {
                                    $instalment_details[$cycle]['status'] = 'Refunded';
                                }
                            }
                        }
                    }
                    $instalment_details = json_encode($instalment_details);
                    $update_details['instalment_cycle_details'] = $instalment_details;
                }

                if ($old_value && !$old_value->EOF) {
                    $original_amount = (float)$old_value->fields['org_total'];
                    $refund_amount   = (float)$old_value->fields['refunded_amount'] + (float)$this->server_request['amount'];

                    $update_details['refunded_amount'] = $refund_amount;
                    if (!$is_instalment_bookback) {
                        $refund_order_status = ($refund_amount < $original_amount) ? '61' : '60';
                    }
                    $db->AutoExecute(
                        DB_PREFIX . '_novalnet_transaction_detail',
                        $update_details,
                        'UPDATE',
                        "tid='" . $this->server_request['shop_tid'] . "' or order_no='" . $this->server_request['order_no'] . "'"
                    );
                }
            }
            $callback_comments = $message;

            $this->update_callback_comments(
                array(
                    'order_no'         => $this->order_id,
                    'comments'         => $callback_comments,
                    'orders_status_id' => $refund_order_status ?? $this->transaction_history['order_current_status'],
                )
            );


            // Send notification mail to Merchant
            $this->send_notify_mail(
                array(
                    'comments' => $callback_comments,
                    'order_no' => $this->order_id,
                )
            );
        }
    }

    /**
     * Callback API Level 2 process
     *
     */
    function second_level_process()
    {
        global $db, $xtPlugin;

        if (in_array($this->server_request['payment_type'], $this->collections) && $this->server_request['status'] == '100' && $this->server_request['tid_status'] == '100') {
            $callback_secound_level_payment_execute = false;
            if (in_array($this->server_request['payment_type'], array('INVOICE_CREDIT', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT'))) {

                // Fetch the total amount paid for the order
                $fetch_total_paid_amount = $db->Execute('SELECT SUM(amount) as amount_total FROM ' . DB_PREFIX . '_novalnet_callback_history WHERE order_no =' . $this->order_id);

                $total_paid_amount = $fetch_total_paid_amount->fields;

                if ($total_paid_amount['amount_total'] < $this->transaction_history['org_total']) {

                    // Form the comments for the amount paid
                    $callback_comments = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_EXECUTED_TEXT, $this->server_request['shop_tid'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'), $this->server_request['tid']);

                    if ($this->server_request['payment_type'] == 'ONLINE_TRANSFER_CREDIT') {

                        $callback_comments = '<br>' . sprintf(XT_NOVALNET_CALLBACK_SCRIPT_EXECUTED_EXTRA_TEXT, $this->server_request['shop_tid'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'), $this->server_request['tid']);

                        $callback_status_id = constant(strtoupper($this->transaction_history['payment_type']) . '_ORDER_STATUS');
                    }

                    if ($this->transaction_history['org_total'] <= ($total_paid_amount['amount_total'] + $this->server_request['amount'])) {
                        $callback_secound_level_payment_execute = false;
                        // Full Payment paid
                        $callback_status_id = (in_array($this->server_request['payment_type'], array('INVOICE_CREDIT', 'CASHPAYMENT_CREDIT'))) ? constant(strtoupper($this->transaction_history['payment_type']) . '_CALLBACK_ORDER_STATUS') : $this->transaction_history['order_current_status'];

                        if ($this->server_request['payment_type'] == 'ONLINE_TRANSFER_CREDIT') {

                            $callback_status_id = constant(strtoupper($this->transaction_history['payment_type']) . '_ORDER_STATUS');
                        }
                        // Form transaction comments
                        $test_mode = (!empty($this->server_request['test_mode']) && $this->server_request['test_mode'] == '1');
                        $callback_sever_response['tid'] = $this->server_request['shop_tid'];
                        $transaction_comments  = self::form_transaction_comments($callback_sever_response, $test_mode);

                        $shop_tid = $this->server_request['shop_tid'];

                        // Update the invoice details if exists
                        if (!empty($xtPlugin->active_modules['xt_orders_invoices']) && file_exists(_SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php')) {

                            include_once _SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php';

                            $xt_orders_invoices = new xt_orders_invoices();

                            $exists = $xt_orders_invoices->isExistByOrderId($this->order_id);
                            if ($exists) {
                                $db->AutoExecute(TABLE_ORDERS_INVOICES, array(
                                    'invoice_paid'    => '1',
                                    'invoice_comment' => $transaction_comments,
                                ), 'UPDATE', 'orders_id = ' . $this->order_id);
                            }
                        }
                        // Update the comments after paid full amount
                        $db->Execute("UPDATE " . TABLE_ORDERS_STATUS_HISTORY . " SET comments='" . $transaction_comments . "' WHERE orders_id='" . $this->order_id . "' AND comments LIKE '%$shop_tid%' ORDER BY orders_status_history_id ASC LIMIT 1");

                        $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', array(
                            'gateway_status' => $this->server_request['tid_status']
                        ), 'UPDATE', 'order_no = ' . $this->order_id);
                        $this->noval_order->_updateOrderStatus($callback_status_id, PHP_EOL . $callback_comments . '<br/><br/>', 'true', 'true', 'callback', '0', '');
                        // Log callback process (for all types of payments default)
                        $this->log_callback_process();

                        // Send notification mail to Merchant
                        $this->send_notify_mail(
                            array(
                                'comments'        => $callback_comments,
                                'order_no'        => $this->order_id,
                            )
                        );
                    } else {
                        $callback_secound_level_payment_execute = true;
                        // Partial Payment paid
                        $callback_status_id = $this->transaction_history['order_current_status'];
                    }
                }
                if (!$callback_secound_level_payment_execute) {
                    $this->display_message('Novalnet callback received. Callback Script executed already. Refer Order :' . $this->order_id);
                }
            } else {
                $callback_secound_level_payment_execute = true;
                $callback_comments = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_EXECUTED_TEXT, $this->server_request['shop_tid'], $this->received_amount, date_short(date('Y-m-d')), date('H:i:s'), $this->server_request['tid']);
                $callback_status_id = $this->transaction_history['order_current_status'];
            }
            if ($callback_secound_level_payment_execute) {
                // Update callback comments in order status history table
                $this->update_callback_comments(
                    array(
                        'order_no'         => $this->order_id,
                        'comments'         => $callback_comments,
                        'orders_status_id' => $callback_status_id,
                    )
                );
                // Log callback process (for all types of payments default)
                $this->log_callback_process();

                // Send notification mail to Merchant
                $this->send_notify_mail(
                    array(
                        'comments'        => $callback_comments,
                        'order_no'        => $this->order_id,
                    )
                );
            }
        }
    }

    /**
     * Update Callback comments in shop order tables
     *
     * @param array $data
     * @param boolean $send_order_mail
     */
    function update_callback_comments($data, $send_order_mail = false)
    {
        $this->noval_order->_updateOrderStatus($data['orders_status_id'], PHP_EOL . $data['comments'] . '<br/><br/>', 'true', 'true', 'callback', '0', '');
        if ($send_order_mail)
            $this->noval_order->_sendOrderMail();
    }


    /**
     * Send notification mail to Merchant
     *
     * @param array $data
     */
    function send_notify_mail($data, $build_technic_mail = false)
    {
        $email_to = ($build_technic_mail) ? $this->technic_notify_mail : XT_NOVALNET_CALLBACK_MAIL_TO;
        if (!empty($email_to) && filter_var($email_to, FILTER_VALIDATE_EMAIL)) {
            $exportMail = new xtMailer('none');
            $exportMail->_setFrom(_CORE_DEBUG_MAIL_ADDRESS, _STORE_NAME);
            $exportMail->_addReceiver($email_to, _STORE_NAME);
            $exportMail->_setSubject(_STORE_NAME . ' - Novalnet Webhook notification');

            $exportMail->_setContent('', $data['comments']);
            $exportMail->_sendMail();
        }

        $comments = PHP_EOL . $data['comments'];
        $this->display_message($comments);
    }


    /**
     * Get order reference from the novalnet_transaction_detail table
     *
     * @return array
     */
    function get_order_reference()
    {
        global $db;

        $where = 'tid = ' . $this->server_request['shop_tid'];

        if (!empty($this->server_request['order_no'])) {
            $where .= ' or order_no = ' . $this->server_request['order_no'];
        }

        $order_query = $db->Execute('SELECT tid, customer_id, order_no, gateway_status, org_total, payment_type, payment_details, instalment_cycle_details FROM ' . DB_PREFIX . '_novalnet_transaction_detail WHERE ' . $where);
        $db_value    = $order_query->fields;
        $order_no    = !empty($db_value['order_no']) ? $db_value['order_no'] : '';
        $db_value['current_order_previous_status'] = $db_value['gateway_status'];
        $order_no    = !empty($db_value['order_no']) ? $db_value['order_no'] : '';
        $customer_id = !empty($db_value['customer_id']) ? $db_value['customer_id'] : '';
        if (empty($order_no)) {
            if (empty($this->server_request['order_no'])) {
                $this->display_message('Transaction mapping failed');
            }
            // Handle communication failure
            $this->handle_communication_failure();
        }

        if ($order_no) {
            if (!empty($this->server_request['order_no']) && ($order_no != $this->server_request['order_no'])) {
                $this->display_message('Transaction mapping failed');
            }
            // Create order instance
            $this->noval_order = new order($order_no, $customer_id);
            $db_value['order_current_status'] = $this->noval_order->order_data['orders_status_id'];
            // Check payment group for particular payment type
            if (! in_array($this->server_request['payment_type'], $this->payment_groups[$db_value['payment_type']]) && $this->server_request['payment_type'] != 'TRANSACTION_CANCELLATION') {
                $this->display_message('Novalnet callback received. Payment Type [' . $this->server_request['payment_type'] . '] is not matched with ' . $db_value['payment_type'] . '!.');
            }
        }
        return $db_value;
    }

    /**
     * Inserting Affiliate Details
     *
     * @param array $server_request
     *
     */
    function insert_affiliate_details($server_request)
    {
        global $db;
        // Validate required parameters
        foreach ($this->params_required as $v) {
            if (empty($server_request[$v])) {
                $this->display_message('Required param (' . $v . ') missing!');
            }
        }
        // Insert affiliate details
        $db->AutoExecute(
            DB_PREFIX . '_novalnet_affiliate_account_detail',
            array(
                'vendor_id'       => $server_request['vendor_id'],
                'vendor_authcode' => $server_request['vendor_authcode'],
                'product_id'      => $server_request['product_id'],
                'product_url'     => $server_request['product_url'],
                'activation_date' => (isset($server_request['activation_date']) && $server_request['activation_date'] ? date('Y-m-d  H:i:s', strtotime($server_request['activation_date'])) : date('Y-m-d H:i:s')),
                'aff_id'          => $server_request['aff_id'],
                'aff_authcode'    => $server_request['aff_authcode'],
                'aff_accesskey'   => $server_request['aff_accesskey'],
            )
        );
        $comments = 'Novalnet Callback received. Affiliate details has been added successfully.';
        // Send notification mail to Merchant
        $this->send_notify_mail(
            array(
                'comments' => $comments,
                'order_no' => '',
            )
        );
    }


    /**
     * Perform parameter validation process
     *
     * @param array $server_request
     *
     * @return array
     */
    function validate_capture_params($server_request)
    {
        // Validate required parameters
        foreach ($this->params_required as $key) {
            if (empty($server_request[$key])) {
                $this->display_message('Required param (' . $key . ') missing!');
            }

            if (in_array($key, array('tid', 'tid_payment', 'signup_tid', 'instalment_tid')) && ! preg_match('/^\d{17}$/', $server_request[$key])) {
                $this->display_message('Novalnet callback received. Invalid TID [' . $server_request[$key] . '] for Order.');
            }
        }

        if (! in_array($server_request['payment_type'], array_merge($this->payments, $this->chargebacks, $this->collections)) && $server_request['payment_type'] != 'TRANSACTION_CANCELLATION') {
            $this->display_message('Novalnet callback received. Payment type (' . $server_request['payment_type'] . ') is mismatched!');
        }
        return $server_request;
    }


    /**
     * Handling communication failure for the orders
     */
    function handle_communication_failure()
    {
        global $db, $xtPlugin;
        $order_query = $db->Execute('SELECT customers_id, payment_code FROM ' . TABLE_ORDERS . ' WHERE orders_id=' . $this->server_request['order_no']);
        $this->noval_order = new order($this->server_request['order_no'], $order_query->fields['customers_id']);
        if (isset($this->noval_order->order_data['customers_id'])) {
            if (! $this->server_request['status']) {
                $this->display_message('Transaction not found');
            }
            if (! in_array($this->server_request['payment_type'], $this->payment_groups[$this->noval_order->order_data['payment_code']])) {
                $this->display_message('Novalnet callback received. Payment Type [' . $this->server_request['payment_type'] . '] is not valid.');
            }
            // Get vendor and authcode details
            $vendor_id        = trim(XT_NOVALNET_VENDOR_ID);
            $vendor_authcode = trim(XT_NOVALNET_AUTH_CODE);
            if ($vendor_id != $this->server_request['vendor_id']) {
                $aff_authcode = $db->GetOne('SELECT aff_authcode FROM  ' . DB_PREFIX . '_novalnet_affiliate_account_detail WHERE aff_id = ' . addslashes($this->server_request['vendor_id']));
                if (!empty($aff_authcode)) {
                    $vendor_id        = $this->server_request['vendor_id'];
                    $vendor_authcode = $aff_authcode;
                }
            }
            // Assign product and tariff id
            $product_id = trim(XT_NOVALNET_PRODUCT_ID);
            $tariff_id  = trim(XT_NOVALNET_TARIFF_ID);
            // Handle INVOICE_START process
            if ($this->server_request['payment_type'] == 'INVOICE_START') {

                $masking_params = array(
                    'account_holder'    => $this->server_request['invoice_account_holder'],
                    'invoice_account'   => $this->server_request['invoice_account'],
                    'invoice_bankcode'  => $this->server_request['invoice_bankcode'],
                    'invoice_iban'      => $this->server_request['invoice_iban'],
                    'invoice_bic'       => $this->server_request['invoice_bic'],
                    'invoice_bankname'  => $this->server_request['invoice_bankname'],
                    'invoice_bankplace' => $this->server_request['invoice_bankplace'],
                );
            }
            $payment_id = $this->paymentType($this->noval_order->order_data['payment_code']);
            if ($this->server_request['payment_type'] == 'GUARANTEED_INVOICE') {
                $payment_id = '41';
            } elseif ($this->server_request['payment_type'] == 'GUARANTEED_DIRECT_DEBIT_SEPA') {
                $payment_id = '40';
            }
            // Insert the values in novalnet_transaction_detail table
            $table_values = array(
                'tid'             => $this->server_request['shop_tid'],
                'vendor_id'       => $vendor_id,
                'authcode'        => $vendor_authcode,
                'tariff_id'       => trim($tariff_id['1']),
                'product_id'      => $product_id,
                'payment_id'      => $payment_id,
                'payment_type'    => $this->noval_order->order_data['payment_code'],
                'amount'          => ($this->server_request['payment_type'] == 'ONLINE_TRANSFER_CREDIT') ? sprintf('%0.2f', $this->noval_order->order_total['total']['plain']) * 100 : $this->server_request['amount'],
                'org_total'       => sprintf('%0.2f', $this->noval_order->order_total['total']['plain']) * 100,
                'currency'        => $this->server_request['currency'],
                'status'          => $this->server_request['status'],
                'order_no'        => $this->server_request['order_no'],
                'date'            => date('Y-m-d H:i:s'),
                'test_mode'       => (int) $this->server_request['test_mode'],
                'customer_id'     => $this->noval_order->order_data['customers_id'],
                'gateway_status'  => $this->server_request['tid_status'],
                'booked'          => '1',
                'due_date'        => !empty($this->server_request['due_date']) ? $this->server_request['due_date'] : '',
                'payment_details' => !empty($masking_params) ? json_decode($masking_params) : '',
            );
            $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', $table_values);
            $status_code  = (in_array($this->server_request['tid_status'], $this->allowed_status)) ? strtoupper($this->noval_order->order_data['payment_code']) . '_ORDER_STATUS' : XT_NOVALNET_ONHOLD_CANCEL_STATUS;
            if (in_array($this->server_request['tid_status'], array('91', '98', '99', '85'))) {
                $status_code = defined('XT_NOVALNET_ONHOLD_COMPLETE_STATUS') ? trim(XT_NOVALNET_ONHOLD_COMPLETE_STATUS) : '';
            }
            // Set paypal pending status
            if (($this->server_request['payment_type'] == 'PAYPAL' && ($this->server_request['status'] == '90' || $this->server_request['tid_status'] == '85')) || $this->server_request['payment_type'] == 'PRZELEWY24' && ($this->server_request['status'] == '100' && $this->server_request['tid_status'] == '86')) {
                $status_code  = strtoupper($this->noval_order->order_data['payment_code']) . '_PENDING_STATUS';
            }
            if (in_array($this->server_request['tid_status'], array(99, 91, 98, 85, 84))) {
                $status_code = 16;
            }
            $order_status = defined($status_code) ? constant($status_code) : $status_code;
            // Form Transaction comments
            $test_mode = !empty($table_values['test_mode']);
            $transaction_comments  = self::form_transaction_comments($table_values,  $test_mode);
            if (in_array($this->noval_order->order_data['payment_code'], array('xt_novalnet_invoice', 'xt_novalnet_prepayment'))) {
                $transaction_comments .= XT_NOVALNET_ACCOUNT_HOLDER_TEXT . ' NOVALNET AG' . '<br/>';
                $transaction_comments .= XT_NOVALNET_IBAN_TEXT . $this->server_request['invoice_iban'] . '<br/>';
                $transaction_comments .= XT_NOVALNET_BIC_TEXT . $this->server_request['invoice_bic'] . '<br/>';
                $transaction_comments .= XT_NOVALNET_BANK_TEXT . $this->server_request['invoice_bankname'] . '<br/>';
                if (!empty($this->server_request['invoice_bankplace'])) {
                    $transaction_comments .= XT_NOVALNET_BANK_PLACE_TEXT . $this->server_request['invoice_bankplace'] . '<br/>';
                }
                $transaction_comments .= XT_NOVALNET_AMOUNT_TEXT . $table_values['amount'] . '<br/><br/>';
                // Payment Reference
                $references = array_filter(
                    array(
                        'BNR-' . $this->server_request['product_id'] . '-' . $this->server_request['order_no'] => 'payment_reference1',
                        'TID ' . $table_values['tid'] => 'payment_reference2',
                    )
                );

                $transaction_comments       .= XT_NOVALNET_MULTI_PAYMENT_REFERENCE_NOTIFY_TEXT . '<br/>';
                $i = 1;
                foreach ($references as $key => $value) {
                    $transaction_comments .= constant(XT_NOVALNET_PAYMENT_TRANSACTION_REFERENCE_ . $i++) . $key . '<br/>';
                }
            }
            // Append status message for canceled order
            if ($this->server_request['status'] != '100') {
                $transaction_comments .= isset($this->server_request['status_text']) ? '<br/>' . $this->server_request['status_text'] : (isset($this->server_request['status_desc']) ? '<br/>' . $this->server_request['status_desc'] : (isset($this->server_request['status_message']) ? '<br/>' . $this->server_request['status_message'] : ''));
            }
            if ($this->server_request['payment_type'] != 'ONLINE_TRANSFER_CREDIT') {
                $this->update_callback_comments(
                    array(
                        'orders_status_id' => $order_status,
                        'comments'         => $transaction_comments,
                    ),
                    true
                );
            }
            $this->display_message('Transaction details are updated successfully');
        } else {
            $this->display_message($message);
        }
    }

    /**
     * Display the error/info message
     *
     * @param string  $message
     * @param int     $order_no
     * @return string
     */
    function display_message($message, $order_no = null)
    {
        $message = 'message=' . utf8_decode($message);
        if (!empty($order_no))
            $message .= '&order_no=' . $order_no;
        echo utf8_encode($message);
        exit;
    }


    /**
     * Log callback process in novalnet_callback_history table
     */
    function log_callback_process()
    {
        global $db;
        $db->AutoExecute(
            DB_PREFIX . '_novalnet_callback_history',
            array(
                'payment_type' => $this->transaction_history['payment_type'],
                'status'       => $this->server_request['status'],
                'callback_tid' => $this->server_request['tid'],
                'org_tid'      => $this->transaction_history['tid'],
                'amount'       => $this->server_request['amount'],
                'currency'     => $this->server_request['currency'],
                'product_id'   => $this->server_request['product_id'],
                'order_no'     => $this->order_id,
                'date'         => date('Y-m-d H:i:s'),
            )
        );
    }


    /**
     * Cancel and update the comments for przelwey payment
     */
    function update_cancel_order_comments()
    {
        global $db;
        //Przelewy payment failure
        $callback_comments = sprintf(XT_NOVALNET_CALLBACK_SCRIPT_GUARANTEE_TRANS_CANCEL_SUCCESSFUL_MESSAGE,  date_short(date('Y-m-d')), date('H:i:s')) . '<br/>';
        // Update callback comments in order status history table
        $this->update_callback_comments(
            array(
                'order_no'         => $this->order_id,
                'comments'         => $callback_comments,
                'orders_status_id' => XT_NOVALNET_ONHOLD_CANCEL_STATUS,
            )
        );
        $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', array(
            'gateway_status' => $this->server_request['tid_status']
        ), 'UPDATE', 'order_no = ' . $this->order_id);

        // Send notification mail to Merchant
        $this->send_notify_mail(
            array(
                'comments' => $callback_comments,
                'order_no' => $this->order_id,
            )
        );
        $this->display_message($callback_comments);
    }


    /**
     * Get the client ip address
     *
     * @return ip address
     */
    function get_client_ip($get_host_address)
    {
        $ip_keys = array('HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                if (in_array($key, ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST'])) {
                    $forwardedIP = !empty($_SERVER[$key]) ? explode(',', $_SERVER[$key]) : [];
                    return in_array($get_host_address, $forwardedIP) ? $get_host_address : $_SERVER[$key];
                }
                return $_SERVER[$key];
            }
        }
    }


    /**
     * Get the payment key
     *
     * @param string $payment_name
     *
     * @return integer
     */
    function paymentType($payment_name)
    {
        switch ($payment_name) {
            case 'xt_novalnet_invoice':
            case 'xt_novalnet_prepayment':
                $payment_id = '27';
                break;
            case 'xt_novalnet_cc':
                $payment_id = '6';
                break;
            case 'xt_novalnet_sepa':
                $payment_id = '37';
                break;
            case 'xt_novalnet_instantbanktransfer':
                $payment_id = '33';
                break;
            case 'xt_novalnet_paypal':
                $payment_id = '34';
                break;
            case 'xt_novalnet_ideal':
                $payment_id = '49';
                break;
            case 'xt_novalnet_eps':
                $payment_id = '50';
                break;
            case 'xt_novalnet_cashpayment':
                $payment_id = '59';
                break;
            case 'xt_novalnet_giropay':
                $payment_id = '69';
                break;
            case 'xt_novalnet_przelewy24':
                $payment_id = '78';
                break;
            case 'xt_novalnet_twint':
                $payment_id = '151';
                break;
            case 'xt_novalnet_onlinebanktransfer':
                $payment_id = '113';
                break;
            case  'xt_novalnet_instalment_invoice':
                $payment_id = '96';
                break;

            case 'xt_novalnet_instalment_sepa':
                $payment_id = '97';
                break;
        }
        return $payment_id;
    }

    /**
     * Form transaction comments
     *
     * @param array $response
     * @param integer $test_mode
     *
     * @return string
     */
    function form_transaction_comments($response, $test_mode)
    {
        $comments = '';
        if ($response['tid']) {
            if (in_array($response['payment_type'], array('GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA')) && in_array($response['tid_status'], array('91', '99', '100'))) {
                $comments .= XT_NOVALNET_MENTION_PAYMENT_CATEGORY_TEXT . '<br>';
            }
            $comments .= constant(strtoupper($this->noval_order->order_data['payment_code']) . '_TEXT') . '<br>';
            $comments .= XT_NOVALNET_TRANSACTION_ID_TEXT . ': ' . $response['tid'] . '<br>';
            if ($test_mode) {
                $comments .= XT_NOVALNET_TESTBESTELLUNG_TEXT . '<br>';
            }
            $comments .= '<br>';
        }
        return $comments;
    }
}
