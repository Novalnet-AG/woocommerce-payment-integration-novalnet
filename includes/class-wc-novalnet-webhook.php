<?php
/**
 * Novalnet Webhook V2
 *
 * We will notify you through our webhooks, whenerver any trnsaction got
 * initiated (or) modified (capture. cancel, refund, renewal, etc.,).
 * Notifications should be used to keep your Shopsystem backoffice
 * upto date with the status of each payment and modifications. Notifications
 * are sent using HTTP POST to your server (based on your choice).
 *
 * This file describes how HTTP Post notifications can be received and authenticate in PHP.
 *
 * @package  woocommerce-novalnet-gateway/includes/
 * @author   Novalnet
 */

/**
 * Novalnet Webhoook Api Class.
 *
 * WC_Novalnet_Webhook
 */
class WC_Novalnet_Webhook {


	/**
	 * Allowed host from Novalnet.
	 *
	 * @var string
	 */
	protected $novalnet_host_name = 'pay-nn.de';

	/**
	 * Mandatory Parameters.
	 *
	 * @var array
	 */
	protected $mandatory = array(
		'event'       => array(
			'type',
			'checksum',
			'tid',
		),
		'merchant'    => array(
			'vendor',
			'project',
		),
		'result'      => array(
			'status',
		),
		'transaction' => array(
			'tid',
			'payment_type',
			'status',
		),
	);

	/**
	 * Callback test mode.
	 *
	 * @var int
	 */
	protected $test_mode;

	/**
	 * Request parameters.
	 *
	 * @var array
	 */
	protected $event_data = array();

	/**
	 * Your payment access key value
	 *
	 * @var string
	 */
	protected $payment_access_key;

	/**
	 * Order reference values.
	 *
	 * @var array
	 */
	protected $order_reference = array();

	/**
	 * Recived Event type.
	 *
	 * @var string
	 */
	protected $event_type;

	/**
	 * The WC_Order object of the current event.
	 *
	 * @var WC_Order
	 */
	protected $wc_order;

	/**
	 * The WC_Subscription_Order object of the current event.
	 *
	 * @var WC_Subscription_Order
	 */
	protected $wcs_order;

	/**
	 * The WC_Order ID of the current event.
	 *
	 * @var INT
	 */
	protected $wc_order_id;

	/**
	 * The WC_Subscription_Order ID of the current event.
	 *
	 * @var INT
	 */
	protected $wcs_order_id;

	/**
	 * The Return response to Novalnet.
	 *
	 * @var array
	 */
	protected $response;

	/**
	 * Notification to end customer.
	 *
	 * @var bool
	 */
	protected $notify_customer;

	/**
	 * The details need to be update in Novalnet table.
	 *
	 * @var array
	 */
	protected $update_data = array();

	/**
	 * Recived Event TID.
	 *
	 * @var int
	 */
	protected $event_tid;

	/**
	 * Recived Event parent TID.
	 *
	 * @var int
	 */
	protected $parent_tid;

	/**
	 * Novalnet_Webhooks constructor.
	 *
	 * @since 12.0.0
	 */
	public function __construct() {

		// Authenticate request host.
		$this->authenticate_event_data();

		// Set Event data.
		$this->event_type = $this->event_data ['event'] ['type'];
		$this->event_tid  = $this->event_data ['event'] ['tid'];
		$this->parent_tid = $this->event_tid;
		if ( ! empty( $this->event_data ['event'] ['parent_tid'] ) ) {
			$this->parent_tid = $this->event_data ['event'] ['parent_tid'];
		}

		// Get order reference.
		$this->get_order_reference();

		if ( ! empty( $this->event_data ['transaction'] ['order_no'] ) ) {
			$org_post_id = $this->get_post_id( $this->event_data ['transaction'] ['order_no'] );
		}

		// Order number check.
		if ( ! empty( $org_post_id ) && $this->order_reference ['order_no'] !== $org_post_id ) {
			$this->display_message( array( 'message' => 'Order reference not matching.' ) );
		}

		// Create order object.
		$this->wc_order    = new WC_Order( $this->order_reference ['order_no'] );
		$this->wc_order_id = $this->wc_order->get_id();

		$this->response ['message'] = __( 'Notification received from Novalnet for this order. ', 'woocommerce-novalnet-gateway' );

		if ( WC_Novalnet_Validation::is_success_status( $this->event_data ) ) {
			$is_subscription       = false;
			$this->notify_customer = false;
			switch ( $this->event_type ) {

				case 'PAYMENT':
					$this->display_message( array( 'message' => 'The Payment has been received' ) );
					break;

				case 'TRANSACTION_CAPTURE':
				case 'TRANSACTION_CANCEL':
					$this->notify_customer = true;
					$this->handle_transaction_capture_cancel();
					break;

				case 'TRANSACTION_REFUND':
					$this->notify_customer = true;
					$this->handle_transaction_refund();
					break;

				case 'TRANSACTION_UPDATE':
					$this->handle_transaction_update();
					break;
				case 'CREDIT':
					$this->handle_credit();
					break;
				case 'CHARGEBACK':
					$this->handle_chargeback();
					break;
				case 'INSTALMENT':
					$this->handle_instalment();
					break;
				case 'INSTALMENT_CANCEL':
					$this->notify_customer = true;
					$this->handle_instalment_cancel();
					break;
				case 'RENEWAL':
					$this->handle_renewal();
					$is_subscription = true;
					break;
				case 'SUBSCRIPTION_SUSPEND':
					$this->handle_subscription_suspend();
					$this->notify_customer = true;
					$is_subscription       = true;
					break;
				case 'SUBSCRIPTION_REACTIVATE':
					$this->handle_subscription_reactivate();
					$this->notify_customer = true;
					$is_subscription       = true;
					break;
				case 'SUBSCRIPTION_CANCEL':
					$this->handle_subscription_cancel();
					$is_subscription = true;
					break;
				case 'SUBSCRIPTION_UPDATE':
					$this->handle_subscription_update();
					$is_subscription = true;
					break;
				default:
					$this->display_message( array( 'message' => "The webhook notification has been received for the unhandled EVENT type($this->event_type)" ) );
			}
			if ( ! empty( $this->update_data ['update'] ) && $this->update_data ['table'] ) {
				novalnet()->db()->update(
					$this->update_data ['update'],
					array(
						'order_no' => $this->wc_order->get_id(),
					),
					$this->update_data ['table']
				);
			}

			// Update order comments.
			if ( $is_subscription ) {
				novalnet()->helper()->update_comments( $this->wcs_order, $this->response['message'], true, 'note', $this->notify_customer );
			} else {
				novalnet()->helper()->update_comments( $this->wc_order, $this->response['message'], true, 'note', $this->notify_customer );
			}
			if ( ! empty( $this->subscription_cancel_note ) ) {
				$this->wcs_order->add_order_note( $this->subscription_cancel_note );
			}

			// Log callback process.
			$this->log_callback_details( $this->wc_order->get_id() );

			$this->send_notification_mail(
				array(
					'message'  => $this->response['message'],
					'order_no' => $this->wc_order->get_id(),
				)
			);
			$this->display_message( $this->response );
		}
	}

	/**
	 * Handle subscription suspend
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_suspend() {

		/* translators: %1$s: parent_tid, %3$s: date*/
		$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'This subscription transaction has been suspended on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );

		add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

		$this->wcs_order->update_status( 'on-hold' );

		delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );
		$this->update_data ['table']  = 'novalnet_subscription_details';
		$this->update_data ['update'] = array(
			'suspended_date' => date( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Handle subscription cancel
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_cancel() {

		/* translators: %1$s: parent_tid, %2$s: amount, %3$s: next_cycle_date*/
		$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been cancelled due to: %s. ', 'woocommerce-novalnet-gateway' ), $this->event_data ['subscription']['reason'] ) );

		add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

		try {
			$this->wcs_order->update_status( 'pending-cancel' );
		} catch ( Exception $e ) {
			if ( $this->wcs_order->has_status( 'cancelled' ) ) {
				$this->response ['message'] .= 'Order already cancelled.';
			} else {
				$this->response ['message'] .= $e->getMessage();
			}
		}

		delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );

		$this->update_data ['table']  = 'novalnet_subscription_details';
		$this->update_data ['update'] = array(
			'termination_at'     => date( 'Y-m-d H:i:s' ),
			'termination_reason' => $this->event_data ['subscription']['reason'],
		);
	}

	/**
	 * Handle subscription reactivate
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_reactivate() {

		/* translators: %1$s: date, %2$s: amount, %3$s: next_cycle_date*/
		$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been reactivated for the TID:%1$s on %2$s. Next charging date :%3$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date(), wc_novalnet_next_cycle_date( $this->event_data ['subscription'] ) ) );

		$items = $this->wcs_order->get_items();

		foreach ( $items as $item ) {
			$product_id           = $item['product_id'];
			$product_variation_id = $item['variation_id'];
		}
		if ( $product_variation_id ) {
			$end_date   = WC_Subscriptions_Product::get_expiration_date( $product_variation_id );
			$trial_date = WC_Subscriptions_Product::get_trial_expiration_date( $product_variation_id );
		} elseif ( ! $product_variation_id ) {
			$end_date   = WC_Subscriptions_Product::get_expiration_date( $product_id );
			$trial_date = WC_Subscriptions_Product::get_trial_expiration_date( $product_id );
		}

		add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

		// Set requires_manual_renewal flag to activate the cancelled subscription.
		if ( $this->wcs_order->has_status( wcs_get_subscription_ended_statuses() ) ) {
			$this->wcs_order->set_requires_manual_renewal( true );
		}

		try {
			$this->wcs_order->update_status( 'active', '' );
		} catch ( Exception $e ) {
			$novalnet_log = wc_novalnet_logger();
			$novalnet_log->add( 'novalneterrorlog', 'Error occured during status change: ' . $e->getMessage() . '. So, manually updated the status' );
			wp_update_post(
				array(
					'ID'     => $this->wcs_order_id,
					'status' => 'active',
				)
			);
		}

		// Reset requires_manual_renewal flag after successful activation of the cancelled subscription.
		if ( $this->wcs_order->get_requires_manual_renewal() ) {
			$this->wcs_order->set_requires_manual_renewal( false );
		}

		update_post_meta( $this->wcs_order->get_id(), '_schedule_next_payment', $this->event_data ['subscription']['next_cycle_date'] );
		update_post_meta( $this->wcs_order->get_id(), '_schedule_end', $end_date );
		update_post_meta( $this->wcs_order->get_id(), '_schedule_trial_end', $trial_date );

		delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );

		$this->update_data ['table']  = 'novalnet_subscription_details';
		$this->update_data ['update'] = array(
			'suspended_date' => '',
		);
	}

	/**
	 * Handle subscription update
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_update() {

		// Handle change payment method.
		$payment_types               = novalnet()->get_payment_types();
		$this->update_data ['table'] = 'novalnet_subscription_details';
		$next_cycle_date             = wc_novalnet_next_cycle_date( $this->event_data['subscription'] );

		$recurring_amount = wc_novalnet_formatted_amount( $this->wcs_order->get_total() );
		$recurring_date   = date( 'Y-m-d', strtotime( get_post_meta( $this->wcs_order_id, '_schedule_next_payment', true ) ) );

		if ( ( ! empty( $this->event_data ['subscription']['amount'] ) && (int) $recurring_amount !== (int) $this->event_data ['subscription']['amount'] ) || ( $recurring_date !== $next_cycle_date ) ) {

			/* translators: %1$s: amount, %2$s: next_cycle_date */
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription updated successfully. You will be charged %1$s on %2$s.', 'woocommerce-novalnet-gateway' ), ( wc_novalnet_shop_amount_format( $this->event_data ['subscription'] ['amount'] ) ), wc_novalnet_next_cycle_date( $this->event_data ['subscription'] ) ) );
			update_post_meta( $this->wcs_order_id, '_schedule_next_payment', $next_cycle_date );

		}

		if ( ( ! empty( $this->event_data ['transaction'] ['payment_type'] ) && ! empty( $payment_types[ $this->wcs_order->get_payment_method() ] ) && $payment_types[ $this->wcs_order->get_payment_method() ] !== $this->event_data ['transaction'] ['payment_type'] ) ) {

			$payment_types = array_flip( $payment_types );

			/* translators: %s: next_cycle_date */
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_next_cycle_date( $this->event_data ['subscription'] ) ) );

			// Set new payment method.
			WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $this->wcs_order, $payment_types[ $this->event_data ['transaction'] ['payment_type'] ] );

			// Update recurring payment process.
			do_action( 'novalnet_update_recurring_payment', $this->event_data, $this->wc_order_id, $this->wcs_order->get_payment_method(), $this->wcs_order );
		}
	}
	/**
	 * Handle renewal
	 *
	 * @since 12.0.0
	 */
	public function handle_renewal() {

		if ( in_array( $this->event_data['transaction']['status'], array( 'CONFIRMED', 'PENDING' ), true ) ) {

			// Get next cycle date from the event data.
			$next_cycle_date = wc_novalnet_next_cycle_date( $this->event_data['subscription'] );
			if ( empty( $this->wcs_order->get_payment_method() ) && ! empty( $this->subs_order_reference['recurring_payment_type'] ) ) {
				$this->wcs_order->set_payment_method( $this->subs_order_reference['recurring_payment_type'] );
			}

			// Initiate particular payment class.
			$payment_gateway = wc_get_payment_gateway_by_order( $this->wcs_order );

			// Set current payment type in session.
			WC()->session->set( 'current_novalnet_payment', $payment_gateway->id );

			// Create the renewal order.
			$recurring_order = apply_filters( 'novalnet_create_renewal_order', $this->wcs_order );

			$recurring_order->set_payment_method( $this->wcs_order->get_payment_method() );

			/* translators: %1$s: tid, %2$s: amount, %3$s: date */
			$this->response ['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been successfully renewed for the TID: %1$s with the amount %2$s on %3$s. The renewal TID is:%4$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data ['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_tid ) );

			// Do Novalnet process after verify the successful recurring order creation.
			if ( ! empty( $recurring_order->get_id() ) ) {

				/* Update renewal order number */
				$this->response ['order_no'] = $recurring_order->get_id();

				$payment_gateway->check_transaction_status( $this->event_data, $recurring_order, true );
			}

			update_post_meta( $this->wcs_order->get_id(), '_schedule_next_payment', date( 'Y-m-d H:i:s', strtotime( $next_cycle_date ) ) );

			$total_length = apply_filters( 'novalnet_get_order_subscription_length', $this->wcs_order );

			if ( empty( $total_length ) ) {
				// Get Subscription length for the product.
				$item_id       = $this->wc_order->get_items();
				$order_item_id = novalnet()->db()->get_order_item_id( $this->wc_order->get_id() );
				$total_length  = get_post_meta( $item_id [ $order_item_id ] ['product_id'], '_subscription_length', true );
			}

			if ( ! empty( $this->wcs_order->get_trial_period() ) ) {
				$related_orders = ( count( $this->wcs_order->get_related_orders() ) ) - 1;
			} else {
				$related_orders = count( $this->wcs_order->get_related_orders() );
			}

			if ( ! empty( $total_length ) && $related_orders >= $total_length ) {

				add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );
				$this->order_reference                = novalnet()->db()->get_transaction_details( $this->wc_order_id );
				$parameters['subscription']['tid']    = $this->order_reference['tid'];
				$parameters['subscription']['reason'] = '';
				$parameters['custom']['lang']         = wc_novalnet_shop_language();
				$parameters['custom']['shop_invoked'] = 1;

				novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( 'subscription_cancel' ), array( 'post_id' => $this->wc_order_id ) );
				/* translators: %s: tid */
				$this->subscription_cancel_note = PHP_EOL . PHP_EOL . wc_novalnet_format_text( sprintf( __( 'Subscription has been cancelled since the subscription has exceeded the maximum time period for the TID: %s', 'woocommerce-novalnet-gateway' ), $this->order_reference['tid'] ) );

				$this->wcs_order->update_status( 'pending-cancel', $this->response['message'] );

				delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );
			} elseif ( ! empty( wc_novalnet_next_cycle_date( $this->event_data ['subscription'] ) ) ) {
				$this->response ['message'] .= wc_novalnet_format_text( sprintf( __( 'Next charging date will be on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_next_cycle_date( $this->event_data ['subscription'] ) ) );
			}
		}
	}
	/**
	 * Handle instalment
	 *
	 * @since 12.0.0
	 */
	public function handle_instalment() {

		if ( 'CONFIRMED' === $this->event_data['transaction']['status'] && ! empty( $this->event_data['instalment']['cycles_executed'] ) ) {

			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: tid */
			$this->response ['message'] = sprintf( __( 'A new instalment has been received for the Transaction ID:%1$s with amount %2$s. The new instalment transaction ID is: %3$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), $this->event_tid );

			// Store Bank details.
			$this->order_reference ['additional_info'] = apply_filters( 'novalnet_store_instalment_data_webhook', $this->event_data );
			$this->update_data ['table']               = 'novalnet_transaction_detail';
			$this->update_data ['update']              = array(
				'additional_info' => $this->order_reference ['additional_info'],
			);

			if ( 'INSTALMENT_INVOICE' === $this->event_data['transaction']['payment_type'] && empty( $this->event_data ['transaction']['bank_details'] ) ) {
				$this->event_data ['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] );
			}

			// Build & update renewal comments.
			$transaction_comments = PHP_EOL . novalnet()->helper()->prepare_payment_comments( $this->event_data );
			novalnet()->helper()->update_comments( $this->wc_order, $transaction_comments, false, 'transaction_info', false );

			novalnet()->db()->update(
				array(
					'additional_info' => $this->order_reference ['additional_info'],
				),
				array(
					'order_no' => $this->wc_order->get_id(),
				)
			);

			WC()->mailer();
			do_action( 'novalnet_send_instalment_notification_to_customer', $this->wc_order->get_id(), $this->wc_order );

		}
	}

	/**
	 * Handle instalment cancel
	 *
	 * @since 12.0.0
	 */
	public function handle_instalment_cancel() {

		if ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
			$this->update_data ['table']  = 'novalnet_transaction_detail';
			$this->update_data ['update'] = array(
				'gateway_status' => 'DEACTIVATED',
			);
			/* translators: %1$s: parent_tid, %2$s: date */
			$this->response ['message'] = sprintf( __( 'Instalment has been cancelled for the TID %1$s on %2$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date() );
			$order_status               = 'wc-cancelled';
			$this->wc_order->update_status( $order_status );
		}
	}

	/**
	 * Handle credit
	 *
	 * @since 12.0.0
	 */
	public function handle_credit() {
		if ( 'ONLINE_TRANSFER_CREDIT' === $this->event_data['transaction']['payment_type'] ) {

			/* translators: %1$s: tid, %2$s: amount, %3$s: date, %4$s: parent_tid */
			$this->response ['message'] = wc_novalnet_format_text( sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %4$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_data['transaction']['tid'] ) );
		} else {
			/* translators: %s: post type */
			$this->response ['message'] = sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %4$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_data['transaction']['tid'] );
			if ( in_array( $this->event_data['transaction']['payment_type'], array( 'INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'MULTIBANCO_CREDIT' ), true ) ) {

				if ( (int) $this->order_reference ['callback_amount'] < (int) $this->order_reference ['amount'] ) {
					// Calculate total amount.
					$paid_amount = $this->order_reference ['callback_amount'] + $this->event_data['transaction']['amount'];

					// Calculate including refunded amount.
					$amount_to_be_paid = $this->order_reference['amount'] - $this->order_reference ['refunded_amount'];

					$this->update_data ['table']  = 'novalnet_transaction_detail';
					$this->update_data ['update'] = array(
						'gateway_status'  => $this->event_data ['transaction']['status'],
						'callback_amount' => $paid_amount,
					);

					if ( ( (int) $paid_amount >= (int) $amount_to_be_paid ) ) {

						$payment_settings = WC_Novalnet_Configuration::get_payment_settings( $this->wc_order->get_payment_method() );

						// Update callback status.
						$this->wc_order->update_status( $payment_settings ['callback_status'] );
					}
				}
			}
		}
	}
	/**
	 * Handle transaction capture/cancel
	 *
	 * @since 12.0.0
	 */
	public function handle_transaction_capture_cancel() {

		$this->update_data ['table']  = 'novalnet_transaction_detail';
		$this->update_data ['update'] = array(
			'gateway_status' => $this->event_data ['transaction']['status'],
		);
		if ( 'TRANSACTION_CAPTURE' === $this->event_type ) {
			/* translators: %s: Date */
			$this->response ['message'] = sprintf( __( 'The transaction has been confirmed on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );
			$payment_settings           = WC_Novalnet_Configuration::get_payment_settings( $this->wc_order->get_payment_method() );
			$order_status               = $payment_settings['order_success_status'];
			$this->wc_order->payment_complete( $this->event_data['transaction']['tid'] );
			if ( 'novalnet_paypal' === $this->wc_order->get_payment_method() ) {
				$users_tokens = WC_Payment_Tokens::get_customer_tokens( $this->wc_order->get_customer_id(), $this->wc_order->get_payment_method() );
				foreach ( $users_tokens as $user_token ) {
					if ( 'novalnet_paypal' === $this->wc_order->get_payment_method() && $user_token->get_reference_tid() === $this->parent_tid ) {
						$user_token->set_paypal_account( $this->event_data['transaction']['payment_data']['paypal_account'] );
						$user_token->save();
					}
				}
			} elseif ( in_array( $this->wc_order->get_payment_method(), array( 'novalnet_instalment_sepa', 'novalnet_instalment_invoice' ), true ) ) {

				if ( ! empty( $this->order_reference ['additional_info'] ) ) {
					$this->order_reference ['additional_info'] = wc_novalnet_serialize_data( array_merge( wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] ), wc_novalnet_unserialize_data( apply_filters( 'novalnet_store_instalment_data', $this->event_data ) ) ) );
				} else {
					$this->order_reference ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $this->event_data );
				}

				novalnet()->db()->update(
					array(
						'additional_info' => $this->order_reference ['additional_info'],
					),
					array(
						'order_no' => $this->wc_order->get_id(),
					)
				);
			}
			if ( in_array( $this->wc_order->get_payment_method(), array( 'novalnet_invoice', 'novalnet_guaranteed_invoice', 'novalnet_instalment_invoice' ), true ) ) {

				if ( empty( $this->event_data ['transaction']['bank_details'] ) ) {
					$this->event_data ['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] );
				}
				$transaction_comments = novalnet()->helper()->prepare_payment_comments( $this->event_data );

				// Update order comments.
				novalnet()->helper()->update_comments( $this->wc_order, $transaction_comments, false, 'transaction_info', false );
			}
			if ( 'novalnet_invoice' !== $this->wc_order->get_payment_method() ) {
				$this->update_data ['update'] ['callback_amount'] = $this->event_data ['transaction']['amount'];
			}
		} elseif ( 'TRANSACTION_CANCEL' === $this->event_type ) {
			/* translators: %s: Date */
			$this->response ['message'] = sprintf( __( 'The transaction has been cancelled on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );
			$order_status               = 'wc-cancelled';
		}
		update_post_meta( $this->wc_order->get_id(), '_novalnet_gateway_status', $this->event_data ['transaction']['status'] );
		$this->wc_order->update_status( $order_status );
	}

	/**
	 * Handle transaction refund
	 *
	 * @since 12.0.0
	 */
	public function handle_transaction_refund() {
		if ( ! empty( $this->event_data ['transaction'] ['refund'] ['amount'] ) ) {

			// Create the refund.
			$refund = wc_create_refund(
				array(
					'order_id' => $this->wc_order->get_id(),
					'amount'   => sprintf( '%0.2f', ( $this->event_data ['transaction'] ['refund'] ['amount'] / 100 ) ),
					'reason'   => ! empty( $this->event_data ['transaction'] ['reason'] ) ? $this->event_data ['transaction'] ['reason'] : '',
				)
			);

			if ( is_wp_error( $refund ) ) {
				$this->notify_customer = false;
				/* translators: %1$s: date, %2$s: message*/
				$this->response ['message'] = sprintf( __( 'Payment refund failed for the order: %1$s due to: %2$s' ), $this->wc_order_id, $refund->get_error_message() );
				novalnet()->helper()->debug( $this->response ['message'], $this->wc_order_id );
			} else {
				/* translators: %1$s: tid, %2$s: amount */
				$this->response ['message'] = sprintf( __( 'Refund has been initiated for the TID:%1$s with the amount %2$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data ['transaction'] ['refund'] ['amount'] ) );
				if ( ! empty( $this->event_data['transaction']['refund']['tid'] ) ) {
					/* translators: %s: response tid */
					$this->response ['message'] .= sprintf( __( ' New TID:%s for the refunded amount', 'woocommerce-novalnet-gateway' ), $this->event_data ['transaction']['refund']['tid'] );
				}

				// Update transaction details.
				$this->update_data ['table']  = 'novalnet_transaction_detail';
				$this->update_data ['update'] = array(
					// Calculating refunded amount.
					'refunded_amount' => $this->order_reference ['refunded_amount'] + $this->event_data ['transaction'] ['refund'] ['amount'],
					'gateway_status'  => $this->event_data ['transaction']['status'],
				);

				if ( novalnet()->get_supports( 'instalment', $this->wc_order->get_payment_method() ) ) {

					$instalments = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
					foreach ( $instalments as $key => $data ) {
						if ( ! empty( $data ['tid'] ) && (int) $data ['tid'] === (int) $this->event_data ['transaction']['tid'] ) {
							if ( strpos( $instalments [ $key ] ['amount'], '.' ) ) {
								$instalments [ $key ] ['amount'] *= 100;
							}
							$instalments [ $key ] ['amount']                -= $this->event_data ['transaction'] ['refund'] ['amount'];
							$this->update_data ['update']['additional_info'] = wc_novalnet_serialize_data( $instalments );
						}
					}
				}
			}
		}
	}

	/**
	 * Handle chargeback
	 *
	 * @since 12.0.0
	 */
	public function handle_chargeback() {
		if ( wc_novalnet_check_isset( $this->order_reference, 'gateway_status', 'CONFIRMED' ) && ! empty( $this->event_data ['transaction'] ['amount'] ) ) {
			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: tid  */
			$this->response ['message'] = sprintf( __( 'Chargeback executed successfully for the TID: %1$s amount: %2$s on %3$s. The subsequent TID: %4$s.', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data ['transaction'] ['amount'] ), wc_novalnet_formatted_date(), $this->event_tid );
		}
	}

	/**
	 * Handle transaction update
	 *
	 * @since 12.0.0
	 */
	public function handle_transaction_update() {

		$this->update_data ['table']  = 'novalnet_transaction_detail';
		$this->update_data ['update'] = array(
			'gateway_status' => $this->event_data ['transaction']['status'],
		);
		if ( in_array( $this->event_data['transaction']['status'], array( 'PENDING', 'ON_HOLD', 'CONFIRMED', 'DEACTIVATED' ), true ) ) {
			if ( 'DEACTIVATED' === $this->event_data['transaction']['status'] ) {
				$this->notify_customer = true;

				/* translators: %s: Date */
				$this->response ['message'] = sprintf( __( 'The transaction has been cancelled on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );

				$transaction_comments = novalnet()->helper()->prepare_payment_comments( $this->event_data );

				$order_status = 'wc-cancelled';
			} else {
				if ( in_array( $this->order_reference['gateway_status'], array( 'PENDING', 'ON_HOLD' ), true ) ) {
					$this->update_data ['table']                    = 'novalnet_transaction_detail';
					$this->update_data ['update']['gateway_status'] = $this->event_data['transaction']['status'];

					if ( 'ON_HOLD' === $this->event_data['transaction']['status'] ) {
						$this->notify_customer = true;
						if ( empty( $this->event_data ['transaction']['bank_details'] ) && ! empty( $this->order_reference ['additional_info'] ) ) {
							$this->event_data ['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] );
						}
						$order_status = 'wc-on-hold';
					} elseif ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
						$this->notify_customer = true;

						if ( empty( $this->event_data ['transaction']['bank_details'] ) && ! empty( $this->order_reference ['additional_info'] ) ) {
							$this->event_data ['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] );
						}
						if ( novalnet()->get_supports( 'instalment', $this->wc_order->get_payment_method() ) ) {

							if ( ! empty( $this->order_reference ['additional_info'] ) ) {
								$this->order_reference ['additional_info'] = wc_novalnet_serialize_data( array_merge( wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] ), wc_novalnet_unserialize_data( apply_filters( 'novalnet_store_instalment_data', $this->event_data ) ) ) );
							} else {
								$this->order_reference ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $this->event_data );
							}
							novalnet()->db()->update(
								array(
									'additional_info' => $this->order_reference ['additional_info'],
								),
								array(
									'order_no' => $this->wc_order->get_id(),
								)
							);

							WC()->mailer();
							do_action( 'novalnet_send_instalment_notification_to_customer', $this->wc_order->get_id(), $this->wc_order );
						}
						$payment_settings = WC_Novalnet_Configuration::get_payment_settings( $this->wc_order->get_payment_method() );
						$order_status     = $payment_settings ['order_success_status'];
						$this->wc_order->payment_complete( $this->event_data['transaction']['tid'] );
						$this->update_data ['update']['callback_amount'] = (int) $this->order_reference ['amount'];
					}

					// Reform the transaction comments.
					if ( in_array( $this->event_data ['transaction']['payment_type'], array( 'INVOICE', 'PREPAYMENT', 'GUARANTEED_INVOICE', 'INSTALMENT_INVOICE' ), true ) ) {

						if ( empty( $this->event_data ['transaction']['bank_details'] ) ) {
							$this->event_data ['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] );
						}
					}
					if ( 'CASHPAYMENT' === $this->event_data ['transaction']['payment_type'] ) {
						$this->event_data ['transaction']['nearest_stores'] = wc_novalnet_unserialize_data( $this->order_reference ['additional_info'] );
					}

					$transaction_comments = novalnet()->helper()->prepare_payment_comments( $this->event_data );
					if ( (int) $this->event_data['transaction']['amount'] !== (int) $this->order_reference ['amount'] && ! novalnet()->get_supports( 'instalment', $this->wc_order->get_payment_method() ) ) {
						$this->notify_customer                  = true;
						$this->update_data ['update']['amount'] = $this->event_data['transaction']['amount'];
						if ( (int) $this->event_data['transaction']['amount'] < (int) $this->order_reference ['amount'] ) {
							$refund_amount   = (int) ( ( $this->order_reference ['amount'] - $this->order_reference ['refunded_amount'] ) - $this->event_data['transaction']['amount'] );
							$discount_amount = sprintf( '%0.2f', $refund_amount / 100 );

							// Create the refund.
							$refund = wc_create_refund(
								array(
									'order_id' => $this->wc_order->get_id(),
									'amount'   => $discount_amount,
									'reason'   => 'Transaction amount update',
								)
							);
							if ( is_wp_error( $refund ) ) {
								/* translators: %1$s: date, %2$s: message*/
								$this->response ['message'] = sprintf( __( 'Payment refund failed for the order: %1$s due to: %2$s' ), $this->wc_order->get_id(), $refund->get_error_message() );
								novalnet()->helper()->debug( $this->response ['message'], $this->wc_order_id );
							} else {
								$this->update_data ['refunded_amount'] = (int) $this->order_reference ['refunded_amount'] + $refund_amount;
							}
						} else {
							$fee_in_smaller_unit = $this->event_data['transaction']['amount'] - $this->order_reference ['amount'];
							$formatted_fee       = wc_novalnet_shop_amount_format( $fee_in_smaller_unit );
							$fee_in_bigger_unit  = sprintf( '%0.2f', $fee_in_smaller_unit / 100 );

							if ( ! empty( $fee_in_smaller_unit ) ) {
								$fee = new WC_Order_Item_Fee();
								$fee->set_total( $fee_in_bigger_unit );
								$fee->set_order_id( $this->wc_order->get_id() );

								/* translators: %s: formatted_fee */
								$fee->set_name( sprintf( __( '%s fee', 'woocommerce' ), wc_clean( $formatted_fee ) ) );

								$this->wc_order->add_item( $fee );
								$this->wc_order->calculate_taxes( false );
								$this->wc_order->calculate_totals( false );
								$this->wc_order->save();
							}
						}
					}

					if ( ! empty( $this->event_data['transaction']['due_date'] ) ) {
						/* translators: %1$s: tid, %2$s: amount, %3$s: due date */
						$this->response ['message'] = wc_novalnet_format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s and due date %3$s.', 'woocommerce-novalnet-gateway' ), $this->event_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date( $this->event_data['transaction']['due_date'] ) ) );
					} else {
						/* translators: %1$s: tid, %2$s: amount*/
						$this->response ['message'] = wc_novalnet_format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s.', 'woocommerce-novalnet-gateway' ), $this->event_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ) ) );
					}
				}
			}
			update_post_meta( $this->wc_order->get_id(), '_novalnet_gateway_status', $this->event_data ['transaction']['status'] );

			if ( ! empty( $order_status ) ) {
				$this->wc_order->update_status( $order_status );
			}

			if ( ! empty( $transaction_comments ) ) {
				// Update order comments.
				novalnet()->helper()->update_comments( $this->wc_order, $transaction_comments, false, 'transaction_info', false );
			}
		}
	}
	/**
	 * Validate event_data
	 *
	 * @since 12.0.0
	 */
	public function validate_event_data() {
		try {
			$json_input       = WP_REST_Server::get_raw_data();
			$this->event_data = wc_novalnet_unserialize_data( $json_input );
		} catch ( Exception $e ) {
			$this->display_message( array( 'message' => "Received data is not in the JSON format $e" ) );
		}

		if ( ! empty( $this->event_data ['custom'] ['shop_invoked'] ) ) {
			$this->display_message( array( 'message' => 'Process already handled in the shop.' ) );
		}
		// Your payment access key value.
		$this->payment_access_key = WC_Novalnet_Configuration::get_global_settings( 'key_password' );

		// Validate request parameters.
		foreach ( $this->mandatory as $category => $parameters ) {
			if ( empty( $this->event_data [ $category ] ) ) {

				// Could be a possible manipulation in the notification data.
				$this->display_message( array( 'message' => "Required parameter category($category) not received" ) );
			} elseif ( ! empty( $parameters ) ) {
				foreach ( $parameters as $parameter ) {
					if ( empty( $this->event_data [ $category ] [ $parameter ] ) ) {

						// Could be a possible manipulation in the notification data.
						$this->display_message( array( 'message' => "Required parameter($parameter) in the category($category) not received" ) );
					} elseif ( in_array( $parameter, array( 'tid', 'parent_tid' ), true ) && ! preg_match( '/^\d{17}$/', $this->event_data [ $category ] [ $parameter ] ) ) {
						$this->display_message( array( 'message' => "Invalid TID received in the category($category) not received $parameter" ) );
					}
				}
			}
		}
	}
	/**
	 * Validate checksum
	 *
	 * @since 12.0.0
	 */
	public function validate_checksum() {
		$token_string = $this->event_data ['event'] ['tid'] . $this->event_data ['event'] ['type'] . $this->event_data ['result'] ['status'];

		if ( isset( $this->event_data ['transaction'] ['amount'] ) ) {
			$token_string .= $this->event_data ['transaction'] ['amount'];
		}
		if ( isset( $this->event_data ['transaction'] ['currency'] ) ) {
			$token_string .= $this->event_data ['transaction'] ['currency'];
		}
		if ( ! empty( $this->payment_access_key ) ) {
			$token_string .= strrev( $this->payment_access_key );
		}

		$generated_checksum = hash( 'sha256', $token_string );

		if ( $generated_checksum !== $this->event_data ['event'] ['checksum'] ) {
			$this->display_message( array( 'message' => 'While notifying some data has been changed. The hash check failed' ) );
		}

	}
	/**
	 * Authenticate server request
	 *
	 * @since 12.0.0
	 */
	public function authenticate_event_data() {

		// Backend callback option.
		$this->test_mode = (int) ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'callback_test_mode' ) );

		// Host based validation.
		if ( ! empty( $this->novalnet_host_name ) ) {
			$novalnet_host_ip = gethostbyname( $this->novalnet_host_name );

			// Authenticating the server request based on IP.
			$request_received_ip = wc_novalnet_get_ip_address();
			if ( ! empty( $novalnet_host_ip ) && ! empty( $request_received_ip ) ) {
				if ( $novalnet_host_ip !== $request_received_ip && empty( $this->test_mode ) ) {
					$this->display_message( array( 'message' => "Unauthorised access from the IP $request_received_ip" ) );
				}
			} else {
				$this->display_message( array( 'message' => 'Unauthorised access from the IP. Host/recieved IP is empty' ) );
			}
		} else {
			$this->display_message( array( 'message' => 'Unauthorised access from the IP. Novalnet Host name is empty' ) );
		}

		$this->validate_event_data();

		$this->validate_checksum();
	}



	/**
	 * Get post id.
	 *
	 * @param int $wc_order_id  The order id of the processing order.
	 * @since 12.0.0
	 *
	 * @return array
	 */
	public function get_post_id( $wc_order_id ) {

		$post_id = '';
		if ( ! empty( $wc_order_id ) ) {
			$post_id = novalnet()->db()->get_post_id_by_order_number( $wc_order_id );
			if ( empty( $post_id ) ) {
				$post_id = $wc_order_id;
			}
		}
		return $post_id;
	}

	/**
	 * Get order reference.
	 *
	 * @return void
	 */
	public function get_order_reference() {

		if ( ! empty( $this->event_data ['transaction'] ['order_no'] ) || ! empty( $this->parent_tid ) ) {
			if ( ! empty( $this->event_data ['transaction'] ['order_no'] ) ) {
				$this->wc_order_id = $this->get_post_id( $this->event_data ['transaction'] ['order_no'] );
			} elseif ( ! empty( $this->event_data ['subscription'] ['order_no'] ) ) {
				$this->wc_order_id = $this->get_post_id( $this->event_data ['subscription'] ['order_no'] );
			}
			$this->order_reference = novalnet()->db()->get_transaction_details( $this->wc_order_id, $this->parent_tid );
		}

		// Assign payment type based on the order for subscription.
		if ( class_exists( 'WC_Subscription' ) && ! empty( $this->event_data ['subscription'] ['subs_id'] ) ) {
			if ( ! empty( $this->parent_tid ) ) {
				$this->subs_order_reference = novalnet()->db()->get_subscription_details( $this->parent_tid );
			}

			if ( empty( $this->order_reference ['order_no'] ) && ! empty( $this->subs_order_reference ) ) {
				$this->order_reference = novalnet()->db()->get_transaction_details( '', $this->subs_order_reference['tid'] );
			}

			if ( ! empty( $this->order_reference ['order_no'] ) ) {
				$this->wcs_order_id = apply_filters( 'novalnet_get_subscription_id', $this->order_reference ['order_no'] );
				$this->wcs_order    = new WC_Subscription( $this->wcs_order_id );
			}

			if ( ! empty( get_post_meta( $this->wcs_order_id, '_payment_method', true ) ) ) {
				// Get subscription payment type.
				$this->order_reference ['payment_type'] = get_post_meta( $this->wcs_order_id, '_payment_method', true );
			}
		}

		if ( empty( $this->order_reference ) ) {
			if ( 'ONLINE_TRANSFER_CREDIT' === $this->event_data ['transaction'] ['payment_type'] ) {
				if ( ! empty( $this->parent_tid ) ) {
					$this->wc_order_id = $this->get_post_id( $this->event_data ['transaction'] ['order_no'] );
				}
				$this->order_reference ['order_no']       = $this->wc_order_id;
				$this->event_data ['transaction'] ['tid'] = $this->parent_tid;
				$this->update_initial_payment( false );
				$this->order_reference = novalnet()->db()->get_transaction_details( $this->wc_order_id, $this->event_tid );

			} elseif ( 'PAYMENT' === $this->event_data ['event'] ['type'] ) {
				$this->order_reference ['order_no'] = $this->wc_order_id;
				$this->update_initial_payment( true );
			} else {
				$this->display_message( array( 'message' => 'Order reference not found in the shop' ) );
			}
		}
	}


	/**
	 * Update / initialize the payment.
	 *
	 * @since 12.0.0
	 * @param array $communication_failure Check for communication failure payment.
	 */
	public function update_initial_payment( $communication_failure ) {

		$comments = '';
		// Get the order no by using the cancelled order tid.
		if ( ! empty( $communication_failure ) ) {
			$order_id_by_meta = novalnet()->db()->get_post_id_by_meta_data( $this->parent_tid );
			if ( ! empty( $order_id_by_meta ) ) {
				$this->order_reference ['order_no'] = $order_id_by_meta;
			}
		}

		if ( ! empty( $this->order_reference ['order_no'] ) ) {
			$wc_order = new WC_Order( $this->order_reference ['order_no'] );

			$payment_gateway = wc_get_payment_gateway_by_order( $wc_order );

			if ( method_exists( $payment_gateway, 'check_transaction_status' ) ) {
				$comments = $payment_gateway->check_transaction_status( $this->event_data, $wc_order, $communication_failure );
			} else {
				$this->display_message( array( 'message' => 'Payment not found in the order' ) );
			}
		}
		return $comments;
	}

	/**
	 * Print the Webhook messages.
	 *
	 * @param array $data The data.
	 *
	 * @return void
	 */
	public function display_message( $data ) {
		wp_send_json( $data, 200 );
	}

	/**
	 * Send notification mail.
	 *
	 * @since 12.0.0
	 * @param string $comments        Formed comments.
	 */
	public function send_notification_mail( $comments ) {

		wc_novalnet_send_mail( WC_Novalnet_Configuration::get_global_settings( 'callback_emailtoaddr' ), 'Novalnet Callback Script Access Report - WooCommerce', $comments ['message'] );
	}


	/**
	 * Log callback process.
	 *
	 * @since 12.0.0
	 *
	 * @param int $post_id The post id of the processing order.
	 */
	public function log_callback_details( $post_id ) {

		$data = array(
			'event_type'     => $this->event_type,
			'gateway_status' => $this->event_data ['transaction']['status'],
			'event_tid'      => $this->event_tid,
			'parent_tid'     => $this->parent_tid,
			'order_no'       => $post_id,
		);

		if ( isset( $this->event_data ['transaction']['payment_type'] ) ) {
			$data['payment_type'] = $this->event_data ['transaction']['payment_type'];
		}
		if ( isset( $this->event_data ['transaction']['amount'] ) ) {
			$data['amount'] = $this->event_data ['transaction']['amount'];
		}
		novalnet()->db()->insert(
			$data,
			'novalnet_webhook_history'
		);
	}

}

new WC_Novalnet_Webhook();
