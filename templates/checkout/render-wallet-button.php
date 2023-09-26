<?php
/**
 * Wallet input Form.
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/Templates/Checkout
 */

if ( ! defined( 'ABSPATH' ) ) :
	exit; // Exit if accessed directly.
endif;
?>

<?php
	wp_enqueue_script( 'woocommerce-novalnet-gateway-wallet-script', novalnet()->plugin_url . '/assets/js/novalnet-wallet.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );

	global $product;
if ( in_array( $contents['wallet_area'], array( 'product_page', 'mini_cart_page', 'shopping_cart_page' ), true ) ) {
	echo '<b><p class="wallet_seperator" style="text-align:center;margin-top:5px;display:none">-- OR --</p></b>';
}


foreach ( $contents['available_wallets'] as $wallet ) {
	$wallet_sheet_details = get_wallet_sheet_details( $wallet );
	if ( $wallet_sheet_details['cart_has_subs'] <= 1 || 'yes' === get_option( 'novalnet_enable_shop_subs' ) ) {

		if ( ! empty( $product ) ) {
			$wallet_sheet_details['cart_total'] = wc_novalnet_amount( $product->get_price() );
		}
		$wallet_id = $contents['wallet_area'] . '_' . $wallet . '_button';
		$data_id   = $wallet . '_wallet_button';

		$setpending = ( 'billing' === get_option( 'woocommerce_tax_based_on' ) ) ? true : false;

		if ( 'product_page' === $contents['wallet_area'] ) {
			echo '<input type = "hidden" id = "novalnet_product_id" value = "' . esc_attr( $wallet_sheet_details['add_product'] ) . '">';
			echo '<input type = "hidden" id = "product_has_virtual_product" value = "' . esc_attr( $wallet_sheet_details['cart_has_virtual'] ) . '"';
		}

		echo '
			<input type = "hidden" id = "cart_has_virtual" value = "' . esc_attr( $wallet_sheet_details['cart_has_virtual'] ) . '">
			<input type = "hidden" id = "wallet_area" value = "' . esc_attr( $contents['wallet_area'] ) . '">
			<input type = "hidden" id = "setpending" value = "' . esc_attr( $setpending ) . '">
			<input type = "hidden" id = "pay_for_order" value = "' . esc_attr( $wallet_sheet_details['pay_for_order'] ) . '">
			<input type = "hidden" id = "pay_for_order_id" value = "' . esc_attr( $wallet_sheet_details['pay_for_order_id'] ) . '">
			<input type = "hidden" id = "cart_has_one_time_shipping" value = "' . esc_attr( $wallet_sheet_details['cart_has_one_time_shipping'] ) . '">
			<input type = "hidden" id = "novalnet_wallet_article_details" value = "' . htmlentities( wp_json_encode( $wallet_sheet_details['article_details'] ) ) . '">
			<input type = "hidden" id = "novalnet_wallet_shipping_details" value = "' . htmlentities( wp_json_encode( $wallet_sheet_details['shipping_details'] ) ) . '">	
			<div data-type="cart" style="margin: 7px 0px" data-storeName="data-storeName" data-storeLang="' . esc_attr( wc_novalnet_shop_wallet_language() ) . '" data-total="' . esc_attr( (string) ( $wallet_sheet_details['cart_total'] * 100 ) ) . '" data-currency="' . esc_attr( get_woocommerce_currency() ) . '" data-country="' . esc_attr( $wallet_sheet_details['default_country'] ) . '" data-shopname="' . esc_attr( $wallet_sheet_details['seller_name'] ) . '"   data-id="' . esc_attr( $data_id ) . '" id="' . esc_attr( $wallet_id ) . '"></div>';

		if ( 'googlepay' === $wallet ) {
			wc_enqueue_js(
				'
					var id = jQuery("div").find(`[data-id="googlepay_wallet_button"]`).attr("id");
					if( "mini_cart_page_googlepay_button" == id ) {
						if($("#guest_checkout_page_googlepay_button").length){
							$("#guest_checkout_page_googlepay_button").empty();
							wc_novalnet_wallet.initiate_wallet("guest_checkout_page_googlepay_button", "googlepay");
						} else if($("#product_page_googlepay_button").length){
							$("#product_page_googlepay_button").empty();
							wc_novalnet_wallet.initiate_wallet("product_page_googlepay_button", "googlepay");
						}
					}
					$("#"+id).empty();
					wc_novalnet_wallet.initiate_wallet(id, "googlepay");
				'
			);
		} elseif ( 'applepay' === $wallet ) {
			wc_enqueue_js(
				'
					var id = jQuery("div").find(`[data-id="applepay_wallet_button"]`).attr("id");
					if( "mini_cart_page_applepay_button" == id ) {
						if($("#guest_checkout_page_applepay_button").length){
							$("#guest_checkout_page_applepay_button").empty();
							wc_novalnet_wallet.initiate_wallet("guest_checkout_page_applepay_button", "applepay");
						} else if($("#product_page_applepay_button").length){
							$("#product_page_applepay_button").empty();
							wc_novalnet_wallet.initiate_wallet("product_page_applepay_button", "applepay");
						}
					}
					$("#"+id).empty();
					wc_novalnet_wallet.initiate_wallet(id, "applepay");
				'
			);
		}
	}
}
if ( in_array( $contents['wallet_area'], array( 'checkout_page', 'guest_checkout_page' ), true ) ) {
	echo '<b><p class="wallet_seperator" style="text-align:center;margin-top:5px;display:none">-- OR --</p></b>';
}
