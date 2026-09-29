<?php


defined('_VALID_CALL') or die('Direct Access is not allowed.');
global $page;

if ($page->page_name == 'checkout' && $page->page_action == 'payment_process' && strpos($_REQUEST['novalnet_payment'], 'novalnet') !== false) {
    require_once _SRV_WEBROOT . 'plugins/xt_novalnet_config/classes/class.novalnet.php';
    $novalnetLogin = new Novalnet();
    $novalnetLogin->novalnet_getCustomer_data($_POST);

    $_SESSION['selected_payment']       = $_REQUEST['novalnet_payment'];
    $_SESSION['last_order_id']          = $_REQUEST['order_no'];
    $_SESSION['selected_shipping']      = $_REQUEST['selected_shipping'];
    $_SESSION['novalnet']['accesskey']  = $_REQUEST['accesskey'];
}
