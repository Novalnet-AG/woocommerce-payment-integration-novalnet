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
 * xt_novalnet_extension Class
 */

class xt_novalnet_extension
{

    /**
     * Add refund panel fields
     *
     * @param integer $order_id
     * @param array   $order_data
     * @param date    $order_date
     * @return array
     */
    static function getAddRefundBox($order_id, $order_data, $order_date)
    {
        // Create Refund panel
        $amount = !empty($order_data["refunded_amount"]) ? $order_data["amount"] - $order_data["refunded_amount"] : $order_data["amount"];
        $panel = new PhpExt_Form_FormPanel('addRefundForm');

        // Set Refund panel properties
        $panel->setId('addRefundForm' . $order_id)
            ->setTitle(__define('XT_NOVALNET_REFUND_PROCESS_TEXT'))
            ->setAutoWidth(true)
            ->setAutoScroll(true)
            ->setBodyStyle('padding: 10px;')
            ->setUrl("adminHandler.php?plugin=xt_novalnet_config&load_section=xt_novalnet_operations&pg=refundProcess&orders_id=" . $order_id);

        // Add refund panel fields
        $refund_amount = PhpExt_Form_NumberField::createNumberField('novalnet_refund_amount', __define('XT_NOVALNET_REFUND_AMOUNT_TEXT'), '');
        $refund_amount->setValue($amount)
            ->setAllowDecimals(false)
            ->setAllowBlank(false)
            ->setAllowNegative(false);
        $panel->addItem($refund_amount);

        // Check refund reference field
        if ($order_date != '' && date('Y-m-d') != date('Y-m-d', strtotime($order_date))) {
            $panel->addItem(PhpExt_Form_TextField::createTextField('novalnet_refund_ref', __define('XT_NOVALNET_REFUND_REFERENCE_TEXT')));
        }

        $panel->addButton(self::setSubmitButton('addRefundForm' . $order_id, __define('XT_NOVALNET_CONFIRM_BUTTON_TEXT'), __define('XT_NOVALNET_REFUND_CONFIRM_TEXT')));

        return $panel;
    }

    static function getInstalmentSummary($order_id)
    {
        global $db, $price;

        $result = $db->execute(
            "SELECT instalment_cycle_details, currency FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE order_no = ?",
            array((int)$order_id)
        );

        $order_details = $result->fields;
        $installments = !empty($order_details['instalment_cycle_details'])
            ? json_decode($order_details['instalment_cycle_details'], true)
            : array();

        if (empty($installments)) {
            return array('html' => '', 'panels' => array());
        }

        $cancel_remain_flag = false;
        $cancel_all_flag = false;
        $statuses = [];

        foreach ($installments as $cycle) {
            $statuses[] = $cycle['status'];
        }
        if (in_array('Paid', $statuses, true)) {
            if (in_array('Pending', $statuses, true)) {
                $cancel_all_flag = true;
            }
            if (in_array('Canceled', $statuses, true)) {
                $cancel_all_flag = false;
            }
        }

        if (in_array('Pending', $statuses, true)) {
            $cancel_remain_flag = true;
        }

        $html  = '<script>';
        $html .= 'function nnInstalmentRefundToggle(idx) {';
        $html .= 'var row = document.getElementById("nn_instalment_refund_" + idx);';
        $html .= 'if (row.style.display === "none") {';
        $html .= 'row.style.display = "table-row";';
        $html .= '} else {';
        $html .= '  row.style.display = "none";';
        $html .= '}}';
        $html .= '</script>';

        $html .= '<div id="novalnetInstalmentSummary' . (int)$order_id . '" class="novalnet-instalment-summary" style="width:100%;margin:0 0 15px 0;">';
        $html .= '<div style="background:#d9d9d9;color:#222;font-weight:bold;padding:9px 10px;font-size:14px;">' . XT_NOVALNET_INSTALMENT_SUMMARY_TEXT . '</div>';
        $html .= '<div style="padding:8px 8px 7px 8px;background:#f7f7f7;">';
        if ($cancel_remain_flag) {
            $html .= '<button type="button" id="instalment_cancel_remaining" onclick="nnInstalmentcancelRemaining(' . $order_id . ')"  style="background:#337ab7;border:1px solid #2e6da4;border-radius:4px;color:#fff;cursor:pointer;font-size:14px;padding:7px 13px;">' . XT_NOVALNET_INSTALMENT_CANCEL_ALL_REMAINING_TEXT . '</button>';
        }
        if ($cancel_all_flag) {
            $html .= '<button type="button" id="instalment_cancel_All" onclick="nnInstalmentcancelAll(' . $order_id . ')" style="background:#337ab7;border:1px solid #2e6da4;border-radius:4px;color:#fff;cursor:pointer;font-size:14px;padding:7px 13px;margin-left:10px">' . XT_NOVALNET_INSTALMENT_CANCEL_ALL_TEXT . '</button>';
        }
        $html .= '</div>';
        $html .= '<div style="overflow-x:auto;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;background:#f5f5f5;border:1px solid #ddd;">';
        $html .= '<thead><tr>';


        foreach (array(XT_NOVALNET_INSTALMENT_SNO_TEXT, XT_NOVALNET_TRANSACTION_ID_TEXT, XT_NOVALNET_INSTALMENT_AMOUNT_TEXT, XT_NOVALNET_INSTALMENT_NEXT_DATE_TEXT, 'Status', XT_NOVALNET_INSTALMENT_ACTION_TEXT) as $header) {
            $html .= '<th style="text-align:left;font-weight:normal;color:#333;padding:8px;border-bottom:1px solid #ddd;background:#f8f8f8;white-space:nowrap;">' . $header . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($installments as $index => $cycle) {
            $price->_setCurrency($order_details['currency']);
            $formatted = $price->_Format(array('price' => $cycle['instalment_cycle_amount'] / 100, 'format' => true, 'format_type' => 'default'));
            $amount    = $formatted['formated'];
            $next_date = !empty($cycle['next_instalment_date']) ? date('d.m.Y H:i:s', strtotime($cycle['next_instalment_date'])) : '';
            $tid       = !empty($cycle['reference_tid']) ? $cycle['reference_tid'] : '';
            $status    = !empty($cycle['status']) ? $cycle['status'] : 'Pending';
            $cycle_no  = $index + 1;
            $raw_amount = (int)$cycle['instalment_cycle_amount'];

            $html .= '<tr>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . $cycle_no . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . htmlspecialchars($tid, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #ddd;white-space:nowrap;">' . $amount . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #ddd;white-space:nowrap;">' . htmlspecialchars($next_date, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td style="padding:8px;border-bottom:1px solid #ddd;">';

            $refund_key = (int)$order_id . '_' . (int)$index;
            if ($cycle['status'] === 'Paid') {
                $html .= '<button type="button" onclick="nnInstalmentRefundToggle(\'' . $refund_key . '\')" style="background:#337ab7;border:1px solid #2e6da4;border-radius:4px;color:#fff;cursor:pointer;font-size:14px;padding:7px 13px;">' . XT_NOVALNET_INSTALMENT_REFUND_TEXT . '</button>';
            }

            $html .= '</td></tr>';
            $html .= '<tr id="nn_instalment_refund_' . $refund_key . '" style="display:none;">';
            $html .= '<td colspan="6" style="padding:8px;border-bottom:1px solid #ddd;">';
            $html .= '<input type="hidden" id="nn_refund_tid_' . $refund_key . '" value="' . htmlspecialchars($tid, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '<input type="text" id="nn_refund_trans_amount_' . $refund_key . '" value="' . $raw_amount . '" style="width:100px;display:inline-block;margin-right:10px;padding:5px;border:1px solid #ccc;border-radius:3px;">';
            $html .= '<button type="button" onclick="nnInstalmentRefund(' . (int)$order_id . ', ' . (int)$cycle_no . ')" style="background:#337ab7;border:1px solid #2e6da4;border-radius:4px;color:#fff;cursor:pointer;font-size:14px;padding:6px 12px;">' . XT_NOVALNET_INSTALMENT_CONFIRM_TEXT . '</button>';
            $html .= '<button type="button" onclick="nnInstalmentRefundCancel(\'' . $refund_key . '\')" style="background:#d9534f;border:1px solid #d43f3a;border-radius:4px;color:#fff;cursor:pointer;font-size:14px;padding:6px 12px;margin-left:6px;">' . XT_NOVALNET_INSTALMENT_CANCEL_TEXT . '</button>';
            $html .= '</td></tr>';
        }

        $html .= '</tbody></table></div></div>';

        return array('html' => $html, 'panels' => array());
    }


    /**
     * Add manage transaction fields
     *
     * @param integer $order_id
     * @return array
     */
    static function getAddTransBlock($order_id)
    {
        // Create Manage transaction panel
        $panel = new PhpExt_Form_FormPanel('addTransBlock');

        // Set Manage transaction panel properties
        $panel->setId('addTransBlock' . $order_id)
            ->setTitle(__define('XT_NOVALNET_MANAGE_TRANSACTION_TEXT'))
            ->setAutoWidth(true)
            ->setBodyStyle('padding: 10px;')
            ->setUrl("adminHandler.php?plugin=xt_novalnet_config&load_section=xt_novalnet_operations&pg=manageTransactionProcess&orders_id=" . $order_id);

        $combo_box = new ExtFunctions();

        // Set Manage transaction panel fields
        $combo = $combo_box->_comboBox('novalnet_transaction_status_change', __define('XT_NOVALNET_SELECT_STATUS_TEXT'), 'DropdownData.php?get=plg_xt_novalnet_transaction_code&plugin_code=xt_novalnet_config')->setValue(__define('XT_NOVALNET_SELECT_TEXT'));
        $panel->addItem($combo);
        $panel->addButton(self::setSubmitButton('addTransBlock' . $order_id, __define('XT_NOVALNET_UPDATE_BUTTON_TEXT')));

        return $panel;
    }


    /**
     * Add amount update fields
     *
     * @param integer $order_id
     * @param array   $order_data
     * @return array
     */
    static function getAddAmountUpdate($order_id, $order_data)
    {
        // Create Amount update/ Due date change panel
        $panel = new PhpExt_Form_FormPanel('addAmountUpdate');

        $title = ($order_data['payment_id'] == '27') ? __define('XT_NOVALNET_AMOUNT_DUEDATE_UPDATE_TEXT') : __define('XT_NOVALNET_AMOUNT_UPDATE_TEXT');
        // Set Amount update/ Due date change panel properties
        $panel->setId('addAmountUpdate' . $order_id)
            ->setTitle($title)
            ->setAutoWidth(true)
            ->setBodyStyle('padding: 10px;overflow-y:scroll;')
            ->setUrl("adminHandler.php?plugin=xt_novalnet_config&load_section=xt_novalnet_operations&pg=amountUpdateProcess&orders_id=" . $order_id);

        // Set Amount update/ Due date change panel fields
        $update_amount = PhpExt_Form_NumberField::createNumberField('novalnet_amount_update', __define('XT_NOVALNET_AMOUNT_UPDATE_FIELD_TEXT'), '');
        $update_amount->setValue($order_data['amount'])
            ->setAllowDecimals(false)
            ->setAllowBlank(false)
            ->setAllowNegative(false);
        $panel->addItem($update_amount);

        // Add Due date field
        if (in_array($order_data['payment_id'], array('27'))) {

            if (empty($order_data['due_date'])) {
                global $db;
                // Get due date for lower version
                $table = DB_PREFIX . '_novalnet_preinvoice_transaction_detail';
                $result = $db->Execute("SHOW TABLES LIKE '" . $table . "'");

                if ($result->RecordCount()) {
                    $due_date = $db->GetOne("SELECT due_date FROM $table WHERE order_no='" . $order_id . "'");
                }
            } else {
                $due_date = $order_data['due_date'];
            }
            $due_date_title =  __define('XT_NOVALNET_DUE_DATE_FIELD_TEXT');

            $date_field = PhpExt_Form_DateField::createDateField('novalnet_duedate', $due_date_title);
            $date_field->setValue($due_date);
            $panel->addItem($date_field);
        }
        $confirm_text = ($order_data['payment_id'] == '37') ? __define('XT_NOVALNET_AMOUNT_UPDATE_SEPA_CONFIRM_TEXT') : __define('XT_NOVALNET_AMOUNT_UPDATE_INV_PRE_CONFIRM_TEXT');
        $panel->addButton(self::setSubmitButton('addAmountUpdate' . $order_id, __define('XT_NOVALNET_UPDATE_BUTTON_TEXT'), $confirm_text));

        return $panel;
    }


    /**
     * Add zero amount booking fields
     *
     * @param integer $order_id
     * @param array   $order_data
     * @return array
     */
    static function getAddZeroAmountBook($order_id, $order_data)
    {
        //Create Zero zmount booking panel
        $panel = new PhpExt_Form_FormPanel('addZeroAmountBook');

        //Set Zero amount booking panel properties
        $panel->setId('addZeroAmountBook' . $order_id)
            ->setTitle(__define('XT_NOVALNET_AMOUNT_BOOK_PROCESS'))
            ->setAutoWidth(true)
            ->setBodyStyle('padding: 10px;')
            ->setUrl("adminHandler.php?plugin=xt_novalnet_config&load_section=xt_novalnet_operations&pg=amountBookProcess&orders_id=" . $order_id);

        //Set transaction amount booking field
        $booking_amount = PhpExt_Form_NumberField::createNumberField('novalnet_booking_amount', __define('XT_NOVALNET_BOOK_AMOUNT_TEXT'), '');
        $booking_amount->setValue($order_data['order_amount'])
            ->setAllowDecimals(false)
            ->setAllowBlank(false)
            ->setAllowNegative(false);
        $panel->addItem($booking_amount);

        $panel->addButton(self::setSubmitButton('addZeroAmountBook' . $order_id, __define('XT_NOVALNET_BOOK_BUTTON_TEXT'), __define('XT_NOVALNET_ZERO_AMOUNT_BOOK_CONFIRM_TEXT')));

        return $panel;
    }


    /**
     * Create submit button
     *
     * @param string $form
     * @param string $field_name
     * @return array
     */
    static function setSubmitButton($form, $field_name, $confirm_text = '')
    {
        // Create submit button and action
        $submit_button = PhpExt_Button::createTextButton(
            $field_name,
            new PhpExt_Handler(
                PhpExt_Javascript::stm(
                    "var on_hold_update =  '" . $confirm_text . "'
                    if('" . $form . "'.search('addTransBlock') > -1){
                        var on_hold_update = document.getElementById('novalnet_transaction_status_change').value == 100 ? '" . XT_NOVALNET_ON_HOLD_CAPTURE_CONFIRM_TEXT . "' : '" . XT_NOVALNET_ON_HOLD_CANCEL_CONFIRM_TEXT . "';
                    }
                    Ext.Msg.confirm('" . TEXT_CONFIRM . "',on_hold_update,function(btn){ if(btn == 'yes') novalnet_extenstion_process(); })
                    function novalnet_extenstion_process()
                    {
                     Ext.getCmp('" . $form . "').getForm().submit({
                     waitMsg:'" . __define('TEXT_LOADING') . "',
                     success: function(form, action) {
                        var response = action.result;
                        contentTabs.getActiveTab().getUpdater().refresh();
                        Ext.MessageBox.alert('" . __define('TEXT_ALERT') . "', response.message);
                     },
                     failure: function(form, action) {
                        var response = action.result;
                        Ext.Msg.alert('" . __define('TEXT_FAILURE') . "', response.status)
                        Ext.Msg.alert('" . __define('TEXT_FAILURE') . "', response.message);
                     }
                   })
                  }"
                )
            )
        );
        // Set submit button type
        $submit_button->setType(PhpExt_Button::BUTTON_TYPE_SUBMIT);

        return $submit_button;
    }
}
