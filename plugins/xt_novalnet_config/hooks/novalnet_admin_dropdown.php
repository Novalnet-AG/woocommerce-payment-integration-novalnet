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

switch ($request['get']) {
    case 'plg_xt_novalnet_cc_shopping_types': // Shopping Types
        $result = array(
            array(
                'id'   => 'none',
                'name' => XT_NOVALNET_CC_SHOPTYPE_SELECT_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ONECLICK',
                'name' => XT_NOVALNET_CC_ONECLICK_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ZEROAMOUNT',
                'name' => XT_NOVALNET_CC_ZEROAMOUNT_TEXT,
                'desc' => '',
            )
        );
        break;
    case 'plg_xt_novalnet_sepa_shopping_types': // Shopping Types
        $result = array(
            array(
                'id'   => 'none',
                'name' => XT_NOVALNET_SEPA_SHOPTYPE_SELECT_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ONECLICK',
                'name' => XT_NOVALNET_SEPA_ONECLICK_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ZEROAMOUNT',
                'name' => XT_NOVALNET_SEPA_ZEROAMOUNT_TEXT,
                'desc' => '',
            )
        );
        break;
    case 'plg_xt_novalnet_instalment_sepa_shopping_types': // Instalment Sepa Shopping Types
        $result = array(
            array(
                'id'   => 'none',
                'name' => XT_NOVALNET_INSTALMENT_SEPA_SHOPTYPE_SELECT_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ONECLICK',
                'name' => XT_NOVALNET_INSTALMENT_SEPA_ONECLICK_TEXT,
                'desc' => '',
            )
        );
        break;
    case 'plg_xt_novalnet_paypal_shopping_types': // Shopping Types
        $result = array(
            array(
                'id'   => 'none',
                'name' => XT_NOVALNET_PAYPAL_SHOPTYPE_SELECT_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ONECLICK',
                'name' => XT_NOVALNET_PAYPAL_ONECLICK_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'ZEROAMOUNT',
                'name' => XT_NOVALNET_PAYPAL_ZEROAMOUNT_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_transaction_code': // Transaction status
        $result = array(
            array(
                'id'   => '100',
                'name' => XT_NOVALNET_CONFIRM_BUTTON_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => '103',
                'name' => XT_NOVALNET_CANCEL_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_invoice_authorization_types': // Fraud Prevention Types
        $result = array(
            array(
                'id'   => 'CAPTURE',
                'name' => XT_NOVALNET_INVOICE_CAPTURE_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'AUTHORIZE',
                'name' => XT_NOVALNET_INVOICE_AUTHORIZE_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_sepa_authorization_types': // Fraud Prevention Types
        $result = array(
            array(
                'id'   => 'CAPTURE',
                'name' => XT_NOVALNET_SEPA_CAPTURE_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'AUTHORIZE',
                'name' => XT_NOVALNET_SEPA_AUTHORIZE_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_paypal_authorization_types': // Fraud Prevention Types
        $result = array(
            array(
                'id'   => 'CAPTURE',
                'name' => XT_NOVALNET_PAYPAL_CAPTURE_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'AUTHORIZE',
                'name' => XT_NOVALNET_PAYPAL_AUTHORIZE_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_cc_authorization_types': // Fraud Prevention Types
        $result = array(
            array(
                'id'   => 'CAPTURE',
                'name' => XT_NOVALNET_CC_CAPTURE_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'AUTHORIZE',
                'name' => XT_NOVALNET_CC_AUTHORIZE_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_instalment_invoice_authorization_types': // Instalment Invoice Payment Action
        $result = array(
            array(
                'id'   => 'CAPTURE',
                'name' => XT_NOVALNET_INSTALMENT_INVOICE_CAPTURE_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'AUTHORIZE',
                'name' => XT_NOVALNET_INSTALMENT_INVOICE_AUTHORIZE_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_instalment_sepa_authorization_types': // Instalment Sepa Payment Action
        $result = array(
            array(
                'id'   => 'CAPTURE',
                'name' => XT_NOVALNET_INSTALMENT_SEPA_CAPTURE_TEXT,
                'desc' => '',
            ),
            array(
                'id'   => 'AUTHORIZE',
                'name' => XT_NOVALNET_INSTALMENT_SEPA_AUTHORIZE_TEXT,
                'desc' => '',
            )
        );
        break;

    case 'plg_xt_novalnet_config_tariff_types': // Load Tariff id's
        $api_tariff = $db->Execute("SELECT config_value FROM " . TABLE_PLUGIN_CONFIGURATION . " WHERE config_key ='XT_NOVALNET_TARIFF_ID_STORE'");
        $tariff_id = unserialize($api_tariff->fields['config_value']);
        if ($tariff_id['vendor_id'] != '') {
            $split_tariff_id   = explode(",", json_encode($tariff_id['tariff_id']));
            $split_tariff_name = str_replace('"', '', json_encode($tariff_id['tariff_name']));
            $split_tariff_name = explode(",", $split_tariff_name);
            $split_tariff_type = explode(",", json_encode($tariff_id['tariff_type']));
            $nn_tariff_id = array();
            $tariff_name = array();
            $tariff_type = array();
            foreach ($split_tariff_id as $key => $values) {
                $nn_tariff_id[]   = explode(":", $values);
            }
            foreach ($split_tariff_name as $key => $values) {
                $tariff_name[] = explode(":", $values);
            }
            foreach ($split_tariff_type as $key => $values) {
                $tariff_type[] = explode(":", $values);
            }
            $count = count($nn_tariff_id);
            $result = array();
            for ($i = 0; $i < $count; $i++) {
                if (in_array($tariff_type[$i][1], array('1', '4'))) {
                    $result[] = array(
                        'id'   => $tariff_type[$i][1] . '-' . $nn_tariff_id[$i][1],
                        'name' => $tariff_name[$i][1],
                        'desc' => '',
                    );
                }
            }
        }
        break;
}
