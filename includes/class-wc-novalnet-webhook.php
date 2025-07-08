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
	 * Subscription order reference values.
	 *
	 * @var array
	 */
	protected $subs_order_reference = array();

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
	 * Order comments update flag
	 *
	 * @var bool
	 */
	protected $update_comments = true;

	/**
	 * The details need to be update in Novalnet table.
	 *
	 * @var array
	 */
	protected $update_data = array();

	/**
	 * Received Event TID.
	 *
	 * @var int
	 */
	protected $event_tid;

	/**
	 * Received Event parent TID.
	 *
	 * @var int
	 */
	protected $parent_tid;

	/**
	 * Check is subscription.
	 *
	 * @var bool
	 */
	protected $is_subscription;

	/**
	 * Novalnet_Webhooks constructor.
	 *
	 * @since 12.0.0
	 */
	public function __construct() {
		 // Authenticate request host.
		$this->authenticate_event_data();

		// Set Event data.
		$this->event_type = $this->event_data['event']['type'];
		$this->event_tid  = $this->event_data['event']['tid'];
		$this->parent_tid = $this->event_tid;
		if ( ! empty( $this->event_data['event']['parent_tid'] ) ) {
			$this->parent_tid = $this->event_data['event']['parent_tid'];
		}

		// Get order reference.
		$this->get_order_reference();

		$order_reference_match = true;
		// Order number check.
		if ( ! empty( $this->event_data['transaction']['order_no'] ) ) {
			// Retrieve the post ID using the transaction order number.
			$org_post_id           = novalnet()->helper()->get_post_id( $this->event_data['transaction']['order_no'] );
			$order_reference_match = ( ! empty( $org_post_id ) && (string) $this->order_reference['order_no'] === (string) $org_post_id );
		}

		if ( ! empty( $this->event_data['custom']['nn_order_id'] ) ) {
			$org_post_id           = novalnet()->helper()->get_post_id( $this->event_data['custom']['nn_order_id'] );
			$order_reference_match = ( ! empty( $org_post_id ) && (string) $this->order_reference['order_no'] === (string) $org_post_id );
		}

		if ( ! empty( $this->parent_tid ) ) {
			$org_post_id = novalnet()->db()->get_post_id_by_meta_data( $this->parent_tid, '_novalnet_renewal_tid' );
			if ( ! empty( $this->wcs_order ) && 'CREDIT' === $this->event_type && ! empty( $org_post_id ) && (string) $this->order_reference['order_no'] === (string) $org_post_id ) {
				$order_reference_match = true;
			}
		}
		if ( ! $order_reference_match ) {
			$this->display_message( array( 'message' => 'Order reference not matching.' ) );
		}
		// Create order object.
		$this->wc_order            = wc_get_order( $this->order_reference['order_no'] );
		$this->wc_order_id         = $this->wc_order->get_id();
		$this->response['message'] = __( 'Notification received from Novalnet for this order. ', 'woocommerce-novalnet-gateway' );

		if ( WC_Novalnet_Validation::is_success_status( $this->event_data ) || novalnet()->helper()->is_subs_renewal_active_with_collection( $this->event_data ) || ( 'RENEWAL' === $this->event_type && ! WC_Novalnet_Validation::is_success_status( $this->event_data ) && ! empty( $this->event_data['result']['status_text'] ) ) || in_array( $this->event_type, array( 'PAYMENT_REMINDER_1', 'PAYMENT_REMINDER_2', 'SUBMISSION_TO_COLLECTION_AGENCY' ), true ) ) {
			$this->is_subscription = false;
			$this->notify_customer = false;
			switch ( $this->event_type ) {

				case 'PAYMENT':
					$this->handle_payment();
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
					break;
				case 'SUBSCRIPTION_SUSPEND':
					$this->handle_subscription_suspend();
					$this->notify_customer = true;
					break;
				case 'SUBSCRIPTION_REACTIVATE':
					$this->handle_subscription_reactivate();
					$this->notify_customer = true;
					break;
				case 'SUBSCRIPTION_CANCEL':
					$this->handle_subscription_cancel();
					$this->notify_customer = true;
					break;
				case 'SUBSCRIPTION_UPDATE':
					$this->handle_subscription_update();
					$this->notify_customer = true;
					break;
				case 'PAYMENT_REMINDER_1':
					$this->handle_payment_reminder( 1 );
					$this->notify_customer = true;
					break;
				case 'PAYMENT_REMINDER_2':
					$this->handle_payment_reminder( 2 );
					$this->notify_customer = true;
					break;
				case 'SUBMISSION_TO_COLLECTION_AGENCY':
					$this->submission_to_collection_agency();
					$this->notify_customer = true;
					break;
				default:
					$this->display_message( array( 'message' => "The webhook notification has been received for the unhandled EVENT type($this->event_type)" ) );
			}
			if ( ! empty( $this->update_data['update'] ) && $this->update_data['table'] ) {
				novalnet()->db()->update(
					$this->update_data['update'],
					array(
						'order_no' => $this->wc_order->get_id(),
					),
					$this->update_data['table']
				);
			}

			// Update order comments.
			if ( $this->update_comments && $this->is_subscription ) {
				novalnet()->helper()->update_comments( $this->wcs_order, $this->response['message'], 'note', $this->notify_customer );
			} elseif ( $this->update_comments ) {
				novalnet()->helper()->update_comments( $this->wc_order, $this->response['message'], 'note', $this->notify_customer );
			}

			// Log callback process.
			$this->log_callback_details( $this->wc_order->get_id() );

			$this->send_notification_mail(
				array(
					'message' => $this->response['message'],
				)
			);
			$this->display_message( $this->response );
		} else {
			$this->display_message( array( 'message' => 'Novalnet callback received' ) );
		}
	}

	/**
	 * Handle payment event.
	 *
	 * @since 12.6.0
	 */
	public function handle_payment() {
		if (
			isset( $this->event_data['custom']['book_reference'] ) && isset( $this->event_data['custom']['zero_txn_order_amount'] )
			&& '1' === (string) novalnet()->helper()->novalnet_get_wc_order_meta( $this->wc_order, '_novalnet_booking_ref_order' )
		) {
			$additional_info = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
			if ( novalnet()->helper()->update_payment_booking( $this->wc_order_id, $this->wc_order, $this->event_data, $additional_info ) ) {
				$this->update_comments = false;
				/* translators: %1$s: amount, %2$s: TID*/
				$this->response['message'] = sprintf( __( 'Your order has been booked with the amount of %1$s. Your new TID for the booked amount: %2$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), $this->event_data['transaction']['tid'] );
				return true;
			}
		}
		$this->display_message( array( 'message' => 'Novalnet callback received' ) );
	}

	/**
	 * Handle subscription suspend
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_suspend() {
		if ( wcs_novalnet_subscription_exists( $this->wcs_order ) ) {
			/* translators: %1$s: parent_tid, %3$s: date*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'This subscription transaction has been suspended on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );

			add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

			$this->wcs_order->update_status( 'on-hold' );

			delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );
			$this->update_data['table']  = 'novalnet_subscription_details';
			$this->update_data['update'] = array(
				'suspended_date' => gmdate( 'Y-m-d H:i:s' ),
			);
			$this->is_subscription       = true;
		} else {
			/* translators: %1$s: event_tid, %2$s: wc_order_id*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription suspend for TID %1$s not processed: No subscription found for Order ID %2$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, $this->wc_order_id ) );
			return true;
		}
	}

	/**
	 * Handle subscription cancel
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_cancel() {
		if ( wcs_novalnet_subscription_exists( $this->wcs_order ) ) {
			// Flag to check renewal for subscription is failed.
			$subs_cancel_reason = novalnet()->helper()->novalnet_get_wc_order_meta( $this->wc_order, '_subs_cancelled_reason' );
			if ( ! empty( $subs_cancel_reason ) ) {
				$this->event_data['subscription']['reason'] = ( empty( $this->event_data['subscription']['reason'] ) ) ? $subs_cancel_reason : $this->event_data['subscription']['reason'];
				novalnet()->helper()->novalnet_delete_wc_order_meta( $this->wc_order, '_subs_cancelled_reason', true );
			}

			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: next_cycle_date*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been cancelled due to: %s. ', 'woocommerce-novalnet-gateway' ), $this->event_data['subscription']['reason'] ) );

			add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

			try {
				if ( ! $this->wcs_order->needs_payment() ) {
					$this->wcs_order->update_status( 'pending-cancel' );
				} elseif ( $this->wcs_order->can_be_updated_to( 'cancelled' ) ) { // Set subscription status to cancelled if the renewal is failed.
					$this->wcs_order->update_status( 'cancelled' );
				}
			} catch ( Exception $e ) {
				if ( $this->wcs_order->has_status( 'cancelled' ) ) {
					$this->response['message'] .= 'Order already cancelled.';
				} else {
					$this->response['message'] .= $e->getMessage();
				}
			}

			delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );

			$this->update_data['table']  = 'novalnet_subscription_details';
			$this->update_data['update'] = array(
				'termination_at'     => gmdate( 'Y-m-d H:i:s' ),
				'termination_reason' => $this->event_data['subscription']['reason'],
			);
			$this->is_subscription       = true;
		} else {
			/* translators: %1$s: event_tid, %2$s: wc_order_id*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription cancel for TID %1$s not processed: No subscription found for Order ID %2$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, $this->wc_order_id ) );
			return true;
		}
	}

	/**
	 * Handle subscription reactivate
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_reactivate() {
		if ( wcs_novalnet_subscription_exists( $this->wcs_order ) ) {
			/* translators: %1$s: date, %2$s: amount, %3$s: next_cycle_date*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been reactivated for the TID:%1$s on %2$s. Next charging date :%3$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date(), wc_novalnet_next_cycle_date( $this->event_data['subscription'] ) ) );

			add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

			// Set requires_manual_renewal flag to activate the cancelled subscription.
			if ( $this->wcs_order->has_status( wcs_get_subscription_ended_statuses() ) ) {
				$this->wcs_order->set_requires_manual_renewal( true );
			}

			$current_status = $this->wcs_order->get_status();

			if ( 'pending-cancel' !== $current_status ) {
				novalnet()->helper()->update_subscription_dates(
					$this->wcs_order,
					array( 'next_payment' => $this->event_data['subscription']['next_cycle_date'] ),
					( ! empty( $this->wcs_order->get_date( 'cancelled' ) ) )
				);
			}

			try {
				$this->wcs_order->update_status( 'active' );
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

			if ( 'pending-cancel' === $current_status ) {
				novalnet()->helper()->update_subscription_dates(
					$this->wcs_order,
					array( 'next_payment' => $this->event_data['subscription']['next_cycle_date'] ),
				);
			}

			$this->wcs_order->save();

			delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );

			$this->update_data['table']  = 'novalnet_subscription_details';
			$this->update_data['update'] = array(
				'suspended_date'     => null,
				'termination_at'     => null,
				'termination_reason' => null,
			);
			$this->is_subscription       = true;
		} else {
			/* translators: %1$s: event_tid, %2$s: wc_order_id*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription reactivation for TID %1$s not processed: No subscription found for Order ID %2$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, $this->wc_order_id ) );
			return true;
		}
	}

	/**
	 * Handle subscription update
	 *
	 * @since 12.0.0
	 */
	public function handle_subscription_update() {
		if ( wcs_novalnet_subscription_exists( $this->wcs_order ) ) {
			// Handle change payment method.
			$payment_types              = novalnet()->get_payment_types();
			$this->update_data['table'] = 'novalnet_subscription_details';
			$next_cycle_date            = wc_novalnet_next_cycle_date( $this->event_data['subscription'] );

			if ( ! empty( $this->event_data['subscription']['update_type'] ) ) {
				if ( ! empty( $this->event_data['subscription']['amount'] ) && ( in_array( 'RENEWAL_AMOUNT', $this->event_data['subscription']['update_type'], true ) || in_array( 'RENEWAL_DATE', $this->event_data['subscription']['update_type'], true ) ) ) {

					/* translators: %1$s: amount, %2$s: next_cycle_date */
					$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription updated successfully. You will be charged %1$s on %2$s.', 'woocommerce-novalnet-gateway' ), ( wc_novalnet_shop_amount_format( $this->event_data['subscription']['amount'] ) ), wc_novalnet_next_cycle_date( $this->event_data['subscription'] ) ) );
				}

				if ( in_array( 'PAYMENT_DATA', $this->event_data['subscription']['update_type'], true ) && ! empty( $this->event_data['transaction']['payment_type'] ) ) {

					$payment_types = array_flip( $payment_types );

					/* translators: %s: next_cycle_date */
					$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_next_cycle_date( $this->event_data['subscription'] ) ) );

					// Set new payment method.
					WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $this->wcs_order, $payment_types[ $this->event_data['transaction']['payment_type'] ] );

					// Update recurring payment process.
					do_action( 'novalnet_update_recurring_payment', $this->event_data, $this->wcs_order->get_parent_id(), $this->wcs_order->get_payment_method(), $this->wcs_order );

					// 'novalnet_update_recurring_payment' action already update response message customer note.
					$this->update_comments = false;
				}

				if ( in_array( 'STATUS', $this->event_data['subscription']['update_type'], true ) ) {
					/* translators: %1$s: date */
					$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been successfully activated on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );
				}

				if ( ! empty( $next_cycle_date ) ) {
					novalnet()->helper()->update_subscription_dates(
						$this->wcs_order,
						array( 'next_payment' => $next_cycle_date ),
					);
				}
			} else {
				$this->display_message( array( 'message' => 'Subscription update type has not been received.' ) );
			}
			$this->is_subscription = true;
		} else {
			/* translators: %1$s: event_tid, %2$s: wc_order_id*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription update for TID %1$s not processed: No subscription found for Order ID %2$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, $this->wc_order_id ) );
			return true;
		}
	}

	/**
	 * Handle payment_reminders
	 *
	 * @param int $reminder_count The number of reminder send to customer.
	 * @since 12.4.0
	 */
	public function handle_payment_reminder( $reminder_count ) {
		/* translators: %s: reminder count */
		$this->response['message'] = sprintf( __( 'Payment Reminder %1$s has been sent to the customer.', 'woocommerce-novalnet-gateway' ), $reminder_count );
	}

	/**
	 * Handle Submission to Collection Agency
	 *
	 * @since 12.4.0
	 */
	public function submission_to_collection_agency() {
		 /* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: tid  */
		$this->response['message'] = sprintf( __( 'The transaction has been submitted to the collection agency. Collection Reference: %1$s', 'woocommerce-novalnet-gateway' ), $this->event_data['collection']['reference'] );
	}

	/**
	 * Handle renewal
	 *
	 * @since 12.0.0
	 */
	public function handle_renewal() {
		if ( wcs_novalnet_subscription_exists( $this->wcs_order ) ) {
			// Get next cycle date from the event data.
			$next_cycle_date = wc_novalnet_next_cycle_date( $this->event_data['subscription'] );

			if ( empty( $this->wcs_order->get_payment_method() ) && ! empty( $this->subs_order_reference['recurring_payment_type'] ) ) {
				$this->wcs_order->set_payment_method( $this->subs_order_reference['recurring_payment_type'] );
			}

			if ( ! WC_Novalnet_Validation::check_string( $this->wcs_order->get_payment_method() ) ) {
				/* translators: %1$s: renewal tid, %2$s: subscription payment method */
				$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Renewal creation for TID %1$s not processed: Payment method changed to %2$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, $this->wcs_order->get_payment_method_title() ) );
				return true;
			}

			// Initiate particular payment class.
			$payment_gateway = wc_get_payment_gateway_by_order( $this->wcs_order );

			// Set current payment type in session.
			WC()->session->set( 'current_novalnet_payment', $payment_gateway->id );

			// Create the renewal order.
			$recurring_order = apply_filters( 'novalnet_create_renewal_order', $this->wcs_order );
			$recurring_order->set_payment_method( $this->wcs_order->get_payment_method() );
			$recurring_order->set_payment_method_title( $this->wcs_order->get_payment_method_title() );

			/* translators: %1$s: tid, %2$s: amount, %3$s: date, %4$s: tid */
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Subscription has been successfully renewed for the TID: %1$s with the amount %2$s on %3$s. The renewal TID is:%4$s.', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_tid ) );

			// Do Novalnet process after verify the successful recurring order creation.
			if ( ! empty( $recurring_order->get_id() ) ) {
				/* Update renewal order number */
				$this->response['order_no'] = $recurring_order->get_id();
				$wcsr_order                 = wc_get_order( $recurring_order->get_id() );

				if ( ! is_null( $wcsr_order ) && ! empty( $wcsr_order->get_order_number() ) ) {
					novalnet()->helper()->novalnet_update_wc_order_meta( $wcsr_order, '_novalnet_order_number', $wcsr_order->get_order_number(), true );
					$this->response['order_no'] = $wcsr_order->get_order_number();
				}
				novalnet()->helper()->novalnet_update_wc_order_meta( $recurring_order, '_novalnet_renewal_tid', $this->event_data['transaction']['tid'], true );
				if ( ! WC_Novalnet_Validation::is_success_status( $this->event_data ) && ! empty( $this->event_data['result']['status_text'] ) ) {

					/* translators: %1$s: tid, %2$s: date, %3$s: status_text */
					$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'The subscription renewal for TID %1$s was failed on %2$s due to following reason: %3$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date(), $this->event_data['result']['status_text'] ) );

					novalnet()->helper()->novalnet_update_wc_order_meta( $recurring_order, '_novalnet_renewal_subscription_order', $this->wcs_order_id, true );
					novalnet()->helper()->novalnet_update_wc_order_meta( $this->wc_order, '_subs_cancelled_reason', $this->event_data['result']['status_text'], true );
					$insert_data = novalnet()->helper()->prepare_transaction_table_data( $recurring_order, $recurring_order->get_payment_method(), $this->event_data );
					novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );
					$this->update_comments = false;
				}

				if ( novalnet()->helper()->is_subs_renewal_active_with_collection( $this->event_data ) ) {
					novalnet()->helper()->novalnet_update_wc_order_meta( $recurring_order, 'nn_failed_renewal', true, true );
				}

				$payment_gateway->check_transaction_status( $this->event_data, $recurring_order, true );
			}

			$total_length = apply_filters( 'novalnet_get_order_subscription_length', $this->wcs_order );

			$coupon_discount_payment_count = novalnet()->helper()->novalnet_get_wc_order_meta( $this->wc_order, '_novalnet_wcs_number_payments' );
			$used_coupons                  = $this->wcs_order->get_coupon_codes();
			if ( ! empty( $used_coupons ) ) {
				foreach ( $used_coupons as $coupon_code ) {
					$coupon = new WC_Coupon( $coupon_code );
					if ( 'recurring_fee' === $coupon->get_discount_type() || 'recurring_percent' === $coupon->get_discount_type() ) {
						$meta_data_obj = $coupon->get_meta_data();
						foreach ( $meta_data_obj as $wc_meta_data ) {
							$meta_data = $wc_meta_data->get_data();
							if ( in_array( '_wcs_number_payments', $meta_data, true ) ) {
								$coupon_discount_payment_count = $meta_data['value'];
							}
						}
					}
				}
			}

			novalnet()->helper()->update_subscription_dates(
				$this->wcs_order,
				array( 'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( $next_cycle_date ) ) ),
				( ! empty( $this->wcs_order->get_date( 'cancelled' ) ) )
			);

			  // Suscription related order counts.
			  $related_orders = apply_filters( 'novalnet_get_subscription_related_order', $this->wcs_order );
			if ( ! empty( $total_length ) && $related_orders >= $total_length ) {
				$this->subscription_cancel_to_server();
			} elseif ( ! empty( $next_cycle_date ) && WC_Novalnet_Validation::is_success_status( $this->event_data ) ) {
				/* translators: %s: next cycle date */
				$this->response['message'] .= wc_novalnet_format_text( sprintf( __( ' Next charging date will be on %1$s', 'woocommerce-novalnet-gateway' ), $next_cycle_date ) );
			}

			if ( version_compare( WC_Subscriptions::$version, '4.0.0', '<' ) ) {
				WC_Subscriptions_Coupon::check_coupon_usages( $this->wcs_order );
			} else {
				WCS_Limited_Recurring_Coupon_Manager::check_coupon_usages( $this->wcs_order );
			}

			if ( ! empty( $coupon_discount_payment_count ) && (int) $related_orders === (int) $coupon_discount_payment_count ) {
				$this->update_recurring_order_amount();
			}

			  $this->is_subscription = true;
		} else {
			/* translators: %1$s: event_tid, %2$s: wc_order_id*/
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Renewal creation for TID %1$s not processed: No subscription found for Order ID %2$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, $this->wc_order_id ) );
			return true;
		}
	}

	/**
	 * Send the subscription cancel call to the server.
	 *
	 * @since 12.8.1
	 */
	public function subscription_cancel_to_server() {
		add_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated', true );

		$tid = ( ! empty( $this->event_data['event']['parent_tid'] ) ) ? $this->event_data['event']['parent_tid'] : novalnet()->db()->get_subs_data_by_order_id( $this->wcs_order->get_parent_id(), $this->wcs_order->get_id(), 'tid', false );
		if ( empty( $tid ) ) {
			$tid = novalnet()->db()->get_transaction_details( $this->wc_order_id, $this->parent_tid, $this->wcs_order->get_id() );
		}

		$parameters['subscription']['tid']    = ( is_array( $tid ) && isset( $tid['tid'] ) ) ? $tid['tid'] : $tid;
		$parameters['subscription']['reason'] = '';
		$parameters['custom']['lang']         = wc_novalnet_shop_language();
		$parameters['custom']['shop_invoked'] = 1;

		$response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( 'subscription_cancel' ), array( 'post_id' => $this->wc_order_id ) );
		novalnet()->helper()->update_comments( $this->wcs_order, $this->response['message'], 'note', $this->notify_customer );
		if ( WC_Novalnet_Validation::is_success_status( $response ) ) {
			/* translators: %s: tid */
			$subscription_cancel_note = PHP_EOL . PHP_EOL . wc_novalnet_format_text( sprintf( __( 'Subscription has been cancelled since the subscription has exceeded the maximum time period for the TID: %s', 'woocommerce-novalnet-gateway' ), $tid ) );
			if ( $this->wcs_order->can_be_updated_to( 'pending-cancel' ) ) {
				$this->wcs_order->update_status( 'pending-cancel', $subscription_cancel_note );
			}
			$this->update_comments = false;
			delete_post_meta( $this->wcs_order->get_id(), '_nn_subscription_updated' );
		}
	}

	/**
	 * Handle instalment
	 *
	 * @since 12.0.0
	 */
	public function handle_instalment() {
		if ( 'CONFIRMED' === $this->event_data['transaction']['status'] && ! empty( $this->event_data['instalment']['cycles_executed'] ) ) {

			/* translators: %1$s: parent_tid, %2$s: tid, %3$s: amount, %4$s: date */
			$this->response['message'] = sprintf( __( 'A new instalment transaction has been received for the Transaction ID: %1$s . The new instalment Transaction ID is %2$s with the amount %3$s on %4$s. ', 'woocommerce-novalnet-gateway' ), $this->parent_tid, $this->event_tid, wc_novalnet_shop_amount_format( $this->event_data['instalment']['cycle_amount'] ), wc_novalnet_formatted_date() );

			// Store Bank details.
			$this->order_reference['additional_info'] = apply_filters( 'novalnet_store_instalment_data_webhook', $this->event_data );
			$this->update_data['table']               = 'novalnet_transaction_detail';
			$this->update_data['update']              = array(
				'additional_info' => $this->order_reference['additional_info'],
			);

			if ( 'INSTALMENT_INVOICE' === $this->event_data['transaction']['payment_type'] && empty( $this->event_data['transaction']['bank_details'] ) ) {
				$this->event_data['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
			}

			// Build & update renewal comments.
			$transaction_comments = PHP_EOL . novalnet()->helper()->prepare_payment_comments( $this->event_data );
			novalnet()->helper()->update_comments( $this->wc_order, $transaction_comments, 'transaction_info', false, true );

			novalnet()->db()->update(
				array(
					'additional_info' => $this->order_reference['additional_info'],
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
		if ( 'CONFIRMED' === $this->event_data['transaction']['status'] && 'DEACTIVATED' !== (string) $this->order_reference['gateway_status'] ) {
			$this->update_data['table']  = 'novalnet_transaction_detail';
			$this->update_data['update'] = array(
				'gateway_status' => 'DEACTIVATED',
			);
			$instalments                 = novalnet()->db()->get_entry_by_order_id( $this->wc_order_id, 'additional_info' );
			if ( ! empty( $instalments ) ) {
				$instalments['is_instalment_cancelled'] = 1;
				$instalments['is_full_cancelled']       = 1;
				if ( ! empty( $this->event_data['instalment']['cancel_type'] ) ) {
					$instalments['is_full_cancelled'] = ( 'ALL_CYCLES' === (string) $this->event_data['instalment']['cancel_type'] ) ? 1 : 0;
				}
				$this->update_data['update']['additional_info'] = wc_novalnet_serialize_data( $instalments );
			}

			if ( 'REMAINING_CYCLES' === (string) $this->event_data['instalment']['cancel_type'] ) {
				/* translators: %1$s: parent_tid, %2$s: date */
				$this->response['message'] = sprintf( __( 'Instalment has been stopped for the TID: %1$s on %2$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date() );
			} else {
				$refund_note = '';
				if ( isset( $this->event_data['transaction']['refund']['amount'] ) ) {
					$refund_note = sprintf(
						/* translators: %1$s: refund amount */
						__( '& Refund has been initiated with the amount %1$s', 'woocommerce-novalnet-gateway' ),
						wc_novalnet_shop_amount_format(
							wc_novalnet_formatted_amount(
								$this->event_data['transaction']['refund']['amount'] / 100
							)
						)
					);
				}
				/* translators: %1$s: parent_tid, %2$s: date */
				$this->response['message'] = sprintf( __( 'Instalment has been cancelled for the TID: %1$s on %2$s %3$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date(), $refund_note );
				$this->wc_order->update_status( 'wc-cancelled' );
			}
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
			$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %4$s', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_data['transaction']['tid'] ) );
		} else {
			/* translators: %s: post type */
			$this->response['message'] = sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %4$s. ', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_data['transaction']['tid'] );
			$payment_settings          = WC_Novalnet_Configuration::get_payment_settings( $this->wc_order->get_payment_method() );
			if ( in_array( $this->event_data['transaction']['payment_type'], array( 'INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'MULTIBANCO_CREDIT' ), true ) ) {
				$this->update_payment_credit_status_amount( $payment_settings['callback_status'] );
			} elseif ( ! empty( $this->wcs_order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $this->wc_order_id, array( 'order_type' => 'any' ) );
				if ( ! empty( $subscriptions ) ) {
					foreach ( $subscriptions as $subscription ) {
						$is_shop_based_subs = apply_filters( 'novalnet_check_is_shop_scheduled_subscription', $subscription->get_id() );
						if ( $is_shop_based_subs && WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) ) {
							$subscription->update_status( 'active' );
							if ( ! $this->wc_order->has_status( $payment_settings['order_success_status'] ) ) {
								$this->wc_order->update_status( $payment_settings['order_success_status'] ); // Update callback status.
							}
						} elseif ( WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) ) {
							novalnet()->helper()->novalnet_update_wc_order_meta( $this->wc_order, 'nn_credit_tid', $this->event_data['transaction']['tid'], true );
							$this->update_payment_credit_status_amount();
							if ( novalnet()->helper()->novalnet_get_wc_order_meta( $this->wc_order, '_novalnet_renewal_subscription_order' ) ) {
								novalnet()->helper()->novalnet_delete_wc_order_meta( $this->wc_order, '_novalnet_renewal_subscription_order', true );
							}
							// Suscription related order counts.
							$related_orders = apply_filters( 'novalnet_get_subscription_related_order', $this->wcs_order );

							if ( ! empty( $total_length ) && $related_orders >= $total_length ) {
								$this->subscription_cancel_to_server();
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Checks the credit amount and update the order status.
	 *
	 * @param string $order_update_status The order status.
	 * @param bool   $complete_payment    Flag to invoke the WooCommerce payment completion method.
	 *
	 * @since 12.6.1
	 */
	public function update_payment_credit_status_amount( $callback_status = '' ) {
		// Calculate total amount.
		$paid_amount = $this->order_reference['callback_amount'] + $this->event_data['transaction']['amount'];

		// Calculate including refunded amount.
		$amount_to_be_paid = $this->order_reference['amount'] - $this->order_reference['refunded_amount'];

		$this->update_data['table']  = 'novalnet_transaction_detail';
		$this->update_data['update'] = array(
			'gateway_status'  => $this->event_data['transaction']['status'],
			'callback_amount' => $paid_amount,
		);

		if ( ( (int) $paid_amount >= (int) $amount_to_be_paid ) ) {
			if ( $this->wc_order->get_date_paid() == null ) {
				$this->wc_order->payment_complete( $this->parent_tid );
				$this->wc_order->set_date_paid( time() );

			}
			if ( ! empty( $callback_status ) ) {
				$this->wc_order->update_status( $callback_status ); // Update callback status.
			}
			$this->wc_order->save();
			if ( (int) $paid_amount > (int) $amount_to_be_paid ) {
				$this->response['message'] .= sprintf( __( 'The amount has been overpaid', 'woocommerce-novalnet-gateway' ) );
			}
		}
	}


	/**
	 * Handle transaction capture/cancel
	 *
	 * @since 12.0.0
	 */
	public function handle_transaction_capture_cancel() {
		$this->update_data['table']  = 'novalnet_transaction_detail';
		$this->update_data['update'] = array(
			'gateway_status' => $this->event_data['transaction']['status'],
		);
		if ( 'TRANSACTION_CAPTURE' === $this->event_type ) {
			/* translators: %s: Date */
			$this->response['message'] = sprintf( __( 'The transaction has been confirmed on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );
			$payment_settings          = WC_Novalnet_Configuration::get_payment_settings( $this->wc_order->get_payment_method() );
			$order_status              = $payment_settings['order_success_status'];
			if ( $this->wc_order->get_date_paid() == null ) {
				$this->wc_order->payment_complete( $this->event_data['transaction']['tid'] );
				$this->wc_order->set_date_paid( time() );
			}

			if ( in_array( $this->wc_order->get_payment_method(), array( 'novalnet_instalment_sepa', 'novalnet_instalment_invoice' ), true ) ) {

				if ( ! empty( $this->order_reference['additional_info'] ) ) {
					$this->order_reference['additional_info'] = wc_novalnet_serialize_data( array_merge( wc_novalnet_unserialize_data( $this->order_reference['additional_info'] ), wc_novalnet_unserialize_data( apply_filters( 'novalnet_store_instalment_data', $this->event_data ) ) ) );
				} else {
					$this->order_reference['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $this->event_data );
				}

				novalnet()->db()->update(
					array(
						'additional_info' => $this->order_reference['additional_info'],
					),
					array(
						'order_no' => $this->wc_order->get_id(),
					)
				);
			}
			if ( in_array( $this->wc_order->get_payment_method(), array( 'novalnet_invoice', 'novalnet_guaranteed_invoice', 'novalnet_instalment_invoice' ), true ) ) {

				if ( empty( $this->event_data['transaction']['bank_details'] ) ) {
					$this->event_data['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
				}
				$transaction_comments = novalnet()->helper()->prepare_payment_comments( $this->event_data );

				// Update order comments.
				novalnet()->helper()->update_comments( $this->wc_order, $transaction_comments, 'transaction_info', false, true );
			}
			if ( 'novalnet_invoice' !== $this->wc_order->get_payment_method() ) {
				$this->update_data['update']['callback_amount'] = $this->event_data['transaction']['amount'];
			}
		} elseif ( 'TRANSACTION_CANCEL' === $this->event_type ) {
			/* translators: %s: Date */
			$this->response['message'] = sprintf( __( 'The transaction has been cancelled on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );
			$order_status              = 'wc-cancelled';
		}
		// Update status will save the order.
		novalnet()->helper()->novalnet_update_wc_order_meta( $this->wc_order, '_novalnet_gateway_status', $this->event_data['transaction']['status'] );
		$this->wc_order->update_status( $order_status );
	}

	/**
	 * Handle transaction refund
	 *
	 * @since 12.0.0
	 */
	public function handle_transaction_refund() {
		if ( ! empty( $this->event_data['transaction']['refund']['amount'] ) ) {

			/* translators: %1$s: tid, %2$s: amount */
			$this->response['message'] = sprintf( __( 'Refund has been initiated for the TID:%1$s with the amount %2$s.', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['refund']['amount'] ) );
			if ( ! empty( $this->event_data['transaction']['refund']['tid'] ) ) {
				/* translators: %s: response tid */
				$this->response['message'] .= sprintf( __( ' New TID:%s for the refunded amount.', 'woocommerce-novalnet-gateway' ), $this->event_data['transaction']['refund']['tid'] );
			}

			// Update transaction details.
			$this->update_data['table']  = 'novalnet_transaction_detail';
			$this->update_data['update'] = array(
				// Calculating refunded amount.
				'refunded_amount' => $this->order_reference['refunded_amount'] + $this->event_data['transaction']['refund']['amount'],
				'gateway_status'  => $this->event_data['transaction']['status'],
			);

			if ( novalnet()->get_supports( 'instalment', $this->wc_order->get_payment_method() ) ) {

				$instalments = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
				foreach ( $instalments as $key => $data ) {
					if ( ! empty( $data['tid'] ) && (int) $data['tid'] === (int) $this->event_data['transaction']['tid'] ) {
						if ( strpos( $instalments[ $key ]['amount'], '.' ) ) {
							$instalments[ $key ]['amount'] *= 100;
						}
						$instalments[ $key ]['amount']                 -= $this->event_data['transaction']['refund']['amount'];
						$this->update_data['update']['additional_info'] = wc_novalnet_serialize_data( $instalments );
					}
				}
			}

			$refunded_amount = $this->order_reference['refunded_amount'] + $this->event_data['transaction']['refund']['amount'];

			$excess_amount         = $this->order_reference['callback_amount'] - $this->order_reference['amount'];
			$amount_to_be_refunded = 0;
			$order_total_refunded  = $this->wc_order->get_total_refunded();

			if ( $excess_amount > 0 && $excess_amount >= $refunded_amount ) {
				return;
			} elseif ( $excess_amount > 0 ) {
				$previous_refunded_amount = $this->order_reference['refunded_amount'] > 0 ? $this->order_reference['refunded_amount'] : 0;
				$amount_to_be_refunded    = ( abs( $previous_refunded_amount - $excess_amount ) - $this->event_data['transaction']['refund']['amount'] );
				$amount_to_be_refunded    = abs( $amount_to_be_refunded );

				$order_total_refunded = abs( $order_total_refunded );

				if ( $order_total_refunded > 0 ) {
					$amount_to_be_refunded = $this->event_data['transaction']['refund']['amount'];
				}
			}

			// Create the refund.
			$refund = wc_create_refund(
				array(
					'order_id' => $this->wc_order->get_id(),
					'amount'   => sprintf( '%0.2f', ( $amount_to_be_refunded > 0 ? $amount_to_be_refunded : $this->event_data['transaction']['refund']['amount'] ) / 100 ),
					'reason'   => ! empty( $this->event_data['transaction']['reason'] ) ? $this->event_data['transaction']['reason'] : '',
				)
			);

			if ( is_wp_error( $refund ) ) {
				$this->notify_customer = false;
				/* translators: %1$s: date, %2$s: message*/
				$this->response['message'] .= PHP_EOL . sprintf( __( 'Payment refund failed for the order: %1$s due to: %2$s' ), $this->wc_order_id, $refund->get_error_message() );
				novalnet()->helper()->debug( $this->response['message'], $this->wc_order_id );
			}
		}
	}

	/**
	 * Handle chargeback
	 *
	 * @since 12.0.0
	 */
	public function handle_chargeback() {
		if ( wc_novalnet_check_isset( $this->order_reference, 'gateway_status', 'CONFIRMED' ) && ! empty( $this->event_data['transaction']['amount'] ) ) {
			if ( ! empty( $this->wcs_order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $this->wc_order_id, array( 'order_type' => 'any' ) );
				if ( ! empty( $subscriptions ) ) {
					foreach ( $subscriptions as $subscription ) {
						$is_shop_based_subs = apply_filters( 'novalnet_check_is_shop_scheduled_subscription', $subscription->get_id() );
						if ( $is_shop_based_subs ) {
							$subscription->update_status( 'on-hold' );
							if ( ! $this->wc_order->has_status( 'processing' ) ) {
								$this->wc_order->update_status( 'processing' );
							}
						}
					}
				}
			}
			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: tid  */
			$this->response['message'] = sprintf( __( 'Chargeback executed successfully for the TID: %1$s amount: %2$s on %3$s. The subsequent TID: %4$s.', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_shop_amount_format( $this->event_data['transaction']['amount'] ), wc_novalnet_formatted_date(), $this->event_tid );
		}
	}

	/**
	 * Handle transaction update
	 *
	 * @since 12.0.0
	 */
	public function handle_transaction_update() {
		$this->update_data['table']  = 'novalnet_transaction_detail';
		$this->update_data['update'] = array(
			'gateway_status' => $this->event_data['transaction']['status'],
		);
		if ( in_array( $this->event_data['transaction']['status'], array( 'PENDING', 'ON_HOLD', 'CONFIRMED', 'DEACTIVATED' ), true ) ) {
			if ( 'DEACTIVATED' === $this->event_data['transaction']['status'] ) {
				$this->notify_customer = true;

				/* translators: %s: Date */
				$this->response['message'] = sprintf( __( 'The transaction has been cancelled on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );

				$transaction_comments = novalnet()->helper()->prepare_payment_comments( $this->event_data );

				$order_status = 'wc-cancelled';
			} else {
				if ( in_array( $this->order_reference['gateway_status'], array( 'PENDING', 'ON_HOLD' ), true ) ) {
					if ( 'ON_HOLD' === $this->event_data['transaction']['status'] ) {
						$this->notify_customer = true;
						if ( empty( $this->event_data['transaction']['bank_details'] ) && ! empty( $this->order_reference['additional_info'] ) ) {
							$this->event_data['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
						}
						$order_status = 'wc-on-hold';
					} elseif ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
						$this->notify_customer = true;

						if ( empty( $this->event_data['transaction']['bank_details'] ) && ! empty( $this->order_reference['additional_info'] ) ) {
							$this->event_data['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
						}
						if ( novalnet()->get_supports( 'instalment', $this->wc_order->get_payment_method() ) ) {

							if ( ! empty( $this->order_reference['additional_info'] ) ) {
								$this->order_reference['additional_info'] = wc_novalnet_serialize_data( array_merge( wc_novalnet_unserialize_data( $this->order_reference['additional_info'] ), wc_novalnet_unserialize_data( apply_filters( 'novalnet_store_instalment_data', $this->event_data ) ) ) );
							} else {
								$this->order_reference['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $this->event_data );
							}
							novalnet()->db()->update(
								array(
									'additional_info' => $this->order_reference['additional_info'],
								),
								array(
									'order_no' => $this->wc_order->get_id(),
								)
							);

							WC()->mailer();
							do_action( 'novalnet_send_instalment_notification_to_customer', $this->wc_order->get_id(), $this->wc_order );
						}
						$payment_settings = WC_Novalnet_Configuration::get_payment_settings( $this->wc_order->get_payment_method() );
						$order_status     = $payment_settings['order_success_status'];
						if ( $this->wc_order->get_date_paid() == null ) {
							$this->wc_order->payment_complete( $this->event_data['transaction']['tid'] );
							$this->wc_order->set_date_paid( time() );
						}
						$this->update_data['update']['callback_amount'] = (int) $this->order_reference['amount'];
					}

					// Reform the transaction comments.
					if ( in_array( $this->event_data['transaction']['payment_type'], array( 'INVOICE', 'PREPAYMENT', 'GUARANTEED_INVOICE', 'INSTALMENT_INVOICE' ), true ) ) {

						if ( empty( $this->event_data['transaction']['bank_details'] ) ) {
							$this->event_data['transaction']['bank_details'] = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
						}
					}
					if ( 'CASHPAYMENT' === $this->event_data['transaction']['payment_type'] ) {
						$this->event_data['transaction']['nearest_stores'] = wc_novalnet_unserialize_data( $this->order_reference['additional_info'] );
					}

					$transaction_comments = novalnet()->helper()->prepare_payment_comments( $this->event_data );
					if ( (int) $this->event_data['transaction']['amount'] !== (int) $this->order_reference['amount'] && ! novalnet()->get_supports( 'instalment', $this->wc_order->get_payment_method() ) ) {
						$this->update_data['update']['amount'] = $this->event_data['transaction']['amount'];
						if ( (int) $this->event_data['transaction']['amount'] < (int) $this->order_reference['amount'] ) {
							$refund_amount   = (int) ( ( $this->order_reference['amount'] - $this->order_reference['refunded_amount'] ) - $this->event_data['transaction']['amount'] );
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
								$this->response['message'] = sprintf( __( 'Payment refund failed for the order: %1$s due to: %2$s' ), $this->wc_order->get_id(), $refund->get_error_message() );
								novalnet()->helper()->debug( $this->response['message'], $this->wc_order_id );
							} else {
								$this->update_data['refunded_amount'] = (int) $this->order_reference['refunded_amount'] + $refund_amount;
							}
						} else {
							$fee_in_smaller_unit = $this->event_data['transaction']['amount'] - $this->order_reference['amount'];
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

					$amount = $this->event_data['transaction']['amount'];
					if ( ! empty( $this->event_data['instalment']['cycle_amount'] ) ) {
						$amount = $this->event_data['instalment']['cycle_amount'];
					}

					/* translators: %1$s: tid, %2$s: amount*/
					$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s.', 'woocommerce-novalnet-gateway' ), $this->event_tid, wc_novalnet_shop_amount_format( $amount ) ) );
					if ( isset( $this->event_data['transaction']['update_type'] ) ) {
						if ( in_array( $this->event_data['transaction']['update_type'], array( 'AMOUNT', 'AMOUNT_DUE_DATE', 'DUE_DATE' ), true ) ) {
							$this->notify_customer = true;
							if ( 'DUE_DATE' === $this->event_data['transaction']['update_type'] ) {
								/* translators: %1$s: tid, %2$s: due date*/
								$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with due date %2$s.', 'woocommerce-novalnet-gateway' ), $this->event_tid, wc_novalnet_formatted_date( $this->event_data['transaction']['due_date'] ) ) );
							} elseif ( 'AMOUNT_DUE_DATE' === $this->event_data['transaction']['update_type'] ) {
								/* translators: %1$s: tid, %2$s: amount, %3$s: due date */
								$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s and due date %3$s.', 'woocommerce-novalnet-gateway' ), $this->event_tid, wc_novalnet_shop_amount_format( $amount ), wc_novalnet_formatted_date( $this->event_data['transaction']['due_date'] ) ) );
							}
						} elseif ( 'STATUS' === $this->event_data['transaction']['update_type'] ) {
							if ( 'ON_HOLD' === $this->event_data['transaction']['status'] ) {
								/* translators: %1$s: tid, %2$s: date*/
								$this->response['message'] = wc_novalnet_format_text( sprintf( __( 'The transaction status has been changed from pending to on-hold for the TID: %1$s on %2$s.', 'woocommerce-novalnet-gateway' ), $this->parent_tid, wc_novalnet_formatted_date() ) );
							} elseif ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
								$this->notify_customer = true;
								/* translators: %1$s: tid, %2$s: amount, %3$s: due date */
								$this->response['message'] = sprintf( __( 'Transaction updated successfully for the TID: %1$s with the amount %2$s on %3$s', 'woocommerce-novalnet-gateway' ), $this->event_tid, wc_novalnet_shop_amount_format( $amount ), wc_novalnet_formatted_date() );
							} elseif ( 'DEACTIVATED' === $this->event_data['transaction']['status'] ) {
								$this->notify_customer = true;
								/* translators: %1$s: date*/
								$this->response['message'] = sprintf( __( 'The transaction has been cancelled on %1$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );
							}
						}
					}
				}
			}

			if ( ! empty( $transaction_comments ) ) {
				$customer_given_note = novalnet()->helper()->novalnet_get_wc_order_meta( $this->wc_order, '_nn_customer_given_note' );
				// Update order comments.
				novalnet()->helper()->update_comments( $this->wc_order, $transaction_comments, 'transaction_info', false, true, $customer_given_note );
			}
			// Update status or set customer note will save the order.
			novalnet()->helper()->novalnet_update_wc_order_meta( $this->wc_order, '_novalnet_gateway_status', $this->event_data['transaction']['status'], ! ( ! empty( $order_status ) || ! empty( $transaction_comments ) ) );

			if ( ! empty( $order_status ) ) {
				$this->wc_order->update_status( $order_status );
			}

			// Trigger the Admin New order mail.
			WC()->mailer()->get_emails()['WC_Email_New_Order']->trigger( $this->wc_order->get_id(), $this->wc_order );

			if ( ! empty( $transaction_comments ) ) { // Update customer note again to remove html tags.
				if ( ! empty( $customer_given_note ) ) {
					$transaction_comments = $customer_given_note . PHP_EOL . $transaction_comments;
				}
				$this->wc_order->set_customer_note( $transaction_comments );
				$this->wc_order->save();
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
		// Your payment access key value.
		$this->payment_access_key = WC_Novalnet_Configuration::get_global_settings( 'key_password' );

		// Validate request parameters.
		foreach ( $this->mandatory as $category => $parameters ) {
			if ( empty( $this->event_data[ $category ] ) ) {

				// Could be a possible manipulation in the notification data.
				$this->display_message( array( 'message' => "Required parameter category($category) not received" ) );
			} elseif ( ! empty( $parameters ) ) {
				foreach ( $parameters as $parameter ) {
					if ( empty( $this->event_data[ $category ][ $parameter ] ) ) {
						// Could be a possible manipulation in the notification data.
						$this->display_message( array( 'message' => "Required parameter($parameter) in the category($category) not received" ) );
					} elseif ( in_array( $parameter, array( 'tid', 'parent_tid' ), true ) && ! preg_match( '/^\d{17}$/', $this->event_data[ $category ][ $parameter ] ) ) {
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
		$logentry     = array(
			'tid'           => $this->event_data['event']['tid'],
			'event_type'    => $this->event_data['event']['type'],
			'result_status' => $this->event_data['result']['status'],
		);
		$token_string = $this->event_data['event']['tid'] . $this->event_data['event']['type'] . $this->event_data['result']['status'];

		if ( isset( $this->event_data['transaction']['amount'] ) ) {
			$token_string      .= $this->event_data['transaction']['amount'];
			$logentry['amount'] = $this->event_data['transaction']['amount'];
		}
		if ( isset( $this->event_data['transaction']['currency'] ) ) {
			$token_string        .= $this->event_data['transaction']['currency'];
			$logentry['currency'] = $this->event_data['transaction']['currency'];
		}
		if ( ! empty( $this->payment_access_key ) ) {
			$token_string .= strrev( $this->payment_access_key );
		}

		$generated_checksum = hash( 'sha256', $token_string );

		// Generated checksum log entry.
		$logentry['generated_checksum'] = $generated_checksum;
		$json_data                      = wc_novalnet_serialize_data( $logentry );
		$novalnet_log                   = wc_novalnet_logger();
		$novalnet_log->add( 'woocommerce-novalnet-gateway', 'Callback Generated Checksum: ' . $json_data );

		if ( $generated_checksum !== $this->event_data['event']['checksum'] ) {
			$this->display_message( array( 'message' => 'While notifying some data has been changed. The hash check failed' ) );
		}

		if ( ! empty( $this->event_data['custom']['shop_invoked'] ) ) {
			$this->display_message( array( 'message' => 'Process already handled in the shop.' ) );
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
			$request_received_ip = $this->get_ip_address( $novalnet_host_ip );
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
	 * Get the valid IP address.
	 *
	 * @param ADDR $novalnet_host_ip Novalnet Host IP.
	 * @return ADDR|array
	 */
	public function get_ip_address( $novalnet_host_ip ) {

		$ip_keys = array(
			'HTTP_X_FORWARDED_HOST',
			'HTTP_CLIENT_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

	$invalidIp = '';
	foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				if ( in_array( $key, array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST' ), true ) ) {
					$forwarded_ip = ! empty( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) ? explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) : array();
					if (in_array($novalnet_host_ip, $forwarded_ip, true)) {
						return $novalnet_host_ip;
					}
				}
				$forwarded_ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				if ( $forwarded_ip === $novalnet_host_ip) {
					return $forwarded_ip;
				} elseif(!empty($forwarded_ip)){
					$invalidIp = $forwarded_ip;
				}
			}
		}
		return $invalidIp;
	}

	/**
	 * Get order reference.
	 *
	 * @return void
	 */
	public function get_order_reference() {
		if ( ! empty( $this->event_data['transaction']['order_no'] ) || ! empty( $this->parent_tid ) ) {
			if ( ! empty( $this->event_data['transaction']['order_no'] ) ) {
				$this->wc_order_id = novalnet()->helper()->get_post_id( $this->event_data['transaction']['order_no'] );
			} elseif ( ! empty( $this->event_data['subscription']['order_no'] ) ) {
				$this->wc_order_id = novalnet()->helper()->get_post_id( $this->event_data['subscription']['order_no'] );
			}
			$this->order_reference = novalnet()->db()->get_transaction_details( $this->wc_order_id, $this->parent_tid );
		}

		// Assign payment type based on the order for subscription.
		if ( class_exists( 'WC_Subscription' ) ) {

			if ( ! empty( $this->parent_tid ) ) {
				$this->subs_order_reference = novalnet()->db()->get_subscription_details( $this->parent_tid );
			}

			if ( empty( $this->order_reference['order_no'] ) && ! empty( $this->subs_order_reference ) ) {
				$this->order_reference = novalnet()->db()->get_transaction_details( '', $this->subs_order_reference['tid'] );
			}

			if ( empty( $this->subs_order_reference ) && ! empty( $this->event_data['subscription']['tid'] ) ) {
				$this->subs_order_reference = novalnet()->db()->get_subscription_details( $this->event_data['subscription']['tid'] );
			}

			if ( ! empty( $this->order_reference['order_no'] ) ) {
				$this->wcs_order_id = apply_filters( 'novalnet_get_subscription_id', $this->order_reference['order_no'] );
				if ( 'shop_subscription' === novalnet()->helper()->novalnet_get_wc_order_type( $this->wcs_order_id ) ) {
					$this->wcs_order = wcs_get_subscription( $this->wcs_order_id );
					if ( empty( $this->wcs_order ) || ! is_object( $this->wcs_order ) ) {
						/* translators: %d refers to the subscription order ID */
						$message = sprintf( __( 'Subscription order reference not found in the shop for the subscription order: %d', 'novalnet' ), $this->wcs_order_id );
						$this->display_message( array( 'message' => $message ) );
					}
					$this->order_reference['payment_type'] = $this->wcs_order->get_payment_method();
				}
			}
		}
		if ( empty( $this->order_reference ) ) {
			if ( 'ONLINE_TRANSFER_CREDIT' === $this->event_data['transaction']['payment_type'] ) {
				if ( ! empty( $this->parent_tid ) ) {
					$this->wc_order_id = novalnet()->helper()->get_post_id( $this->event_data['transaction']['order_no'] );
				}
				$this->order_reference['order_no'] = $this->wc_order_id;
				$transaction_tid                   = $this->event_data['transaction']['tid'];
				// Update the transaction TID for updating the initial payment.
				$this->event_data['transaction']['tid'] = $this->parent_tid;
				$this->update_initial_payment( false );
				// Reassign the transaction TID after the initial payment is updated.
				$this->event_data['transaction']['tid'] = $transaction_tid;
				$this->order_reference                  = novalnet()->db()->get_transaction_details( $this->wc_order_id, $this->parent_tid );
			} elseif ( 'PAYMENT' === $this->event_data['event']['type'] ) {
				$this->order_reference['order_no'] = $this->wc_order_id;
				$this->update_initial_payment( true );
			} elseif ( 'CREDIT' == $this->event_data['event']['type'] && ! empty( $this->subs_order_reference ) ) {
				$this->order_reference = novalnet()->db()->get_transaction_details( $this->event_data['transaction']['order_no'], $this->parent_tid );
				if ( empty( $this->order_reference ) ) {
					$wsorder     = wc_get_order( $this->event_data['transaction']['order_no'] );
					$insert_data = array(
						'order_no'        => $this->event_data['transaction']['order_no'],
						'tid'             => $this->parent_tid,
						'currency'        => get_woocommerce_currency(),
						'gateway_status'  => $this->event_data['transaction']['status'],
						'payment_type'    => ( is_object( $wsorder ) && $wsorder->get_payment_method() ) ? $wsorder->get_payment_method() : null,
						'amount'          => $this->event_data['transaction']['amount'],
						'subs_id'         => $this->event_data['subscription']['subs_id'],
						'callback_amount' => $this->event_data['transaction']['amount'],
					);
					novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );
					novalnet()->helper()->novalnet_update_wc_order_meta( $wsorder, '_novalnet_order_number', $wsorder->get_order_number(), true );
					novalnet()->helper()->novalnet_update_wc_order_meta( $wsorder, 'nn_credit_tid', $this->event_data['transaction']['tid'], true );
					$this->order_reference = novalnet()->db()->get_transaction_details( $this->event_data['transaction']['order_no'], $this->parent_tid );
				}
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
		if ( ! empty( $communication_failure ) ) {
			// Get the order no by using the cancelled order tid.
			$order_id_by_meta = novalnet()->db()->get_post_id_by_meta_data( $this->parent_tid );
			if ( ! empty( $order_id_by_meta ) ) {
				$this->order_reference['order_no'] = $order_id_by_meta;
			}
		}

		if ( ! empty( $this->order_reference['order_no'] ) ) {
			$wc_order        = wc_get_order( $this->order_reference['order_no'] );
			$payment_gateway = wc_get_payment_gateway_by_order( $wc_order );

			if ( is_object( $wc_order ) && $payment_gateway !== false ) {
				if ( method_exists( $payment_gateway, 'check_transaction_status' ) ) {
					$comments = $payment_gateway->check_transaction_status( $this->event_data, $wc_order, $communication_failure );
				} else {
					$this->display_message( array( 'message' => 'Payment not found in the order' ) );
				}
			} else {
				$this->display_message( array( 'message' => 'Order reference not found in the shop' ) );
			}
		} else {
			$this->display_message( array( 'message' => 'Initial payment update failed due to missing order number.' ) );
		}
		return $comments;
	}

	/**
	 * Update recurring amount after coupon removed.
	 */
	public function update_recurring_order_amount() {
		$tid        = novalnet()->helper()->get_novalnet_subscription_tid( $this->wcs_order->get_parent_id(), $this->wcs_order->get_id() );
		$parameters = array(
			'subscription' => array(
				'amount' => wc_novalnet_formatted_amount( $this->wcs_order->get_total() ),
				'tid'    => $tid,
			),
			'custom'       => array(
				'lang'         => wc_novalnet_shop_language(),
				'shop_invoked' => 1,
			),
		);
		novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( 'subscription_update' ), array( 'post_id' => $this->wcs_order->get_id() ) );
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
	 * @param array $comments        Formed comments.
	 */
	public function send_notification_mail( $comments ) {

		$mail_subject = 'Novalnet Callback Script Access Report - WooCommerce';
		if ( is_object( $this->wc_order ) && $this->wc_order instanceof WC_Order ) {
			/* translators: %1$s: order_id*/
			$mail_subject .= wc_novalnet_format_text( sprintf( __( ' - [Order #%1$s]', 'woocommerce-novalnet-gateway' ), $this->wc_order->get_order_number() ) );
		}

		wc_novalnet_send_mail( WC_Novalnet_Configuration::get_global_settings( 'callback_emailtoaddr' ), $mail_subject, $comments['message'] );
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
			'gateway_status' => $this->event_data['transaction']['status'],
			'event_tid'      => $this->event_tid,
			'parent_tid'     => $this->parent_tid,
			'order_no'       => $post_id,
		);

		if ( isset( $this->event_data['transaction']['payment_type'] ) ) {
			$data['payment_type'] = $this->event_data['transaction']['payment_type'];
		}
		if ( isset( $this->event_data['transaction']['amount'] ) ) {
			$data['amount'] = $this->event_data['transaction']['amount'];
		}
		novalnet()->db()->insert(
			$data,
			'novalnet_webhook_history'
		);
	}
}

new WC_Novalnet_Webhook();
