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
 * Formating the amount for the Wallet payment
 *
 * @since 12.2.0
 * @param int $amount The amount value.
 *
 * @return string
 */
function wc_novalnet_amount_as_string( $amount ) {

	return (string) ( round( (float) $amount * 100 ) );
}

/**
 * Formating the amount for the Wallet payment
 *
 * @since 12.2.0
 * @param int $amount The amount value.
 *
 * @return string
 */
function wc_novalnet_amount( $amount ) {

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

	if ( empty( $data ) ) {
		return $result;
	}

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

	return gmdate( 'Y-m-d', mktime( 0, 0, 0, gmdate( 'm' ), ( gmdate( 'd' ) + $days ), gmdate( 'Y' ) ) );
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
 * Returns Wordpress-blog language.
 *
 * @since  12.4.0
 * @param string $language The blog language.
 *
 * @return string
 */
function wc_novalnet_shop_wallet_language( $language = '' ) {

	// Retrieve language code from blog language.
	if ( '' === $language ) {
		$language = get_bloginfo( 'language' );
	}
	return str_replace( '_', '-', $language );
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
 * @param string $wallet The payment type.
 * @return array
 */
function get_wallet_sheet_details( $wallet ) {

	global $woocommerce;
	global $product;

	$cart_has_subs                      = 0;
	$cart_has_virtual                   = ( ! empty( $product ) ) ? ( ( ( $product->is_downloadable() || $product->is_virtual() ) ) ? 1 : 0 ) : 1;
	$article_details                    = array();
	$shipping_details                   = array();
	$add_product                        = '';
	$cart_products                      = array();
	$default_country                    = explode( ':', get_option( 'woocommerce_default_country' ) );
	$settings                           = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_' . $wallet );
	$seller_name                        = $settings['seller_name'];
	$cart_has_one_time_shipping_product = 0;

	$pay_for_order    = false;
	$pay_for_order_id = '';
	// If paying from order, we need to get total from order not cart.
	if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) { // @codingStandardsIgnoreLine.
		global $wp;
		$pay_for_order    = true;
		$pay_for_order_id = wc_clean( $wp->query_vars['order-pay'] );
		$wc_order         = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
		foreach ( $wc_order->get_items() as $item_id => $item_values ) {
			$_product = wc_get_product( $item_values['product_id'] );
			if ( ! empty( $_product ) ) {

				$attributes = '';
				$count      = 0;
				if ( 'variable' === $_product->get_type() ) {
					$variation_id = $item_values->get_variation_id();
					$parent       = wp_get_post_parent_id( $variation_id );
					if ( $parent > 0 ) {
						$_product = new WC_Product_Variation( $variation_id );
					}
					foreach ( $_product->get_attributes() as $attr => $value ) {
						if ( 0 === $count ) {
							$attributes = $attr . ':' . $value;
						} else {
							$attributes .= ', ' . $attr . ':' . $value;
						}
						$count++;
					}
					$attributes = '( ' . $attributes . ' )';
				}

				// Get product price.
				$product_price   = get_product_price( $_product );
				$total           = $product_price * $item_values['quantity'];
				$product_details = $_product->get_title() . $attributes . ' (' . $item_values['quantity'] . ' X ' . $product_price . ')';
				if ( ! $_product->is_downloadable() && ! $_product->is_virtual() ) {
					$cart_has_virtual = 0;
				}

				$total = wc_novalnet_amount( $total );

				$article_details[] = array(
					'label'  => $product_details,
					'amount' => wc_novalnet_amount_as_string( $total ),
					'type'   => 'SUBTOTAL',
				);
			}
		}

		if ( wc_novalnet_formatted_amount( $wc_order->get_total_tax() ) > 0 ) {
			$cart_tax_amount   = wc_novalnet_amount( $wc_order->get_total_tax() );
			$article_details[] = array(
				'label'  => 'Tax',
				'amount' => wc_novalnet_amount_as_string( $cart_tax_amount ),
				'type'   => 'SUBTOTAL',
			);
		}

		if ( wc_novalnet_formatted_amount( $wc_order->get_shipping_total() ) > 0 ) {
			$shipping_total    = wc_novalnet_amount( $wc_order->get_shipping_total() );
			$article_details[] = array(
				'label'  => 'Shipping',
				'amount' => wc_novalnet_amount_as_string( $shipping_total ),
				'type'   => 'SUBTOTAL',
			);
		}
		$total = 0;
		foreach ( $article_details as $article_detail ) {
			$total += $article_detail['amount'];
		}

		$order_total = (int) wc_novalnet_formatted_amount( $wc_order->get_total() );
		$total       = (int) $total;

		if ( $order_total < $total ) {
			$article_details[] = array(
				'label'  => 'Discount',
				'amount' => '-' . ( $total - $order_total ),
				'type'   => 'SUBTOTAL',
			);
		}

		$cart_subtotal = $wc_order->get_total();

	} else {
		$cart_subtotal = WC()->cart->total;
		$items         = $woocommerce->cart->get_cart();
		$packages      = WC()->shipping()->get_packages();
		$chosen_method = '';

		$shipping_details = array();
		foreach ( $packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			foreach ( $package['rates'] as $values ) {
				$shipping_total     = wc_novalnet_amount( $values->cost );
				$shipping_details[] = array(
					'label'      => $values->label,
					'amount'     => wc_novalnet_amount_as_string( $shipping_total ),
					'identifier' => $values->id,
					'detail'     => '',
				);
			}
		}
		$cart_has_normal_product = 0;
		foreach ( $items as $item => $values ) {

			$_product        = wc_get_product( $values['data']->get_id() );
			$cart_products[] = $values['data']->get_id();

			// Get product price.
			$product_price   = get_product_price( $_product );
			$total           = $product_price * $values['quantity'];
			$product_details = $_product->get_name() . ' (' . $values['quantity'] . ' X ' . $product_price . ')';
			if ( ! $_product->is_downloadable() && ! $_product->is_virtual() ) {
				$cart_has_normal_product = 1;
				$cart_has_virtual        = 0;
			}

			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
				++$cart_has_subs;
			}

			$total = wc_novalnet_amount( $total );

			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
				$signup_fee = get_post_meta( $values['data']->get_id(), '_subscription_sign_up_fee', 1 );
				if ( $signup_fee > 0 ) {
					$article_details[] = array(
						'label'  => 'Signup Fee',
						'amount' => wc_novalnet_amount_as_string( $signup_fee ),
						'type'   => 'SUBTOTAL',
					);
				}

				$one_time_shipping = get_post_meta( $values['data']->get_id(), '_subscription_one_time_shipping', 1 );
				if ( wcs_cart_contains_renewal() && ( ! empty( $one_time_shipping ) ) && ( 'yes' === (string) $one_time_shipping ) ) {
					$cart_has_one_time_shipping_product = 1;
				}
			}
			$article_details[] = array(
				'label'  => $product_details,
				'amount' => wc_novalnet_amount_as_string( $total ),
				'type'   => 'SUBTOTAL',
			);
		}

		if ( ( ! empty( $product ) ) && ! in_array( $product->get_id(), $cart_products, true ) ) {
			if ( ! $product->is_downloadable() && ! $product->is_virtual() ) {
				$cart_has_virtual = 0;
			} elseif ( ! $cart_has_normal_product ) {
				$cart_has_virtual = 1;
			}
			$product_total = 0;
			$add_product   = $product->get_id();
			if ( in_array( $product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
				$signup_fee = get_post_meta( $product->get_id(), '_subscription_sign_up_fee', 1 );
				++$cart_has_subs;
				if ( $signup_fee > 0 ) {
					$cart_subtotal    += $signup_fee;
					$product_total    += $signup_fee;
					$article_details[] = array(
						'label'  => 'Signup Fee',
						'amount' => wc_novalnet_amount_as_string( $signup_fee ),
						'type'   => 'SUBTOTAL',
					);
				}
			}
			$product_price     = get_product_price( $product );
			$cart_subtotal    += $product_price;
			$product_total    += $product_price;
			$product_details   = $product->get_name() . ' ( 1 X ' . $product_price . ')';
			$article_details[] = array(
				'label'  => $product_details,
				'amount' => wc_novalnet_amount_as_string( $product_total ),
				'type'   => 'SUBTOTAL',
			);
		}

		// Add cart amount.
		$cart_tax_amount = 0;
		foreach ( WC()->cart->get_taxes() as $tax_amount ) {
			$cart_tax_amount += $tax_amount;
		}

		if ( $cart_tax_amount > 0 ) {
			$cart_tax_amount   = wc_novalnet_amount( $cart_tax_amount );
			$article_details[] = array(
				'label'  => 'Tax',
				'amount' => wc_novalnet_amount_as_string( $cart_tax_amount ),
				'type'   => 'SUBTOTAL',
			);
		}

		$applied_coupon = WC()->cart->get_applied_coupons();
		foreach ( $applied_coupon as $coupon ) {
			if ( ! empty( $coupon ) ) {
				$coupon_obj        = new WC_Coupon( $coupon );
				$article_details[] = array(
					'label'  => 'discount(' . $coupon . ')',
					'amount' => '-' . wc_novalnet_amount_as_string( WC()->cart->get_coupon_discount_amount( $coupon_obj->get_code(), WC()->cart->display_cart_ex_tax ) ),
					'type'   => 'SUBTOTAL',
				);
			}
		}

		// Add shipping details.
		$for_count = count( $shipping_details );
		for ( $i = 0; $i < $for_count; $i++ ) {
			if ( $shipping_details[ $i ]['identifier'] === $chosen_method ) {
				$article_details[] = array(
					'label'  => $shipping_details[ $i ]['label'],
					'amount' => wc_novalnet_amount_as_string( $shipping_details[ $i ]['amount'] ),
					'type'   => 'SUBTOTAL',
				);
			}
		}

		$cart_subtotal = wc_novalnet_amount( $cart_subtotal );
	}

	return array(
		'cart_total'                 => $cart_subtotal,
		'article_details'            => $article_details,
		'shipping_details'           => $shipping_details,
		'seller_name'                => $seller_name,
		'cart_has_subs'              => $cart_has_subs,
		'add_product'                => $add_product,
		'cart_has_virtual'           => $cart_has_virtual,
		'default_country'            => $default_country[0],
		'pay_for_order'              => $pay_for_order,
		'pay_for_order_id'           => $pay_for_order_id,
		'cart_has_one_time_shipping' => $cart_has_one_time_shipping_product,
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
 * Get values for wallets.
 *
 * @param string $page The page name.
 *
 * @since 12.4.0
 */
function get_available_wallets( $page ) {
	// Get wallet settings.
	global $woocommerce;
	global $product;
	$wallet_payments   = array( 'applepay', 'googlepay' );
	$available_wallets = array();
	$cart_products     = array();
	$cart_has_virtual  = ( ! empty( $product ) ) ? ( ( ( $product->is_downloadable() || $product->is_virtual() ) ) ? 1 : 0 ) : 1;
	foreach ( $wallet_payments as $wallet ) {
		if ( class_exists( 'WC_Subscriptions_Switcher' ) ) {
			if ( ( isset( $_GET['switch-subscription'] ) ) || ( isset( WC()->cart ) && WC_Subscriptions_Switcher::cart_contains_switches() ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				continue;
			}
		}

		$is_wallet_available_for_virtual = true;
		$wallet_setting                  = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_' . $wallet );
		if ( ! empty( $wallet_setting ) ) {
			$dispay_button_on = 'display_' . $wallet . '_button_on';

			$items               = $woocommerce->cart->get_cart();
			$pay_for_order_total = 0;
			if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) { // @codingStandardsIgnoreLine.
				global $wp;
				$pay_for_order       = true;
				$pay_for_order_id    = wc_clean( $wp->query_vars['order-pay'] );
				$wc_order            = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
				$items               = $wc_order->get_items();
				$pay_for_order_total = $wc_order->get_total();
			}

			$cart_has_subs       = 0;
			$cart_has_subs_valid = 0;
			foreach ( $items as $item => $values ) {
				if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$_product = wc_get_product( $values['product_id'] );
				} else {
					$_product = wc_get_product( $values['data']->get_id() );
				}
				if ( ! empty( $_product ) ) {
					$cart_products[] = $_product->get_id();
					if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
						$cart_has_subs = 1;
						if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'enable_subs' ) ) {
							$subs_payments = WC_Novalnet_Configuration::get_global_settings( 'subs_payments' );
							if ( in_array( 'novalnet_' . $wallet, $subs_payments, true ) ) {
								$cart_has_subs_valid = 1;
							}
						}
					}
					if ( ! $_product->is_downloadable() && ! $_product->is_virtual() ) {
						$cart_has_virtual = 0;
					}
				}
			}

			$cart_has_external = 0;
			if ( 'product_page' === $page && ( ! empty( $product ) ) ) {
				if ( 'external' === $product->get_type() ) {
					$cart_has_external = 1;
				}
				if ( 'grouped' === $product->get_type() ) {
					continue;
				}

				$product_has_free_trial = false;
				if ( in_array( $product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
					$cart_has_subs = 1;

					$signup_fee   = get_post_meta( $product->get_id(), '_subscription_sign_up_fee', 1 );
					$trial_length = get_post_meta( $product->get_id(), '_subscription_trial_length', 1 );

					$product_has_free_trial = ( $trial_length >= 1 && $signup_fee <= 0 ) ? true : false;

					if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'enable_subs' ) ) {
						$subs_payments = WC_Novalnet_Configuration::get_global_settings( 'subs_payments' );
						if ( in_array( 'novalnet_' . $wallet, $subs_payments, true ) ) {
							$cart_has_subs_valid = 1;
						}
					}
				}

				if ( 'external' === $product->get_type() ) {
					$cart_has_external = 1;
				}

				if ( ! in_array( $product->get_id(), $cart_products, true ) ) {
					if ( ! $product->is_downloadable() && ! $product->is_virtual() ) {
						$cart_has_virtual = 0;
					} else {
						$cart_has_virtual = 1;

					}
				}
			}

			if ( ( 1 === $cart_has_virtual ) && wc_novalnet_check_isset( $wallet_setting, 'enable_for_virtual', 'no' ) ) {
				$is_wallet_available_for_virtual = false;
			}

			if ( $cart_has_subs && $cart_has_subs_valid
			&& ( ( 'product_page' === $page && $product_has_free_trial && WC()->cart->total <= 0 )
				|| ( 'product_page' !== $page && WC()->cart->total <= 0 )
			)
			) {
				if ( version_compare( get_option( 'woocommerce_subscriptions_active_version' ), '4.0.0', '<' ) ) {
					$zero_initial_requires_payment = WC_Subscriptions_Cart::zero_initial_payment_requires_payment();
				} else {
					$zero_initial_requires_payment = WCS_Zero_Initial_Payment_Checkout_Manager::zero_initial_checkout_requires_payment();
				}
				if ( ! $zero_initial_requires_payment ) {
					continue;
				}
			}

			if ( ! $cart_has_external && 'yes' === $wallet_setting['enabled'] && ( is_array( $wallet_setting[ $dispay_button_on ] ) && in_array( $page, $wallet_setting[ $dispay_button_on ], true ) ) && $is_wallet_available_for_virtual ) {
				if ( $cart_has_subs && $cart_has_subs_valid ) {
					$available_wallets[] = $wallet;
				} else {
					if ( 'product_page' === $page && ! $cart_has_subs ) {
						$available_wallets[] = $wallet;
					} elseif ( ( WC()->cart->total > 0 || $pay_for_order_total > 0 ) && ! $cart_has_subs ) {
						$available_wallets[] = $wallet;
					}
				}
			}
		}
	}
	return $available_wallets;
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

/**
 * Set Paypal sheet details
 *
 * @since 12.4.0
 *
 * @param string $parameters The parameters.
 * @param string $wc_order The wc_order.
 *
 * @return void
 */
function set_paypal_sheet_details( &$parameters, $wc_order ) {

	foreach ( $wc_order->get_items() as $item_id => $item_values ) {
		$_product = wc_get_product( $item_values['product_id'] );

		if ( ! empty( $_product ) ) {
			$attributes = '';
			$count      = 0;
			if ( in_array( $_product->get_type(), array( 'variable', 'variable-subscription' ), true ) ) {
				$variation_id = $item_values->get_variation_id();
				$parent       = wp_get_post_parent_id( $variation_id );
				if ( $parent > 0 ) {
					$_product = new WC_Product_Variation( $variation_id );
				}
				foreach ( $_product->get_attributes() as $attr => $value ) {
					if ( 0 === $count ) {
						$attributes = $attr . ':' . $value;
					} else {
						$attributes .= ', ' . $attr . ':' . $value;
					}
					$count++;
				}
				$attributes = '( ' . $attributes . ' )';
			}

			// Get product price.
			$product_price = get_product_price( $_product );

			// Is_downloadable.
			$is_virtual = $_product->is_virtual();

			// Is_downloadable.
			$is_downloadable = $_product->is_downloadable();

			$product_type = 'physical';

			if ( $is_virtual || $is_downloadable ) {
				$product_type = 'digital';
			}

			$product_details = $_product->get_data();

			if ( in_array( $_product->get_type(), array( 'variation', 'subscription_variation' ), true ) && empty( $product_details['short_description'] ) && ! empty( $product_details['description'] ) ) {
				$product_details['short_description'] = wp_strip_all_tags( wp_specialchars_decode( sanitize_text_field( $product_details['description'] ) ) );
			} else {
				$product_details['short_description'] = wp_strip_all_tags( wp_specialchars_decode( sanitize_text_field( $product_details['short_description'] ) ) );
			}

			$product_details['short_description'] = ( ! empty( $product_details['short_description'] ) ) ? substr( preg_replace( '/[^A-Za-z0-9\s]/', '', $product_details['short_description'] ), 0, 100 ) : '';

			$sanitize_title = wp_strip_all_tags( wp_specialchars_decode( sanitize_text_field( $_product->get_title() ) ) );
			$product_title  = preg_replace( '/[^A-Za-z0-9\s]/', '', $sanitize_title );

			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation' ), true ) ) {
				$signup_fee   = get_post_meta( $item_values['product_id'], '_subscription_sign_up_fee', 1 );
				$trial_length = get_post_meta( $item_values['product_id'], '_subscription_trial_length', 1 );

				if ( 0 === (int) $trial_length ) {
					if ( $signup_fee > 0 ) {
						$parameters['cart_info']['line_items'][] = array(
							'name'        => 'Signup Fee',
							'price'       => wc_novalnet_amount_as_string( $signup_fee ),
							'quantity'    => $item_values['quantity'],
							'description' => $product_title,
							'category'    => $product_type,
						);
					}
					$parameters['cart_info']['line_items'][] = array(
						'name'        => $product_title . $attributes,
						'price'       => wc_novalnet_amount_as_string( $product_price ),
						'quantity'    => $item_values['quantity'],
						'description' => $product_details['short_description'],
						'category'    => $product_type,
					);
				} elseif ( $signup_fee > 0 ) {
						$remove_shipping                         = 1;
						$parameters['cart_info']['line_items'][] = array(
							'name'        => 'Signup Fee',
							'price'       => wc_novalnet_amount_as_string( $signup_fee ),
							'quantity'    => $item_values['quantity'],
							'description' => $product_title,
							'category'    => $product_type,
						);
				}
			} else {
				$parameters['cart_info']['line_items'][] = array(
					'name'        => $product_title . $attributes,
					'price'       => wc_novalnet_amount_as_string( $product_price ),
					'quantity'    => $item_values['quantity'],
					'description' => $product_details['short_description'],
					'category'    => $product_type,
				);
			}
		}
	}

	$cart_tax_amount                            = wc_novalnet_amount( $wc_order->get_total_tax() );
	$parameters['cart_info']['items_tax_price'] = wc_novalnet_amount_as_string( $cart_tax_amount );

	$applied_coupon  = WC()->cart->get_applied_coupons();
	$discount_amount = wc_novalnet_amount_as_string( WC()->cart->get_cart_discount_total() );

	if ( ! empty( $applied_coupon[0] ) ) {
		$parameters['cart_info']['line_items'][] = array(
			'name'        => 'Discount',
			'price'       => -$discount_amount,
			'quantity'    => 1,
			'description' => implode( ', ', $wc_order->get_used_coupons() ),
			'category'    => '',
		);
	}

	$shipping_total                                  = wc_novalnet_amount( $wc_order->get_shipping_total() );
	$parameters['cart_info']['items_shipping_price'] = wc_novalnet_amount_as_string( $shipping_total );

	$cart_info_total = 0;
	if ( ! empty( $parameters['cart_info']['line_items'] ) ) {
		foreach ( $parameters['cart_info']['line_items'] as $value ) {
			$cart_info_total += ( $value['price'] * $value['quantity'] );
		}
	}
	$cart_info_total += ( $parameters['cart_info']['items_tax_price'] + $parameters['cart_info']['items_shipping_price'] );

	if ( ! empty( $parameters['subscription']['trial_amount'] ) ) {
		$diff_amount                                 = $parameters['subscription']['trial_amount'] - $cart_info_total;
		$parameters['cart_info']['items_tax_price'] += $diff_amount;
	} else {
		$diff_amount                                 = $parameters['transaction']['amount'] - $cart_info_total;
		$parameters['cart_info']['items_tax_price'] += $diff_amount;
	}
}

/**
 * Get product price
 *
 * @since 12.4.0
 *
 * @param string $_product The product.
 *
 * @return string
 */
function get_product_price( $_product ) {
	if ( wc_prices_include_tax() ) {
		$product_price = wc_get_price_excluding_tax( $_product );
		$product_price = wc_novalnet_amount( $product_price );
	} else {
		$product_price = $_product->get_price();
		$product_price = wc_novalnet_amount( $product_price );
	}
	return $product_price;
}

/**
 * Loads all of the shipping method options for the enable_for_methods field.
 *
 * @return array
 */
function load_shipping_method_options() {
	// Since this is expensive, we only want to do it if we're actually on the settings page.
	if ( ! is_accessing_settings() ) {
		return array();
	}

	$data_store = WC_Data_Store::load( 'shipping-zone' );
	$raw_zones  = $data_store->get_zones();

	foreach ( $raw_zones as $raw_zone ) {
		$zones[] = new WC_Shipping_Zone( $raw_zone );
	}

	$zones[] = new WC_Shipping_Zone( 0 );

	$options = array();
	foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

		$options[ $method->get_method_title() ] = array();

		// Translators: %1$s shipping method name.
		$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );

		foreach ( $zones as $zone ) {

			$shipping_method_instances = $zone->get_shipping_methods();

			foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

				if ( $shipping_method_instance->id !== $method->id ) {
					continue;
				}

				$option_id = $shipping_method_instance->get_rate_id();

				// Translators: %1$s shipping method title, %2$s shipping method id.
				$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

				// Translators: %1$s zone name, %2$s shipping method instance name.
				$option_title = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );

				$options[ $method->get_method_title() ][ $option_id ] = $option_title;
			}
		}
	}

	return $options;
}

/**
 * Checks to see whether or not the admin settings are being accessed by the current request.
 *
 * @return bool
 */
function is_accessing_settings() {
	if ( is_admin() ) {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
			return false;
		}
		if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
			return false;
		}
		if ( ! isset( $_REQUEST['section'] ) ) {
			return false;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return true;
	}
	return false;
}

/**
 * Get canonical order shipping item rate ids
 *
 * @param array $order_shipping_items Order shipping items.
 */
function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

	$canonical_rate_ids = array();

	foreach ( $order_shipping_items as $order_shipping_item ) {
		$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
	}

	return $canonical_rate_ids;
}

/**
 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
 *
 * @since  3.4.0
 *
 * @param array $rate_ids         Rate ids to check.
 * @param array $shipping_methods Shipping methods.
 * @return boolean
 */
function get_matching_rates( $rate_ids, $shipping_methods ) {
	// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
	return array_unique( array_merge( array_intersect( $shipping_methods, $rate_ids ), array_intersect( $shipping_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
}

/**
 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
 *
 * @since  3.4.0
 *
 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
 */
function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

	$shipping_packages  = WC()->shipping()->get_packages();
	$canonical_rate_ids = array();

	if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
		foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
			if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
				$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
				$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
			}
		}
	}

	return $canonical_rate_ids;
}
