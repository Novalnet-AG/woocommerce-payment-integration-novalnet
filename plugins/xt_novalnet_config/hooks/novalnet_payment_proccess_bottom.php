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


global $xtLink, $page, $tmp_link, $language;

if ($page->page_name == 'checkout' && $page->page_action == 'payment_process' && isset($_REQUEST['novalnet_payment'])) {
    $tmp_link = $xtLink->_link(array(
        'page' => 'checkout',
        'paction' => 'success',
        'conn' => 'SSL',
        'lang_code' => strtolower($_REQUEST['lang'])
    ));
}
