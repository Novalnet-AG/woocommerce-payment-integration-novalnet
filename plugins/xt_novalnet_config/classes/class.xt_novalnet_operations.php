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
 * xt_novalnet_operations Class
 */

class xt_novalnet_operations
{
    public $position;

    public $response = null;

    function __construct()
    {
        $this->response = new stdClass();
        $this->response->success = false;
        $this->response->message = '';
    }

    /**
     * Manage the transaction status process
     *
     * @param array $request
     */
    public function manageTransactionProcess($request)
    {
        global $xtPlugin, $db, $price;

        // Validate transaction status
        if (empty($request['novalnet_transaction_status_change']) || (!empty($request['novalnet_transaction_status_change']) && ! in_array($request['novalnet_transaction_status_change'], array('100', '103')))) {
            $this->response->message = XT_NOVALNET_SELECT_STATUS_TEXT;
            return json_encode($this->response);
        }

        // Fetch transaction details
        $transaction_details = $this->fetch_transaction_detail($request['orders_id']);
        $input_value = array(
            'vendor'      => $transaction_details['vendor_id'],
            'product'     => $transaction_details['product_id'],
            'tariff'      => $transaction_details['tariff_id'],
            'auth_code'   => $transaction_details['authcode'],
            'edit_status' => '1',
            'tid'         => $transaction_details['tid'],
            'key'         => $transaction_details['payment_id'],
            'status'      => $request['novalnet_transaction_status_change'],
            'remote_ip'   => Novalnet::get_client_ip(),
        );


        // Sending manage transaction request to server
        $server_response = Novalnet::perform_curlrequest($input_value);

        parse_str($server_response, $response);
        // Get the server message
        $this->response->message = Novalnet::set_response_message($response);
        // Execute post process
        if ($response['status'] == '100') {
            $on_hold_mail = false;
            // Capture / void process
            if ($request['novalnet_transaction_status_change'] == '100') {
                $status = ($transaction_details['payment_id'] == '41') ? constant(strtoupper($transaction_details['payment_type']) . '_CALLBACK_ORDER_STATUS') : constant(strtoupper($transaction_details['payment_type']) . '_ORDER_STATUS');
                $comments = (in_array($transaction_details['payment_id'], array('27', '41')) && $response['due_date'] != '') ? sprintf(XT_NOVALNET_TRANSACTION_CONFIRM_INVOICE_MESSAGE, date_short(date('d-m-Y')), date('H:i:s')) . '<br>' : sprintf(XT_NOVALNET_TRANSACTION_CONFIRM_MESSAGE_TEXT, date_short(date('d-m-Y')), date('H:i:s')) . '<br>';

                // Update invoice paid status if available
                if (!empty($xtPlugin->active_modules['xt_orders_invoices']) && file_exists(_SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php')) {

                    include_once _SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php';

                    $xt_orders_invoices = new xt_orders_invoices();
                    $exists = $xt_orders_invoices->isExistByOrderId($request['orders_id']);

                    if ($exists) {
                        $db->AutoExecute(TABLE_ORDERS_INVOICES, array(
                            'invoice_paid'    => '1',
                        ), 'UPDATE', "orders_id = " . $request['orders_id']);
                    }
                }
            } else {
                $status   = XT_NOVALNET_ONHOLD_CANCEL_STATUS;
                $comments = sprintf(XT_NOVALNET_TRANSACTION_CANCEL_MESSAGE_TEXT, date_short(date('Y-m-d')), date('H:i:s')) . '<br>';
            }
            $transaction_details = array_merge($transaction_details, $response);
            if (in_array($transaction_details['payment_type'], array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
                if (!empty($transaction_details['instalment_cycle_details'])) {
                    $transaction_details['instalment_cycle_amount'] = $transaction_details['instalment_cycle_details'];
                }
                $total_amount = $transaction_details['org_total'];
                $instalmentdetails = Novalnet::storeInstalmentdetails($transaction_details, $total_amount);
            }
            // General transaction comments
            $comments .= '<br>' . Novalnet::form_transaction_comments($transaction_details, $transaction_details['test_mode'], $transaction_details['payment_type']);

            $update_parameters = array(
                'gateway_status' => $response['tid_status'],
            );
            if (in_array($transaction_details['payment_type'], array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
                $update_parameters['instalment_cycle_details'] = $instalmentdetails;
            }

            if ($transaction_details['payment_type'] == 'xt_novalnet_paypal' && !empty($transaction_details['payment_details'])) {
                $payment_details = json_encode(array(
                    'paypal_transaction_id' => $response['paypal_transaction_id'],
                ));
                $update_parameters['payment_details'] = $payment_details;
            }

            if (in_array($transaction_details['payment_id'], array('27', '41', '96')) && $response['due_date'] != '') {
                $payment_details   = !empty($transaction_details['payment_details']) ? (array) json_decode($transaction_details['payment_details']) : '';
                // Get bank details lower version
                if (empty($payment_details)) {
                    $table = DB_PREFIX . '_novalnet_preinvoice_transaction_detail';
                    $result = $db->Execute("SHOW TABLES LIKE '" . $table . "'");
                    if ($result->RecordCount() > 0) {
                        $sql = $db->Execute("SELECT account_number, bank_code, bank_name, bank_city, bank_iban, bank_bic FROM $table WHERE order_no='" . $request['orders_id'] . "'");

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
                $price->_setCurrency($transaction_details['currency']);
                $formated_amount = $price->_Format(
                    array(
                        'price'       => $transaction_details['org_total'] / 100,
                        'format'      => true,
                        'format_type' => 'default'
                    )
                );
                $request = array_merge($request, $payment_details, $transaction_details);
                $request = array_merge($request, $response);
                $request['due_date']        = $response['due_date'];
                $request['formated_amount'] = $formated_amount['formated'];
                $comments .= Novalnet::form_invoice_prepayment_comments($request, $request['product_id'], $request['payment_type'], false);
                $update_parameters['due_date'] = $response['due_date'];
                $on_hold_mail = true;
            }

            // Update the order status and comments
            $this->update_order_status(
                $request['orders_id'],
                array(
                    'orders_status' => $status,
                    'comments'      => $comments,
                    'customer_id'   => $transaction_details['customer_id'],
                ),
                $on_hold_mail
            );
            // Update novalnet_transaction_detail table
            $this->update_transaction_detail($update_parameters, "tid='" . $transaction_details['tid'] . "'");

            $this->response->success  = true;
        }

        // Send response
        return json_encode($this->response);
    }

    /**
     * Amount booking process
     *
     * @param array $request
     */
    public function amountBookProcess($request)
    {
        global $db, $price;

        // Validation
        if (empty($request['novalnet_booking_amount']) || $request['novalnet_booking_amount'] <= 0) {
            $this->response->message = XT_NOVALNET_AMOUNT_INVALID_TEXT;
            return json_encode($this->response);
        }

        // Fetch transaction details
        $transaction_details               = $this->fetch_transaction_detail($request['orders_id']);

        // Get payment request parameters
        $payment_parameters                = json_decode($transaction_details['payment_params'], true);
        $payment_parameters['amount']      = $request['novalnet_booking_amount'];
        $payment_parameters['payment_ref'] = $transaction_details['tid'];
        $payment_parameters['remote_ip']   = Novalnet::get_client_ip();

        // Check for reference transaction parameter
        if (isset($payment_parameters['create_payment_ref'])) {
            unset($payment_parameters['create_payment_ref']);
        }

        // Sending update booking amount request to server
        $server_response = Novalnet::perform_curlrequest($payment_parameters);
        parse_str($server_response, $response);

        // Get status message
        $this->response->message = Novalnet::set_response_message($response);

        if ($response['status'] == '100') {

            // Format amount as per shop structure
            $price->_setCurrency($transaction_details['currency']);
            $formated_amount = $price->_Format(
                array(
                    'price'       => $request['novalnet_booking_amount'] / 100,
                    'format'      => true,
                    'format_type' => 'default'
                )
            );

            $test_mode = (!empty($response['test_mode']) || !empty($payment_parameters['test_mode']));

            //Form Transaction comments
            $comments     = Novalnet::form_transaction_comments($response, $test_mode, $transaction_details['payment_type']);

            $update_notes = sprintf(XT_NOVALNET_BOOK_AMOUNT_SUCCESS_TEXT, $formated_amount['formated'], $response['tid']);

            // Get the order status
            $order_status = Novalnet::get_order_status($response['order_no']);

            // Update the order status and comments
            $this->update_order_status(
                $request['orders_id'],
                array(
                    'orders_status' => $order_status,
                    'comments'      => $comments . '<br>' . $update_notes,
                )
            );

            // Update invoice paid status if available
            if (!empty($xtPlugin->active_modules['xt_orders_invoices']) && file_exists(_SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php') && $response['tid_status'] == '100') {

                include_once _SRV_WEBROOT . 'plugins/xt_orders_invoices/classes/class.xt_orders_invoices.php';

                $xt_orders_invoices = new xt_orders_invoices();
                $exists = $xt_orders_invoices->isExistByOrderId($request['orders_id']);

                if ($exists) {
                    $db->AutoExecute(TABLE_ORDERS_INVOICES, array(
                        'invoice_paid'    => '1',
                        'invoice_comment'    => $comments,
                    ), 'UPDATE', "orders_id = " . $request['orders_id']);
                }
            }

            // Update novalnet_transaction_detail table
            $this->update_transaction_detail(
                array(
                    'tid'               => $response['tid'],
                    'gateway_status'    => $response['tid_status'],
                    'org_total'         => $request['novalnet_booking_amount'],
                    'booked'            => '1',
                    'date'              => date('Y-m-d H:i:s'),
                ),
                "order_no='" . $response['order_no'] . "'"
            );

            // Update novalnet_callback_history table
            $db->AutoExecute(
                DB_PREFIX . '_novalnet_callback_history',
                array(
                    'amount'        => $request['novalnet_booking_amount'],
                    'callback_tid'  => $response['tid'],
                    'org_tid'       => $response['tid'],
                    'date'          => date('Y-m-d H:i:s')
                ),
                "UPDATE",
                "order_no='" . $response['order_no'] . "'"
            );

            $this->response->success  = true;
        }

        // Send response
        return json_encode($this->response);
    }


    /**
     * Instalment cycle refund process
     *
     * @param array $request
     */
    public function instalmentRefundProcess($request)
    {
        global $price, $db;

        if (empty($request['novalnet_refund_amount']) || $request['novalnet_refund_amount'] <= 0) {
            $this->response->message = XT_NOVALNET_AMOUNT_INVALID_TEXT;
            return json_encode($this->response);
        }

        $transaction_details = $this->fetch_transaction_detail($request['orders_id']);

        // Get the cycle's own TID from instalment_cycle_details
        $cycle_details = !empty($transaction_details['instalment_cycle_details'])
            ? json_decode($transaction_details['instalment_cycle_details'], true)
            : array();

        $cycle_index = (int)$request['instalment_cycle_no'] - 1;
        $cycle_tid   = !empty($request['tid']) ? $request['tid'] : (!empty($cycle_details[$cycle_index]['reference_tid']) ? $cycle_details[$cycle_index]['reference_tid'] : $transaction_details['tid']);

        $parameters = array(
            'vendor'         => $transaction_details['vendor_id'],
            'product'        => $transaction_details['product_id'],
            'tariff'         => $transaction_details['tariff_id'],
            'key'            => $transaction_details['payment_id'],
            'auth_code'      => $transaction_details['authcode'],
            'refund_request' => '1',
            'tid'            => $cycle_tid,
            'refund_param'   => $request['novalnet_refund_amount'],
            'remote_ip'      => Novalnet::get_client_ip(),
        );

        $server_response = Novalnet::perform_curlrequest($parameters);
        parse_str($server_response, $response);

        $this->response->message = Novalnet::set_response_message($response);

        if ($response['status'] == '100') {
            $price->_setCurrency($transaction_details['currency']);
            $formated_amount = $price->_Format(array(
                'price'       => $parameters['refund_param'] / 100,
                'format'      => true,
                'format_type' => 'default'
            ));

            $comments = sprintf(XT_NOVALNET_REFUND_SUCCESS_MESSAGE_TEXT, $cycle_tid, $formated_amount['formated']);
            if (!empty($response['tid'])) {
                $comments .= sprintf(XT_NOVALNET_REFUND_NEW_TID_MESSAGE_TEXT, $response['tid'], $formated_amount['formated']);
            }

            // Mark the cycle as Refunded in instalment_cycle_details
            if (isset($cycle_details[$cycle_index])) {
                $balance_amount = $cycle_details[$cycle_index]['instalment_cycle_amount'] - $request['novalnet_refund_amount'];
                $cycle_details[$cycle_index]['instalment_cycle_amount'] = $balance_amount;
                $cycle_details[$cycle_index]['status'] = $cycle_details[$cycle_index]['instalment_cycle_amount'] <= 0 ? 'Refunded' : $cycle_details[$cycle_index]['status'];
                $this->update_transaction_detail(
                    array('instalment_cycle_details' => json_encode($cycle_details)),
                    "order_no='" . $request['orders_id'] . "'"
                );
            }

            $order_status = Novalnet::get_order_status($request['orders_id']);
            $this->update_order_status($request['orders_id'], array(
                'orders_status' => $order_status,
                'comments'      => $comments,
            ));

            $this->response->success = true;
        }

        return json_encode($this->response);
    }


    /**
     * Instalment cycle cancel cycles
     *
     * @param array $request
     */
    public function instalmentcancelcycles($request)
    {
        $transaction_details = $this->fetch_transaction_detail($request['orders_id']);

        // Get the cycle's own TID from instalment_cycle_details
        $cycle_details = !empty($transaction_details['instalment_cycle_details'])
            ? json_decode($transaction_details['instalment_cycle_details'], true)
            : array();

        $cycle_tid   = $transaction_details['tid'];
        $cancel_type = $request['cancel_type'] == 'remaining_cycles' ? 'DEACTIVATE_REMAINING_CYCLES' : 'DEACTIVATE';
        $parameters = array(
            'vendor'         => $transaction_details['vendor_id'],
            'product'        => $transaction_details['product_id'],
            'tariff'         => $transaction_details['tariff_id'],
            'key'            => $transaction_details['payment_id'],
            'auth_code'      => $transaction_details['authcode'],
            'refund_request' => '1',
            'tid'            => $cycle_tid,
            'refund_param'   => $cancel_type,
            'remote_ip'      => Novalnet::get_client_ip(),
        );
        $parameters['req_tid'] = $request['tid'];
        $parameters['req_order_id'] = $request['orders_id'];
        $parameters['db_tid'] = $transaction_details['tid'];

        $server_response = Novalnet::perform_curlrequest($parameters);
        parse_str($server_response, $response);

        $this->response->message = Novalnet::set_response_message($response);

        if ($response['status'] == '100') {

            $comments = sprintf(XT_NOVALNET_INSTALMENT_CANCEL_REMAINING_CYCLES_TEXT, $cycle_tid, date('Y-m-d H:i:s'));
            $order_status = Novalnet::get_order_status($request['orders_id']);
            if ($request['cancel_type'] == 'all_cycles') {
                $comments = sprintf(XT_NOVALNET_INSTALMENT_CANCEL_ALL_CYCLES_TEXT, $cycle_tid, date('Y-m-d H:i:s'));
                $order_status = XT_NOVALNET_ONHOLD_CANCEL_STATUS;
            }

            foreach ($cycle_details as $key => $cycle_details_data) {
                if ($cycle_details_data['status'] == 'Pending') {
                    $cycle_details[$key]['status'] = 'Canceled';
                }
                if ($request['cancel_type'] == 'all_cycles' && $cycle_details_data['status'] == 'Paid') {
                    $cycle_details[$key]['status'] = 'Refunded';
                }
            }

            $this->update_transaction_detail(
                array('instalment_cycle_details' => json_encode($cycle_details), 'gateway_status'  => $response['tid_status']),
                "order_no='" . $request['orders_id'] . "'"
            );
            
            $this->update_order_status($request['orders_id'], array(
                'orders_status' => $order_status,
                'comments'      => $comments,
            ));

            $this->response->success = true;
        }

        return json_encode($this->response);
    }


    /**
     * Amount refund process
     *
     * @param array $request
     */
    public function refundProcess($request)
    {
        global $price;

        // Validation
        if (empty($request['novalnet_refund_amount']) || $request['novalnet_refund_amount'] <= 0) {
            $this->response->message = XT_NOVALNET_AMOUNT_INVALID_TEXT;
            return json_encode($this->response);
        }

        // Fetch transaction details
        $transaction_details = $this->fetch_transaction_detail($request['orders_id']);
        $parameters = array(
            'vendor'            => $transaction_details['vendor_id'],
            'product'           => $transaction_details['product_id'],
            'tariff'            => $transaction_details['tariff_id'],
            'key'               => $transaction_details['payment_id'],
            'auth_code'         => $transaction_details['authcode'],
            'refund_request'    => '1',
            'tid'               => $transaction_details['tid'],
            'refund_param'      => $request['novalnet_refund_amount'],
            'remote_ip'         => Novalnet::get_client_ip(),

        );

        // Check for Refund refernce
        if (!empty($request['refund_ref'])) {
            $parameters['refund_ref'] = $request['refund_ref'];
        }

        // Check for on hold transaction parameter
        if (isset($parameters['on_hold'])) {
            unset($parameters['on_hold']);
        }

        // Sending refund request to server
        $server_response = Novalnet::perform_curlrequest($parameters);
        parse_str($server_response, $response);

        // Get status message
        $this->response->message = Novalnet::set_response_message($response);

        if ($response['status'] == '100') {

            // Update novalnet_transaction_detail table
            $this->update_transaction_detail(
                array(
                    'refunded_amount' => $transaction_details['refunded_amount'] + $parameters['refund_param'],
                    'gateway_status'  => $response['tid_status']
                ),
                "tid='" . $transaction_details['tid'] . "'"
            );

            // Get the order status
            $order_status = ($response['tid_status'] != '100') ? XT_NOVALNET_ONHOLD_CANCEL_STATUS : Novalnet::get_order_status($request['orders_id']);
            $original_amount = (float)$transaction_details['org_total'];
            $refund_amount   = (float)$transaction_details['refunded_amount'] + (float)$parameters['refund_param'];
            $order_status = $refund_amount < $original_amount ? '61' : ($refund_amount >= $original_amount ? '60' : $order_status);

            // Format amount as per shop structure
            $price->_setCurrency($transaction_details['currency']);
            $formated_amount = $price->_Format(
                array(
                    'price'       => $parameters['refund_param'] / 100,
                    'format'      => true,
                    'format_type' => 'default'
                )
            );
            $comments = sprintf(XT_NOVALNET_REFUND_SUCCESS_MESSAGE_TEXT, $parameters['tid'], $formated_amount['formated']);

            // Update the comments for new TID
            if (!empty($response['tid'])) {
                $comments .= sprintf(XT_NOVALNET_REFUND_NEW_TID_MESSAGE_TEXT, $response['tid'], $formated_amount['formated']);
            } elseif (!empty($response['paypal_refund_tid'])) {
                $comments .= sprintf(XT_NOVALNET_REFUND_NEW_TID_MESSAGE_TEXT, $response['paypal_refund_tid'], $formated_amount['formated']);
            }
            // Update the order status and comments
            $this->update_order_status(
                $request['orders_id'],
                array(
                    'orders_status'     => $order_status,
                    'comments'          => $comments,
                )
            );

            $this->response->success = true;
        }

        // Send response
        return json_encode($this->response);
    }

    /**
     * Amount/ duedate process
     *
     * @param array $request
     */
    public function amountUpdateProcess($request)
    {
        global $price, $db;

        // Validation
        if (empty($request['novalnet_amount_update']) || $request['novalnet_amount_update'] <= 0) {
            $this->response->message = XT_NOVALNET_AMOUNT_INVALID_TEXT;
            return json_encode($this->response);
        }

        // Fetch transaction details
        $transaction_details = $this->fetch_transaction_detail($request['orders_id']);

        $parameters = array(
            'vendor'            => $transaction_details['vendor_id'],
            'product'           => $transaction_details['product_id'],
            'tariff'            => $transaction_details['tariff_id'],
            'key'               => $transaction_details['payment_id'],
            'auth_code'         => $transaction_details['authcode'],
            'edit_status'       => '1',
            'update_inv_amount' => '1',
            'tid'               => $transaction_details['tid'],
            'status'            => 100,
            'amount'            => $request['novalnet_amount_update'],
            'remote_ip'         => Novalnet::get_client_ip(),
        );


        // Check payment type
        if (in_array($transaction_details['payment_id'], array('27'))) {
            // Validate transaction due date
            if (empty($request['novalnet_duedate'])) {
                $error_message =  XT_NOVALNET_DUEDATE_EMPTY_ERROR_TEXT;
                $this->response->message = $error_message;
                return json_encode($this->response);
            } else if (strtotime($request['novalnet_duedate']) < strtotime(date('Y-m-d'))) {
                $this->response->message = XT_NOVALNET_DUEDATE_ERROR_TEXT;
                return json_encode($this->response);
            }
            $request['novalnet_duedate'] = date("Y-m-d", strtotime($request['novalnet_duedate']));
            $parameters['due_date'] = $request['novalnet_duedate'];

            // Check for on hold transaction parameter
            if (isset($parameters['on_hold'])) {
                unset($parameters['on_hold']);
            }
        }
        // Sending amount/duedate request to server
        $server_response = Novalnet::perform_curlrequest($parameters);
        parse_str($server_response, $response);

        $this->response->message = Novalnet::set_response_message($response);

        if ($response['status'] == '100') {

            // Format amount as per shop structure
            $price->_setCurrency($transaction_details['currency']);
            $formated_amount = $price->_Format(
                array(
                    'price'       => $request['novalnet_amount_update'] / 100,
                    'format'      => true,
                    'format_type' => 'default'
                )
            );

            $amount_update_text = XT_NOVALNET_TRANSACTION_AMOUNT_UPDATE_WITH_DUE_DATE_MESSAGE_TEXT;

            $comments = ($transaction_details['payment_id'] == '37') ? sprintf(XT_NOVALNET_TRANSACTION_AMOUNT_UPDATE_MESSAGE_TEXT, $formated_amount['formated'], date_short(date('Y-m-d')), date('H:m:s')) . '<br>' : sprintf($amount_update_text, $formated_amount['formated'], date_short($request['novalnet_duedate']), date('H:m:s')) . '<br>';

            $status = Novalnet::get_order_status($request['orders_id']);

            $update_details = array(
                'org_total'      => $request['novalnet_amount_update'],
            );

            // Check payment type
            if (in_array($transaction_details['payment_id'], array('59', '27', '41'))) {
                $payment_details   = !empty($transaction_details['payment_details']) ? (array) json_decode($transaction_details['payment_details']) : '';
                // Get bank details lower version
                if (empty($payment_details)) {

                    $table = DB_PREFIX . '_novalnet_preinvoice_transaction_detail';
                    $result = $db->Execute("SHOW TABLES LIKE '" . $table . "'");
                    if ($result->RecordCount() > 0) {
                        $sql = $db->Execute("SELECT account_number, bank_code, bank_name, bank_city, bank_iban, bank_bic FROM $table WHERE order_no='" . $request['orders_id'] . "'");

                        $payment_details = array(
                            'account_holder'    => $sql->fields['account_holder'],
                            'invoice_account'   => $sql->fields['account_number'],
                            'invoice_bankcode'  => $sql->fields['bank_code'],
                            'invoice_iban'      => $sql->fields['bank_iban'],
                            'invoice_bic'       => $sql->fields['bank_bic'],
                            'invoice_bankname'  => $sql->fields['bank_name'],
                            'invoice_bankplace' => $sql->fields['bank_city'],
                        );
                        $update_details['payment_details'] = json_encode($payment_details);
                    }
                }
                $request = array_merge($request, $payment_details);
            }

            $request = array_merge($request, $transaction_details);
            $request = array_merge($request, $response);
            $request['due_date']        = $request['novalnet_duedate'];
            $update_details['due_date'] = date('Y-m-d', strtotime($request['novalnet_duedate']));
            $request['formated_amount'] = $formated_amount['formated'];
            $test_mode = ($request['test_mode'] == '1');

            // General transaction comments
            $comments .= '<br>' . Novalnet::form_transaction_comments($transaction_details, $test_mode, $transaction_details['payment_type']);

            if (in_array($transaction_details['payment_id'], array('27', '41'))) {
                $comments .= Novalnet::form_invoice_prepayment_comments($request, $request['product_id'], $request['payment_type'], false);
            }

            // Update the order status and comments
            $this->update_order_status(
                $request['orders_id'],
                array(
                    'orders_status' => $status,
                    'comments'      => $comments,
                )
            );
            $this->update_transaction_detail($update_details, "tid='" . $transaction_details['tid'] . "'");

            $this->response->success = true;
        }

        // Send response
        return json_encode($this->response);
    }


    /**
     * Set the order status
     *
     * @param integer $order_id
     * @param array   $data
     */
    public function update_order_status($order_id, $data, $manageTransactionProcess = false)
    {
        $customer_id = isset($data['customer_id']) ? $data['customer_id'] : '';
        $novalnet_order = new order($order_id, $customer_id);
        $status_mail = ($manageTransactionProcess) ? 'true' : 'false';
        $novalnet_order->_updateOrderStatus($data['orders_status'], PHP_EOL . $data['comments'] . '<br/><br/>', $status_mail, 'true');
    }


    /**
     * Update novalnet_transaction_detail table
     *
     * @param array  $data
     * @param string $where
     */
    public function update_transaction_detail($data, $where)
    {
        global $db;
        $db->AutoExecute(DB_PREFIX . '_novalnet_transaction_detail', $data, 'UPDATE', $where);
    }


    /**
     * Fetch the order details from novalnet_transaction_detail table
     *
     * @param integer $order_no
     *
     * @return array
     */
    public function fetch_transaction_detail($order_no)
    {
        global $db;
        $order_detail = $db->execute("SELECT vendor_id, authcode, tariff_id, product_id, tid, customer_id, org_total, test_mode, payment_id, payment_type, refunded_amount, payment_params, payment_details, currency, instalment_cycle_details FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE order_no = '" . $order_no . "'");

        return $order_detail->fields;
    }

    /**
     * Performs Novalnet Global Auto Configuration
     *
     * @param integer $details
     *
     * @return json encoded array
     */
    public function autoConfigHash($details)
    {
        if (!empty($details['hash'])) {
            $data = array(
                'hash'      => $details['hash'],
                'lang'      => !empty($_SESSION['selected_language']) ? strtoupper($_SESSION['selected_language']) : 'EN',
            );
            $response =  Novalnet::perform_curlrequest($data, 'https://payport.novalnet.de/autoconfig');
            $response_data =  json_decode($response);
            $json_error = json_last_error();
            if (!$json_error) {
                if (isset($response_data->status) && $response_data->status == '100') {
                    return json_encode($response_data);
                } elseif (isset($response_data->status) && $response_data->status == '106') {
                    $result = sprintf(XT_NOVALNET_SERVER_ADDRESS_CONFIG_TEXT, $_SERVER['SERVER_ADDR']);
                } else {
                    $result = !empty($response_data->config_result) ? $response_data->config_result : $response_data->status_desc;
                }
                return json_encode(array('status_desc' => $result));
            } else {
                return $json_error;
            }
        }
    }


    /**
     * Set position for extension block
     *
     * @param integer $position
     * @return array
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }


    /**
     * Get extension parameters
     *
     * @return array
     */
    public function _getParams()
    {
        return array();
    }
    public static function _get()
    {
        return new stdClass();
    }
}
