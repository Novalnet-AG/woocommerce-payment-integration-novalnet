<?php
/**
 * Novalnet Validation handler
 *
 * This file have all type of validations handled in this module.
 *
 * @class    WC_Novalnet_Validation
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Validation Class.
 *
 * @class   WC_Novalnet_Validation
 */
class WC_Novalnet_Validation {

	/**
	 * Checks the cart amount with the manual check limit
	 * value to process the payment as on-hold transaction.
	 *
	 * @since 12.0.0
	 * @param string $payment_type The payment type.
	 * @param int    $order_amount The order amount.
	 * @param array  $settings     The payment settings.
	 *
	 * @return boolean
	 */
	public static function is_authorize( $payment_type, $order_amount, $settings ) {

		// Check for authorize supported payment and amount limit authorize process.
		if ( novalnet()->get_supports( 'authorize', $payment_type ) && wc_novalnet_check_isset( $settings, 'payment_status', 'authorize' ) ) {
			if ( empty( $settings['limit'] ) || ! self::is_valid_digit( $settings['limit'] ) ) {
				return true;
			}
			return (int) wc_novalnet_formatted_amount( $order_amount ) >= $settings['limit'];
		}
		return false;

	}

	/**
	 * Is force payment disabled
	 *
	 * @since 12.0.0
	 * @param array $settings The settings.
	 *
	 * @return boolean
	 */
	public static function is_force_payment_disabled( $settings ) {

		return ( wc_novalnet_check_isset( $settings, 'force_normal_payment', 'no' ) );
	}

	/**
	 * Validates the Novalnet global configuration
	 *
	 * @since 12.0.0
	 * @param array $options The novalnet options value.
	 *
	 * @return boolean
	 */
	public static function check_global_configuration( $options ) {
		// Validate global configuration fields.
		if ( empty( $options ['public_key'] ) || empty( $options ['key_password'] ) ) {
			return true;
		} elseif ( ! self::is_valid_digit( $options ['tariff_id'] ) ) {
			return true;
		} elseif ( wc_novalnet_check_isset( $options, 'enable_subs', '1' ) && empty( $options ['subs_payments'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Validate the global configuration from back-end.
	 *
	 * @param array $request The request values.
	 *
	 * @since  12.0.0
	 * @return boolean
	 */
	public static function validate_configuration( $request ) {

		$options = array();
		foreach ( $request as $k => $v ) {
			$key              = str_replace( 'novalnet_', '', $k );
			$options [ $key ] = $v;
		}
		// Validate global configuration fields.
		return ( self::check_global_configuration( $options ) );
	}

	/**
	 * Validate Customer details.
	 *
	 * @since 12.0.0
	 *
	 * @param array $parameters The customer parameters.
	 * @param array $customer_data Required customer data.
	 *
	 * @return boolean
	 */
	public static function has_valid_customer_data( $parameters, $customer_data = array() ) {
		$required_customer_data = array(
			'customer' => array(
				'first_name' => __( 'Billing First name', 'woocommerce-novalnet-gateway' ),
				'last_name'  => __( 'Billing Last name', 'woocommerce-novalnet-gateway' ),
				'email'      => __( 'Billing Email address', 'woocommerce-novalnet-gateway' ),
				'billing'    => array(
					'street'       => __( 'Billing Street address', 'woocommerce-novalnet-gateway' ),
					'city'         => __( 'Billing Town / City', 'woocommerce-novalnet-gateway' ),
					'zip'          => __( 'Billing Postcode / ZIP', 'woocommerce-novalnet-gateway' ),
					'country_code' => __( 'Billing Country / Region', 'woocommerce-novalnet-gateway' ),
				),
			),
		);
		$required_customer_data = ( ! empty( $customer_data ) ) ? $customer_data : $required_customer_data;

		foreach ( $required_customer_data as $index => $data ) {

			if ( is_array( $data ) ) {
				if ( empty( $parameters[ $index ] ) ) {
					return array(
						'is_invalid' => true,
						/* translators: %1$s: missing data */
						'message'    => sprintf( __( 'Please enter values in the required fields - %1$s', 'woocommerce-novalnet-gateway' ), ucwords( str_replace( '_', ' ', $index ) ) ),
					);
				}
				return self::has_valid_customer_data( $parameters[ $index ], $data );
			}
			if ( ! isset( $parameters[ $index ] ) || empty( trim( $parameters[ $index ] ) ) ) {
				return array(
					'is_invalid' => true,
					/* translators: %1$s: missing data */
					'message'    => sprintf( __( 'Please enter values in the required fields - %1$s', 'woocommerce-novalnet-gateway' ), $data ),
				);
			}
		}
		return array( 'is_invalid' => false );
	}

	/**
	 * Validate payment input fileds.
	 *
	 * @since 12.0.0
	 *
	 * @param array $input_values The payment values.
	 * @param array $field_names  Field names need to check.
	 *
	 * @return boolean.
	 */
	public static function validate_payment_input_field( $input_values, $field_names ) {

		foreach ( $field_names as $field_name ) {
			if ( empty( $input_values[ $field_name ] ) ) {
				return false;
			} elseif ( preg_match( '/[#%\^<>@$=*!]/', $input_values[ $field_name ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Restrict the gateway based on configuration
	 *
	 * @since 12.0.0
	 *
	 * @param string $settings     The settings.
	 * @param string $payment_type The payment type.
	 *
	 * @return boolean
	 */
	public static function is_payment_available( $settings, $payment_type = '' ) {
		global $woocommerce;

		if ( empty( WC_Novalnet_Configuration::get_global_settings( 'public_key' ) ) || empty( WC_Novalnet_Configuration::get_global_settings( 'key_password' ) ) ) {
			return false;
		}

		$needs_shipping = false;
		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( $order && 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( wc_novalnet_check_isset( $settings, 'enable_for_virtual', 'no' ) && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $settings['enable_for_methods'] ) && $needs_shipping ) {
			$order_shipping_items            = ( isset( $order ) && is_object( $order ) ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( get_matching_rates( $canonical_rate_ids, $settings['enable_for_methods'] ) ) ) {
				return false;
			}
		}

		// Set min_amount equals to 1 if the cart contains subscription and min_amount empty.
		if ( empty( $settings ['min_amount'] ) && apply_filters( 'novalnet_cart_contains_subscription', true )
		&& in_array( $payment_type, array( 'novalnet_barzahlen', 'novalnet_multibanco' ), true ) && ! is_admin() && ! is_account_page() ) {
			$settings ['min_amount'] = 1;
		}

		if ( ! is_admin() && ! empty( $settings ['min_amount'] ) ) {
			if ( apply_filters( 'novalnet_cart_contains_subscription', true ) && ! is_account_page() ) {

				if ( ! in_array( $payment_type, WC_Novalnet_Configuration::get_global_settings( 'subs_payments' ), true ) ) {
					return false;
				}

				$recurring_carts = WC()->cart->recurring_carts;
				if ( ! empty( $recurring_carts ) ) {
					foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
						if ( wc_novalnet_formatted_amount( $recurring_cart->total ) < $settings ['min_amount'] ) {
							return false;
						}
					}
				}

				$items = $woocommerce->cart->get_cart();
				if ( ! empty( $items ) ) {
					foreach ( $items as $values ) {
						$product = wc_get_product( $values['data']->get_id() );
						if ( ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) ) {
							if ( (int) ( WC_Subscriptions_Product::get_price( $product ) ) === 0 && wc_novalnet_formatted_amount( WC()->cart->total ) > $settings ['min_amount'] ) {
								return true;
							}
							if ( ( ! empty( $values['quantity'] ) && ( ( ( $product->get_price() ) * (int) $values['quantity'] + WC()->cart->get_shipping_total() ) * 100 ) < $settings['min_amount'] ) || wc_novalnet_formatted_amount( WC()->cart->total ) < $settings ['min_amount'] ) {
								return false;
							}
						}
					}
				}
			} else {
				if ( is_account_page() || ! empty( novalnet()->request ['change_payment_method'] ) ) {
					return true;
				}

				if ( is_object( WC()->cart ) ) {
					$total        = ( novalnet()->helper()->get_pay_order() ) ? novalnet()->helper()->get_pay_order()->get_total() : WC()->cart->total;
					$order_amount = wc_novalnet_formatted_amount( $total );
				}

				return ( ! empty( $order_amount ) && 0 < $order_amount && 0 < $settings ['min_amount'] && $settings ['min_amount'] <= $order_amount );
			}
		}

		return true;
	}

	/**
	 * Checks for the given string in given text.
	 *
	 * @since 12.0.0
	 * @param string $string The string value.
	 * @param string $data   The data value.
	 *
	 * @return boolean
	 */
	public static function check_string( $string, $data = 'novalnet' ) {
		return ( false !== strpos( $string, $data ) );
	}

	/**
	 * Validate payment input fileds.
	 *
	 * @param string $payment_type The payment type.
	 * @param array  $settings     The payment settings.
	 * @param bool   $normal       Flag to notify normal payment.
	 * @since 11.2.0
	 *
	 * @return boolean.
	 */
	public static function is_guarantee_available( $payment_type, $settings, $normal = false ) {

		if ( wc_novalnet_check_session() ) {
			global $woocommerce;

			$order = ( novalnet()->helper()->get_pay_order() ) ? novalnet()->helper()->get_pay_order() : WC()->session->customer;
			// Basic validations.
			if ( ! wc_novalnet_check_isset( $settings, 'enabled', 'yes' ) ) {
				return false;
			} elseif ( 'EUR' !== get_woocommerce_currency() ) {
				return false;
			} elseif ( ! self::is_payment_available( $settings, $payment_type ) ) {
				return false;
			}

			if ( class_exists( 'WC_Subscriptions_Switcher' ) ) {
				$cart_switches = WC_Subscriptions_Switcher::cart_contains_switches();
				if ( isset( WC()->cart ) && false !== $cart_switches && wc_novalnet_check_isset( $settings, 'force_normal_payment', 'yes' ) ) {
					return false;
				}
			}

			// Billing address.
			list( $billing_customer, $billing_address ) = novalnet()->helper()->get_address( $order, 'billing' );

			// Shipping address.
			list( $shipping_customer, $shipping_address ) = novalnet()->helper()->get_address( $order, 'shipping' );

			// Check for same billing & shipping address.
			if ( ! empty( $shipping_address ) && $billing_address !== $shipping_address ) {
				return false;
			}

			// Assigning post values in session.
			$session = novalnet()->helper()->set_post_value_session(
				$payment_type,
				array(
					'post_data',
				)
			);

			// Check for B2B user.
			if ( ! empty( $session ['post_data'] ) ) {
				parse_str( $session ['post_data'], $posted_data );
				if ( wc_novalnet_check_isset( $settings, 'allow_b2b', 'yes' ) && ! empty( $posted_data['novalnet_valid_company'] ) ) {
					WC()->session->__unset( $payment_type . '_show_dob' );
				} else {
					WC()->session->set( $payment_type . '_show_dob', true );
				}
			} elseif ( ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) ) { // @codingStandardsIgnoreLine.
				if ( wc_novalnet_check_isset( $settings, 'allow_b2b', 'yes' ) && is_object( $order ) && ! empty( $order->get_billing_company() ) ) {
					WC()->session->__unset( $payment_type . '_show_dob' );
				} else {
					WC()->session->set( $payment_type . '_show_dob', true );
				}
			}

			if ( ! empty( $billing_address['country_code'] ) && ! in_array( $billing_address['country_code'], apply_filters( 'novalnet_allowed_guaranteed_countries', $payment_type ), true ) ) {
				return false;
			}

			return true;
		}

		return true;
	}

	/**
	 * Check for change payment method.
	 *
	 * @since 12.0.0
	 *
	 * @return boolean.
	 */
	public static function is_change_payment_method() {
		if ( ! empty( novalnet()->request ['change_payment_method'] ) ) {
			WC()->session->set( 'novalnet_change_payment_method', novalnet()->request ['change_payment_method'] );
			return true;
		} else {
			WC()->session->__unset( 'novalnet_change_payment_method' );
		}
		return false;
	}

	/**
	 * Check the order is failed renewal order.
	 *
	 * @since 12.6.0
	 *
	 * @param WC_Order $wc_order The order object.
	 *
	 * @return boolean.
	 */
	public static function is_failed_renewal_order( $wc_order ) {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			if ( ! wcs_order_contains_early_renewal( $wc_order ) && wcs_order_contains_renewal( $wc_order ) && $wc_order->has_status( array( 'failed' ) ) && wc_novalnet_check_session() ) {
				novalnet()->helper()->novalnet_update_wc_order_meta( $wc_order, '_novalnet_renew_subscription', 1, true );
				return true;
			}
			novalnet()->helper()->novalnet_delete_wc_order_meta( $wc_order, '_novalnet_renew_subscription', true );
		}
		return false;
	}

	/**
	 * Check is pay for order
	 *
	 * @since 12.5.5
	 */
	public static function is_pay_for_order() {
		if ( isset( novalnet()->request['pay_for_order'], novalnet()->request['key'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check for Valid checksum recevied from Novalnet server.
	 *
	 * @param string $data       The payment data.
	 * @param string $txn_secret The txn secret.
	 * @param string $access_key The access key.
	 *
	 * @since 12.0.0
	 */
	public static function is_valid_checksum( $data, $txn_secret, $access_key ) {

		if ( ! empty( $data['checksum'] ) && ! empty( $data['tid'] ) && ! empty( $data ['status'] ) && ! empty( $txn_secret ) && ! empty( $access_key ) ) {
			$checksum = hash( 'sha256', $data['tid'] . $txn_secret . $data['status'] . strrev( $access_key ) );
			if ( $checksum === $data['checksum'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validates the given input data is numeric or not.
	 *
	 * @since 12.0.0
	 * @param int $input The input value.
	 *
	 * @return boolean
	 */
	public static function is_valid_digit( $input ) {
		return (bool) ( preg_match( '/^[0-9]+$/', $input ) );
	}

	/**
	 * Checks guarantee payment.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $session      The payment session data.
	 * @param string $payment_type The payment type.
	 * @param array  $settings     The payment settings.
	 *
	 * @return string
	 */
	public static function has_valid_guarantee_input( $session, $payment_type, $settings = array() ) {
		$message = '';

		// No need to check the Date of birth, if the order is belongs to B2B user.
		if ( ! empty( $session [ $payment_type . '_dob' ] ) ) {

			if ( time() < strtotime( '+18 years', strtotime( $session [ $payment_type . '_dob' ] ) ) ) {
				$message = __( 'You need to be at least 18 years old', 'woocommerce-novalnet-gateway' );
			} else {
				$date                              = gmdate( 'Y-m-d', strtotime( $session [ $payment_type . '_dob' ] ) );
				$session[ $payment_type . '_dob' ] = $date;
				WC()->session->set( $payment_type, $session );
				WC()->session->__unset( $payment_type . '_switch_payment' );
			}
		} elseif ( ! WC()->session->__isset( $payment_type . '_dob_hided' ) && ! is_admin() ) {
			$message = __( 'Please enter your date of birth', 'woocommerce-novalnet-gateway' );
		}

		// Check order amount and address, if the order is placed by admin.
		if ( is_admin() && WC()->session->get( 'admin_add_shop_order' ) ) {
			$order  = wc_get_order( novalnet()->request['post_ID'] );
			$amount = wc_novalnet_formatted_amount( $order->get_total() );
			if ( $amount < $settings['min_amount'] ) {
				$min_amount = wc_novalnet_shop_amount_format( $settings['min_amount'] );
				$message   .= '<br>';
				/* translators: Minimum order amount %s */
				$message .= sprintf( __( 'Minimum order amount should be greater than or equal to %s', 'woocommerce-novalnet-gateway' ), $min_amount );
			}
			list($billing_customer, $billing_address) = novalnet()->helper()->get_address( $order, 'billing' );
			// Get shipping details.
			list($shipping_customer, $shipping_address) = novalnet()->helper()->get_address( $order, 'shipping' );
			if ( $billing_address !== $shipping_address ) {
				$message .= '<br>';
				$message .= __( 'The billing address must be the same as the shipping address', 'woocommerce-novalnet-gateway' );
			}
		}
		if ( '' !== $message && novalnet()->get_supports( 'guarantee', $payment_type ) ) {

			// Switch payment to normal payment.
			if ( wc_novalnet_check_isset( $settings, 'force_normal_payment', 'yes' ) ) {
				$switch_payment         = wc_novalnet_switch_payment( $payment_type );
				$non_guarantee_settings = WC_Novalnet_Configuration::get_payment_settings( $switch_payment );
				if ( wc_novalnet_check_isset( $non_guarantee_settings, 'enabled', 'yes' ) ) {
					WC()->session->set( $payment_type . '_switch_payment', true );
					$message = '';
				}
			}
		}
		return $message;
	}

	/**
	 * Get Instalment Cycles from Instalment payment settings.
	 *
	 * @param array  $settings   The payment settings.
	 * @param string $amount     Transaction amount.
	 * @since 12.0.0
	 *
	 * @return array
	 */
	public static function has_valid_instalment_cycles( $settings, $amount ) {
		$total_period = $settings['instalment_total_period'];

		$cycle_amount = wc_novalnet_formatted_amount( $amount ) / min( $total_period );
		if ( $cycle_amount >= 999 ) {
			return true;
		}
		return false;
	}

	/**
	 * Is force payment available
	 *
	 * @param array $settings   The payment settings.
	 * @since 12.0.0
	 *
	 * @return bool
	 */
	public static function is_force_payment_available( $settings ) {
		return wc_novalnet_check_isset( $settings, 'enabled', 'yes' ) && wc_novalnet_check_isset( $settings, 'force_normal_payment', 'yes' );
	}
	/**
	 * Is subscription available
	 *
	 * @since 12.0.0
	 *
	 * @return bool
	 */
	public static function is_subscription_plugin_available() {
		return class_exists( 'WC_Subscription' );
	}

	/**
	 * Check for the success status of the
	 * Novalnet payment call.
	 *
	 * @since 12.0.0
	 *
	 * @param array $data The given array.
	 *
	 * @return boolean
	 */
	public static function is_success_status( $data ) {
		return ( ( ! empty( $data['result']['status'] ) && 'SUCCESS' === $data['result']['status'] ) || ( ! empty( $data['status'] ) && 'SUCCESS' === $data['status'] ) );
	}

	/**
	 * Do minimum amount validation for the guaranteed / Instalment.
	 *
	 * @since 12.0.0
	 */
	public static function backend_validation() {
		if ( ! empty( novalnet()->request['section'] ) ) {
			$payment_type       = novalnet()->request['section'];
			$min_amount         = ( isset( novalnet()->request [ 'woocommerce_' . $payment_type . '_min_amount' ] ) ) ? ( (int) novalnet()->request [ 'woocommerce_' . $payment_type . '_min_amount' ] ) : '';
			$default_min_amount = 100;
			$error              = true;
			if ( empty( WC_Novalnet_Configuration::get_global_settings( 'public_key' ) ) || empty( WC_Novalnet_Configuration::get_global_settings( 'key_password' ) ) ) {
				$error  = __( 'Please enter the required fields under Novalnet API Configuration.', 'woocommerce-novalnet-gateway' );
				$error .= sprintf(
					/* translators: %s: novalnet-settings link */
					__( ' Novalnet API configuration can be adjusted <a href="%s">here</a>.', 'woocommerce-novalnet-gateway' ),
					wc_novalnet_generate_admin_link(
						array(
							'page' => 'wc-settings',
							'tab'  => 'novalnet-settings',
						)
					)
				);
				WC_Admin_Meta_Boxes::add_error( $error );
				wc_novalnet_safe_redirect( admin_url( "admin.php?page=wc-settings&tab=checkout&section=$payment_type" ) );
			} elseif ( in_array( $payment_type, array( 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa', 'novalnet_instalment_invoice', 'novalnet_instalment_sepa' ), true ) ) {

				$default_min_amount = 999;

				if ( novalnet()->get_supports( 'instalment', $payment_type ) ) {

					if ( empty( novalnet()->request [ 'woocommerce_' . $payment_type . '_instalment_total_period' ] ) ) {
						WC_Admin_Meta_Boxes::add_error( __( 'Please select atleast one of the instalment cycles to proceed', 'woocommerce-novalnet-gateway' ) );
						wc_novalnet_safe_redirect( admin_url( "admin.php?page=wc-settings&tab=checkout&section=$payment_type" ) );
					}

					if ( ! empty( novalnet()->request [ 'woocommerce_' . $payment_type . '_instalment_total_period' ] ) ) {
						$default_min_amount *= min( novalnet()->request [ 'woocommerce_' . $payment_type . '_instalment_total_period' ] );
					} else {
						$default_min_amount *= 3;
					}
				}

				if ( $min_amount < $default_min_amount ) {

					/* translators: %s: default_min_amount */
					WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Minimum order amount should be greater than or equal to %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $default_min_amount ) ) );
					wc_novalnet_safe_redirect( admin_url( "admin.php?page=wc-settings&tab=checkout&section=$payment_type" ) );
				}
			} elseif ( ! in_array( $payment_type, array( 'novalnet_googlepay', 'novalnet_applepay' ), true ) && $min_amount < 0 ) {

				/* translators: %s: default_min_amount */
				WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Minimum order amount should be greater than or equal to %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $default_min_amount ) ) );
				wc_novalnet_safe_redirect( admin_url( "admin.php?page=wc-settings&tab=checkout&section=$payment_type" ) );
			}
		}
	}

	/**
	 * Verify that the selected tariff supports zero amount bookings.
	 *
	 * @since 12.6.0
	 *
	 * @return bool
	 */
	public static function check_zero_amount_tariff_types() {
		if ( '2' === WC_Novalnet_Configuration::get_global_settings( 'tariff_type' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check the availability of the zero amount booking process for payments.
	 *
	 * @since 12.6.0
	 *
	 * @param string $payment_id Payment id.
	 * @param object $wc_order   The order object.
	 *
	 * @return bool
	 */
	public static function can_proceed_zero_amount_booking( $payment_id, $wc_order = '' ) {
		$process_zero_amount = false;
		$payment_settings    = WC_Novalnet_Configuration::get_payment_settings( $payment_id );
		if ( novalnet()->get_supports( 'zero_amount_booking', $payment_id ) && self::check_zero_amount_tariff_types() && wc_novalnet_check_isset( $payment_settings, 'payment_status', 'zero_amount_booking' ) ) {
			$process_zero_amount = true;
		}

		if ( isset( WC()->cart ) && WC()->cart->needs_payment() && ! ( WC()->cart->total > 0 ) ) {
			$process_zero_amount = false;
		}

		if ( self::is_pay_for_order() && empty( $wc_order ) && ! is_object( $wc_order ) ) {
			$wc_order = novalnet()->helper()->get_pay_order();
		}

		return apply_filters( 'can_proceed_zero_amount_booking', $process_zero_amount, $wc_order );
	}
}
