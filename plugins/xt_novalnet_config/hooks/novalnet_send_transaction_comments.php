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

if (strpos($this->order_data['payment_code'], 'novalnet') !== false) {
    if (isset($_SESSION['novalnet_comments'])) {
        $infoPlain = $infoHtml = $_SESSION['novalnet_comments'];
    } else {
        $record =  $db->Execute(
            "SELECT comments FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE `orders_id` = ? ORDER BY `orders_status_id`  ASC LIMIT 1",
            array((int)$this->order_data['orders_id'])
        );
        $infoPlain = $infoHtml = $record->fields['comments'];
    }

    if ($rs->fields['payment_email_desc_html'] != '') {
        $infoHtml .= '<br /><br />' . $rs->fields['payment_email_desc_html'];
    }
    if ($rs->fields['payment_email_desc_txt'] != '') {
        $infoPlain .= "\n\n" . $rs->fields['payment_email_desc_txt'];
    }

    $ordermail->_assign('payment_info', $infoPlain);
    $ordermail->_assign('payment_info_html', $infoHtml);
    $ordermail->_assign('payment_info_txt', $infoPlain);
    unset($_SESSION['novalnet_comments']);
}
