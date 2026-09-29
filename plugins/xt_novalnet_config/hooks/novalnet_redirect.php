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
global $page;

$selected_payment = $_SESSION['selected_payment'];
if ($page->page_name == 'checkout' && $page->page_action == 'novalnet_redirect' && strpos($selected_payment, 'novalnet') !== false) {

    $template = new Template();
    $tpl_data = array('param' => $_SESSION[$selected_payment]['redirect_parameters'], 'redirect_url' => $_SESSION[$selected_payment]['redirect_url']);
    $_SESSION[$selected_payment]['redirect_parameters'] = $_SESSION[$selected_payment]['redirect_parameters'];
    $tpl = 'xt_novalnet_redirect.html';
    $template->getTemplatePath($tpl, 'xt_novalnet_config', '', 'plugin');
    echo ($template->getTemplate('', $tpl, $tpl_data));
}

if ($page->page_name == 'cart') {
    echo '<style>
     @media (min-width: 992px) {
#cart .col-md-6 {
width: 100%;

 }
}
    </style>';
}
