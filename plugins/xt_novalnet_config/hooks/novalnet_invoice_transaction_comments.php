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


if (strpos($orderData['order_data']['payment_code'], 'novalnet') !== false) {
    global $db;
    $order_id = $orderData['order_data']['orders_id'];

    // Get transaction details for the current order
    $query = $db->execute("SELECT tid, gateway_status, org_total, booked FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE order_no='$order_id'");

    $transaction_details = $query->fields;
    $tid = $transaction_details['tid'];


    $novalnet_comments = $db->GetOne("SELECT comments FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id='$order_id' AND comments LIKE '%$tid%' ORDER BY orders_status_history_id ASC LIMIT 1");

    $item['invoice_comment'] .= $novalnet_comments;
    $update_paid_status = true;

    // Check for Invoice and Prepayment
    if (in_array($orderData['order_data']['payment_code'], array('xt_novalnet_invoice', 'xt_novalnet_prepayment'))) {
        $paid_amount = $db->GetOne("SELECT SUM(amount) as amount_total FROM " . DB_PREFIX . "_novalnet_callback_history WHERE order_no = $order_id");
        if ((int) $paid_amount < (int) $transaction_details['org_total']) {
            $update_paid_status = false;
        }
    }

    // Update invoice paid status
    if ($update_paid_status && $transaction_details['booked'] == '1' && $transaction_details['gateway_status'] == '100') {
        $item['invoice_paid'] = '1';
    }
}
