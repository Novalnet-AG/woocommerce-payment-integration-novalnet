<?php
/**
 * Novalnet Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class    WC_Novalnet_Abstract_Payment_Gateways
 * @extends  WC_Payment_Gateway
 * @package  woocommerce-novalnet-gateway/includes/abstracts/
 * @category Abstract Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Abstract_Payment_Gateways Abstract Class.
 */
abstract class WC_Novalnet_Abstract_Payment_Gateways extends WC_Payment_Gateway {


	/**
	 * Settings of the gateway.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * True if the gateway shows fields on the checkout.
	 *
	 * @var bool
	 */
	public $has_fields = true;


	/**
	 * Form gateway parameters to process in the Novalnet server.
	 *
	 * @param WC_Order $wc_order    The order object.
	 * @param int      $parameters  The parameters.
	 */
	abstract public function generate_payment_parameters( $wc_order, &$parameters );

	/**
	 * Perform the payment call to Novalnet server.
	 *
	 * @since 12.0.0
	 * @param int $wc_order_id The order id.
	 *
	 * @return array
	 */
	public function perform_payment_call( $wc_order_id ) {

		// The order object.
		$wc_order = new WC_Order( $wc_order_id );

		// Set current payment id in session.
		WC()->session->set( 'current_novalnet_payment', $this->id );

		// Generate basic parameters.
		$parameters = $this->generate_basic_parameters( $wc_order, WC_Novalnet_Validation::is_change_payment_method( $wc_order ) );

		// Generate payment related parameters.
		$this->generate_payment_parameters( $wc_order, $parameters );

		// Set endpoint.
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {
			$endpoint                                  = novalnet()->helper()->get_action_endpoint( 'subscription_update' );
			$parameters['transaction']['payment_type'] = novalnet()->get_payment_types( $this->id );
		} else {
			$endpoint = $this->get_payment_endpoint();
		}

		// Update order number in post meta.
		if ( ! empty( $parameters ['transaction']['order_no'] ) ) {
			update_post_meta( $wc_order->get_id(), '_novalnet_order_number', $parameters ['transaction']['order_no'] );
		}

		// Submit the given request.
		$response = novalnet()->helper()->submit_request( $parameters, $endpoint, array( 'post_id' => $wc_order->get_id() ) );

		// Handle redirection (if needed).
		if ( ! empty( $response ['result'] ['redirect_url'] ) && ! empty( $response['transaction']['txn_secret'] ) ) {
			WC()->session->set( 'novalnet_post_id', $wc_order_id );
			WC()->session->set( 'novalnet_txn_secret', $response['transaction']['txn_secret'] );
			novalnet()->helper()->debug( 'Going to redirect the end-user to the URL - ' . $response ['result'] ['redirect_url'] . ' to complete the payment', $wc_order_id );
			return array(
				'result'   => 'success',
				'redirect' => $response ['result'] ['redirect_url'],
			);
		}

		// Handle response.
		return $this->check_transaction_status( $response, $wc_order );
	}

	/**
	 * Built logo with link to display in front-end.
	 *
	 * @since 12.0.0
	 *
	 * @return string
	 */
	public function built_logo() {
		$icon = '';

		if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'payment_logo' ) ) {
			$icon_url = novalnet()->plugin_url . '/assets/images/' . $this->id . '.png';
			$icon     = "<img src='$icon_url' alt='" . $this->title . "' title='" . $this->title . "' />";
		}
		return $icon;
	}


	/**
	 * Align order confirmation mail transaction comments.
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order The order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 */
	public function add_email_instructions( $wc_order, $sent_to_admin ) {
		$language = strtolower( wc_novalnet_shop_language() );

		if ( $wc_order->get_payment_method() === $this->id && ! $sent_to_admin && ! empty( $this->settings [ 'instructions_' . $language ] ) ) {

			// Set email notes.
			echo wp_kses_post( wpautop( wptexturize( $this->settings [ 'instructions_' . $language ] ) ) );

		}
	}

	/**
	 * Align order confirmation transaction comments in checkout page.
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order The order object.
	 */
	public function align_transaction_details( $wc_order ) {

		// Check Novalnet payment.
		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {
			if ( version_compare( WOOCOMMERCE_VERSION, '3.6.5', '>' ) ) {
				$wc_order->set_customer_note( str_replace( PHP_EOL, '<\br>', $wc_order->get_customer_note() ) );
			}
		}
	}

	/**
	 * Forming basic params to process payment in Novalnet server.
	 *
	 * @since 12.0.0
	 * @param WC_Order $wc_order           The order object.
	 * @param string   $is_change_payment  The change payment.
	 *
	 * @throws Exception For error.
	 * @return array
	 */
	public function generate_basic_parameters( $wc_order, $is_change_payment ) {

		$parameters = array();

		if ( ! $is_change_payment ) {

			// Form vendor parameters.
			$parameters['merchant'] = array(
				'signature' => WC_Novalnet_Configuration::get_global_settings( 'public_key' ),
				'tariff'    => WC_Novalnet_Configuration::get_global_settings( 'tariff_id' ),
			);

			// Form order details parameters.
			$parameters['transaction'] = array(
				// Add payment type defined in Novalnet.
				'payment_type'   => novalnet()->get_payment_types( $this->id ),

				// Add test mode value as 1/ 0 based on configuration value.
				'test_mode'      => (int) ( 'yes' === $this->settings ['test_mode'] ),

				// Add Amount details.
				'amount'         => wc_novalnet_formatted_amount( $wc_order->get_total() ),
				'currency'       => get_woocommerce_currency(),

				// Add formated order number.
				'order_no'       => ltrim( $wc_order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ) ),

				// Add System details.
				'system_name'    => 'wordpress-woocommerce',
				'system_version' => get_bloginfo( 'version' ) . '-' . WOOCOMMERCE_VERSION . '-NN' . NOVALNET_VERSION,
				'system_url'     => site_url(),
				'system_ip'      => wc_novalnet_get_ip_address( 'SERVER_ADDR' ),
			);
		}

		$parameters['customer'] = novalnet()->helper()->get_customer_data( $wc_order );

		$parameters['custom'] ['lang'] = wc_novalnet_shop_language();

		if ( ! is_admin() && wc_novalnet_check_session() ) {

			if ( ! empty( $parameters['transaction'] ['order_no'] ) ) {
				WC()->session->set( 'formatted_order_no', $parameters['transaction'] ['order_no'] );
			}
			// Set current payment method in session.
			WC()->session->set( 'current_novalnet_payment', $this->id );

			$is_change_payment = (bool) WC()->session->__isset( 'novalnet_change_payment_method' );

			if ( ! WC_Novalnet_Validation::has_valid_customer_data( $parameters ) ) {
				throw new Exception( __( 'Please enter values in the required fields', 'woocommerce-novalnet-gateway' ) );
			}
		}

		if ( $this->supports( 'subscriptions' ) ) {

			// Set Subscription related parameters if available.
			$parameters = apply_filters( 'novalnet_generate_subscription_parameters', $parameters, $wc_order, $is_change_payment );
		}

		// Save customer note.
		$customer_given_note = $wc_order->get_customer_note();
		if ( ! empty( $customer_given_note ) ) {
			update_post_meta( $wc_order->get_id(), '_nn_customer_given_note', $customer_given_note );
		}

		return $parameters;
	}

	/**
	 * Form basic redirect payment parameters.
	 *
	 * @since 12.0.0
	 *
	 * @param array    $wc_order     The encode values.
	 * @param WC_Order $parameters   The parameters.
	 */
	public function redirect_payment_params( $wc_order, &$parameters ) {

		// Customize the shop return URL's based on payment process type.
		$parameters ['transaction']['return_url'] = esc_url( add_query_arg( 'wc-api', 'response_' . $this->id, apply_filters( 'novalnet_return_url', $this->get_return_url( $wc_order ) ) ) );

		// Send order number in input value.
		$parameters ['custom']['input1']    = 'nn_shopnr';
		$parameters ['custom']['inputval1'] = $wc_order->get_id();
	}

	/**
	 * Assigning the shop order process based on the
	 * Novalnet server response whether success / failure.
	 *
	 * @since 12.0.0
	 * @param string   $server_response The server response data.
	 * @param WC_Order $wc_order        The order object.
	 * @param bool     $is_webhook      The flag to notify the webhook action.
	 *
	 * @return array|string
	 */
	public function check_transaction_status( $server_response, $wc_order, $is_webhook = false ) {

		novalnet()->helper()->debug( 'Response successfully reached to shop for the order: ' . $wc_order->get_id(), $wc_order->get_id() );
		if ( WC_Novalnet_Validation::is_success_status( $server_response ) ) {
			return $this->transaction_success( $server_response, $wc_order, $is_webhook );
		}
		return $this->transaction_failure( $server_response, $wc_order, $is_webhook );
	}

	/**
	 * Transaction success process for completing the order.
	 *
	 * @since 12.0.0
	 * @param array    $server_response The server response data.
	 * @param WC_Order $wc_order        The order object.
	 * @param bool     $is_webhook      The flag to notify the webhook action.
	 *
	 * @return array|string
	 */
	public function transaction_success( $server_response, $wc_order, $is_webhook ) {

		$subs_id = '';
		if ( ! empty( $server_response ['subscription']['subs_id'] ) ) {
			$subs_id = $server_response ['subscription']['subs_id'];
		}

		// Store payment token (if applicable).
		$this->store_payment_token( $server_response ['transaction'], $wc_order );

		// Request sent to process change payment method in Novalnet server.
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {
			$wcs_order = new WC_Order( $wc_order->get_id() );

			// Update recurring payment process.
			if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {

				do_action( 'novalnet_update_recurring_payment', $server_response, $wc_order->get_parent_id(), $this->id, $wcs_order );
			}

			$success_url = $this->get_return_url( $wcs_order );
			if ( ! empty( $server_response['transaction']['redirect_url'] ) ) {
				// Get success URL for change payment method.
				$data        = apply_filters( 'novalnet_subscription_change_payment_method_success_url', $wcs_order );
				$success_url = $data ['success_url'];
			}

			return $this->novalnet_redirect( $success_url );

			// Update comments with TID for normal payment.
		} elseif ( empty( novalnet()->request ['change_payment_method'] ) ) {

			// Form order comments.
			$transaction_comments = novalnet()->helper()->prepare_payment_comments( $server_response );
			if ( empty( $is_webhook ) ) {
				$customer_given_note = $wc_order->get_customer_note();
			}

			if ( empty( $customer_given_note ) ) {
				$nn_customer_given_note = get_post_meta( $wc_order->get_id(), '_nn_customer_given_note', true );
				if ( ! empty( $nn_customer_given_note ) ) {
					$customer_given_note = $nn_customer_given_note;
				}
			}

			// Update order comments.
			novalnet()->helper()->update_comments( $wc_order, $transaction_comments, true, 'transaction_info', false );

			// Update comments for change payment method (initiated newly).
		} else {

			/* translators: %s: Message  */
			$message = wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );

			// Update order comments.
			novalnet()->helper()->update_comments( $wc_order, $message, true, 'note', true );
		}
		$insert_data = array(
			'order_no'       => $wc_order->get_id(),
			'tid'            => $server_response['transaction']['tid'],
			'currency'       => get_woocommerce_currency(),
			'gateway_status' => $server_response['transaction']['status'],
			'payment_type'   => $this->id,
			'subs_id'        => $subs_id,
			'amount'         => wc_novalnet_formatted_amount( $wc_order->get_total() ),
		);

		$insert_data['callback_amount'] = $insert_data ['amount'];

		if ( novalnet()->get_supports( 'instalment', $this->id ) ) {
			$insert_data ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $server_response );
		}
		if ( ( novalnet()->get_supports( 'pay_later', $this->id ) || 'novalnet_guaranteed_invoice' === $this->id || 'novalnet_instalment_invoice' === $this->id ) ) {
			if ( ! empty( $insert_data ['additional_info'] ) ) {
				$insert_data ['additional_info'] = wc_novalnet_serialize_data( wc_novalnet_unserialize_data( $insert_data ['additional_info'] ) + $server_response['transaction']['bank_details'] );
			} elseif ( ! empty( $server_response['transaction']['bank_details'] ) ) {
				$insert_data ['additional_info'] = wc_novalnet_serialize_data( $server_response['transaction']['bank_details'] );
			} elseif ( ! empty( $server_response['transaction']['nearest_stores'] ) ) {
				$insert_data ['additional_info'] = wc_novalnet_serialize_data( $server_response['transaction']['nearest_stores'] );
			}
		}

		update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $server_response ['transaction'] ['status'] );

		if ( in_array( $insert_data['gateway_status'], array( 'PENDING', 'ON_HOLD' ), true ) ) {
			$insert_data ['callback_amount'] = 0;
		}

		if ( ! empty( $server_response['transaction']['checkout_js'] ) && ! empty( $server_response['transaction']['checkout_token'] ) ) {
			$overlay_details                    = array();
			$overlay_details ['checkout_js']    = $server_response['transaction']['checkout_js'];
			$overlay_details ['checkout_token'] = $server_response['transaction']['checkout_token'];
			update_post_meta( $wc_order->get_id(), '_nn_cp_checkout_token', wc_novalnet_serialize_data( $overlay_details ) );
		}

		// Unset the Novalnet sessions.
		wc_novalnet_unset_payment_session( $this->id );

		// Insert the transaction details.
		novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );

		// Update Novalnet version while processing the current post id.
		update_post_meta( $wc_order->get_id(), '_nn_version', NOVALNET_VERSION );

		// Complete the payment process.
		$wc_order->payment_complete( $server_response['transaction']['tid'] );

		if ( ! empty( $transaction_comments ) ) {

			if ( empty( $customer_given_note ) ) {
				// Update customer note.
				$wc_order->set_customer_note( wc_novalnet_format_text( $transaction_comments ) );
			} else {
				// Update customer note.
				$wc_order->set_customer_note( $customer_given_note . PHP_EOL . wc_novalnet_format_text( $transaction_comments ) );
			}
			$wc_order->save();
		}

		// Handle subscription process.
		do_action( 'novalnet_handle_subscription_post_process', $wc_order->get_id(), $this->id, $server_response, $wc_order );

		// Log to notify order got success.
		novalnet()->helper()->debug( 'Transaction got completed successfully TID: ' . $server_response['transaction']['tid'], $wc_order->get_id() );

		if ( ! empty( $is_webhook ) ) {
			return $transaction_comments;
		}
		return $this->novalnet_redirect( $this->get_return_url( $wc_order ) );
	}

	/**
	 * Transaction failure process which cancel the
	 * order and redirect to checkout page with error.
	 *
	 * @since 12.0.0
	 * @param array    $server_response The server response data.
	 * @param WC_Order $wc_order        The order object.
	 * @param bool     $is_webhook      The flag to notify the webhook action.
	 *
	 * @return array
	 *
	 * @throws Exception For admin change payment method.
	 */
	public function transaction_failure( $server_response, $wc_order, $is_webhook = false ) {

		// Get message.
		$message = wc_novalnet_response_text( $server_response );

		// Log to notify order got failed.
		novalnet()->helper()->debug( "Transaction got failed due to: $message", $wc_order->get_id() );
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {

			// Update cancelled transaction payment method with old payment method.
			$old_payment_method = $wc_order->get_meta( '_old_payment_method' );
			$old_payment_title  = $wc_order->get_meta( '_old_payment_method_title' );
			update_post_meta( WC()->session->novalnet_change_payment_method, '_payment_method', $old_payment_method );
			update_post_meta( WC()->session->novalnet_change_payment_method, '_payment_method_title', $old_payment_title );

			// Update notice comments.
			/* translators: %s: Reason */
			$transaction_comments = sprintf( __( 'Your action to change payment method failed for the renewal order due to %s', 'woocommerce-novalnet-gateway' ), $message );

			// Update transaction comments.
			novalnet()->helper()->update_comments( $wc_order, $transaction_comments, true, 'note' );

		} else {
			// Form transaction comments.
			$transaction_comments = novalnet()->helper()->form_comments( $server_response, true );

			// Update transaction comments.
			novalnet()->helper()->update_comments( $wc_order, $transaction_comments, true, 'note', false );
			update_post_meta( $wc_order->get_id(), '_nn_version', NOVALNET_VERSION );

			if ( ! empty( $server_response ['transaction']['status'] ) ) {
				update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $server_response ['transaction'] ['status'] );
			} elseif ( ! empty( $server_response ['status'] ) ) {
				update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $server_response ['status'] );
			}

			// Cancel a order.
			$wc_order->update_status( 'failed' );
		}

		$url              = wc_get_checkout_url();
		$error_return_url = apply_filters( 'novalnet_error_return_url', $url );

		// Unset used sessions.
		WC()->session->__unset( 'novalnet_change_payment_method' );
		WC()->session->__unset( $this->id );

		// Display message.
		$this->display_info( $message );

		if ( ! empty( $is_webhook ) ) {
			return $transaction_comments;
		}

		// Redirecting to checkout page.
		return $this->novalnet_redirect( $error_return_url, 'error' );
	}


	/**
	 * Assigning initial reference.
	 * parameters in session to store in database.
	 *
	 * @since 12.0.0
	 *
	 * @param array $parameters The formed parameters.
	 */
	public function set_payment_token( &$parameters ) {

		$payment = $this->id;
		if ( ! empty( WC()->session->$payment [ 'wc-' . $payment . '-payment-token' ] ) && ! wc_novalnet_check_isset( WC()->session->$payment, 'wc-' . $payment . '-payment-token', 'new' ) ) {
			$token = WC_Payment_Tokens::get( WC()->session->$payment [ 'wc-' . $payment . '-payment-token' ] );
			$parameters ['transaction']['payment_data']['token'] = $token->get_reference_token();
		} elseif ( $this->supports( 'tokenization' ) && ! empty( WC()->session->$payment [ 'wc-' . $payment . '-new-payment-method' ] ) && wc_novalnet_check_isset( WC()->session->$payment, 'wc-' . $payment . '-new-payment-method', 'true' ) ) {
			$parameters ['transaction']['create_token'] = '1';
		}
	}

	/**
	 * Assigning initial reference.
	 * parameters in session to store in database.
	 *
	 * @since 12.0.0
	 *
	 * @param array $transaction The formed transaction.
	 * @param array $wc_order    The order object.
	 */
	public function store_payment_token( $transaction, $wc_order ) {
		if ( $this->supports( 'tokenization' ) ) {
			$payment_type = $this->id;

			if ( WC()->session->novalnet_guaranteed_sepa_switch_payment ) {
				if ( 'novalnet_invoice' === $this->id ) {
					$payment_type = 'novalnet_guaranteed_invoice';
				} elseif ( 'novalnet_sepa' === $this->id ) {
					$payment_type = 'novalnet_guaranteed_sepa';
				}
			}

			if ( ! empty( $transaction ['payment_data'] ['token'] ) && ( ( empty( WC()->session->$payment_type [ 'wc-' . $payment_type . '-payment-token' ] ) || wc_novalnet_check_isset( WC()->session->$payment_type, 'wc-' . $payment_type . '-payment-token', 'new' ) ) && ! empty( WC()->session->$payment_type [ 'wc-' . $payment_type . '-new-payment-method' ] ) && wc_novalnet_check_isset( WC()->session->$payment_type, 'wc-' . $payment_type . '-new-payment-method', 'true' ) ) ) {
				$payment_data = $transaction['payment_data'];

				$token = new WC_Payment_Token_Novalnet();
				$token->delete_duplicate_tokens( $payment_data, $payment_type );

				$token->set_token( $payment_data ['token'] );
				$token->set_reference_token( $payment_data ['token'] );
				$token->set_reference_tid( $transaction['tid'] );
				$token->set_gateway_id( $payment_type );
				$token->store_token_data( $payment_type, $payment_data, $token );
				$token->set_user_id( get_current_user_id() );
				$token->save();

				$wc_order->add_payment_token( $token );
			}
		}
	}

	/**
	 * Grab and display our saved payment methods.
	 *
	 * @since 12.0.0
	 */
	public function saved_payment_methods() {

		$tokens = $this->get_tokens();

		// Merge both guaranteed & non-guaranteed SEPA tokens together.
		if ( 'novalnet_sepa' === $this->id ) {
			$tokens = array_merge( $tokens, WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_guaranteed_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_instalment_sepa' ) );
		} elseif ( 'novalnet_guaranteed_sepa' === $this->id ) {
			$tokens = array_merge( $tokens, WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_instalment_sepa' ) );
		} elseif ( 'novalnet_instalment_sepa' === $this->id ) {
			$tokens = array_merge( $tokens, WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_guaranteed_sepa' ) );
		}

		$html            = '<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods" data-count="' . esc_attr( count( $tokens ) ) . '">';
		$is_token_exists = array(
			'novalnet_paypal'          => false,
			'novalnet_sepa'            => false,
			'novalnet_cc'              => false,
			'novalnet_guaranteed_sepa' => false,
			'novalnet_instalment_sepa' => false,
		);
		foreach ( $tokens as $token ) {

			$gateway_status       = novalnet()->db()->get_entry_by_tid( $token->get_reference_tid() );
			$saved_payment_list   = '';
			$show_saved_card_data = true;
			if ( 'novalnet_paypal' === $this->id && 'novalnet_paypal' === $token->get_gateway_id() && empty( $token->get_paypal_account() ) ) {
				$show_saved_card_data = false;
			}

			// Restrict token display for PayPal on-hold token.
			if ( ! empty( $show_saved_card_data ) ) {

				$saved_payment_list = sprintf(
					'<li class="woocommerce-SavedPaymentMethods-token">
                        <input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
                        <label for="wc-%1$s-payment-token-%2$s">%3$s</label>
                    </li>',
					esc_attr( $this->id ),
					esc_attr( $token->get_id() ),
					$token->get_display_name(),
					checked( $token->is_default(), true, false )
				);

				$html .= apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $saved_payment_list, $token, $this );

				$is_token_exists[ $this->id ] = true;
			}
		}

		if ( ! empty( $is_token_exists[ $this->id ] ) && true === $is_token_exists[ $this->id ] ) {
			$html .= $this->get_new_payment_method_option_html();
		}
		$html .= '</ul>';

        echo apply_filters( 'wc_payment_gateway_form_saved_payment_methods_html', $html, $this ); // @codingStandardsIgnoreLine
	}


	/**
	 * Processing redirect payment process.
	 *
	 * @since 12.0.0
	 *
	 * @return array|string
	 */
	public function process_redirect_payment_response() {

		$post_id  = WC()->session->get( 'novalnet_post_id' );
		$wc_order = new WC_Order( $post_id );
		if ( WC_Novalnet_Validation::is_success_status( novalnet()->request ) ) {
			$nn_status = get_post_meta( $post_id, '_novalnet_gateway_status', true );
			if ( ! empty( $nn_status ) && 'FAILURE' !== $nn_status ) {
				return $this->novalnet_redirect( $this->get_return_url( $wc_order ) );
			} elseif ( WC_Novalnet_Validation::is_valid_checksum( novalnet()->request, WC()->session->get( 'novalnet_txn_secret' ), WC_Novalnet_Configuration::get_global_settings( 'key_password' ) ) ) {
				$parameters      = array(
					'transaction' => array(
						'tid' => novalnet()->request ['tid'],
					),
					'custom'      => array(
						'lang' => wc_novalnet_shop_language(),
					),
				);
				$endpoint        = novalnet()->helper()->get_action_endpoint( 'transaction_details' );
				$server_response = novalnet()->helper()->submit_request( $parameters, $endpoint, array( 'post_id' => $post_id ) );

				if ( empty( $wc_order ) && ! empty( $server_response ['custom']['nn_shopnr'] ) ) {
					$post_id  = $server_response ['custom']['nn_shopnr'];
					$wc_order = new WC_Order( $post_id );
				}
				// Checks transaction status.
				return $this->check_transaction_status( $server_response, $wc_order );
			} else {
				$server_response                          = novalnet()->helper()->format_querystring_response( novalnet()->request );
				$server_response['result']['status_text'] = __( 'Please note some data has been changed while redirecting', 'woocommerce-novalnet-gateway' );
			}
		}

		return $this->transaction_failure( novalnet()->request, $wc_order, false );
	}

	/**
	 * Assigning basic details in gateway instance.
	 *
	 * @since 12.0.0
	 */
	public function assign_basic_payment_details() {

		// Get language.
		$language = strtolower( wc_novalnet_shop_language() );

		// Initiate payment settings.
		$this->init_settings();

		$payment_text = WC_Novalnet_Configuration::get_payment_text( $this->id );

		// Payment title in back-end.
		$this->method_title = wc_novalnet_get_payment_text( $this->settings, $payment_text, $language, $this->id, 'admin_title' );

		// Payment title in front-end.
		$this->title = wc_novalnet_get_payment_text( $this->settings, $payment_text, $language, $this->id );

		// Payment description.
		$this->description = wc_novalnet_get_payment_text( $this->settings, $payment_text, $language, $this->id, 'description' );

		// Gateway view transaction URL.
		$this->view_transaction_url = 'https://admin.novalnet.de';

		if ( ! empty( $payment_text['admin_desc'] ) ) {
			$this->method_description = $payment_text['admin_desc'];
		}

		// Basic payment supports.
		$this->supports = array(
			'refunds',
			'products',
		);

		// Display payment configuration fields.
		$this->init_form_fields();

		// Handle the payment selection & complete process.
		if ( ! is_admin() ) {
			$this->chosen = ( wc_novalnet_check_session() && WC()->session->__isset( 'chosen_payment_method' ) && WC()->session->chosen_payment_method === $this->id );
			if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
				add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 3 );
			} else {
				add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 2 );
			}

			// Display error message in checkout page.
			add_action( 'woocommerce_check_cart_items', array( $this, 'show_error_message_on_redirect' ) );
		}

		// Restrict Add payment method option.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'restrict_add_payment_method' ) );

		// Customize E-mail.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'add_email_instructions' ), 10, 2 );

		// Check extra Line break for Customer note in checkout.
		add_action( 'woocommerce_order_details_after_order_table_items', array( &$this, 'align_transaction_details' ), 10, 2 );

		// Do Payment related validation before save the settings.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Customize front-end my-account option.
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'filter_my_account_action' ), 10, 2 );

		// Customize thank you page.
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page_instructions' ) );
	}


	/**
	 * Handle validations before update the configuration into tables
	 *
	 * @since 12.0.0.
	 */
	public function process_admin_options() {

		// Do backend validation.
		WC_Novalnet_Validation::backend_validation();

		// Call core process_admin_options().
		parent::process_admin_options();

	}

	/**
	 * Restrict the add payment method option for tokenization
	 *
	 * @since 12.0.0
	 *
	 * @param array $gateways The supported gateways.
	 *
	 * @return array
	 */
	public function restrict_add_payment_method( $gateways ) {
		global $wp;
		$page_id = wc_get_page_id( 'myaccount' );

		if ( ( $page_id && is_page( $page_id ) && isset( $wp->query_vars['add-payment-method'] ) ) ) {
			foreach ( novalnet()->get_supports( 'tokenization' ) as $payment_type ) {
				if ( isset( $gateways [ $payment_type ] ) ) {
					unset( $gateways [ $payment_type ] );
				}
			}
		}
		return $gateways;
	}

		/**
		 * Customizing shop thankyou page.
		 *
		 * @since 12.0.0
		 *
		 * @param int $wc_order_id The order ID.
		 *
		 * @return void
		 */
	public function thankyou_page_instructions( $wc_order_id ) {

		$language = strtolower( wc_novalnet_shop_language() );

		// Check Novalnet payment.
		if ( ! empty( $this->settings [ 'instructions_' . $language ] ) ) {
			echo wp_kses_post( wpautop( wptexturize( $this->settings [ 'instructions_' . $language ] ) ) );
		}

		$checkout_token = get_post_meta( $wc_order_id, '_nn_cp_checkout_token', true );

		if ( ! empty( $checkout_token ) ) {
			$overlay_details = wc_novalnet_unserialize_data( $checkout_token );
			if ( ! empty( $overlay_details ['checkout_js'] ) && ! empty( $overlay_details ['checkout_token'] ) ) {
				wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script-barzahlen', esc_url( $overlay_details ['checkout_js'] . '?token=' . esc_attr( $overlay_details ['checkout_token'] ) ), array(), NOVALNET_VERSION, false );
				echo wp_kses(
					"<button id='barzahlen_button' class='bz-checkout-btn'>" . __( 'Pay now with Barzahlen', 'woocommerce-novalnet-gateway' ) . '</button>',
					array(
						'button' => array(
							'id'    => true,
							'class' => true,
						),
					)
				);
			}
		}
	}

	/**
	 * Shows the TESTMODE notification.
	 *
	 * @since 12.0.0
	 *
	 * @return void
	 */
	public function test_mode_notification() {
		$html = '';
		if ( wc_novalnet_check_isset( $this->settings, 'test_mode', 'yes' ) ) {
			$html = '<p><div class="novalnet-test-mode">' . __( 'TESTMODE', 'woocommerce-novalnet-gateway' ) . '</div></p>';
		}

		echo wp_kses(
			wpautop( '<div id="' . $this->id . '_error" role="alert"></div><div class="clear"></div>' . $html . '<br/>' ),
			array(
				'div' => array(
					'class' => true,
					'id'    => true,
				),
				'br'  => array(),
			)
		);
	}

	/**
	 * Show description
	 *
	 * @since 12.0.0
	 *
	 * @param array $additional_info Additiona information to be displayed in payment checkout.
	 *
	 * @return void
	 */
	public function show_description( $additional_info = array() ) {

		// Unset payment session for ignored payments.
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'chosen_payment_method' ) && WC()->session->chosen_payment_method !== $this->id ) {
			WC()->session->__unset( $this->id );
		}

		// Hide multiple payment fields.
		wc_novalnet_hide_multiple_payment();

		$contents = array();

		$contents[] = $this->description;
		if ( ! empty( $additional_info ) ) {
			$contents = array_merge( $contents, $additional_info );
		}

		if ( ! empty( $contents ) ) {
			if ( count( $contents ) > 1 ) {
				$text = '<ul>';
				foreach ( $contents as $content ) {
					if ( ! empty( $content ) ) {
						$text .= '<li>' . $content . '</li>';
					}
				}
				$text .= '</ul>';
			} elseif ( ! empty( $contents['0'] ) ) {
				$text = $contents['0'];
			}

			if ( ! empty( $text ) ) {
				echo wp_kses(
					wpautop( '<div class="novalnet-info-box">' . $text . '</div><br/>' ),
					array(
						'div'    => array(
							'class'   => true,
							'id'      => true,
							'style'   => true,
							'display' => true,
						),
						'a'      => array(
							'id'      => true,
							'onclick' => true,
							'style'   => true,
						),
						'p'      => array(),
						'strong' => array(),
						'ul'     => array(),
						'li'     => array(),
					)
				);
			}
		}
	}

	/**
	 * Redirects to the given URL.
	 *
	 * @since 12.0.0
	 *
	 * @param string $url      The url value.
	 * @param string $redirect The result type.
	 *
	 * @return array
	 */
	public function novalnet_redirect( $url = '', $redirect = 'success' ) {
		if ( '' === $url ) {
			$url = wc_get_checkout_url();
		}
		return array(
			'result'   => $redirect,
			'redirect' => $url,
		);
	}

	/**
	 * To display the success and failure
	 * messages.
	 *
	 * @since 12.0.0
	 *
	 * @param string $message      The message value.
	 * @param string $message_type The message type value.
	 */
	public function display_info( $message, $message_type = 'error' ) {
		wc_add_notice( $message, $message_type );
	}

	/**
	 * Checks and unset the other Novalnet sessions.
	 *
	 * @since 12.0.0
	 */
	public function unset_other_payment_session() {
		if ( wc_novalnet_check_session() ) {
			foreach ( array_keys( novalnet()->get_payment_types() ) as $payment ) {
				WC()->session->__unset( $payment . '_dob' );
				WC()->session->__unset( $this->id . '_dob' );
				WC()->session->__unset( $this->id . '_show_dob' );
				WC()->session->__unset( $this->id );
				if ( $this->id !== $payment ) {
					WC()->session->__unset( $payment );
					WC()->session->__unset( 'current_novalnet_payment' );
					WC()->session->__unset( $payment . '_switch_payment' );
					WC()->session->__unset( 'novalnet_post_id' );
				}
			}
		}
	}

	/**
	 * Get endpoint url to send request.
	 *
	 * @since 12.0.0
	 *
	 * @return string
	 */
	public function get_payment_endpoint() {

		$action = 'payment';
		if ( WC_Novalnet_Validation::is_authorize( $this->id, WC()->cart->total, $this->settings ) ) {
			$action = 'authorize';
		}
		return novalnet()->helper()->get_action_endpoint( $action );
	}

	/**
	 * Outputs a checkbox for saving a new payment method to the database.
	 *
	 * @since 12.0.0
	 */
	public function save_payment_method_checkbox() {
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                <input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" checked style="width:auto;" />
                <label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
            </p>',
			esc_attr( $this->id ),
			esc_html( __( 'Save for future purchase', 'woocommerce-novalnet-gateway' ) )
		);
	}

	/**
	 * Handle payment switch
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order   The order object.
	 * @param array    $parameters The formed parameters.
	 */
	public function handle_payment_switch( $wc_order, &$parameters ) {

		if ( wc_novalnet_check_session() && WC()->session->__isset( $this->id . '_switch_payment' ) ) {
			$this->id                                    = wc_novalnet_switch_payment( $this->id );
			$this->settings                              = WC_Novalnet_Configuration::get_payment_settings( $this->id );
			$parameters ['transaction'] ['payment_type'] = novalnet()->get_payment_types( $this->id );
			WC()->session->set( 'current_novalnet_payment', $this->id );
			$payment_text         = WC_Novalnet_Configuration::get_payment_text( $this->id );
			$payment_method_title = wc_novalnet_get_payment_text( $this->settings, $payment_text, wc_novalnet_shop_language(), $this->id, 'title' );
			$wc_order->set_payment_method_title( $payment_method_title );
			$wc_order->set_payment_method( $this->id );
		}
	}

	/**
	 * Returns the order status.
	 *
	 * @param string   $wc_order_status The order status.
	 * @param int      $wc_order_id     The post ID.
	 * @param WC_Order $wc_order   The order object.
	 *
	 * @return string
	 */
	public function get_order_status( $wc_order_status, $wc_order_id, $wc_order = '' ) {

		if ( empty( $wc_order ) ) {
			$wc_order = new WC_Order( $wc_order_id );
		}

		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {
			$gateway_status = get_post_meta( $wc_order_id, '_novalnet_gateway_status', true );
			novalnet()->helper()->status_mapper( $gateway_status );

			if ( ! empty( $gateway_status ) ) {
				if ( 'PENDING' === $gateway_status && ! novalnet()->get_supports( 'pay_later', $wc_order->get_payment_method() ) ) {
					$wc_order_status = 'wc-pending';
				} elseif ( 'ON_HOLD' === $gateway_status ) {
					$wc_order_status = 'wc-on-hold';
				} else {
					$settings = WC_Novalnet_Configuration::get_payment_settings( $wc_order->get_payment_method() );
					if ( ! empty( $settings ['order_success_status'] ) ) {
						$wc_order_status = $settings ['order_success_status'];
					}
				}
			}
			if ( 'PENDING' === $gateway_status && ( novalnet()->get_supports( 'pay_later', $wc_order->get_payment_method() ) && 'novalnet_invoice' != $wc_order->get_payment_method() ) ) {
				// get order items = each product in the order.
				$items = $wc_order->get_items();

				// Set variable.
				$found = false;

				foreach ( $items as $item ) {
					// Get product id.
					$product = wc_get_product( $item['product_id'] );

					// Is virtual.
					$is_virtual = $product->is_virtual();

					// Is_downloadable.
					$is_downloadable = $product->is_downloadable();

					if ( $is_virtual || $is_downloadable ) {
						$found = true;
						// true, break loop
						break;
					}
				}

				// true.
				if ( $found ) {
					$wc_order_status = 'wc-on-hold';
				}
			}
		}
		return $wc_order_status;
	}

	/**
	 * Set error notice for payment failure to display in checkout page.
	 *
	 * @since 12.0.0
	 */
	public function show_error_message_on_redirect() {

		if ( is_checkout() && wc_notice_count( 'error' ) > 0 && wc_novalnet_check_session() && WC()->session->__isset( 'chosen_payment_method' ) && WC_Novalnet_Validation::check_string( WC()->session->chosen_payment_method ) ) {

			// Show non-cart errors.
			wc_print_notices();
		}
	}

	/**
	 * Restricting the Pay/Cancel option shop front-end
	 * if succesfull transaction has pending status.
	 *
	 * @since 12.0.0
	 *
	 * @param array    $actions The actions data.
	 * @param WC_Order $wc_order   The order object.
	 *
	 * @return array
	 */
	public function filter_my_account_action( $actions, $wc_order ) {

		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {

			if ( $wc_order->has_status( 'pending' ) ) {
				unset( $actions['pay'] );
			}

			// Unset user order cancel option.
			if ( ! empty( $actions['cancel'] ) ) {
				unset( $actions['cancel'] );
			}
		}
		return $actions;
	}
}
