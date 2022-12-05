<?php
/**
 * Handling Novalnet subscription functions.
 *
 * @class    WC_Novalnet_Subscription
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Subscription Class.
 */
class WC_Novalnet_Subscription {

	/**
	 * The single instance of the class.
	 *
	 * @var   WC_Novalnet_Subscription The single instance of the class
	 * @since 12.0.0
	 */
	protected static $instance = null;

	/**
	 * Main WC_Novalnet_Subscription Instance.
	 *
	 * Ensures only one instance of WC_Novalnet_Subscription is loaded or can be loaded.
	 *
	 * @since  12.0.0
	 * @static
	 *
	 * @return WC_Novalnet_Subscription Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WC_Novalnet_Subscription Constructor.
	 */
	public function __construct() {

		// Subscription script.
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );

		add_filter( 'novalnet_cart_contains_subscription', array( $this, 'cart_contains_subscription' ) );

		add_filter( 'novalnet_check_is_shop_scheduled_subscription_enabled', array( $this, 'is_shop_based_subs_enabled' ), 10, 1 );

		add_filter( 'novalnet_check_is_shop_scheduled_subscription', array( $this, 'is_shop_based_subs' ), 10, 1 );

		add_filter( 'novalnet_check_is_subscription', array( $this, 'is_subscription' ), 10, 1 );

		// Get return URL for subscription change payment method.
		add_action( 'novalnet_return_url', array( &$this, 'get_subscription_change_payment_return_url' ) );

		// Get subscription success URL.
		add_action( 'novalnet_subscription_change_payment_method_success_url', array( &$this, 'get_subscription_success_url' ), 10, 2 );

		// Process back-end change payment method.
		add_filter( 'woocommerce_subscription_validate_payment_meta', array( &$this, 'handle_admin_payment_process' ), 11, 3 );

		// Return subscription supports.
		add_filter( 'novalnet_subscription_supports', array( $this, 'get_subscription_supports' ), 10, 2 );

		// Create renewal order.
		add_filter( 'novalnet_create_renewal_order', array( $this, 'create_renewal_order' ) );

		// Get subscription length.
		add_filter( 'novalnet_get_order_subscription_length', array( $this, 'get_order_subscription_length' ) );

		// Form subscription parameters.
		add_filter( 'novalnet_generate_subscription_parameters', array( $this, 'generate_subscription_parameters' ), 10, 3 );

		// Get subscription details.
		add_filter( 'novalnet_get_subscription_id', array( $this, 'get_subscription_id' ) );

		// Shows back-end change payment method form.
		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_novalnet_payment_meta_details' ), 10, 2 );

		// Customize back-end subscription cancel URL.
		add_filter( 'woocommerce_subscription_list_table_actions', array( $this, 'customize_admin_subscription_process' ), 9, 2 );

		// Process subscription action.
		add_filter( 'woocommerce_can_subscription_be_updated_to_on-hold', array( $this, 'suspend_subscription_process' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_active', array( $this, 'reactivate_subscription_process' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_pending-cancel', array( $this, 'cancel_subscription_process' ), 10, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_cancelled', array( $this, 'cancel_subscription_process' ), 10, 2 );

		// Process next payment date change.
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'update_next_payment_date' ) );

		// Restrict subscription option.
		add_filter( 'wcs_view_subscription_actions', array( $this, 'customize_myaccount_subscription_process' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'maybe_restrict_edit_address_endpoint' ) );

		// Process recurring amount change.
		add_action( 'woocommerce_saved_order_items', array( $this, 'perform_subscription_recurring_amount_update' ), 10, 2 );

		add_filter( 'wp_ajax_novalnet_wc_order_recalculate_success', array( $this, 'novalnet_wcs_order_recalculate_success' ) );

		add_action( 'novalnet_handle_subscription_post_process', array( $this, 'perform_subscription_post_process' ), 10, 4 );

		add_action( 'novalnet_update_recurring_payment', array( $this, 'update_recurring_payment' ), 10, 4 );

		// Load Iframe in shop admin.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( &$this, 'novalnet_subscription_add_iframe' ) );

		// Action to unset postmeta.
		add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'unset_post_meta' ) );
		add_action( 'unable_to_suspend_subscription', array( $this, 'unset_post_meta' ) );
		add_action( 'woocommerce_subscription_status_active', array( $this, 'unset_post_meta' ) );
		add_action( 'unable_to_activate_subscription', array( $this, 'unset_post_meta' ) );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'unset_post_meta' ) );
		add_action( 'unable_to_cancel_subscription', array( $this, 'unset_post_meta' ) );
		add_action( 'admin_init', array( $this, 'unset_post_meta' ) );

		// Stop gateway based subscription.
		add_filter( 'woocommerce_subscription_payment_gateway_supports', array( $this, 'stop_gateway_based_subscription' ), 1, 3 );

		// Set_flag_for_shopbased_subs.
		add_filter( 'novalnet_set_shopbased_subs_flag', array( $this, 'set_flag_for_shopbased_subs' ), 10, 3 );
	}

	/**
	 * Stop gateway based subscription
	 *
	 * @since 12.0.0
	 * @param boolean  $payment_gateway_supports  The subscription supports.
	 * @param array    $payment_gateway_feature   The payment gateway feature.
	 * @param WC_Order $subscription              The order object.
	 *
	 * @return array
	 */
	public function stop_gateway_based_subscription( $payment_gateway_supports, $payment_gateway_feature, $subscription ) {
		$subs_id = $subscription->get_id();
		if ( WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) ) {
			$is_shop_based_subs = $this->is_shop_based_subs( $subs_id );

			if ( 'gateway_scheduled_payments' === $payment_gateway_feature ) {
				if ( $is_shop_based_subs ) {
					return false;
				} else {
					return true;
				}
			}
		}
		return $payment_gateway_supports;
	}


	/**
	 * Check subscription order shop based or server based.
	 *
	 * @since 12.5.0
	 * @param int $wcs_order_id Subscription order id.
	 */
	public function is_shop_based_subs( $wcs_order_id ) {
		$subscription = new WC_Subscription( $wcs_order_id );
		if ( WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) ) {
			$is_shop_scheduled = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $wcs_order_id, 'shop_based_subs' );
			$shop_based_subs   = get_post_meta( $wcs_order_id, 'novalnet_shopbased_subs', 1 );
			if ( 1 === (int) $is_shop_scheduled || ! empty( $shop_based_subs ) ) {
				return true;
			}
			return false;
		}
		return true;
	}

	/**
	 * Check shop subscription enabled or not
	 *
	 * @since 12.5.0
	 * @param boolean $enabled Check for shopbased subscription.
	 */
	public function is_shop_based_subs_enabled( $enabled = false ) {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'enable_subs' ) && 'yes' === WC_Novalnet_Configuration::get_global_settings( 'enable_shop_subs' ) ) {
				return true;
			}
			return false;
		}
	}

	/**
	 * Set_flag_for_shopbased_subs
	 *
	 * @since 12.5.0
	 * @param WC_Order $wc_order             The order object.
	 * @param boolean  $is_change_payment    Check for subscription.
	 *
	 * @return void
	 */
	public function set_flag_for_shopbased_subs( $wc_order, $is_change_payment = true ) {
		// Checks for Novalnet subscription.
		if ( $this->is_subscription( $wc_order ) && $this->is_shop_based_subs_enabled() ) {
			$wc_order_id   = $wc_order->get_id();
			$subscriptions = wcs_get_subscriptions_for_order( $wc_order_id );
			if ( ! empty( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$wcs_order_id = $subscription->get_id();
					if ( ! empty( $wcs_order_id ) ) {
						update_post_meta( $wcs_order_id, 'novalnet_shopbased_subs', 1 );
					}
				}
			}
		}
	}

	/**
	 * Unset postmeta.
	 *
	 * @since 12.0.0
	 */
	public function unset_post_meta() {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$post_id = '';
			if ( ! empty( novalnet()->request ['post_ID'] ) && wc_novalnet_check_isset( novalnet()->request, 'post_type', 'shop_subscription' ) ) {
				$post_id = novalnet()->request ['post_ID'];
			} elseif ( ! empty( novalnet()->request ['post'] ) && ! empty( novalnet()->request ['action'] ) ) {
				$post_id = novalnet()->request ['post'];
			} elseif ( ! empty( novalnet()->request ['subscription_id'] ) && ! empty( novalnet()->request ['change_subscription_to'] ) ) {
				$post_id = novalnet()->request ['subscription_id'];
			}
			delete_post_meta( $post_id, '_nn_subscription_updated' );
		}
	}

	/**
	 * Check cart has subscription product.
	 *
	 * @since 12.0.0
	 *
	 * return bool
	 */
	public function cart_contains_subscription() {
		return class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription();
	}

	/**
	 * Customize the my-account page to show
	 * execute novalnet subscription process.
	 *
	 * @since 12.0.0
	 * @param array           $actions      The action data.
	 * @param WC_Subscription $subscription The subscription object.
	 *
	 * @return array
	 */
	public function customize_myaccount_subscription_process( $actions, $subscription ) {
		if ( WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) ) {
			$is_shop_based_subs   = $this->is_shop_based_subs( $subscription->get_id() );
			$restricted_actions   = array();
			$subs_cancel_frontend = WC_Novalnet_Configuration::get_global_settings( 'usr_subcl' );
			if ( ! $is_shop_based_subs ) {
				$restricted_actions = array( 'suspend', 'reactivate' );
				if ( 'no' === $subs_cancel_frontend ) {
					$restricted_actions[] = 'cancel';
				} else {
					wp_enqueue_script( 'woocommerce-novalnet-gateway-subscription-script', novalnet()->plugin_url . '/assets/js/novalnet-subscription.js', array( 'jquery' ), NOVALNET_VERSION, true );
					wp_localize_script(
						'woocommerce-novalnet-gateway-subscription-script',
						'wcs_novalnet_data',
						array(
							'reason_list'   => wc_novalnet_subscription_cancel_form(),
							'customer'      => 1,
							'error_message' => __( 'Please select reason', 'woocommerce-novalnet-gateway' ),
						)
					);
					if ( ! empty( $actions['cancel']['url'] ) && WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) && 'pending-cancel' !== $subscription->get_status() ) {
						$actions['cancel']['url'] .= '&novalnet-api=novalnet_subscription_cancel';
					}
				}
			} else {
				if ( 'no' === $subs_cancel_frontend ) {
					$restricted_actions[] = 'cancel';
				}
			}

			// Hide customer subscription cancel, reactivate, suspend options.
			foreach ( $restricted_actions as $value ) {
				if ( ! empty( $actions [ $value ] ) ) {
					unset( $actions [ $value ] );
				}
			}
		}
		return $actions;
	}

	/**
	 * Restrict subscription change address for guarantee payment.
	 *
	 * @since 12.5.0
	 */
	public function maybe_restrict_edit_address_endpoint() {
		if ( ! is_wc_endpoint_url() || 'edit-address' !== WC()->query->get_current_endpoint() || ! isset( $_GET['subscription'] ) ) {
			return;
		}
		$subscription = new WC_Subscription( $_GET['subscription'] );

		if ( ! empty( $subscription ) && in_array( $subscription->get_payment_method(), array( 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa' ), true ) ) {
			wc_add_notice( __( 'Changing of invoicing/delivery address is not allowed for this payment method', 'woocommerce-novalnet-gateway' ), 'error' );
			wp_safe_redirect( $subscription->get_view_order_url() );
			exit();
		}
	}

	/**
	 * Adding subscription script.
	 *
	 * @since 12.0.0
	 */
	public function admin_enqueue_scripts() {
		global $post_type;

		if ( isset( $post_type ) && 'shop_subscription' === $post_type ) {

			wp_enqueue_script( 'woocommerce-novalnet-gateway-subscription-script', novalnet()->plugin_url . '/assets/js/novalnet-subscription.js', array( 'jquery' ), time(), true );
			$data = array(
				'reason_list'                  => wc_novalnet_subscription_cancel_form(), // Display Subscription cancel reason.
				'change_payment_text'          => __( 'Change Payment', 'woocommerce-novalnet-gateway' ),
				'error_message'                => __( 'Please select reason', 'woocommerce-novalnet-gateway' ),
				'change_address_error_message' => __( 'Changing of invoicing/delivery address is not allowed for this payment method', 'woocommerce-novalnet-gateway' ),
			);

			if ( ! empty( novalnet()->request ['post'] ) ) {
				$wc_order           = new WC_Order( novalnet()->request ['post'] );
				$get_payment_method = $wc_order->get_payment_method();
				if ( WC_Novalnet_Validation::check_string( $get_payment_method ) && ! ( $this->is_shop_based_subs( $wc_order->get_id() ) ) ) {
					$data ['hide_unsupported_features'] = true;
				}
			}
			wp_localize_script( 'woocommerce-novalnet-gateway-subscription-script', 'wcs_novalnet_data', $data );
		}
	}

	/**
	 * Create / Initiate recurring order.
	 *
	 * @since 12.0.0
	 * @param WC_Subscription $subscription_order The subscription object.
	 *
	 * @return object
	 */
	public function create_renewal_order( $subscription_order ) {
		return wcs_create_renewal_order( $subscription_order );
	}


	/**
	 * Handle order recalculate event to update amount
	 *
	 * @since 12.5.0
	 */
	public function novalnet_wcs_order_recalculate_success() {
		if ( wc_novalnet_check_isset( novalnet()->request, 'action', 'novalnet_wc_order_recalculate_success' ) && ! empty( novalnet()->request ['novalnet_check_order_id'] ) ) {
			$wcs_order_id = novalnet()->request ['novalnet_check_order_id'];
			$this->perform_subscription_recurring_amount_update( $wcs_order_id );
		}
	}

	/**
	 * Update subscription recurring amount
	 *
	 * @since 12.0.0
	 * @param int $wcs_order_id The Subscription ID.
	 *
	 * @return void
	 */
	public function perform_subscription_recurring_amount_update( $wcs_order_id ) {
		if ( ( wc_novalnet_check_isset( novalnet()->request, 'action', 'woocommerce_save_order_items' ) || wc_novalnet_check_isset( novalnet()->request, 'action', 'novalnet_wc_order_recalculate_success' ) ) && 'shop_subscription' === get_post_type( $wcs_order_id ) ) {
			// Initiating order object.
			$wcs_order          = new WC_Order( $wcs_order_id );
			$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order_id );
			if ( WC_Novalnet_Validation::check_string( $wcs_order->get_payment_method() ) && ! $is_shop_based_subs ) {

				$update_amount = wc_novalnet_formatted_amount( $wcs_order->get_total() );

				if ( ! empty( $update_amount ) ) {
					$parameters = array(
						'subscription' => array(
							'amount' => $update_amount,
						),
					);
					$this->perform_action_api( $wcs_order, $parameters, 'subscription_update', false );
				}
			}
		}
	}

	/**
	 * Changing Next payment date process
	 *
	 * @since 12.0.0
	 * @param int $wcs_order_id The subscription id.
	 */
	public function update_next_payment_date( $wcs_order_id ) {
		global $post_type;

		if ( WC_Novalnet_Validation::is_subscription_plugin_available() ) {
			$wcs_order          = new WC_Subscription( $wcs_order_id );
			$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order_id );
			// Checks for Novalnet payment.
			if ( 'shop_subscription' === $post_type && WC_Novalnet_Validation::check_string( $wcs_order->get_payment_method() ) && ! $is_shop_based_subs && ! empty( novalnet()->request ['next_payment_timestamp_utc'] ) ) {
				$scheduled_date_time = date( 'Y-m-d', strtotime( get_post_meta( $wcs_order_id, '_schedule_next_payment', true ) ) );
				$scheduled_date      = date( 'Y-m-d', strtotime( $scheduled_date_time ) );

				// Requested date.
				$updated_date = date( 'Y-m-d', novalnet()->request ['next_payment_timestamp_utc'] );

				// Check for the previous date.
				if ( $updated_date !== $scheduled_date ) {

					// Check for the future date.
					if ( $updated_date < $scheduled_date ) {
						wcs_add_admin_notice( __( 'The date should be in future.', 'woocommerce-novalnet-gateway' ), 'error' );

						// Redirect to subscription page.
						wc_novalnet_safe_redirect(
							add_query_arg(
								array(
									'action' => 'edit',
									'post'   => $wcs_order_id,
								),
								admin_url( 'post.php' )
							)
						);
					}
					$date_difference = wcs_estimate_periods_between( strtotime( $scheduled_date_time ), strtotime( $updated_date ), 'day' );

					if ( ! empty( $date_difference ) ) {
						$parameters = array(
							'subscription' => array(
								'interval' => $date_difference . 'd',
							),
						);
						$this->perform_action_api( $wcs_order, $parameters, 'subscription_update' );
					}
				}
			}
		}
	}

	/**
	 * Cancel the subscription process.
	 *
	 * @since 12.0.0
	 * @param boolean         $can_update For process cancel action.
	 * @param WC_Subscription $wcs_order  The Subscription object.
	 *
	 * @return boolean
	 */
	public function cancel_subscription_process( $can_update, $wcs_order ) {

		$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order->get_id() );

		// Check Novalnet payment.
		if ( WC_Novalnet_Validation::check_string( $wcs_order->get_payment_method() ) && ! $is_shop_based_subs && $can_update && ! WC_Novalnet_Validation::check_string( $wcs_order->get_status(), 'cancel' ) && ! get_post_meta( $wcs_order->get_id(), '_nn_subscription_updated', true ) && ( $this->check_subscription_status( 'cancel' ) || ! empty( novalnet()->request['novalnet_subscription_cancel_reason'] ) ) ) {

			// Get subscrition cancellation reason.
			$reason = wc_novalnet_subscription_cancel_list();

			// Check for cancel subscription reason.
			if ( ! empty( novalnet()->request ['novalnet_subscription_cancel_reason'] ) ) {
				$reason = $reason [ novalnet()->request ['novalnet_subscription_cancel_reason'] ];
			} else {
				$reason = 'other';
			}

			$parameters = array(
				'subscription' => array(
					'reason' => $reason,
				),
			);

			$this->perform_action_api( $wcs_order, $parameters, 'subscription_cancel' );

			// Set value to notify subscription updated.
			update_post_meta( $wcs_order->get_id(), '_nn_subscription_updated', true );
		}
		return $can_update;
	}

	/**
	 * Suspend the subscription process.
	 *
	 * @since 12.0.0
	 * @param boolean         $can_update For process suspend action.
	 * @param WC_Subscription $wcs_order  The subscription object.
	 *
	 * @return boolean
	 */
	public function suspend_subscription_process( $can_update, $wcs_order ) {
		$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order->get_id() );
		// Checks Novalnet payment.
		if ( WC_Novalnet_Validation::check_string( $wcs_order->get_payment_method() ) && ! $is_shop_based_subs && $can_update && ! get_post_meta( $wcs_order->get_id(), '_nn_subscription_updated', true ) && $this->check_subscription_status( 'on-hold', 'active' ) ) {

			$parameters = array();
			$this->perform_action_api( $wcs_order, $parameters, 'subscription_suspend' );

			// Set value to notify subscription updated.
			update_post_meta( $wcs_order->get_id(), '_nn_subscription_updated', true );
		}

		return $can_update;
	}

	/**
	 * Reactivate the subscription process.
	 *
	 * @since 12.0.0
	 * @param WC_Subscription $wcs_order    The subscription object.
	 * @param parameters      $parameters   The formed parameters.
	 * @param string          $action       The action name..
	 * @param int             $exception    The exception.
	 */
	public function perform_action_api( $wcs_order, $parameters, $action, $exception = true ) {

		$tid = novalnet()->db()->get_entry_by_order_id( $wcs_order->get_parent_id(), 'tid' );

		if ( ! empty( $tid ) ) {
			// Form common parameter tid and lang.
			$parameters['subscription']['tid']    = $tid;
			$parameters['custom']['lang']         = wc_novalnet_shop_language();
			$parameters['custom']['shop_invoked'] = 1;

			$server_response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( $action ), array( 'post_id' => $wcs_order->get_id() ) );

			// Handle SUCCESS status.
			if ( WC_Novalnet_Validation::is_success_status( $server_response ) ) {

				// Update recurring amount (if available).
				$update_data = array(
					'recurring_amount' => ! empty( $server_response['subscription']['amount'] ) ? $server_response['subscription']['amount'] : '',
				);

				// Handle subscription suspend.
				if ( 'subscription_suspend' === $action ) {
					$update_data['suspended_date'] = date( 'Y-m-d H:i:s' );
					/* translators: %s: date  */
					$comments = wc_novalnet_format_text( sprintf( __( 'This subscription transaction has been suspended on %s', 'woocommerce-novalnet-gateway' ), $update_data['suspended_date'] ) );

					// Handle subscription reactive.
				} elseif ( 'subscription_reactivate' === $action ) {
					$update_data['suspended_date'] = '';
					/* translators: %1$s: date, %2$s: amount, %3$s: charging date  */
					$comments = wc_novalnet_format_text( sprintf( __( 'Subscription has been reactivated for the TID: %1$s on %2$s. Next charging date : %3$s', 'woocommerce-novalnet-gateway' ), $server_response ['transaction']['tid'], wc_novalnet_formatted_date(), wc_novalnet_next_cycle_date( $server_response ['subscription'] ) ) );

					// Handle subscription cancel.
				} elseif ( 'subscription_cancel' === $action ) {
					$update_data['termination_at']     = date( 'Y-m-d H:i:s' );
					$update_data['termination_reason'] = $parameters['subscription']['reason'];

					/* translators: %s: reason  */
					$comments = wc_novalnet_format_text( sprintf( __( 'Subscription has been cancelled due to: %s', 'woocommerce-novalnet-gateway' ), $update_data['termination_reason'] ) );

				} else {
					/* translators: %1$s: amount, %2$s: charging date */
					$comments = wc_novalnet_format_text( sprintf( __( 'Subscription updated successfully. You will be charged %1$s on %2$s', 'woocommerce-novalnet-gateway' ), ( wc_novalnet_shop_amount_format( $server_response ['subscription'] ['amount'] ) ), wc_novalnet_next_cycle_date( $server_response ['subscription'] ) ) );
				}

				$next_payment_date = wc_novalnet_next_cycle_date( $server_response ['subscription'] );
				if ( ! empty( $next_payment_date ) ) {
					$update_data['next_payment_date'] = $next_payment_date;
					update_post_meta( $wcs_order->get_id(), '_schedule_next_payment', $next_payment_date );
				}

				novalnet()->db()->update(
					$update_data,
					array(
						'order_no' => $wcs_order->get_parent_id(),
					),
					'novalnet_subscription_details'
				);

				novalnet()->helper()->update_comments( $wcs_order, $comments );
				if ( function_exists( 'wcs_add_admin_notice' ) ) {
					wcs_add_admin_notice( $comments );
				}
				$wcs_order->save();
			} else {

				/* translators: %s: Message */
				$message = wc_novalnet_format_text( sprintf( __( 'Recent action failed due to: %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_response_text( $server_response ) ) );
				$this->subscription_error_process( $message, $exception );
			}
		} else {

			/* translators: %s: Message */
			$message = wc_novalnet_format_text( sprintf( __( 'Recent action failed due to: %s', 'woocommerce-novalnet-gateway' ), __( 'No Transaction ID found for this Order', 'woocommerce-novalnet-gateway' ) ) );
			novalnet()->helper()->update_comments( $wcs_order, $message );
			$this->subscription_error_process( $message, $exception );
		}
	}

	/**
	 * Reactivate the subscription process.
	 *
	 * @since 12.0.0
	 * @param boolean         $can_update   For process reactivate action.
	 * @param WC_Subscription $wcs_order The subscription object.
	 *
	 * @return boolean
	 */
	public function reactivate_subscription_process( $can_update, $wcs_order ) {
		$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order->get_id() );
		// Checks Novalnet payment.
		if ( ( $can_update && WC_Novalnet_Validation::check_string( $wcs_order->get_payment_method() ) && ( ! get_post_meta( $wcs_order->get_id(), '_nn_subscription_updated', true ) && $this->check_subscription_status( 'active', 'on-hold' ) ) || ( $this->check_subscription_status( 'active', 'cancelled' ) ) ) && ! $is_shop_based_subs ) {

			$parameters        = array();
			$next_payment_date = get_post_meta( $wcs_order->get_id(), '_schedule_next_payment', true );
			if ( empty( $next_payment_date ) && $wcs_order->has_status( 'pending-cancel' ) ) {
				$next_payment_date = $wcs_order->get_date( 'end' );
			}
			$previous_cycle           = date( 'Y-m-d', strtotime( $next_payment_date ) );
			$previous_cycle_timestamp = strtotime( $previous_cycle );
			$next_subs_cycle          = $previous_cycle;
			$current_date_timestamp   = strtotime( date( 'Y-m-d' ) );

			if ( $previous_cycle_timestamp <= $current_date_timestamp ) {

				while ( strtotime( $next_subs_cycle ) <= $current_date_timestamp ) {
					$next_subs_cycle = date( 'Y-m-d', strtotime( $next_subs_cycle . '+' . get_post_meta( $wcs_order->get_id(), '_billing_interval', true ) . ' ' . get_post_meta( $wcs_order->get_id(), '_billing_period', true ) ) );
				}

				if ( strtotime( $next_subs_cycle ) > $current_date_timestamp ) {
					// Calculate date difference.
					$difference = date_diff( date_create( $previous_cycle ), date_create( $next_subs_cycle ) );

					if ( $difference->days > 0 ) {
						$parameters = array(
							'subscription' => array(
								'interval' => $difference->days . 'd',
							),
						);
					}
				}
			}
			$this->perform_action_api( $wcs_order, $parameters, 'subscription_reactivate' );

			// Set value to notify subscription updated.
			update_post_meta( $wcs_order->get_id(), '_nn_subscription_updated', true );

			if ( $this->check_subscription_status( 'active', 'cancelled' ) ) {
				return true;
			}
		} elseif ( WC_Novalnet_Validation::check_string( $wcs_order->get_payment_method() ) && ! $is_shop_based_subs && $wcs_order->has_status( 'pending-cancel' ) ) {
			return true;
		}
		return $can_update;
	}

	/**
	 * Customizing admin subscription cancel link to
	 * show Novalnet cancel reasons.
	 *
	 * @since 12.0.0
	 * @param array           $actions      The action data.
	 * @param WC_Subscription $subscription The subscription object.
	 *
	 * @return array
	 */
	public function customize_admin_subscription_process( $actions, $subscription ) {

		$is_shop_based_subs = $this->is_shop_based_subs( $subscription->get_id() );

		// Checks for Novalnet payment to overwrite cancel URL.
		if ( WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) && ! $is_shop_based_subs && 'wc-pending-cancel' !== $subscription->get_status() ) {

			if ( ! empty( $actions['cancelled'] ) ) {
				$action_url           = explode( '?', $actions['cancelled'] );
				$actions['cancelled'] = $action_url['0'] . '?novalnet-api=novalnet_subscription_cancel&' . $action_url['1'];
			}

			if ( ! $subscription->get_date( 'next_payment' ) ) {
				unset( $actions['cancelled'], $actions['on-hold'] );
			}
		}

		return $actions;
	}

	/**
	 * Change payment method process in
	 * shop back-end.
	 *
	 * @since 12.0.0
	 * @param string $payment_type The payment type.
	 * @param array  $post_meta    The post meta data.
	 *
	 * @throws Exception For admin process.
	 */
	public function handle_admin_payment_process( $payment_type, $post_meta ) {

		// Checks for Novalnet payment.
		if ( wc_novalnet_check_payment_method_change( $payment_type ) ) {
			throw new Exception( __( 'Please accept the change of payment method by clicking on the checkbox', 'woocommerce-novalnet-gateway' ) );
		}

		$wcs_order   = new WC_Subscription( novalnet()->request ['post_ID'] );
		$wc_order_id = novalnet()->helper()->get_order_post_id( $wcs_order );
		$wc_order    = new WC_Order( $wc_order_id );

		// Request sent to process change payment method in Novalnet server.
		$recurring_payment_type = get_post_meta( $wcs_order->get_id(), '_payment_method', true );

		if ( ! empty( $post_meta['post_meta']['novalnet_payment']['value'] ) ) {
			$is_shop_based_subs = false;
			if ( ! empty( $wcs_order ) ) {
				$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order->get_id() );
			}

			if ( ! $is_shop_based_subs && ! empty( $recurring_payment_type ) && WC_Novalnet_Validation::check_string( $recurring_payment_type ) ) {
				$parameters = array(
					'customer'     => novalnet()->helper()->get_customer_data( $wcs_order ),
					'transaction'  => array(
						'payment_type' => novalnet()->get_payment_types( $payment_type ),
					),
					'subscription' => array(
						'tid' => novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'tid' ),
					),
				);
				$endpoint   = 'subscription_update';
			} else {

				$payment_gateways = WC()->payment_gateways()->payment_gateways();
				if ( ! empty( $payment_gateways[ $payment_type ] ) ) {
					$parameters = $payment_gateways[ $payment_type ]->generate_basic_parameters( $wc_order, false );
					$endpoint   = 'payment';
				}
				$parameters['transaction']['amount'] = 0;
			}
			if ( 'novalnet_sepa' === $payment_type ) {
				$data['novalnet_sepa_account_holder'] = $parameters ['customer'] ['first_name'] . ' ' . $parameters ['customer'] ['last_name'];
				$data['novalnet_sepa_iban']           = $post_meta ['post_meta'] ['novalnet_sepa_iban'] ['value'];
				if ( ! WC_Novalnet_Validation::validate_payment_input_field(
					$data,
					array(
						'novalnet_sepa_account_holder',
						'novalnet_sepa_iban',
					)
				) ) {
					$this->subscription_error_process( __( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ) );
				}

				$parameters ['transaction']['payment_data'] = array(
					'account_holder' => $data ['novalnet_sepa_account_holder'],
					'iban'           => $data ['novalnet_sepa_iban'],
				);
			} elseif ( 'novalnet_cc' === novalnet()->request ['_payment_method'] ) {
				if ( ! WC_Novalnet_Validation::validate_payment_input_field(
					novalnet()->request,
					array(
						'novalnet_cc_pan_hash',
						'novalnet_cc_unique_id',
					)
				) ) {
					$this->subscription_error_process( __( 'Your card details are invalid', 'woocommerce-novalnet-gateway' ) );
				}
				$parameters ['transaction']['payment_data'] = array(
					'pan_hash'  => novalnet()->request ['novalnet_cc_pan_hash'],
					'unique_id' => novalnet()->request ['novalnet_cc_unique_id'],
				);
			}

			$server_response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( $endpoint ), array( 'post_id' => $wc_order_id ) );
			if ( WC_Novalnet_Validation::is_success_status( $server_response ) ) {
				$this->admin_transaction_success( $server_response, $wc_order, $wc_order_id, $payment_type, $wcs_order, $recurring_payment_type );

				update_post_meta( novalnet()->request ['post_ID'], '_nn_version', NOVALNET_VERSION );
				if ( ! $shop_based_subs ) {
					update_post_meta( novalnet()->request ['post_ID'], '_nn_subscription_updated', true );
				}
			} else {
				// Throw exception error for admin change payment method.
				$this->subscription_error_process( wc_novalnet_response_text( $server_response ) );
			}
		}
	}

	/**
	 * Transaction success process for completing the order.
	 *
	 * @since 12.0.0
	 * @param array    $server_response        The server response data.
	 * @param WC_Order $wc_order               The order object.
	 * @param int      $wc_post_id             The Post ID.
	 * @param string   $payment_type           The payment type value.
	 * @param WC_Order $subscription_order     The subscription order object.
	 * @param string   $recurring_payment_type The recurring payment type.
	 *
	 * @return array|string
	 */
	public function admin_transaction_success( $server_response, $wc_order, $wc_post_id, $payment_type, $subscription_order, $recurring_payment_type ) {

		// Check for recurring payment type available in Novalnet table for the payment.
		if ( ! empty( $recurring_payment_type ) ) {

			// Update recurring payment process.
			return $this->update_recurring_payment( $server_response, $wc_post_id, $payment_type, $subscription_order );
		}

		$insert_data = array(
			'order_no'     => $wc_post_id,
			'tid'          => $server_response['transaction']['tid'],
			'currency'     => get_woocommerce_currency(),
			'payment_type' => $payment_type,
			'amount'       => wc_novalnet_formatted_amount( $wc_order->get_total() ),
			'subs_id'      => ! empty( $server_response ['subscription'] ['subs_id'] ) ? $server_response ['subscription'] ['subs_id'] : '',
		);

		$insert_data['callback_amount'] = $insert_data['amount'];

		if ( ! empty( $server_response ['transaction']['status'] ) ) {
			update_post_meta( $wc_post_id, '_novalnet_gateway_status', $server_response ['transaction']['status'] );
			$insert_data ['gateway_status'] = $server_response ['transaction']['status'];
			if ( 'PENDING' === $server_response['transaction']['status'] ) {
				$insert_data['callback_amount'] = '0';
			}
		}

		// Handle subscription process.
		$this->perform_subscription_post_process( $wc_post_id, $payment_type, $server_response, $wc_order );

		// Insert the transaction details.
		novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );

		// Update Novalnet version while processing the current post id.
		update_post_meta( $post_id, '_nn_version', NOVALNET_VERSION );

		/* translators: %s: Message  */
		return wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );
	}

	/**
	 * Add Credit Card iframe.
	 *
	 * @since 12.0.0
	 */
	public function novalnet_subscription_add_iframe() {
		global $post_type;

		// Check for subscription post.
		if ( 'shop_subscription' === $post_type ) {
			// Get payment settings.
			$settings = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_cc' );
			if ( wc_novalnet_check_isset( $settings, 'enabled', 'yes' ) ) {
				$data ['standard_label'] = $settings ['standard_label'];
				$data ['standard_input'] = $settings ['standard_input'];
				$data ['standard_css']   = $settings ['standard_css'];
				$data ['inline_form']    = (int) ( ! empty( $settings ['enable_iniline_form'] ) && 'yes' === $settings ['enable_iniline_form'] );
				$data ['client_key']     = WC_Novalnet_Configuration::get_global_settings( 'client_key' );
				$data ['test_mode']      = $settings ['test_mode'];
				$data ['lang']           = wc_novalnet_shop_language();
				$data ['amount']         = '0';
				$data ['currency']       = get_woocommerce_currency();
				$data ['admin']          = 'true';
				$data ['error_message']  = __( 'Card type not accepted, try using another card type', 'woocommererce-novalnet-gateway' );

				// Enqueue script.
				wp_enqueue_script( 'woocommerce-novalnet-gateway-admin-cc-script', novalnet()->plugin_url . '/assets/js/novalnet-cc.js', array( 'jquery' ), NOVALNET_VERSION, true );
				wp_localize_script( 'woocommerce-novalnet-gateway-admin-cc-script', 'wc_novalnet_cc_data', $data );
				?>
				<div>
					<div class="novalnet-cc-error" role="alert"></div>
					<div id="novalnet-admin-psd2-notification" style="display:inline-block"><?php esc_attr_e( 'More security with the new Payment Policy (PSD2) Info', 'woocommerce-novalnet-gateway' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'European card issuing banks often requires a password or some other form of authentication (EU Payment Services Directive "PSD2") for secure payment. If the payment is not successful, you can try again. If you have any further questions, please contact your bank.', 'woocommerce-novalnet-gateway' ); ?>"></span>
					</div>
						<iframe style="opacity:1 !important" frameBorder="0" scrolling="no" width="100%" id = "novalnet_cc_iframe"></iframe><input type="hidden" name="novalnet_cc_pan_hash" id="novalnet_cc_pan_hash"/><input type="hidden" name="novalnet_cc_unique_id" id="novalnet_cc_unique_id"/>
					<div class="clear"></div>
				</div>
				<?php
				wc_enqueue_js(
					"
                    wc_novalnet_cc.init();
                    jQuery( '.edit_address' ).on( 'click', function( evt ) {
                        var elem          = $( this ),
                        order_data_column = elem.closest( '.order_data_column' ),
                        edit_address      = order_data_column.find( 'div.edit_address' ),
                        is_billing        = Boolean( edit_address.find( 'input[name^=\"_billing_\"]' ).length );
	                    if ( is_billing && 'novalnet_cc' === jQuery( '#_payment_method option:selected' ).val() ) {
		                    jQuery( '#novalnet_cc_iframe' ).show();
		                    jQuery( '#novalnet-admin-psd2-notification' ).show();
	                    } else {
		                    jQuery( '#novalnet_cc_iframe' ).hide();
		                    jQuery( '#novalnet-admin-psd2-notification' ).hide()
	                    }
                    } );
                    jQuery( '#_payment_method' ).on( 'change', function() {
                        if ( jQuery( '#_payment_method' ).is(':visible') && 'novalnet_cc' === jQuery( '#_payment_method' ).val() ) {
                            jQuery( '#novalnet-admin-psd2-notification' ).show();
                            jQuery( '#novalnet_cc_iframe' ).show();
                        } else {
                            jQuery( '#novalnet-admin-psd2-notification' ).hide();
                            jQuery( '#novalnet_cc_iframe' ).hide();
                        }
                    }).change();
                "
				);
			}
		}
	}

	/**
	 * Change payment method Payment form fields / script.
	 *
	 * @since 12.0.0
	 * @param array $payment_meta The payment meta data.
	 *
	 * @return array
	 */
	public static function add_novalnet_payment_meta_details( $payment_meta ) {

		$payment_meta['novalnet_sepa']['post_meta']['novalnet_sepa_iban'] = array(
			'value'             => '',
			'label'             => __( 'IBAN', 'woocommerce-novalnet-gateway' ) . ' *',
			'custom_attributes' => array(
				'style' => 'text-transform: uppercase',
			),
		);
		foreach ( array(
			'novalnet_prepayment',
			'novalnet_invoice',
			'novalnet_sepa',
			'novalnet_paypal',
			'novalnet_cc',
		) as $payment_type ) {
			$payment_meta[ $payment_type ]['post_meta']['novalnet_payment_change'] = array(
				'label' => ' ',
				'value' => '',
			);
			$payment_meta[ $payment_type ]['post_meta']['novalnet_payment']        = array(
				'label' => ' ',
			);
		}

		return $payment_meta;
	}

	/**
	 * Check & generate subscription parameters
	 *
	 * @since 12.0.0
	 * @param array    $parameters           The payment parameters.
	 * @param WC_Order $wc_order             The order object.
	 * @param boolean  $is_change_payment    Check for subscription.
	 *
	 * @return array
	 */
	public function generate_subscription_parameters( $parameters, $wc_order, $is_change_payment = true ) {

		// Checks for Novalnet subscription.
		if ( ( $this->is_subscription( $wc_order ) || $is_change_payment ) && ! $this->is_shop_based_subs_enabled() ) {

			$wc_order_id = $wc_order->get_id();
			$switch_psp  = false;
			if ( $is_change_payment ) {

				if ( ! novalnet()->db()->get_entry_by_order_id( $wc_order->get_parent_id(), 'tid' ) ) {
					if ( wc_novalnet_check_session() ) {
						WC()->session->__unset( 'novalnet_change_payment_method' );
					}
					$is_change_payment = false;
					$switch_psp        = true;
				}
			}

			$wcs_order_id = $this->get_subscription_id( $wc_order_id );
			if ( ! empty( $wcs_order_id ) ) {
				$subscription_order = new WC_Subscription( $wcs_order_id );

				if ( WC_Novalnet_Validation::check_string( get_post_meta( $subscription_order->get_id(), '_payment_method', true ) ) && $is_change_payment ) {

					$parameters ['subscription']['tid'] = novalnet()->db()->get_entry_by_order_id( $wc_order->get_parent_id(), 'tid' );
					return $parameters;
				}

				$subscription_data = array(
					'interval' => $subscription_order->get_billing_interval(),
					'period'   => $subscription_order->get_billing_period(),
					'amount'   => wc_novalnet_formatted_amount( get_post_meta( $wcs_order_id, '_order_total', true ) ), // Converting the amount into cents.
				);

				if ( $switch_psp ) {
					$subscription_data ['free_length'] = '';
					$subscription_data ['free_period'] = '';
					$trial_period                      = false;
				} else {
					$subscription_data['free_length'] = wcs_estimate_periods_between( $subscription_order->get_time( 'date_created' ), $subscription_order->get_time( 'trial_end' ), $subscription_order->get_trial_period() );
					$subscription_data['free_period'] = $subscription_order->get_trial_period();

					// Calculate trial period.
					$trial_period = $this->calculate_subscription_period( $subscription_data ['free_length'], $subscription_data ['free_period'] );
				}

				if ( $subscription_order->get_date( 'next_payment' ) && 0 < $subscription_data['amount'] ) {

					// Calculate recurring period.
					$recurring_period = $this->calculate_subscription_period( $subscription_data ['interval'], $subscription_data['period'] );

					$this->set_subscription_data( $trial_period, $recurring_period, $wcs_order_id, $wc_order, $switch_psp, $subscription_data, $parameters );
				}
			}
		}
		return $parameters;
	}
	/**
	 * Set tariff period.
	 *
	 * @since 12.0.0

	 * @param string   $trial_period          The trial period.
	 * @param string   $recurring_period      The recurring period.
	 * @param string   $subscription_post_id  The subscription post ID.
	 * @param WC_Order $wc_order              The order object.
	 * @param WC_Order $switch_psp            The switch psp flag.
	 * @param array    $subscription_data     The subscription data.
	 * @param array    $parameters            The payment parameters.
	 */
	public function set_subscription_data( $trial_period, $recurring_period, $subscription_post_id, $wc_order, $switch_psp, $subscription_data, &$parameters ) {

		$parameters ['subscription']['interval'] = $recurring_period;

		$cart_total = wc_novalnet_formatted_amount( $wc_order->get_total() );
		if ( $subscription_data['amount'] !== $cart_total || ! empty( $trial_period ) ) {
			$parameters ['subscription']['trial_interval'] = ! empty( $trial_period ) ? $trial_period : $recurring_period;
			$parameters ['subscription']['trial_amount']   = $cart_total;
		}

		if ( ! empty( $switch_psp ) ) {
			$next_payment_date = get_post_meta( $subscription_post_id, '_schedule_next_payment', true );
			// Assign tariff period as days.
			if ( $next_payment_date ) {
				$difference = date_diff( date_create( date( 'Y-m-d' ) ), date_create( date( 'Y-m-d', strtotime( $next_payment_date ) ) ) );
				if ( $difference->days > 0 ) {
					$parameters ['subscription']['trial_interval'] = $difference->days . 'd';
					$parameters ['subscription']['trial_amount']   = '0';
				}
			}
		}
		$parameters ['transaction']['amount'] = $subscription_data['amount'];

		if ( empty( $wc_order->get_parent_id() ) && ! empty( novalnet()->request ['_order_total'] ) ) {
			$parameters ['transaction']['amount'] = wc_novalnet_formatted_amount( novalnet()->request ['_order_total'] );
		}

		$parameters ['merchant']['tariff'] = WC_Novalnet_Configuration::get_global_settings( 'subs_tariff_id' );
	}

	/**
	 * Checking for subscription active.
	 *
	 * @since 12.0.0
	 * @param WC_Order $wc_order          The order object.
	 *
	 * @return boolean
	 */
	public function is_subscription( $wc_order ) {

		return ( ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $wc_order ) ) || 'shop_subscription' === get_post_type( $wc_order->get_id() ) );
	}

	/**
	 * Renewal order count.
	 *
	 * @since 12.0.0
	 *
	 * @param object $subscription The subscription object.
	 *
	 * @return array
	 */
	public function get_subscription_success_url( $subscription ) {
		return array(
			'success_url' => $subscription->get_view_order_url(),
			'notice'      => __( 'Payment method updated successfully.', 'woocommerce-subscriptions' ),
		);
	}

	/**
	 * Calculate subscription length.
	 *
	 * @since 12.0.0
	 * @param WC_order $wc_order The order object.
	 *
	 * @return int
	 */
	public function get_order_subscription_length( $wc_order ) {
		$order_item_id = novalnet()->db()->get_order_item_id( $wc_order->get_id() );
		$variation_id  = wc_get_order_item_meta( $order_item_id, '_variation_id' );

		// Get Subscription length for variable product.
		if ( $variation_id ) {
			return get_post_meta( $variation_id, '_subscription_length', true );
		} else {

			// Get Subscription length for the product.
			$item_id               = $wc_order->get_items();
			$subscription_length   = get_post_meta( $item_id [ $order_item_id ] ['product_id'], '_subscription_length', true );
			$subscription_interval = get_post_meta( $item_id [ $order_item_id ] ['product_id'], '_subscription_period_interval', true );
			if ( $subscription_length && $subscription_interval ) {
				return $subscription_length / $subscription_interval;
			}
		}
		return '';
	}

	/**
	 * Get subscription change payment method URL
	 *
	 * @since 12.0.0
	 * @param string $return_url Default return URL.
	 *
	 * @return array
	 */
	public function get_subscription_change_payment_return_url( $return_url ) {
		if ( WC()->session->__isset( 'novalnet_change_payment_method' ) ) {
			$subscription = new WC_Order( WC()->session->novalnet_change_payment_method );
			$return_url   = $subscription->get_view_order_url();
		}
		return $return_url;
	}

	/**
	 * Subscription error process.
	 *
	 * @since 12.0.0
	 * @param string $message   The message value.
	 * @param string $exception The exception value.
	 *
	 * @throws Exception For subscription process.
	 */
	public function subscription_error_process( $message, $exception = true ) {
		if ( ! is_admin() ) {
			wc_add_notice( $message, 'error' );
			$view_subscription_url = wc_get_endpoint_url( 'view-subscription', novalnet()->request ['subscription_id'], wc_get_page_permalink( 'myaccount' ) );
			wp_safe_redirect( $view_subscription_url );
			exit;
		} elseif ( ! empty( $exception ) ) {
			throw new Exception( $message );
		}
	}

	/**
	 * Fetch subscription details.
	 *
	 * @since 12.0.0
	 * @param integer $post_id The post id.
	 *
	 * @return array
	 */
	public function get_subscription_id( $post_id ) {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$subscription = array_keys( wcs_get_subscriptions_for_order( $post_id ) );
			if ( ! empty( $subscription [0] ) ) {
				$post_id = $subscription [0];
			}
		}
		return $post_id;

	}

	/**
	 * Add supports to subscription.
	 *
	 * @since 12.0.0
	 * @param array  $supports     The supports data.
	 * @param string $payment_type The payment type value.
	 *
	 * @return array
	 */
	public function get_subscription_supports( $supports, $payment_type ) {

		if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'enable_subs' ) ) {
			$subs_payments = WC_Novalnet_Configuration::get_global_settings( 'subs_payments' );
			if ( in_array( $payment_type, $subs_payments, true ) ) {
				$supports [] = 'subscriptions';
				$supports [] = 'subscription_cancellation';
				$supports [] = 'subscription_suspension';
				$supports [] = 'subscription_reactivation';
				$supports [] = 'subscription_date_changes';
				$supports [] = 'subscription_amount_changes';

				if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'enable_shop_subs' ) ) {
					$supports [] = 'multiple_subscriptions';
				} else {
					$supports [] = 'gateway_scheduled_payments';
				}

				if ( ! in_array( $payment_type, array( 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa' ), true ) ) {
					$supports [] = 'subscription_payment_method_change_customer';
				}

				if ( ( ! in_array( $payment_type, array( 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa', 'novalnet_paypal', 'novalnet_applepay', 'novalnet_googlepay' ), true ) ) ) {
					$supports [] = 'subscription_payment_method_change_admin';
				}
			}
		}
		return $supports;
	}

	/**
	 * Check the status of the subscription
	 *
	 * @since 12.0.0
	 * @param string $update_status  Update status of the subscription.
	 * @param string $current_status Current status of the subscription.
	 *
	 * @return boolean
	 */
	public function check_subscription_status( $update_status, $current_status = '' ) {

		return ( wc_novalnet_check_isset( novalnet()->request, 'action', $update_status ) ) || ( wc_novalnet_check_isset( novalnet()->request, 'action2', $update_status ) ) || ( wc_novalnet_check_isset( novalnet()->request, 'post_type', 'shop_subscription' ) && ! empty( novalnet()->request ['order_status'] ) && WC_Novalnet_Validation::check_string( novalnet()->request ['order_status'], $update_status ) && ( empty( $current_status ) || ( ! empty( novalnet()->request ['order_status'] ) && WC_Novalnet_Validation::check_string( novalnet()->request ['post_status'], $current_status ) ) ) );
	}

	/**
	 * Handle subscription process
	 *
	 * @since  12.0.0
	 *
	 * @param int      $wc_order_id        The post ID value.
	 * @param string   $payment            The payment ID.
	 * @param array    $server_response    Response of the transaction.
	 * @param WC_Order $wc_order           The WC_Order object.
	 */
	public function perform_subscription_post_process( $wc_order_id, $payment, $server_response, $wc_order ) {

		if ( $this->is_subscription( $wc_order ) && empty( $server_response['event'] ['type'] ) && ! empty( WC()->session ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $wc_order_id );
			if ( ! empty( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$wcs_order_id = $subscription->get_id();

					$shop_based_subs = $this->is_shop_based_subs_enabled();
					if ( ! empty( $wcs_order_id ) ) {
						$shop_based_subs = get_post_meta( $wcs_order_id, 'novalnet_shopbased_subs', true );
					}

					$tid = $server_response ['transaction']['tid'];

					$subscription_details = array(
						'order_no'               => $wc_order_id,
						'subs_order_no'          => $wcs_order_id,
						'payment_type'           => $payment,
						'recurring_payment_type' => $payment,
						'recurring_amount'       => wc_novalnet_formatted_amount( get_post_meta( $wcs_order_id, '_order_total', true ) ),
						'tid'                    => $server_response ['transaction']['tid'],
						'signup_date'            => date( 'Y-m-d H:i:s' ),
						'subscription_length'    => apply_filters( 'novalnet_get_order_subscription_length', $subscription ),
					);

					if ( $shop_based_subs && empty( $server_response['subscription'] ) ) {

						if ( isset( $server_response['custom']['reference_tid'] ) && ! empty( $server_response['custom']['reference_tid'] ) ) {
							$subscription_details['tid'] = $server_response['custom']['reference_tid'];
						}

						$nn_txn_token = null;
						if ( in_array( $payment, array( 'novalnet_sepa', 'novalnet_cc', 'novalnet_paypal', 'novalnet_guaranteed_sepa', 'novalnet_applepay', 'novalnet_googlepay' ), true ) ) {
							if ( ! empty( $server_response['transaction']['payment_data']['token'] ) ) {
								$nn_txn_token = $server_response['transaction']['payment_data']['token'];
							} elseif ( ! empty( $server_response['custom']['reference_token'] ) ) {
								$nn_txn_token = $server_response['custom']['reference_token'];
							}
						}
						$subscription_details['nn_txn_token']    = $nn_txn_token;
						$subscription_details['shop_based_subs'] = 1;

						novalnet()->helper()->debug( "SHOP_SCHEDULED_SUBSCIPTION: Subs_ID : $wcs_order_id ( TID{$server_response ['transaction']['tid']} )", $wc_order_id, true );

					} else {

						$subscription_details['recurring_tid']     = $server_response ['subscription']['tid'];
						$subscription_details['shop_based_subs']   = 0;
						$subscription_details['subs_id']           = $server_response ['subscription']['subs_id'];
						$subscription_details['next_payment_date'] = wc_novalnet_next_cycle_date( $server_response['subscription'] );

					}

					// Insert the subscription details.
					novalnet()->db()->insert(
						$subscription_details,
						'novalnet_subscription_details'
					);
				}
			}
		}
	}

	/**
	 * Update the recurring payment.
	 *
	 * @since  12.0.0
	 *
	 * @param array           $server_response Response of the transaction.
	 * @param int             $wc_order_id     The post ID value.
	 * @param string          $payment_type    The payment ID.
	 * @param WC_Subscription $wcs_order       The subscription object.
	 */
	public function update_recurring_payment( $server_response, $wc_order_id, $payment_type, $wcs_order ) {
		$is_shop_based_subs = $this->is_shop_based_subs( $wcs_order->get_id() );
		$subs_tid           = novalnet()->db()->get_subs_data_by_order_id( $wcs_order->get_parent_id(), $wcs_order->get_id(), 'tid' );
		$subs_order_no      = novalnet()->db()->get_subs_data_by_order_id( $wcs_order->get_parent_id(), $wcs_order->get_id(), 'subs_order_no' );

		if ( ! empty( $subs_tid ) ) {

			$where_array = array(
				'order_no' => $wc_order_id,
			);

			if ( ! empty( $subs_order_no ) ) {
				$where_array['subs_order_no'] = $subs_order_no;
			}

			$update_data = array(
				'recurring_payment_type' => $payment_type,
			);

			if ( ! $is_shop_based_subs ) {
				/* translators: %s: Payment method */
				$order_note                   = PHP_EOL . wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_next_cycle_date( $server_response ['subscription'] ) ) );
				$update_data['recurring_tid'] = $server_response ['transaction']['tid'];
			} else {
				/* translators: %s: Payment method */
				$order_note    = PHP_EOL . wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );
				$recurring_tid = novalnet()->db()->get_subs_data_by_order_id( $wcs_order->get_parent_id(), $subscription_id, 'recurring_tid' );
				if ( ! empty( $recurring_tid ) ) {
					$update_data['recurring_tid'] = $server_response ['transaction']['tid'];
				} else {
					$update_data['tid'] = $server_response ['transaction']['tid'];
				}
			}

			if ( in_array( $payment_type, array( 'novalnet_sepa', 'novalnet_cc', 'novalnet_paypal', 'novalnet_guaranteed_sepa', 'novalnet_applepay', 'novalnet_googlepay' ), true ) ) {
				if ( ! empty( $server_response['transaction']['payment_data']['token'] ) ) {
					$update_data['nn_txn_token'] = $server_response['transaction']['payment_data']['token'];
				} elseif ( ! empty( $server_response['custom']['reference_token'] ) ) {
					$update_data['nn_txn_token'] = $server_response['custom']['reference_token'];
				}
			}

			// Update recurring payment details in Novalnet subscription details.
			novalnet()->db()->update( $update_data, $where_array, 'novalnet_subscription_details' );

		} elseif ( ! empty( $wcs_order->get_parent_id() ) && empty( $subs_tid ) ) {
			$insert_data = array(
				'order_no'     => $wcs_order->get_parent_id(),
				'tid'          => $server_response['transaction']['tid'],
				'currency'     => get_woocommerce_currency(),
				'payment_type' => $payment_type,
				'amount'       => wc_novalnet_formatted_amount( $server_response['transaction']['amount'] ),
				'subs_id'      => ! empty( $server_response ['subscription'] ['subs_id'] ) ? $server_response ['subscription'] ['subs_id'] : '',
			);

			$insert_data['callback_amount'] = $insert_data['amount'];

			if ( ! empty( $server_response ['transaction']['status'] ) ) {
				update_post_meta( $wc_post_id, '_novalnet_gateway_status', $server_response ['transaction']['status'] );
				$insert_data ['gateway_status'] = $server_response ['transaction']['status'];
				if ( 'PENDING' === $server_response['transaction']['status'] ) {
					$insert_data['callback_amount'] = '0';
				}
			}

			// Handle subscription process.
			$this->perform_subscription_post_process( $wcs_order->get_parent_id(), $payment_type, $server_response, $wcs_order );

			// Insert the transaction details.
			novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );

			// Update Novalnet version while processing the current post id.
			update_post_meta( $wcs_order->get_parent_id(), '_nn_version', NOVALNET_VERSION );
		}
		// Form order comments.
		$transaction_comments = novalnet()->helper()->prepare_payment_comments( $server_response );

		// Update order comments.
		novalnet()->helper()->update_comments( $wcs_order, $transaction_comments, true, 'transaction_info', false );

		novalnet()->helper()->update_comments( $wcs_order, $order_note, true, 'note', true );
	}

	/**
	 * Calculate subscription period.
	 *
	 * @since 12.0.0
	 * @param int    $interval The subscription interval value.
	 * @param string $period   The subscription period value.
	 *
	 * @return string
	 */
	public function calculate_subscription_period( $interval, $period ) {
		if ( $interval > 0 ) {
			$period = substr( $period, 0, 1 );
			if ( 'w' === $period ) {
				$period   = 'd';
				$interval = $interval * 7;
			}
			return $interval . $period;
		}
		return '';
	}
}

// Initiate WC_Novalnet_Subscription if subscription plugin available.
new WC_Novalnet_Subscription();
