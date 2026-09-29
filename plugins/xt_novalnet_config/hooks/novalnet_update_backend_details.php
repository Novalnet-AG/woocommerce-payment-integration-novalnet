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
 * Get the client ip address
 *
 * @return ip address
 */
function get_client_ip()
{
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}

global $store_handler;

$stores = $store_handler->getStores();
$js = '';
$server_ip = $_SERVER['SERVER_ADDR'];

$remote_ip = get_client_ip();

$lang = !empty($_SESSION['selected_language']) ? strtoupper($_SESSION['selected_language']) : 'EN';
foreach ($stores as $store) {
    $store_id = $store['id'];
    $js .= "
    if (typeof jQuery.fn.closest == 'undefined' || !(jQuery.isFunction(jQuery.fn.closest))) {
        var s = document.createElement('script');
        s.type = 'text/javascript';
        s.src = '../plugins/xt_novalnet_config/javascript/xt_novalnet_jquery.js';
        document.getElementsByTagName('head')[0].appendChild(s);
    }
    $(document).ready(
    function() {
        var get_activation_key = 'conf_XT_NOVALNET_ACTIVATION_KEY_shop_" . $store_id . "';

        $('[name='+get_activation_key+']').parents('.x-form-item').before('<div class=x-form-item tabindex=-1>" . XT_NOVALNET_ADMIN_PORTAL_LINK_TITLE . "</div>');

        if($('[name='+get_activation_key+']') != undefined ) {
            var payment_logo_key = 'conf_XT_NOVALNET_PAYMENT_LOGO_ENABLE_shop_" . $store_id . "';
            if($('[name='+payment_logo_key+']') != undefined ) {
                $('[name='+payment_logo_key+']').parents('.x-form-item').before('<div class=x-form-item tabindex=-1>" . XT_NOVALNET_LOGO_MANAGEMENT_TITLE . "</div>');
            }

            var order_status_management_key = 'conf_XT_NOVALNET_ONHOLD_COMPLETE_STATUS_shop_" . $store_id . "';
            if($('[name='+order_status_management_key+']') != undefined ) {
                $('[name='+order_status_management_key+']').parents('.x-form-item').before('<div class=x-form-item tabindex=-1>" . XT_NOVALNET_ORDER_STATUS_MANAGEMENT_TITLE . "</div>');
            }

            var callback_management_key = 'conf_XT_NOVALNET_CALLBACK_TESTMODE_shop_" . $store_id . "';
            if($('[name='+callback_management_key+']') != undefined ) {
                $('[name='+callback_management_key+']').parents('.x-form-item').before('<div class=x-form-item tabindex=-1>" . XT_NOVALNET_MERCHANT_SCRIPT_MANAGEMENT_TITLE . "</div>');
            }

            var assign_vendor_id = 'conf_XT_NOVALNET_VENDOR_ID_shop_" . $store_id . "';
            $('[name='+assign_vendor_id+']').attr('readonly', true);
            var assign_auth_code = 'conf_XT_NOVALNET_AUTH_CODE_shop_" . $store_id . "';
            $('[name='+assign_auth_code+']').attr('readonly', true);
            var assign_product_id = 'conf_XT_NOVALNET_PRODUCT_ID_shop_" . $store_id . "';
            $('[name='+assign_product_id+']').attr('readonly', true);
            var assign_access_key= 'conf_XT_NOVALNET_PAYMENT_ACCESS_KEY_shop_" . $store_id . "';
            $('[name='+assign_access_key+']').attr('readonly', true);
            var get_tariff_id = 'conf_XT_NOVALNET_TARIFF_ID_shop_" . $store_id . "';
            var saved_tariff_id = $('[name='+get_tariff_id+']').val();
            novalnet_refill(saved_tariff_id, " . $store_id . ");
            $(document).change('[name='+get_activation_key+']',function(){
                var saved_tariff_id = $('#conf_XT_NOVALNET_TARIFF_ID_shop_" . $store_id . "').val();
                novalnet_refill(saved_tariff_id, " . $store_id . ");
            });
        }
    });

    function novalnet_refill(saved_tariff_id, shop_id)
    {
        var get_activation_key = 'conf_XT_NOVALNET_ACTIVATION_KEY_shop_'+shop_id;
        var url_param = {
            'system_ip': '" . $server_ip . "',
            'lang': '" . $lang . "',
            'remote_ip' : '" . $remote_ip . "',
            'api_config_hash': $('[name='+get_activation_key+']').val()
        };
        if($('[name='+get_activation_key+']') != undefined && $.trim($('[name='+get_activation_key+']').val()) != '') {
            $.ajax({
                type: 'POST',
                url: 'https://payport.novalnet.de/autoconfig',
                data: url_param,
                success: function( response ) {
                    novalnet_config_hash_response(response, saved_tariff_id, shop_id);
                    console.log(response);
                }
            });
        } else {
            novalnet_null_basic_params(shop_id);
        }
    }

    function novalnet_null_basic_params(shop_id)
    {
        var empty_vendor_id = 'conf_XT_NOVALNET_VENDOR_ID_shop_'+shop_id;
        $('[name='+empty_vendor_id+']').val('');
        var empty_auth_code = 'conf_XT_NOVALNET_AUTH_CODE_shop_'+shop_id;
        $('[name='+empty_auth_code+']').val('');
        var empty_product_id = 'conf_XT_NOVALNET_PRODUCT_ID_shop_'+shop_id;
        $('[name='+empty_product_id+']').val('');
        var empty_access_key= 'conf_XT_NOVALNET_PAYMENT_ACCESS_KEY_shop_'+shop_id;
        $('[name='+empty_access_key+']').val('');
        var empty_client_key = 'conf_XT_NOVALNET_PAYMENT_CLIENT_KEY_shop_'+shop_id;
        $('[name='+empty_client_key+']').val('');

        var tariff_key = 'conf_XT_NOVALNET_TARIFF_ID_shop_'+shop_id;

        $('[name='+tariff_key+']').find('option').remove();
        $('[name='+tariff_key+']').replaceWith('<select id='+tariff_key+' name='+tariff_key+'><option>" . XT_NOVALNET_SELECT_TEXT . "</option></select>');
    }

    function novalnet_config_hash_response ( data, saved_tariff_id, shop_id )
    {
        var tariff_key = 'conf_XT_NOVALNET_TARIFF_ID_shop_'+shop_id;
        if (undefined != data.config_result && '' != data.config_result ) {
            Ext.MessageBox.alert('Error', data.config_result);
            novalnet_null_basic_params(shop_id);
            return false;
        }
        $('[name='+tariff_key+']').replaceWith('<select id='+tariff_key+' name='+tariff_key+'><option>" . XT_NOVALNET_SELECT_TEXT . "</option></select>');

        var hash_tariff_id = data.tariff_id.split(',');
        var hash_tariff_name = data.tariff_name.split(',');
        var hash_tariff_type = data.tariff_type.split(',');
        for (var i = 0; i < hash_tariff_id.length; i++) {
            var tariff_id = hash_tariff_id[i].split(':');
            var tariff_name = hash_tariff_name[i].split(':');
            var tariff_type = hash_tariff_type[i].split(':');
            var tariff_value = ( undefined != tariff_name[ '2' ] ) ? tariff_name[ '1' ] + ':' + tariff_name[ '2' ] : tariff_name[ '1' ];
            $('[name='+tariff_key+']').append(
                $(
                    '<option>', {
                        value: $.trim(tariff_type[ '1' ]) +'-'+$.trim(tariff_id[ '1' ]),
                        text : $.trim(tariff_value)
                    }
                )
            );

            // Assign tariff id.
            if (saved_tariff_id == $.trim(tariff_type[ '1' ]) +'-'+$.trim(tariff_id[ '1' ]) ) {
                   $('[name='+tariff_key+']').val($.trim(tariff_type[ '1' ]) +'-'+$.trim(tariff_id[ '1' ]));
            }
        }
        // Assign vendor details.
        var assign_client_key = 'conf_XT_NOVALNET_PAYMENT_CLIENT_KEY_shop_'+shop_id;
        $('[name='+assign_client_key+']').val(data.client_key);
        var assign_vendor_id = 'conf_XT_NOVALNET_VENDOR_ID_shop_'+shop_id;
        $('[name='+assign_vendor_id+']').val(data.vendor_id);
        var assign_auth_code = 'conf_XT_NOVALNET_AUTH_CODE_shop_'+shop_id;
        $('[name='+assign_auth_code+']').val(data.auth_code);
        var assign_product_id = 'conf_XT_NOVALNET_PRODUCT_ID_shop_'+shop_id;
        $('[name='+assign_product_id+']').val(data.product_id);
        var assign_access_key= 'conf_XT_NOVALNET_PAYMENT_ACCESS_KEY_shop_'+shop_id;
        $('[name='+assign_access_key+']').val(data.access_key);
    }";
}
$params['rowActionsJavascript'] .= $js;
