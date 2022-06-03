<?php
/**
 * Applepay input Form.
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/Templates/Checkout
 */

if ( ! defined( 'ABSPATH' ) ) :
	exit; // Exit if accessed directly.
endif;
?>

<?php

	global $product;
	$Applepay_sheet_details = get_applepay_sheet_details();
	
	if( ! $Applepay_sheet_details['cart_has_subs'] ) {

		if( $contents['apple_pay_button'] == 'cart_page_apple_pay_button' || $contents['apple_pay_button'] == 'product_details_page_apple_pay_button' || $contents['apple_pay_button'] == 'minicart_apple_pay_button' ) {
			echo '<b><h5 class="nn-apple-pay" style="text-align:center"> -- OR --</h5></b>';
		}

		echo'
		<input type = "hidden" id = "novalnet_applepay_article_details" value = "'.htmlentities(json_encode($Applepay_sheet_details["article_details"])).'">
		<input type = "hidden" id = "novalnet_applepay_shipping_details" value = "'.htmlentities(json_encode($Applepay_sheet_details["shipping_details"])).'">
		<button id="'.$contents['apple_pay_button'].'" class="nn-apple-pay" data-type="cart" data-storeName="data-storeName" data-storeLang="'.wc_novalnet_shop_language().'" data-applepayTotal="'.(string)($Applepay_sheet_details['cart_total']*100).'" data-applepayCurrency="'.get_woocommerce_currency().'" data-applepayCountry="'.$Applepay_sheet_details["default_country"].'" data-applepayShopname="'.$Applepay_sheet_details['seller_name'].'"></button>';

		if( $contents['apple_pay_button'] == 'product_details_page_apple_pay_button' ) {
			echo '<input type="hidden" id="novalnet_applepay_product_id" value="'.$product->get_id().'">';
		}

		if( $contents['apple_pay_button'] == 'checkout_apple_pay_button' || $contents['apple_pay_button'] == 'myaccount_page_apple_pay_button' ) {
			echo '<b><h5 class="nn-apple-pay" style="text-align:center;padding-top:15px">-- OR --</h5></b>';
		}

	}
if(  'cart_page_apple_pay_button' == $contents['apple_pay_button']  );
