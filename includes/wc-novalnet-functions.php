<?php
/**
 * Novalnet Functions.
 *
 * General Novalnet functions.
 *
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Core
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Redirect to given URL.
 *
 * @since 12.0.0
 * @param string $url The url value.
 */
function wc_novalnet_safe_redirect( $url = '' ) {
	if ( '' === $url ) {
		$url = wc_get_checkout_url();
	}
	wp_safe_redirect( $url );
	exit;
}

/**
 * Format the text.
 *
 * @since 12.0.0
 * @param string $text The test value.
 *
 * @return int|boolean
 */
function wc_novalnet_format_text( $text ) {

	return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Get next subscription cycle date.
 *
 * @since 12.0.0
 * @param array $data The response data.
 *
 * @return string
 */
function wc_novalnet_next_cycle_date( $data ) {

	// Check for next subscription cycle parameter.
	if ( ! empty( $data ['next_cycle_date'] ) ) {
		return $data ['next_cycle_date'];
	}
}

/**
 * Formating the amount for the Applepay payment
 *
 * @since 12.2.0
 * @param int $amount The amount value.
 *
 * @return string
 */
function wc_novalnet_applepay_amount_as_string( $amount ) {

	return (string)( round ( (float) $amount * 100 ) );
}

/**
 * Formating the amount for the Applepay payment
 *
 * @since 12.2.0
 * @param int $amount The amount value.
 *
 * @return string
 */
function wc_novalnet_applepay_amount( $amount ) {
	
	return number_format( (float) $amount, 2, '.', '' );
}

/**
 * Formating the amount as per the
 * shop structure.
 *
 * @since 12.0.0
 * @param int $amount The amount value.
 *
 * @return string
 */
function wc_novalnet_shop_amount_format( $amount ) {
	return wp_strip_all_tags( wc_price( sprintf( '%0.2f', ( $amount / 100 ) ) ) );
}

/**
 * Formating the date as per the
 * shop structure.
 *
 * @since 12.0.0
 * @param date $date The date value.
 *
 * @return string
 */
function wc_novalnet_formatted_date( $date = '' ) {
	if ( ! empty( $date ) ) {
		return date_i18n( wc_date_format(), strtotime( $date ) );
	}
	return date_i18n( wc_date_format(), strtotime( gmdate( 'Y-m-d H:i:s' ) ) );
}


/**
 * Subscription cancellation reason form.
 *
 * @since  12.0.0
 * @return string
 */
function wc_novalnet_subscription_cancel_form() {
	$form = '<div class="clear"></div><form method="POST" style = "margin-top:1%" id="novalnet_subscription_cancel"><select id="novalnet_subscription_cancel_reason" name="novalnet_subscription_cancel_reason">';

	// Append subscription cancel reasons.
	foreach ( wc_novalnet_subscription_cancel_list() as $key => $reason ) {
		$form .= "<option value=$key>$reason</option>";
	}
	$form .= '</select><div class="clear"></div><br/><br/><input 
	type="submit" class="button novalnet_cancel" onclick="return wcs_novalnet.process_subscription_cancel(this);" id="novalnet_cancel" value=' . __( 'Confirm', 'woocommerce-novalnet-gateway' ) . '></form>';
	return $form;
}

	/**
	 * Retrieves the Novalnet subscription cancel reasons.
	 *
	 * @since  12.0.0
	 * @return array
	 */
function wc_novalnet_subscription_cancel_list() {
	return array(
		__( '--Select--', 'woocommerce-novalnet-gateway' ),
		__( 'Product is costly', 'woocommerce-novalnet-gateway' ),
		__( 'Cheating', 'woocommerce-novalnet-gateway' ),
		__( 'Partner interfered', 'woocommerce-novalnet-gateway' ),
		__( 'Financial problem', 'woocommerce-novalnet-gateway' ),
		__( 'Content does not match my likes', 'woocommerce-novalnet-gateway' ),
		__( 'Content is not enough', 'woocommerce-novalnet-gateway' ),
		__( 'Interested only for a trial', 'woocommerce-novalnet-gateway' ),
		__( 'Page is very slow', 'woocommerce-novalnet-gateway' ),
		__( 'Not happy customer', 'woocommerce-novalnet-gateway' ),
		__( 'Logging in problems', 'woocommerce-novalnet-gateway' ),
		__( 'Other', 'woocommerce-novalnet-gateway' ),
	);
}

/**
 * Perform serialize data.
 *
 * @since 12.0.0
 * @param array $data The resourse data.
 *
 * @return string
 */
function wc_novalnet_serialize_data( $data ) {
	$result = '';

	if ( ! empty( $data ) ) {
		$result = wp_json_encode( $data );
	}
	return $result;
}

/**
 * Perform unserialize data.
 *
 * @since 12.0.0
 * @param array $data The resourse data.
 * @param array $need_as_array The notification for need array.
 *
 * @return array
 */
function wc_novalnet_unserialize_data( $data, $need_as_array = true ) {

	$result = array();

	if ( is_serialized( $data ) ) {
		return maybe_unserialize( $data );
	}

	$result = json_decode( $data, $need_as_array, 512, JSON_BIGINT_AS_STRING );

	if ( json_last_error() === 0 ) {
		return $result;
	}
	wc_novalnet_logger()->add( 'novalneterrorlog', json_last_error() );

	return $result;
}

/**
 * Unset thankyou page session.
 *
 * @since 12.0.0
 */
function wc_novalnet_thankyou_page_session_unset() {

	// $post_id used in action.
	WC()->session->__unset( 'novalnet_thankyou_page' );
}

/**
 * Removing / unset the gateway used sessions.
 *
 * @since 12.0.0
 * @param string $payment_type The payment type value.
 */
function wc_novalnet_unset_payment_session( $payment_type ) {

	$sessions = array(
		'novalnet_change_payment_method',
		'current_novalnet_payment',
		'novalnet_valid_company',
		'novalnet_post_id',
		'novalnet',
		'cart_page_applepay_token',
		'novalnet_applepay_token',
		$payment_type,
		$payment_type . '_dob',
		$payment_type . '_show_dob',
		$payment_type . '_switch_payment',
	);

	foreach ( $sessions as $session ) {
		WC()->session->__unset( $session );
	}
}

/**
 * Format due_date.
 *
 * @since 12.0.0
 * @param int $days The date value.
 *
 * @return string
 */
function wc_novalnet_format_due_date( $days ) {

	return date( 'Y-m-d', mktime( 0, 0, 0, date( 'm' ), ( date( 'd' ) + $days ), date( 'Y' ) ) );
}

/**
 * Retrieves messages from server response.
 *
 * @since 12.0.0
 * @param array $data The response data.
 *
 * @return string
 */
function wc_novalnet_response_text( $data ) {
	if ( ! empty( $data ['result']['status_text'] ) ) {
		return $data ['result']['status_text'];
	}
	if ( ! empty( $data ['status_text'] ) ) {
		return $data ['status_text'];
	}
	return __( 'Payment was not successful. An error occurred', 'woocommerce-novalnet-gateway' );
}

/**
 * Retrieve the name of the end user.
 *
 * @since 12.0.0
 * @param string $name The customer name value.
 *
 * @return array
 */
function wc_novalnet_retrieve_name( $name ) {

	// Retrieve first name and last name from order objects.
	if ( empty( $name['0'] ) ) {
		$name['0'] = $name['1'];
	}
	if ( empty( $name['1'] ) ) {
		$name['1'] = $name['0'];
	}
	return $name;
}

/**
 * Return server / remote address.
 *
 * @since 12.0.0
 * @param string $type The host address type.
 *
 * @return float
 */
function wc_novalnet_get_ip_address( $type = 'REMOTE_ADDR' ) {
	$server = $_SERVER; // input var okay.

	// Check for valid IP.
	if ( 'SERVER_ADDR' === $type ) {
		if ( empty( $server [ $type ] ) ) {
			$ip_address = gethostbyname( $server['HTTP_HOST'] );
			return $ip_address;
		}
		return $server [ $type ];
	}
	$ip_address = WC_Geolocation::get_ip_address();
	return $ip_address;
}

/**
 * Returns Wordpress-blog language.
 *
 * @since  12.0.0
 * @param string $language The blog language.
 *
 * @return string
 */
function wc_novalnet_shop_language( $language = '' ) {

	// Retrieve language code from blog language.
	if ( '' === $language ) {
		$language = get_bloginfo( 'language' );
	}
	return strtoupper( substr( $language, 0, 2 ) );
}

/**
 * Converting the amount into cents
 *
 * @since 12.0.0
 * @param float $amount The amount.
 *
 * @return int
 */
function wc_novalnet_formatted_amount( $amount ) {

	return str_replace( ',', '', sprintf( '%0.2f', $amount ) ) * 100;
}

/**
 * Check for change payment method option.
 *
 * @since 12.0.0
 * @param string $payment_type   The data value.
 *
 * @return boolean
 */
function wc_novalnet_check_payment_method_change( $payment_type ) {
	return ( ! empty( novalnet()->request ['post_type'] ) && 'shop_subscription' === novalnet()->request ['post_type'] && WC_Novalnet_Validation::check_string( $payment_type ) && get_post_meta( novalnet()->request ['post_ID'], '_payment_method', true ) !== $payment_type && empty( novalnet()->request ['novalnet_payment_change'] ) && in_array( $payment_type, array( 'novalnet_cc', 'novalnet_sepa', 'novalnet_invoice', 'novalnet_prepayment' ), true ) );
}

/**
 * Initiate WC_Logger
 *
 * @since 12.0.0
 *
 * @return object
 */
function wc_novalnet_logger() {
	return new WC_Logger();
}

/**
 * Send Mail Notification.
 *
 * @since 12.0.0
 * @param int $email_to_address  E-mail to address.
 * @param int $email_subject     E-mail subject.
 * @param int $comments          E-mail Message content.
 */
function wc_novalnet_send_mail( $email_to_address, $email_subject, $comments ) {

	if ( '' !== $email_to_address ) {
		$headers = '';
		$mailer  = WC()->mailer();
		$message = $mailer->wrap_message( $email_subject, $comments );
		$mailer->send( $email_to_address, $email_subject, $message, $headers );
	}
}

/**
 * To avoid multiple payment fields while using
 * woocommerce-german-market plugin.
 *
 * @since 12.0.0
 */
function wc_novalnet_hide_multiple_payment() {
	if ( class_exists( 'Woocommerce_German_Market' ) ) {
		wc_enqueue_js(
			'
			if ( $( "div[id=payment]" ).length > 1) {
				' . wc_novalnet_process_multiple_payment_hide() . '
			}
		'
		);
	}
}

/**
 * Process to hide mutiple payment fields.
 *
 * @since 12.0.0
 */
function wc_novalnet_process_multiple_payment_hide() {
	$priority = 20;
	if ( class_exists( 'WooCommerce_Germanized' ) ) {
		$priority = 10;
		if ( 'yes' === get_option( 'woocommerce_gzd_display_checkout_fallback' ) ) {
			add_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', 10 );
		}
	}
	remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', $priority );
}

/**
 * Get the payment title / description / admin title.
 *
 * @since 12.0.0
 *
 * @param array  $settings     The payment settings.
 * @param string $payment_text Payment text details.
 * @param string $language     Current shop language.
 * @param string $payment_id   The payment ID.
 * @param string $title        The text to be returned.
 *
 * @return string
 */
function wc_novalnet_get_payment_text( $settings, $payment_text, $language, $payment_id, $title = 'title' ) {

	if ( isset( $settings [ $title . '_' . $language ] ) ) {
		return $settings [ $title . '_' . $language ];
	}

	return isset( $payment_text[ $title . '_' . $language ] ) ? $payment_text[ $title . '_' . $language ] : $payment_text[ $title . '_en' ];
}

/**
 * Check isset values.
 *
 * @since 12.0.0
 * @param array $data   Check the data.
 * @param array $key    Check the key.
 * @param array $value  Check the value.
 *
 * @return array
 */
function wc_novalnet_check_isset( $data, $key, $value = '' ) {
	return ( ! empty( $data [ $key ] ) && $value === $data [ $key ] );
}

/**
 * Returns Admin page URL.
 *
 * @since 12.0.0
 *
 * @param array $parameters  Parameters as array.
 * @param array $page        Page name.
 *
 * @return string
 */
function wc_novalnet_generate_admin_link( $parameters, $page = 'admin.php' ) {
	$query_string = http_build_query( $parameters );
	$url          = admin_url( $page );
	return "$url?$query_string";
}

/**
 * Checks Woocommerce Session
 *
 * @since 12.0.0
 *
 * @return boolean
 */
function wc_novalnet_check_session() {
	return ( isset( WC()->session ) );
}

/**
 * Chabge guaranteed payment type to respective normal payment type
 *
 * @since 12.0.0
 *
 * @param string $payment_type The guaranteed payment type.
 * @param string $find The string to find.
 * @param string $replace The string to replace.
 *
 * @return string
 */
function wc_novalnet_switch_payment( $payment_type, $find = 'guaranteed_', $replace = '' ) {
	return str_ireplace( $find, $replace, $payment_type );
}

/**
 * Change guaranteed payment type to respective normal payment type
 *
 * @since 12.0.0
 *
 * @param string $payment_type The payment type.
 * @return string
 */
function wc_novalnet_get_class_name( $payment_type ) {
	return 'WC_Gateway_' . ucwords( $payment_type, '_' );
}

/**
 * Get Applepay sheet details
 *
 * @since 12.2.0
 *
 * @param string $payment_type The payment type.
 * @return string
 */
function get_applepay_sheet_details() {

	global $woocommerce;
	global $product;

	$packages = WC()->shipping()->get_packages();
	$chosen_method = '';

	$first    = true;
	$shippingDetails = [];
	foreach ( $packages as $i => $package ) {
		$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			foreach ( $package['rates'] as $values) {
				$shipping_total = wc_novalnet_applepay_amount( $values->cost );
				$shippingDetails[]=array('label'=> $values->label, 'amount' => wc_novalnet_applepay_amount_as_string( $shipping_total ), 'identifier' => $values->id, 'detail' => '', );
			}
	}

	$cart_subtotal = WC()->cart->total;
	$items = $woocommerce->cart->get_cart();
	$cart_has_subs = 0;
	$articleDetails = [];
	foreach($items as $item => $values) {
		$_product =  wc_get_product( $values['data']->get_id());

		if( wc_prices_include_tax() ) {
			$product_price = wc_get_price_excluding_tax($_product);
			$product_price = wc_novalnet_applepay_amount( $product_price );
		} else {
			$product_price = $_product->get_price();
			$product_price = wc_novalnet_applepay_amount( $product_price );
		}

		$total = $product_price * $values['quantity'];
		$product_detals = $_product->get_name() . ' (' . $values['quantity'].' X '. $product_price . ')';

		if( 'subscription' == $_product->get_type() ) {
			$cart_has_subs = 1;
		}
		$total = wc_novalnet_applepay_amount( $total );
		$articleDetails[]=array( 'label'=> $product_detals, 'amount' => wc_novalnet_applepay_amount_as_string( $total ) );
	}

	// Add cart amount.
	$cart_tax_amount = 0;
	foreach ( WC()->cart->get_taxes() as $tax_amount ) {
		$cart_tax_amount +=  $tax_amount;
	}

	if ( $cart_tax_amount > 0 ) {
		$cart_tax_amount = wc_novalnet_applepay_amount( $cart_tax_amount );
		$articleDetails[] = array( 'label'=> 'Tax', 'amount' => wc_novalnet_applepay_amount_as_string( $cart_tax_amount ) );
	}

	$applied_coupon = WC()->cart->get_applied_coupons();
	foreach( $applied_coupon as $coupon )
	if( ! empty ( $coupon ) ) {
		$coupon_obj = new WC_Coupon($coupon);
		$articleDetails[]=array('label'=> 'discount('.$coupon.')', 'amount' => '-'.wc_novalnet_applepay_amount_as_string( WC()->cart->get_coupon_discount_amount( $coupon_obj->get_code(), WC()->cart->display_cart_ex_tax ) ) );
	}

	// Add shipping details.
	for( $i = 0; $i < count($shippingDetails); $i++ ) {
		if( $shippingDetails[$i]['identifier'] == $chosen_method ) {
			$articleDetails[]=array( 'label'=> $shippingDetails[$i]['label'], 'amount' => wc_novalnet_applepay_amount_as_string( $shippingDetails[$i]['amount'] ) );
		}
	}

	if( 'product_details_page_apple_pay_button' == $contents['apple_pay_button'] ) {
		$product_detals = $product->get_name() . ' (' . '1 X '. $product_price. ')';
		$articleDetails[]=array('label'=> $product_detals, 'amount' => wc_novalnet_applepay_amount_as_string( $product_price * 100 ) );
		$cart_subtotal = $cart_subtotal + $product_price;
	}

	$cart_subtotal = wc_novalnet_applepay_amount( $cart_subtotal );

	$settings = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_applepay' );
	$seller_name = $settings['seller_name'];

	$default_country = explode( ":",get_option( 'woocommerce_default_country' ) );

	return array (
		'cart_total' => $cart_subtotal,
		'article_details' => $articleDetails,
		'shipping_details' => $shippingDetails,
		'seller_name' => $seller_name,
		'cart_has_subs' => $cart_has_subs,
		'default_country' => $default_country[0],
	);
}

/**
 * SEPA mandate message n front-end
 *
 * @since 12.0.0
 *
 * @param string $payment_type The payment type.
 *
 * @return string
 */
function wc_novalnet_sepa_mandate_text( $payment_type ) {
	return sprintf(
		'<a id="%s_mandate" style="cursor:pointer;" 
	onclick="%s">%s</a><div class="woocommerce-info novalnet-display-none" 
	id="%s_about_mandate" 
	style="display:none;"><p>%s</p><p><strong>%s</strong></p><p><strong>%s</strong>%s</p></div>',
		$payment_type,
		"jQuery('#" . $payment_type . "_about_mandate').toggle('slow')",
		__( 'I hereby grant the mandate for the SEPA direct debit (electronic transmission) and confirm that the given bank details are correct!', 'woocommerce-novalnet-gateway' ),
		$payment_type,
		__( 'I authorise (A) Novalnet AG to send instructions to my bank to debit my account and (B) my bank to debit my account in accordance with the instructions from Novalnet AG.', 'woocommerce-novalnet-gateway' ),
		__( 'Creditor identifier: DE53ZZZ00000004253', 'woocommerce-novalnet-gateway' ),
		__( 'Note:', 'woocommerce-novalnet-gateway' ),
		__( 'You are entitled to a refund from your bank under the terms and conditions of your agreement with bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.', 'woocommerce-novalnet-gateway' )
	);
}

/**
 * Update value process
 *
 * @since 12.0.0
 * @param string $key The key to be updated.
 * @param array  $option_value The configuration value.
 */
function wc_novalnet_update_value( $key, &$option_value ) {
	if ( isset( $option_value[ $key ] ) ) {
		if ( '1' === $option_value[ $key ] ) {
			$option_value[ $key ] = 'yes';
		} elseif ( '0' === $option_value[ $key ] ) {
			$option_value[ $key ] = 'no';
		}
	}
}


