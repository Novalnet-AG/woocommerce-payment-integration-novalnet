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

include_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'xt_novalnet_config/classes/class.xt_novalnet_extension.php';

// Check for Novalnet payment gateway.
if (strpos($this->order_data['order_data']['payment_code'], 'novalnet') !== false) {
    global $db;

    echo '<script type="text/javascript" src="../plugins/xt_novalnet_config/javascript/xt_novalnet_extension.js"></script>';

    // Fetch transaction details
    $fetch_order_details = $db->execute("SELECT payment_id, org_total, due_date, gateway_status, refunded_amount, booked FROM " . DB_PREFIX . "_novalnet_transaction_detail WHERE order_no = '" . $this->oID . "'");

    $order_details = $fetch_order_details->fields;

    $order_details['amount'] = $order_details['org_total'];

    // Check for Novalnet panel
    if (in_array($order_details['gateway_status'], array('91', '90', '98', '99', '100', '85'))) {

        // Create Novalnet panel
        $panel = new PhpExt_TabPanel();
        $panel->setId('novalnetPanel' . $this->oID)
            ->setActiveTab(0)
            ->setDeferredRender(true);

        // Get paid amount
        $total_paid_amount = $db->execute("SELECT SUM(amount) AS paid_amount FROM " . DB_PREFIX . "_novalnet_callback_history WHERE order_no = '" . $this->oID . "'");
        $order_details['paid_amount'] = $total_paid_amount->fields['paid_amount'];

        // Amount booking tab
        if (in_array($order_details['payment_id'], array('6', '37', '34')) && $order_details['booked'] != '1') {
            // Get order amount
            $order_details['order_amount']     = (sprintf('%0.2f', $this->order_data['order_total']['total']['plain']) * 100);
            $panel->addItem(xt_novalnet_extension::getAddZeroAmountBook($this->oID, $order_details));
        } else {

            // Check Transaction management tab
            if (in_array($order_details['gateway_status'], array('91', '98', '99', '85')) && in_array($order_details['payment_id'], array('6', '27', '37', '34', '40', '41', '96', '97'))) {

                $panel->addItem(xt_novalnet_extension::getAddTransBlock($this->oID));

                // Check Refund tab
            } else if (!in_array($this->order_data['order_data']['payment_code'], array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa'), true) && $order_details['refunded_amount'] <  $order_details['amount']) {

                $order_details['first_name'] = $this->order_data['order_data']['billing_firstname'];
                $order_details['last_name']  = $this->order_data['order_data']['billing_lastname'];

                $panel->addItem(xt_novalnet_extension::getAddRefundBox($this->oID, $order_details, $this->order_data['order_data']['date_purchased']));
            }

            // Check Amount / Due Date Update tab
            if (($order_details['gateway_status'] == '100' && in_array($order_details['payment_id'], array('27', '59')) &&  $order_details['paid_amount'] < $order_details['amount']) || ($order_details['gateway_status'] == '99' && $order_details['payment_id'] == '37')) {
                $panel->addItem(xt_novalnet_extension::getAddAmountUpdate($this->oID, $order_details));
            }
        }

        // Create layout for the panel
        $layout = new PhpExt_Panel();
        $layout->setLayout(new PhpExt_Layout_BorderLayout())
            ->setId('center' . $this->oID)
            ->setAutoWidth(false)
            ->setHeight(250)
            ->addItem($panel, PhpExt_Layout_BorderLayoutData::createCenterRegion())
            ->setRenderTo(PhpExt_Javascript::variable("Ext.get('tabbedNovalnetPanel" . $this->oID . "')"));

        // Concatenate the formed panel with existing template
        $js .= PhpExt_Ext::OnReady(
            '$("#memoContainer"+' . $this->oID . ').prepend("<div style=\'width:100%\' id=\'tabbedNovalnetPanel' . $this->oID . '\'></div>")',
            $layout->getJavascript(false, "tabbedNovalnetPanel" . $this->oID)
        );
    }

    // Display the instalment summary for instalment payments.
    if (in_array($this->order_data['order_data']['payment_code'], array('xt_novalnet_instalment_invoice', 'xt_novalnet_instalment_sepa')) && $order_details['gateway_status'] == '100') {
        $instalment_summary = xt_novalnet_extension::getInstalmentSummary($this->oID);

        if (!empty($instalment_summary['html'])) {
            $js .= PhpExt_Ext::OnReady(
                '$("#memoContainer"+' . (int)$this->oID . ').prepend(' . json_encode($instalment_summary['html']) . ');'
            );
        }
    }
}
