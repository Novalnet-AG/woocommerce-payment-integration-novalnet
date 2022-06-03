<?php
/**
 * Novalnet Configuration Class
 *
 * @author   Novalnet
 * @category Admin
 * @package  woocommerce-novalnet-gateway/includes/admin/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * WC_Novalnet_Admin
 */
class WC_Novalnet_Admin extends WC_Settings_API {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Save Novalnet settings.
		add_action( 'woocommerce_settings_save_novalnet-settings', array( $this, 'save' ) );

		add_action( 'woocommerce_admin_field_novalnet_hidden', array( $this, 'form_novalnet_hidden' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( NN_PLUGIN_FILE ), array( $this, 'action_novalnet_links' ) );

		add_filter( 'wp_ajax_get_novalnet_vendor_details', array( $this, 'get_novalnet_vendor_details' ) );

		add_filter( 'wp_ajax_handle_webhook_configure', array( $this, 'handle_webhook_configure' ) );
		
		add_filter( 'wp_ajax_check_amount_admin_order', array( $this, 'check_amount_admin_order' ) );

		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_novalnet_settings_tab' ), 50 );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
		
		add_action( 'woocommerce_saved_order_items', array( $this, 'handle_amount_update' ) );
			
		add_action( 'woocommerce_register_post_type', array( $this, 'show_meta_box' ) );

		// Add dropdown to admin orders screen to filter based on payment type.
		add_action( 'restrict_manage_posts', array( $this, 'customize_payment_selection_filter_admin' ), 50 );

		// Add filter to queries on admin orders screen to filter based on payment type.
		add_filter( 'request', array( $this, 'orders_by_paymenttype_query' ), 25 );
		
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'novalnet_wc_admin_shop_order'), 70, 2 );
				
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( &$this, 'wc_admin_shop_order_novalnet_payment_fields' ) ,10,1);
		
		add_action( 'woocommerce_before_order_object_save', array( $this, 'novalnet_wc_shop_order_customer_note_check' ), 50,1);
		
	}

	/**
	 * Add admin dropdown for order types to filter based on payment type.
	 *
	 * @since 12.0.0
	 */
	public static function customize_payment_selection_filter_admin() {
		global $typenow;

		if ( ! empty( $typenow ) && in_array( $typenow, array( 'shop_order', 'shop_subscription' ), true ) ) {
			$html     = '<select name="shop_order_novalnet_paymenttype" id="dropdown_shop_order_novalnet_paymenttype"><option value="">' . __( 'All Payment Methods', 'woocommerce-novalnet-gateway' ) . '</option>';
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			foreach ( $gateways as $payment_type => $details ) {

				if ( wc_novalnet_check_isset( $details->settings, 'enabled', 'yes' ) ) {
					if ( 'shop_subscription' === $typenow && ! $details->supports( 'subscriptions' ) ) {
						continue;
					}
					$html .= '<option value="' . esc_attr( $payment_type ) . '"';
					if ( ! empty( novalnet()->request ['shop_order_novalnet_paymenttype'] ) && novalnet()->request ['shop_order_novalnet_paymenttype'] === $payment_type ) {
						$html .= 'selected="selected"';
					}
					$html .= '>' . esc_attr( $details->method_title ) . '</option>';
				}
			}
			$html .= '</select>';

			echo wp_kses(
				$html,
				array(
					'select' => array(
						'name' => true,
						'id'   => true,
					),
					'option' => array(
						'value' => true,
					),
				)
			);
		}
	}

	/**
	 * Add request filter for order types to filter based on payment
	 *
	 * @since 12.0.0
	 *
	 * @param array $vars The options for execute the query.
	 * @return array
	 */
	public static function orders_by_paymenttype_query( $vars ) {
		global $typenow;

		if ( ! empty( $typenow ) && in_array( $typenow, array( 'shop_order', 'shop_subscription' ), true ) && ! empty( novalnet()->request ['shop_order_novalnet_paymenttype'] ) ) {

				$meta_key = apply_filters( 'woocommerce_novalnet_admin_order_type_filter_meta_key', '_payment_method', novalnet()->request ['shop_order_novalnet_paymenttype'] );

			if ( ! empty( $meta_key ) ) {
				$vars['meta_query'][] = array(
					'key'     => '_payment_method',
					'compare' => '=',
					'value'   => novalnet()->request ['shop_order_novalnet_paymenttype'],
				);
			}
		}

		return $vars;
	}

	/**
	 * Adding admin script
	 *
	 * @since 12.0.0
	 */
	public function admin_enqueue_scripts() {

		// Enqueue style & script.
		wp_enqueue_media();
		wp_enqueue_script( 'woocommerce-novalnet-gateway-script', novalnet()->plugin_url . '/assets/js/novalnet.js', '', NOVALNET_VERSION, true );
		wp_enqueue_style( 'woocommerce-novalnet-gateway-css', novalnet()->plugin_url . '/assets/css/novalnet.css', array(), NOVALNET_VERSION, false );
		wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery' ), NOVALNET_VERSION, true );
		wp_enqueue_script( 'woocommerce-novalnet-gateway-admin-script', novalnet()->plugin_url . '/assets/js/novalnet-admin.js', '', NOVALNET_VERSION, true );

		wp_localize_script(
			'woocommerce-novalnet-gateway-admin-script',
			'wc_novalnet_admin_data',
			array(
				'admin'               => true,
				'paypal_notice'       => __( 'In order to use this option you must have billing agreement option enabled in your PayPal account. Please contact your account manager at PayPal.', 'woocommerce-novalnet-gateway' ),
				'webhook_notice'      => __( 'Are you sure you want to configure the Webhook URL in Novalnet Admin Portal?', 'woocommerce-novalnet-gateway' ),
				'webhook_url_error'   => __( 'Please enter the valid webhook URL', 'woocommerce-novalnet-gateway' ),
				'refund_amount_error' => __( 'Invalid refund amount', 'woocommerce-novalnet-gateway' ),
				'change_payment_error' => __( 'Payment method cannot be changed', 'woocommerce-novalnet-gateway' ),
				'invalid_tid' => __( 'Invalid TID', 'woocommerce-novalnet-gateway' ),
				'payment_method_empty' => __( 'Payment method cannot be left blank. Please choose a payment method.', 'woocommerce-novalnet-gateway' ),
				'guarantee_text' => array(
					'novalnet_guaranteed_sepa' => __( 'Direct Debit SEPA with payment guarantee', 'woocommerce-novalnet-gateway' ),
					'novalnet_guaranteed_invoice' => __( 'Invoice with payment guarantee', 'woocommerce-novalnet-gateway' )
					),
			)
		);
	}

	/**
	 * Adds Novalnet global Configuration.
	 *
	 * @since 12.0.0
	 *
	 * @param  array $woocommerce_tab The woocommerce admin settings tab.
	 * @return array
	 */
	public function add_novalnet_settings_tab( $woocommerce_tab ) {

		$woocommerce_tab ['novalnet-settings'] = __( 'Novalnet Global Configuration', 'woocommerce-novalnet-gateway' );
		return $woocommerce_tab;
	}

	/**
	 * Handle config hash call
	 *
	 * @since 12.0.0
	 */
	public function get_novalnet_vendor_details() {

		if ( ! empty( novalnet()->request ['novalnet_api_key'] ) && ! empty( novalnet()->request ['novalnet_key_password'] ) ) {

			$request = array(
				'merchant' => array(
					'signature' => novalnet()->request ['novalnet_api_key'],
				),
				'custom'   => array(
					'lang' => wc_novalnet_shop_language(),
				),
			);

			$response = novalnet()->helper()->submit_request( $request, novalnet()->helper()->get_action_endpoint( 'merchant_details' ), array( 'access_key' => novalnet()->request ['novalnet_key_password'] ) );

			if ( ! empty( $response['result']['status'] ) && 'SUCCESS' === $response['result']['status'] ) {

				wp_send_json_success( $response );
			}

			wp_send_json_error(
				array(
					'error' => $response['result']['status_text'],
				)
			);
		}
		wp_send_json_error(
			array(
				'error' => __( 'Please enter the required fields under Novalnet API Configuration.', 'woocommerce-novalnet-gateway' ),
			)
		);
	}

	/**
	 * Handle Webhook configure
	 *
	 * @since 12.0.0
	 */
	public function handle_webhook_configure() {
		if ( ! empty( novalnet()->request ['novalnet_api_key'] ) && ! empty( novalnet()->request ['novalnet_key_password'] ) ) {

			if ( empty( novalnet()->request ['novalnet_webhook_url'] ) ) {
				wp_send_json_error(
					array(
						'error' => __( 'Please enter the valid webhook URL', 'woocommerce-novalnet-gateway' ),
					)
				);
			}

			$request = array(
				'merchant' => array(
					'signature' => novalnet()->request ['novalnet_api_key'],
				),
				'webhook'  => array(
					'url' => novalnet()->request ['novalnet_webhook_url'],
				),
				'custom'   => array(
					'lang' => wc_novalnet_shop_language(),
				),
			);

			$response = novalnet()->helper()->submit_request( $request, novalnet()->helper()->get_action_endpoint( 'webhook_configure' ), array( 'access_key' => novalnet()->request ['novalnet_key_password'] ) );

			if ( ! empty( $response['result']['status'] ) && 'SUCCESS' === $response['result']['status'] ) {
				$response['result']['status_text'] = __( 'Notification / Webhook URL is configured successfully in Novalnet Admin Portal', 'woocommerce-novalnet-gateway' );
				wp_send_json_success( $response );
			}

			wp_send_json_error(
				array(
					'error' => $response['result']['status_text'],
				)
			);
		}
		wp_send_json_error(
			array(
				'error' => __( 'Please enter the required fields under Novalnet API Configuration.', 'woocommerce-novalnet-gateway' ),
			)
		);
	}

	/**
	 * Check amount for admin orders
	 *
	 * @since 12.0.0
	 */
	public function check_amount_admin_order() {				
		if ( ! empty( novalnet()->request ['novalnet_check_post_id'] )) {
			$order = wc_get_order(novalnet()->request['novalnet_check_post_id']);
			$payment_method = novalnet()->request['novalnet_admin_payment'];
			if($order->get_total() > 0) {
				$settings = WC_Novalnet_Configuration::get_payment_settings( $payment_method );
				if(isset($settings['min_amount']) && !empty($settings['min_amount']) && $settings['min_amount'] > 0) {
					$order_amount = wc_novalnet_formatted_amount( $order->get_total() );
					if($order_amount < $settings['min_amount']) {
						$min_amount = wc_novalnet_shop_amount_format($settings['min_amount']);
						$response = array( 'is_amount_valid' =>0 , 'error' => sprintf( __( 'Minimum order amount should be greater than or equal to %s', 'woocommerce-novalnet-gateway' ), $min_amount ));
						wp_send_json_error($response);						
					}	
				}
				$response = array( 'is_amount_valid' =>1 , 'response_text' => 'Order has valid amount');
				wp_send_json_success( $response );				
			} else if($order->get_total() == 0){
				$response = array( 'is_amount_valid' =>0 , 'error' => __( 'Please add item(s) and click Recalculate before creating the order.', 'woocommerce-novalnet-gateway' ));
				wp_send_json_error($response);								
			}
			
		}
	}

	/**
	 * Form custom hidden field
	 *
	 * @since 12.0.0
	 * @param array $value The attributes of the field.
	 */
	public function form_novalnet_hidden( $value ) {
		$option_value = self::get_option( $value['id'] );
		?>
		<input name="<?php echo esc_attr( $value['id'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" type="hidden" value="<?php echo esc_attr( $option_value ); ?>"/>
		<?php
	}

	/**
	 * Novalnet plugin action links
	 *
	 * @since 12.0.0
	 * @param array $links Default/ available links.
	 *
	 * @return array
	 */
	public function action_novalnet_links( $links ) {
		return array_merge(
			array(
				'<a href="' . wc_novalnet_generate_admin_link(
					array(
						'page' => 'wc-settings',
						'tab'  => 'novalnet-settings',
					)
				) . '">' . __(
					'Configuration',
					'woocommerce-novalnet-gateway'
				) . '</a>',
			),
			$links
		);
	}

	/**
	 * To validate the Novalnet configuration
	 *
	 * @since 12.0.0
	 */
	public function save() {

		// Process backend global configuration validation.
		if ( ! empty( novalnet()->request ['tab'] ) && 'novalnet-settings' === novalnet()->request ['tab'] && ! empty( novalnet()->request ['save'] ) ) {
			if ( WC_Novalnet_Validation::validate_configuration( novalnet()->request ) ) {
				$error = esc_attr( __( 'Please fill in the required fields', 'woocommerce-novalnet-gateway' ) );
			} else {
				$error = '';
			}

			// Redirect while error occured.
			if ( '' !== $error ) {
				WC_Admin_Meta_Boxes::add_error( $error );
				wc_novalnet_safe_redirect(
					wc_novalnet_generate_admin_link(
						array(
							'page' => 'wc-settings',
							'tab'  => 'novalnet-settings',
						)
					)
				);
			}
		}
	}

	/**
	 * Show meta box.
	 *
	 * @since 12.0.0
	 */
	public function show_meta_box() {

		// Get WC_Order id value.
		if ( ! empty( novalnet()->request ['post'] ) ) {
			$wc_order_id = novalnet()->request ['post'];
		} elseif ( ! empty( novalnet()->request ['post_ID'] ) ) {
			$wc_order_id = novalnet()->request ['post_ID'];
		}

		if ( ! empty( $wc_order_id ) ) {

			// Dont use $post_type as variable name these may override the existing core value.
			$nn_post_type = get_post_type( $wc_order_id );
			if ( in_array( $nn_post_type, array( 'shop_order', 'subscription_order' ), true ) ) {
				$payment_method = get_post_meta( $wc_order_id, '_payment_method', true );

				if ( WC_Novalnet_Validation::check_string( $payment_method ) ) {
					if ( 'shop_order' === $nn_post_type ) {
						$data['disallow_refund_reversal'] = true;
					}
					// Get the current payment status of the transaction.
					$gateway_status = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'gateway_status' );

					// Checks for Novalnet payment & Initiate Novalnet_Admin_Meta_Boxes.
					if ( novalnet()->get_supports( 'instalment', $payment_method ) && 'CONFIRMED' === $gateway_status ) {
						include_once 'meta-boxes/class-wc-novalnet-meta-box-instalment-summary.php';
						add_action( 'woocommerce_process_shop_order_meta', array( 'WC_Novalnet_Meta_Box_Instalment_Summary', 'save' ), 1, 1 );
						add_action( 'add_meta_boxes', array( $this, 'add_instalment_details_box' ), 25 );
					}
					wp_enqueue_script( 'woocommerce-novalnet-gateway-admin-script', novalnet()->plugin_url . '/assets/js/novalnet-admin.js', '', NOVALNET_VERSION, true );
					wp_localize_script(
						'woocommerce-novalnet-gateway-admin-script',
						'wc_novalnet_order_data',
						$data
					);
				}
			}
		}
	}

	/**
	 * Add instalment details box.
	 *
	 * @since 12.0.0
	 */
	public function add_instalment_details_box() {

		add_meta_box( 'novalnet-instalment-details', _x( 'Instalment Summary', 'meta box title', 'woocommerce-novalnet-gateway' ), 'WC_Novalnet_Meta_Box_Instalment_Summary::output', 'shop_order', 'normal', 'default' );
	}

	/**
	 * Handle the amount update.
	 *
	 * @param  int $wc_order_id The order id.
	 *
	 * @since 12.0.0
	 */
	public function handle_amount_update( $wc_order_id ) {
		if ( ( 'shop_subscription' === get_post_type( $wc_order_id ) || 'shop_order' === get_post_type( $wc_order_id ) ) && ( wc_novalnet_check_isset( novalnet()->request, 'action', 'woocommerce_save_order_items' ) || wc_novalnet_check_isset( novalnet()->request, 'action', 'woocommerce_calc_line_taxes' ) ) ) {

			// Get payment method name.
			$payment_method = get_post_meta( $wc_order_id, '_payment_method', true );
			
			// Check for the Novalnet payment_type.
			if ( WC_Novalnet_Validation::check_string( $payment_method ) ) {
				if ( novalnet()->get_supports( 'amount_update', $payment_method ) ) {

					// Get the current payment status of the transaction.
					$gateway_status = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'gateway_status' );

					if ( in_array( $gateway_status, array( 'ON_HOLD', 'PENDING' ), true ) || 'shop_subscription' === get_post_type( $wc_order_id ) ) {

						$wc_order = new WC_Order( $wc_order_id );

						$updated_amount = wc_novalnet_formatted_amount( $wc_order->get_total() );

						// Get the Transaction ID of the transaction.
						$tid = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'tid' );

						if ( empty( $tid ) ) {
							$tid = novalnet()->db()->get_entry_by_order_id( $wc_order->get_parent_id(), 'tid' );
						}

						if ( novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'amount' ) != $updated_amount ) {

							// Form API request.
							$parameters = array(
								'transaction' => array(
									'tid'    => $tid,
									'amount' => $updated_amount,
								),
								'custom'      => array(
									'lang'         => wc_novalnet_shop_language(),
									'shop_invoked' => 1,
								),
							);

							// Send API request call.
							$response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( 'transaction_update' ), array( 'post_id' => $wc_order_id ) );

							// Handle success process.
							if ( WC_Novalnet_Validation::is_success_status( $response ) ) {

								/* translators: %s: Amount, Date */
								$message = sprintf( __( 'Amount %1$s has been updated successfully on %2$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $updated_amount ), wc_novalnet_formatted_date() );

								if ( 'DEACTIVATED' === $response['transaction']['status'] ) {
									$wc_order->update_status( 'wc-cancelled' );
								}

								// Update transaction details.
								novalnet()->db()->update( array( 'amount' => $updated_amount ), array( 'order_no' => $wc_order_id ) );

								// Update Novalnet comments.
								novalnet()->helper()->update_comments( $wc_order, $message );

								if ( in_array( $payment_method, array( 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_guaranteed_invoice' ), true ) ) {
									if ( empty( $response['transaction']['bank_details'] ) ) {
										$response['transaction']['bank_details'] = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'additional_info' );
									}
									$transaction_comments = novalnet()->helper()->prepare_payment_comments( $response );
									novalnet()->helper()->update_comments( $wc_order, $transaction_comments, false, 'transaction_info', false );
								}
							} else {
								/* translators: %s: Message */
								novalnet()->helper()->update_comments( $wc_order, sprintf( __( 'Amount update failed due to: %s' ), wc_novalnet_response_text( $response ) ), true, 'note', false );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Capture the transaction when the order is changed from on-hold to complete or processing.
	 *
	 * @since 12.0.0
	 * @param  int      $wc_order_id The order id.
	 * @param  string   $old_status Old order status.
	 * @param  string   $new_status New order status.
	 * @param  WC_Order $wc_order The order object.
	 * @throws Exception For response.
	 */
	public function handle_status_change( $wc_order_id, $old_status, $new_status, $wc_order ) {

		// Check for the Novalnet payment_type.
		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) && wc_novalnet_check_isset( novalnet()->request, 'post_type', 'shop_order' ) ) {

			// Get the current payment status of the transaction.
			$gateway_status = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'gateway_status' );

			if ( 'cancelled' === $new_status && in_array( $gateway_status, array( 'CONFIRMED', 'PENDING' ), true ) ) {

				$refunded_amount = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'refunded_amount' );
				$amount          = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'amount' );
				if ( $amount > $refunded_amount ) {
					$action              = 'transaction_refund';
					$amount_to_be_refund = $amount - $refunded_amount;
				}
				if ( novalnet()->get_supports( 'instalment', $wc_order->get_payment_method() ) ) {
					$action = 'instalment_cancel';
				}
			} elseif ( in_array( $gateway_status, array( 'ON_HOLD' ), true ) && novalnet()->get_supports( 'authorize', $wc_order->get_payment_method() ) ) {
				if ( in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
					$action = 'transaction_capture';
				} elseif ( 'cancelled' === $new_status ) {
					$action = 'transaction_cancel';
				}
			}

			$wc_order->set_customer_note( strip_tags( $wc_order->get_customer_note() ) );
			$wc_order->save();

			if ( ! empty( $action ) ) {

				// Get the Transaction ID of the transaction.
				$tid = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'tid' );

				// Form API request.
				if ( ! empty( $tid ) ) {
					if ( 'instalment_cancel' !== $action ) {
						$parameters = array(
							'transaction' => array(
								'tid' => $tid,
							),
							'custom'      => array(
								'lang'         => wc_novalnet_shop_language(),
								'shop_invoked' => 1,
							),
						);
					} else {
						$parameters = array(
							'instalment' => array(
								'tid' => $tid,
							),
							'custom'     => array(
								'lang'         => wc_novalnet_shop_language(),
								'shop_invoked' => 1,
							),
						);
					}

					if ( 'transaction_refund' === $action && ! empty( $amount_to_be_refund ) ) {
						$parameters['transaction']['amount'] = $amount_to_be_refund;
					}

					// Send API request call.
					$response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( $action ), array( 'post_id' => $wc_order_id ) );

					// Handle success process.
					if ( WC_Novalnet_Validation::is_success_status( $response ) ) {

						$update_info['gateway_status'] = $response ['transaction']['status'];
						update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $response ['transaction']['status'] );
						if ( 'instalment_cancel' === $action ) {
							$update_info['gateway_status'] = 'DEACTIVATED';
						}
						// Handle transaction capture.
						if ( 'transaction_capture' === $action ) {

							/* translators: %s: Date */
							$message = sprintf( __( 'The transaction has been confirmed on %s.', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );

							// Update callback amount.
							if ( 'CONFIRMED' === $update_info['gateway_status'] ) {
								$update_info ['callback_amount'] = wc_novalnet_formatted_amount( $wc_order->get_total() );
							}

							// Store instalment details.
							if ( novalnet()->get_supports( 'instalment', $wc_order->get_payment_method() ) ) {

								$bank_details = novalnet()->db()->get_entry_by_order_id( $wc_order, 'additional_info' );

								if ( ! empty( $this->order_reference ['additional_info'] ) ) {
									$update_info ['additional_info'] = wc_novalnet_serialize_data( wc_novalnet_unserialize_data( $bank_details ) + wc_novalnet_unserialize_data( apply_filters( 'novalnet_store_instalment_data', $response ) ) );
								} else {
									$update_info ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $response );
								}

								// Store Paypal token details.
							} elseif ( 'novalnet_paypal' === $wc_order->get_payment_method() && ! empty( $response['transaction']['payment_data']['paypal_account'] ) ) {
								$token = new WC_Payment_Token_Novalnet();
								$token->delete_duplicate_tokens( $response['transaction']['payment_data'], $wc_order->get_payment_method() );
								$tokens = WC_Payment_Tokens::get_order_tokens( $wc_order->get_id() );
								foreach ( $tokens as $token ) {
									if ( $token->get_reference_tid() === $tid ) {
										$token->set_paypal_account( $response['transaction']['payment_data']['paypal_account'] );
										$token->save();
										break;
									}
								}
							}

							// Update comments on amount / due date update.
							if ( in_array( $wc_order->get_payment_method(), array( 'novalnet_invoice', 'novalnet_guaranteed_invoice', 'novalnet_instalment_invoice' ), true ) && ! empty( $response['transaction']['due_date'] ) && ! empty( $response['transaction']['amount'] ) ) {
								/* translators: %1$s: amount, %2$s: due date */
								if ( in_array( $wc_order->get_payment_method(), array( 'novalnet_invoice', 'novalnet_instalment_invoice', 'novalnet_guaranteed_invoice' ), true ) ) {
									if ( empty( $response['transaction']['bank_details'] ) ) {
										$response['transaction']['bank_details'] = novalnet()->db()->get_entry_by_order_id( $wc_order_id, 'additional_info' );
									}
									$transaction_comments = novalnet()->helper()->prepare_payment_comments( $response );

									$customer_given_note = get_post_meta( $wc_order->get_id(), '_nn_customer_given_note', true );
									if ( ! empty( $customer_given_note ) ) {
										$transaction_comments = $customer_given_note . PHP_EOL . $transaction_comments;
									}

									novalnet()->helper()->update_comments( $wc_order, $transaction_comments, false, 'transaction_info', false );
								}
							}
						} elseif ( 'transaction_cancel' === $action ) {
							/* translators: %s: Date */
							$message = sprintf( __( 'The transaction has been cancelled on %s.', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() );
						} elseif ( 'transaction_refund' === $action || 'instalment_cancel' === $action ) {

							// Create the refund.
							wc_create_refund(
								array(
									'order_id' => $wc_order_id,
									'amount'   => sprintf( '%0.2f', ( $response['transaction']['refund']['amount'] / 100 ) ),
								)
							);

							$update_info['refunded_amount'] = wc_novalnet_formatted_amount( $wc_order->get_total() );

							if ( 'instalment_cancel' === $action ) {
								$message = sprintf(
									/* translators: %1$s: tid, %2$s: amount */
									__( 'Instalment has been cancelled for the TID: %1$s on %2$s & Refund has been initiated with the amount %3$s', 'woocommerce-novalnet-gateway' ),
									$response['transaction']['tid'],
									wc_novalnet_formatted_date(),
									wc_novalnet_shop_amount_format(
										wc_novalnet_formatted_amount(
											$response
											['transaction']['refund']['amount'] / 100
										)
									)
								);
							} else {

								$message = sprintf(
									/* translators: %1$s: tid, %2$s: amount */
									__( 'Refund has been initiated for the TID: %1$s with the amount of %2$s.', 'woocommerce-novalnet-gateway' ),
									$response['transaction']['tid'],
									wc_novalnet_shop_amount_format(
										wc_novalnet_formatted_amount(
											$response
											['transaction']['refund']['amount'] / 100
										)
									)
								);

								// Get the new TID.
								if ( ! empty( $response['transaction']['refund']['tid'] ) ) {
									/* translators: %s: response tid */
									$message .= sprintf( __( ' New TID:%s for the refunded amount', 'woocommerce-novalnet-gateway' ), $response ['transaction']['refund']['tid'] );
								}
							}
						}
						// Update transaction details.
						novalnet()->db()->update(
							$update_info,
							array(
								'order_no' => $wc_order_id,
							)
						);
						novalnet()->helper()->update_comments( $wc_order, wc_novalnet_format_text( $message ) );
					} else {
						$message = wc_novalnet_response_text( $response );
						$wc_order->set_status( $old_status, $message, true );
						$wc_order->save();
						if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
							throw new Exception( $message );
						}
					}
				} else {
					$message = __( 'No Transaction ID found for this Order', 'woocommerce-novalnet-gateway' );
					$wc_order->set_status( $old_status, $message, true );
					$wc_order->save();
					if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
						throw new Exception( $message );
					}
				}
			}
		}
	}
	
	/**
	 * Process to handle admin placed orders
	 *
	 * @since 12.0.0
	 * @param  int      $post_id The post id.
	 * @param  WP_Post   $post The post object.
	 */
	public static function novalnet_wc_admin_shop_order($post_id, $post)
	{
		$request = novalnet()->request;		
		if( is_admin() && $request['post_status'] === 'auto-draft' && in_array($request['_payment_method'],array('novalnet_prepayment', 'novalnet_invoice', 'novalnet_sepa', 'novalnet_barzahlen', 'novalnet_guaranteed_invoice','novalnet_guaranteed_sepa','novalnet_multibanco'),true) ) {
			WC()->initialize_session();
			WC()->session->set( 'admin_add_shop_order', true );
			$order = wc_get_order( $post_id );
			$order_id = $order->get_id();
			$items = $order->get_items();
			if ( ! empty( $items ) ) { // Check order contains subscription product
				foreach ( $items as $item_id => $item ) {
					$product = $item->get_product();
					if ( ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) ) {
						$message = __( 'Subscription orders are not supported in the backend order creation.' , 'woocommerce-novalnet-gateway' );
						WC_Admin_Meta_Boxes::add_error( $message );
						$order->add_order_note( $message );
						$order->update_status( 'failed' );
						return ;					
					}
				}
			}
			$payment_method = $order->get_payment_method();
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			if(empty(novalnet()->request['_transaction_id']))
			{
				$error_redirect = $available_gateways[ $payment_method ]->validate_fields();
				if(!empty($error_redirect)) {
					$order->update_status( 'failed' );
					return ;
				}
			} else {
				$tid_details = novalnet()->db()->get_transaction_details( '' , novalnet()->request['_transaction_id'] );
				if(!empty($tid_details)) {
					$message = __( 'Transaction ID already exists.' , 'woocommerce-novalnet-gateway' );
					WC_Admin_Meta_Boxes::add_error( $message );
					$order->add_order_note( $message );
					$order->update_status( 'failed' );
					return ;					
				}
			}
											
			$result = $available_gateways[ $payment_method ]->process_payment( $order_id );
			WC()->session->__unset( 'admin_add_shop_order');
		}
	}
	
	/**
	 * Add novalnet payment fields in add order page.
	 *
	 * @param  WC_Order $wc_order The order object.
	 * 
	 * @since 12
	 */
	public function wc_admin_shop_order_novalnet_payment_fields($order) 
	{
		global $post_type;
		$order_data = $order->get_data(); 
		if ( 'shop_order' === $post_type && $order_data['status'] === 'auto-draft' ) {
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			?>
			<div id="novalnet_admin_order_error" style="display: none;" class="error"></div>
			<div id="wc_shop_order_novalnet_payment_method">
				<input type="hidden" name="novalnet_valid_amount" id="novalnet_valid_amount" value=0 />
			<?php
			$supported_payment = array(
				'novalnet_prepayment',
				'novalnet_invoice',
				'novalnet_sepa',
				'novalnet_barzahlen',
				'novalnet_multibanco'
			);
			
			if( 'EUR' === get_woocommerce_currency() ) {
				$supported_payment[] = 'novalnet_guaranteed_invoice';
				$supported_payment[] = 'novalnet_guaranteed_sepa';
			}
			
			$allowed_countries = (new WC_Novalnet_Guaranteed_Process())->allowed_countries();
			
			foreach ( $supported_payment as $payment_type )
			{
				$settings = WC_Novalnet_Configuration::get_payment_settings( $payment_type );
				if ( wc_novalnet_check_isset( $settings, 'enabled', 'yes' ) ) {			
					$allow_b2b = (isset($settings['allow_b2b']) && !empty($settings['allow_b2b']))?	"allow_b2b='{$settings['allow_b2b']}'":'';						
					?>
					<div class="form-field form-field-wide wc_shop_admin_order_novalnet_method" id="wc_shop_order_admin_<?php echo $payment_type?>" style="display:none" <?php echo $allow_b2b;?>>
					<?php $available_gateways[ $payment_type ]->payment_fields(); ?>				
					</div>
					<?php				
				}	
			}
			wp_localize_script('woocommerce-novalnet-gateway-admin-script','wc_novalnet_admin_supported_payment',$supported_payment);
			wp_localize_script('woocommerce-novalnet-gateway-admin-script','wc_novalnet_allowed_countries',$allowed_countries);
			echo '</div>';			
		}
	}
	
	/**
	 * Remove html strings form customer note
	 *
	 * @since 12.0.0
	 * 
	 * @param  WC_Order $wc_order_data_object The order object.
	 * @throws Exception For response.
	 */

	public function novalnet_wc_shop_order_customer_note_check($wc_order_data_object)
	{	
		if(is_admin() && isset(novalnet()->request['post_type']) && novalnet()->request['post_type'] === 'shop_order' && novalnet()->request['action'] === 'editpost' && novalnet()->request['wc_order_action'] === 'send_order_details_admin')
		{
			$string = preg_replace('/(<([^>]+)>)/i', '', $wc_order_data_object->get_customer_note());
			$wc_order_data_object->set_customer_note($string);			
		}
	}
	
}

// Initiate Admin.
new WC_Novalnet_Admin();
