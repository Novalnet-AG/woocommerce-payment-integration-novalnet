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

if (!empty($data['payment_code']) && strpos($data['payment_code'], 'novalnet') !== false) {

    // Set Novalnet error array to identify
    $novalnet_error = array();

    global $store_handler;

    // Get stores ID
    $novalnet_stores = $store_handler->getStores();

    // Handle validation
    foreach ($novalnet_stores as $novalnet_store) {
        $novalnet_store_id = $novalnet_store['id'];

        // Validate guarantee process
        if (in_array($data['payment_code'], array('xt_novalnet_invoice', 'xt_novalnet_sepa', 'xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
            $payment_guarantee = $data['conf_' . strtoupper($data['payment_code']) . '_ENABLE_GUARANTEE_PAYMENT_shop_' . $novalnet_store_id];
            if (in_array($data['payment_code'], array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
                $payment_guarantee = '1';
            }

            if ($payment_guarantee == '1') {

                // Check and assign default values
                $minimum_amount = trim($data['conf_' . strtoupper($data['payment_code']) . '_GUARANTEE_PAYMENT_MIN_AMOUNT_shop_' . $novalnet_store_id]);
                if ($minimum_amount === '' || (int)$minimum_amount === 0) {
                    $minimum_amount = in_array($data['payment_code'], ['xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'], true) ? 1998 : 999;
                }

                // Validate GUARANTEE minimum amount
                if (! ctype_digit($minimum_amount)) {
                    $novalnet_error['shop_' . $novalnet_store_id . '_error'] = constant(strtoupper($data['payment_code']) . '_AMOUNT_INVALID_TEXT');
                    unset($data['conf_' . strtoupper($data['payment_code']) . '_GUARANTEE_PAYMENT_MIN_AMOUNT_shop_' . $novalnet_store_id]);
                } elseif ($minimum_amount < 999) {
                    $novalnet_error['shop_' . $novalnet_store_id . '_error'] = constant(strtoupper($data['payment_code']) . '_GUARANTEE_MIN_ERROR_TEXT');
                    unset($data['conf_' . strtoupper($data['payment_code']) . '_GUARANTEE_PAYMENT_MIN_AMOUNT_shop_' . $novalnet_store_id]);
                } elseif (in_array($data['payment_code'], ['xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa']) && $minimum_amount < 1998) {
                    $novalnet_error['shop_' . $novalnet_store_id . '_error'] = constant(strtoupper($data['payment_code']) . '_GUARANTEE_MIN_ERROR_TEXT');
                    unset($data['conf_' . strtoupper($data['payment_code']) . '_GUARANTEE_PAYMENT_MIN_AMOUNT_shop_' . $novalnet_store_id]);
                }
            }
        }

        // Validate SEPA due date
        if ($data['payment_code'] == 'xt_novalnet_sepa') {

            $sepa_due_date = trim($data['conf_' . strtoupper($data['payment_code']) . '_DUEDATE_shop_' . $novalnet_store_id]);
            if ($sepa_due_date != '' && ($sepa_due_date > 14 || $sepa_due_date < 3  || ($sepa_due_date != '' && !preg_match("/^[0-9]+$/", $sepa_due_date)))) {
                $novalnet_error['shop_' . $novalnet_store_id . '_error'] = XT_NOVALNET_SEPA_DUEDATE_ERROR_TEXT;
                unset($data['conf_' . strtoupper($data['payment_code']) . '_DUEDATE_shop_' . $novalnet_store_id]);
            }
        }

        // Validate PREPAYMENT due date
        if ($data['payment_code'] == 'xt_novalnet_prepayment') {

            $prepayment_due_date = trim($data['conf_' . strtoupper($data['payment_code']) . '_PREPAYMENT_DUE_DATE_shop_' . $novalnet_store_id]);
            if ($prepayment_due_date != '' && ($prepayment_due_date > 28 || $prepayment_due_date < 7  || ($prepayment_due_date != '' && !preg_match("/^[0-9]+$/", $prepayment_due_date)))) {
                $novalnet_error['shop_' . $novalnet_store_id . '_error'] = XT_NOVALNET_PREPAYMENT_DUEDATE_ERROR_TEXT;
                unset($data['conf_' . strtoupper($data['payment_code']) . '_PREPAYMENT_DUE_DATE_shop_' . $novalnet_store_id]);
            }
        }

        // Default and validate instalment cycles
        if (in_array($data['payment_code'], array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'))) {
            $cycles_key = 'conf_' . strtoupper($data['payment_code']) . '_INSTALMENT_CYCLES_shop_' . $novalnet_store_id;
            $cycles_value = trim($data[$cycles_key]);
            $cycles_invalid = false;
            if ($cycles_value === '') {
                $data[$cycles_key] = '2,3,4,5,6,7,8,9,10,11,12,15,18,24,36';
            } elseif (!preg_match('/^[0-9]+(,[0-9]+)*$/', $cycles_value)) {
                $cycles_invalid = true;
                $novalnet_error['shop_' . $novalnet_store_id . '_error'] = defined('XT_NOVALNET_INSTALMENT_INVOICE_CYCLES_FORMAT_ERROR_TEXT')
                    ? XT_NOVALNET_INSTALMENT_INVOICE_CYCLES_FORMAT_ERROR_TEXT
                    : 'Invalid instalment cycles format. Only numbers and comma as separator are allowed (e.g. 2,3,4,6,12).';
            } else {
                foreach (explode(',', $cycles_value) as $cycle) {
                    if ((int)$cycle < 2 || (int)$cycle > 36) {
                        $cycles_invalid = true;
                        break;
                    }
                }
            }
            if ($cycles_invalid) {
                $data[$cycles_key] = '2,3,4,5,6,7,8,9,10,11,12,15,18,24,36';
            }
        }

        // Validate Notification for the buyer field
        $buyer_notify = trim($data['conf_' . strtoupper($data['payment_code']) . '_INFORMATION_TO_USER_shop_' . $novalnet_store_id]);

        if (preg_match('/<|>/', $buyer_notify)) {
            $novalnet_error['shop_' . $novalnet_store_id . '_error'] = XT_NOVALNET_NOTIFY_CUSTOMER_ERROR_TEXT;
            unset($data['conf_' . strtoupper($data['payment_code']) . '_INFORMATION_TO_USER_shop_' . $novalnet_store_id]);
        }

        unset($novalnet_store_id);
    }
    $payment_name = strtoupper($data['payment_code']);
    unset($payment_name, $novalnet_stores);
}
