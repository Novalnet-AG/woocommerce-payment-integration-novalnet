<?php

/**
 * Woocommerce Novalnet Gateway Plugin class.
 *
 * @package  woocommerce-novalnet-gateway
 * @category Class WC_Novalnet
 * @author   Novalnet
 */

/**
 * Main WC_Novalnet Class.
 *
 * @class   WC_Novalnet
 */
final class WC_Novalnet {


	/**
	 * Available payment ID and its type in this module.
	 *
	 * @var array
	 */
	private $payments = array(
		'novalnet_sepa'                 => 'DIRECT_DEBIT_SEPA',
		'novalnet_guaranteed_sepa'      => 'GUARANTEED_DIRECT_DEBIT_SEPA',
		'novalnet_cc'                   => 'CREDITCARD',
		'novalnet_applepay'             => 'APPLEPAY',
		'novalnet_googlepay'            => 'GOOGLEPAY',
		'novalnet_invoice'              => 'INVOICE',
		'novalnet_guaranteed_invoice'   => 'GUARANTEED_INVOICE',
		'novalnet_prepayment'           => 'PREPAYMENT',
		'novalnet_ideal'                => 'IDEAL',
		'novalnet_instantbank'          => 'ONLINE_TRANSFER',
		'novalnet_online_bank_transfer' => 'ONLINE_BANK_TRANSFER',
		'novalnet_giropay'              => 'GIROPAY',
		'novalnet_twint'                => 'TWINT',
		'novalnet_barzahlen'            => 'CASHPAYMENT',
		'novalnet_przelewy24'           => 'PRZELEWY24',
		'novalnet_eps'                  => 'EPS',
		'novalnet_instalment_invoice'   => 'INSTALMENT_INVOICE',
		'novalnet_instalment_sepa'      => 'INSTALMENT_DIRECT_DEBIT_SEPA',
		'novalnet_paypal'               => 'PAYPAL',
		'novalnet_postfinance_card'     => 'POSTFINANCE_CARD',
		'novalnet_postfinance'          => 'POSTFINANCE',
		'novalnet_bancontact'           => 'BANCONTACT',
		'novalnet_alipay'               => 'ALIPAY',
		'novalnet_wechatpay'            => 'WECHATPAY',
		'novalnet_trustly'              => 'TRUSTLY',
		'novalnet_multibanco'           => 'MULTIBANCO',
		'novalnet_mbway'                => 'MBWAY',
		'novalnet_blik'                 => 'BLIK',
		'novalnet_payconiq'             => 'PAYCONIQ',
		'novalnet_ach'                  => 'DIRECT_DEBIT_ACH',
	);

	/**
	 * Supported payment types based on process.
	 *
	 * @var array
	 */
	private $supports = array(
		'tokenization'        => array(
			'novalnet_cc',
			'novalnet_sepa',
			'novalnet_guaranteed_sepa',
			'novalnet_instalment_sepa',
			'novalnet_ach',
			'novalnet_paypal',
		),
		'subscription'        => array(
			'novalnet_cc',
			'novalnet_sepa',
			'novalnet_guaranteed_sepa',
			'novalnet_paypal',
			'novalnet_invoice',
			'novalnet_guaranteed_invoice',
			'novalnet_prepayment',
			'novalnet_googlepay',
			'novalnet_applepay',
			'novalnet_ach',
		),
		'authorize'           => array(
			'novalnet_cc',
			'novalnet_applepay',
			'novalnet_googlepay',
			'novalnet_sepa',
			'novalnet_paypal',
			'novalnet_invoice',
			'novalnet_guaranteed_sepa',
			'novalnet_guaranteed_invoice',
			'novalnet_instalment_invoice',
			'novalnet_instalment_sepa',
		),
		'amount_update'       => array(
			'novalnet_guaranteed_sepa',
			'novalnet_guaranteed_invoice',
			'novalnet_sepa',
			'novalnet_invoice',
			'novalnet_prepayment',
			'novalnet_barzahlen',
		),
		'instalment'          => array(
			'novalnet_instalment_sepa',
			'novalnet_instalment_invoice',
		),
		'guarantee'           => array(
			'novalnet_guaranteed_sepa',
			'novalnet_guaranteed_invoice',
		),
		'pay_later'           => array(
			'novalnet_invoice',
			'novalnet_prepayment',
			'novalnet_barzahlen',
			'novalnet_multibanco',
		),
		'invoice_payments'    => array(
			'novalnet_invoice',
			'novalnet_instalment_invoice',
			'novalnet_guaranteed_invoice',
		),
		'sepa_payments'       => array(
			'novalnet_sepa',
			'novalnet_instalment_sepa',
			'novalnet_guaranteed_sepa',
		),
		'zero_amount_booking' => array(
			'novalnet_cc',
			'novalnet_sepa',
			'novalnet_applepay',
			'novalnet_googlepay',
			'novalnet_ach',
		),
	);

	/**
	 * Novalnet plugin URL
	 *
	 * @var $plugin_url
	 */
	public $plugin_url;

	/**
	 * Novalnet plugin directory path
	 *
	 * @var $plugin_dir_path
	 */
	public $plugin_dir_path;

	/**
	 * Store the POST/GET request
	 *
	 * @var array
	 */
	public $request;

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Novalnet The single instance of the class.
	 *
	 * @since 12.0.0
	 */
	protected static $instance = null;

	/**
	 * Main WC_Novalnet Instance.
	 *
	 * Ensures only one instance of Novalnet is loaded.
	 *
	 * @since  12.0.0
	 * @static
	 * @see    novalnet()
	 * @return WC_Novalnet - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WC_Novalnet Constructor.
	 */
	public function __construct() {
		// Including required files.
		include_once 'includes/wc-novalnet-functions.php';
		include_once 'includes/class-wc-novalnet-db-handler.php';
		include_once 'includes/admin/class-wc-novalnet-amount-refund.php';
		include_once 'includes/admin/class-wc-novalnet-configuration.php';
		include_once 'includes/admin/class-wc-novalnet-admin.php';
		include_once 'includes/abstracts/class-wc-novalnet-abstract-payment-gateways.php';
		include_once 'includes/class-wc-novalnet-install.php';
		include_once 'includes/class-wc-novalnet-validation.php';
		include_once 'includes/class-wc-payment-token-novalnet.php';
		include_once 'includes/class-wc-novalnet-helper.php';
		include_once 'includes/class-wc-novalnet-subscription.php';
		include_once 'includes/class-wc-novalnet-guaranteed-process.php';
		// Store the request data.
		$this->request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification

		// Handle webhook action.
		add_action( 'woocommerce_api_novalnet_callback', array( $this, 'handle_webhook_process' ) );

		// Including available Novalnet payment gateway files.
		foreach ( glob( dirname( __FILE__ ) . '/includes/gateways/*.php' ) as $filename ) {
			include_once $filename;
		}

		$this->plugin_url      = untrailingslashit( plugins_url( '/', NN_PLUGIN_FILE ) );
		$this->plugin_dir_path = untrailingslashit( plugin_dir_path( __FILE__ ) );

		// Initiate the text domain.
		load_plugin_textdomain( 'woocommerce-novalnet-gateway', false, dirname( plugin_basename( NN_PLUGIN_FILE ) ) . '/i18n/languages/' );

		// Handle installation process on plugin activation.
		register_activation_hook( NN_PLUGIN_FILE, array( 'WC_Novalnet_Install', 'install' ) );

		// Handle uninstallation process on plugin deactivation.
		register_deactivation_hook( NN_PLUGIN_FILE, array( 'WC_Novalnet_Install', 'uninstall' ) );

		// Handle version process on plugin update.
		add_action( 'admin_init', array( 'WC_Novalnet_Install', 'update' ) );

		// Add Novalnet payments to the WooCommerce.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_novalnet_payments' ) );

		// Align the transaction details.
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'align_transaction_info' ), 10, 3 );

		add_filter( 'woocommerce_email_order_meta_fields', array($this, 'add_qr_to_order_emails'), 10, 3 );

		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_qr_to_order_success_page' ), 5 );

		// Add plugin scripts (front-end).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );

		// Update Novalnet settings.
		add_action( 'woocommerce_update_options_novalnet-settings', array( 'WC_Novalnet_Configuration', 'update_novalnet_settings' ) );

		// Add Novalnet settings tab.
		add_action( 'woocommerce_settings_tabs_novalnet-settings', array( 'WC_Novalnet_Configuration', 'novalnet_settings_page' ) );

		// Customize script data.
		add_filter( 'script_loader_tag', array( $this, 'customize_script' ), 10, 3 );

		// Customize style sheet data.
		add_filter( 'style_loader_tag', array( $this, 'customize_style_sheet' ), 10, 4 );

		// add virtual product in to the cart.
		add_action( 'wp_ajax_add_virtual_product_in_cart', array( $this, 'add_virtual_product_in_cart' ) );
		add_action( 'wp_ajax_nopriv_add_virtual_product_in_cart', array( $this, 'add_virtual_product_in_cart' ) );

                // Restrict instant mail from Germanized plugin.
		add_filter( 'woocommerce_gzd_instant_order_confirmation', array( $this, 'restrict_instant_email' ) );

		// register the ajax action for authenticated users.
		add_action( 'wp_ajax_novalnet_order_creation', array( $this, 'novalnet_order_creation' ) );
		add_action( 'wp_ajax_nopriv_novalnet_order_creation', array( $this, 'novalnet_order_creation' ) );

		// Check shipping address.
		add_action( 'wp_ajax_novalnet_shipping_address_update', array( $this, 'novalnet_shipping_address_update' ) );
		add_action( 'wp_ajax_nopriv_novalnet_shipping_address_update', array( $this, 'novalnet_shipping_address_update' ) );

		// register the ajax action for authenticated users.
		add_action( 'wp_ajax_novalnet_shipping_method_update', array( $this, 'novalnet_shipping_method_update' ) );
		add_action( 'wp_ajax_nopriv_novalnet_shipping_method_update', array( $this, 'novalnet_shipping_method_update' ) );

		// Add button in cart page.
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'wallet_cart_hook' ), 1000 );

		// Add button in minicart page.
		add_action( 'woocommerce_widget_shopping_cart_after_buttons', array( $this, 'wallet_minicart_hook' ), 1000 );

		// Add button in checkout page.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'wallet_checkout_hook' ), 1000 );

		// Add button in myaccount page.
		add_action( 'woocommerce_before_customer_login_form', array( $this, 'wallet_myaccount_hook' ), 1000 );

		// Add button in product page.
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'wallet_product_hook' ), 1000 );

		// Add button in pay later page.
		if ( version_compare( WOOCOMMERCE_VERSION, '6.6.0', '>=' ) ) {
			add_action( 'before_woocommerce_pay_form', array( $this, 'wallet_paylater_hook' ), 1000 );
		} else {
			add_action( 'before_woocommerce_pay', array( $this, 'wallet_paylater_hook' ), 1000 );
		}

		if ( class_exists( 'woocommerce_wpml' ) ) {
			add_filter( 'wcml_multi_currency_ajax_actions', array( $this, 'add_novalnet_wallet_action' ) );
		}

		// Check multistep payment availability.
		add_action( 'wp_ajax_check_mutlistep_payment', array( $this, 'check_multistep_checkout_payment' ) );
		add_action( 'wp_ajax_nopriv_check_mutlistep_payment', array( $this, 'check_multistep_checkout_payment' ) );

		add_action( 'before_woocommerce_init', array( $this, 'novalnet_wc_hpos_compatibility' ) );

		add_filter( 'woocommerce_order_needs_payment', array( $this, 'needs_payment_pending_order' ), 10, 3 );

		// Prevents unpaid order auto cancelling.
		add_action( 'woocommerce_cancel_unpaid_order', array( $this, 'prevent_unpaid_order_cancelling' ), 10, 2 );
	}

	/**
	 * Add  QR code block to WooCommerce order emails.
	 *
	 * @since 12.9.0
	 * @param array      $fields        Existing email order meta fields.
	 * @param bool       $sent_to_admin Whether the email is sent to admin.
	 * @param WC_Order   $order         WooCommerce order object.
	 *
	 * @return array Modified email meta fields including  QR code if available.
	 */
	 public function add_qr_to_order_emails( $fields, $sent_to_admin, $order ) {

		$qr_url = (
			isset(WC()->session) && WC()->session->get('novalnet_qr_url')
		)
			? WC()->session->get('novalnet_qr_url')
			: (
				!empty($this->helper()->novalnet_get_wc_order_meta($order, '_novalnet_qr_code'))
					? $this->helper()->novalnet_get_wc_order_meta($order, '_novalnet_qr_code')
					: $this->helper()->novalnet_get_wc_order_meta($order, '_novalnet_epc_qr_code')
			);
		
		if (!empty($qr_url)) {
				$fields['qr_code'] = array(
					'label' => sprintf(__("Alternatively, you can use the QR code below for your convenience. Please scan it with your banking app to complete the payment", 'woocommerce-novalnet-gateway' )),
					'value' => '<img src="' . esc_url( $qr_url ) . '" alt="' . esc_attr( 'QR Code' ) . '" style="' . esc_attr( 'max-width:220px;height:auto;margin:10px auto;display:block;' ) . '" />',
				);
			}

		return $fields;
	}

	/**
     * Display QR block after customer note on Success page and my account order page
	 * 
	 * @since 12.9.0
	 * @param WC_Order   $order  WooCommerce order object.
     */
    public function add_qr_to_order_success_page( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

		$qr_url = !empty($this->helper()->novalnet_get_wc_order_meta($order, '_novalnet_qr_code'))
		? $this->helper()->novalnet_get_wc_order_meta($order, '_novalnet_qr_code')
		: $this->helper()->novalnet_get_wc_order_meta($order, '_novalnet_epc_qr_code');

		if (!empty($qr_url)) {
			echo '<section class="woocommerce-order-qr" style="margin:20px 0; padding:15px; border:1px solid #ddd; border-radius:8px;">';
			echo '<b><p style="margin-bottom:10px;">' . esc_html__( 'Scan & Pay', 'woocommerce-novalnet-gateway' ) . '</p></b>';
			echo '<p>' . esc_html__( "Alternatively, you can use the QR code below for your convenience. Please scan it with your banking app to complete the payment.", 'woocommerce-novalnet-gateway' ) . '</p>';
			echo '<img src="' . esc_url( $qr_url ) . '" alt="' . esc_attr( ' QR Code' ) . '" style="' . esc_attr( 'max-width:220px;height:auto;margin:10px auto;display:block;' ) . '" />';
			echo '</section>';
		}
    }
	

	/**
	 * Prevents WooCommerce's hold stock feature from cancelling Novalnet orders.
	 *
	 * @since 12.8.1
	 * @param bool     $cancel_order Order cancelling status.
	 * @param WC_Order $order        The order object.
	 */
	public function prevent_unpaid_order_cancelling( $cancel_order, $order ) {
		if ( ! $cancel_order || ! $order instanceof WC_Order ) {
			return $cancel_order;
		}
		if ( WC_Novalnet_Validation::check_string( $order->get_payment_method() ) ) {
			$cancel_order = false;
		}
		return $cancel_order;
	}

	/**
	 * Needs Payment for pending order.
	 * If the order created in novalnet payment.
	 *
	 * @since 12.6.3
	 *
	 * @param bool   $status       Need payment status.
	 * @param object $wc_order     Order details.
	 * @param array  $valid_status Valid order status.
	 */
	public function needs_payment_pending_order( $status, $wc_order, $valid_status ) {
		if ( $status ) {
			$draft_order_id = ( null !== wc()->session ) ? wc()->session->get( 'store_api_draft_order', 0 ) : null;
			$order_id       = $wc_order->get_id();
			if ( $draft_order_id === $order_id && WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) && 'pending' === $wc_order->get_status() ) {
				$cart_hash = md5( json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
				$wc_order->set_cart_hash( $cart_hash );
				$wc_order->save();
			}
		}
		return $status;
	}

	/**
	 * Novalet compatibility for WooCommerce custome order table
	 *
	 * @since 12.6.2
	 */
	public function novalnet_wc_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NN_PLUGIN_FILE, true );
		}
	}

	/**
	 * Check previously selected payment method available after changes multicheckout.
	 *
	 * @since 12.5.6
	 */
	public function check_multistep_checkout_payment() {
		$chosen_payment = '';
		if ( class_exists( 'WooCommerce_Germanized_Pro' ) && get_option( 'woocommerce_gzdp_checkout_enable' ) === 'yes' ) {
			$chosen_payment = WC()->session->get( 'chosen_payment_method' );
		}
		wp_send_json_success(
			array(
				'wc_chosen_payment' => $chosen_payment,
			)
		);
	}

	/**
	 * Add Novalnet ajax action in WooCommerce Multilingual & Multicurrency load currency action list.
	 *
	 * @since 12.5.5
	 * @param array $ajax_action  Allowed currency load action list.
	 */
	public function add_novalnet_wallet_action( $ajax_action ) {
		$ajax_action[] = 'novalnet_order_creation';
		$ajax_action[] = 'add_virtual_product_in_cart';
		$ajax_action[] = 'novalnet_shipping_address_update';
		$ajax_action[] = 'novalnet_shipping_method_update';
		$ajax_action[] = 'novalnet_wc_order_recalculate_success';
		return $ajax_action;
	}

	/**
	 * Process of Wallet for product page.
	 *
	 * @since 12.5.0
	 */
	public function wallet_paylater_hook() {
		// Get wallet settings.
		$data['wallet_area'] = 'paylater_page';
		// For paylater page get avilable_wallets in Checkout Page.
		$data['available_wallets'] = get_available_wallets( 'checkout_page' );
		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Process of Wallet for product page.
	 *
	 * @since 12.4.0
	 */
	public function wallet_product_hook() {

		global $product;

		$is_subscription_product = false;

		if ( is_product() && $product instanceof WC_Product ) {
			if ( class_exists( 'WC_Subscriptions' ) &&  WC_Subscriptions_Product::is_subscription( $product ) ) {
				$is_subscription_product = true;
			}
		}

		 // Get wallet settings.
		$data['wallet_area']       = 'product_page';
		$data['available_wallets'] = ( $this->can_display_wallet_button( array( 'is_subscription_product' => $is_subscription_product ) ) ) ? get_available_wallets( 'product_page' ) : array();

		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Process of Wallet for my-account page.
	 *
	 * @since 12.4.0
	 */
	public function wallet_myaccount_hook() {
		// Get wallet settings.
		$data['wallet_area']       = 'guest_checkout_page';
		$data['available_wallets'] = ( $this->can_display_wallet_button() ) ? get_available_wallets( 'guest_checkout_page' ) : array();
		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Process of Wallet for checkout page.
	 *
	 * @since 12.4.0
	 */
	public function wallet_checkout_hook() {
		// Get wallet settings.
		$data['wallet_area']       = 'checkout_page';
		$data['available_wallets'] = ( $this->can_display_wallet_button() ) ? get_available_wallets( 'checkout_page' ) : array();
		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Process of Wallet for minicart page.
	 *
	 * @since 12.4.0
	 */
	public function wallet_minicart_hook() {
		// Get wallet settings.
		$data['wallet_area']       = 'mini_cart_page';
		$data['available_wallets'] = ( $this->can_display_wallet_button() ) ? get_available_wallets( 'mini_cart_page' ) : array();
		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Process of Wallet for cart page.
	 *
	 * @since 12.4.0
	 */
	public function wallet_cart_hook() {
		// Get wallet settings.
		$data['wallet_area']       = 'shopping_cart_page';
		$data['available_wallets'] = ( $this->can_display_wallet_button() ) ? get_available_wallets( 'shopping_cart_page' ) : array();
		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Check the shop is enable for guest to place order.
	 *
	 * @since 12.5.5
	 */
	public function can_display_wallet_button($data = []) {
		if ( is_user_logged_in() ) {
			return true;
		}

		global $woocommerce;

		$items         = $woocommerce->cart->get_cart();
		$cart_has_subs = 0;
		foreach ( $items as $item => $values ) {
			$_product = wc_get_product( $values['data']->get_id() );
			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
				$cart_has_subs = 1;
			}
		}
		$signup_subscription_enabled = get_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions' ) === 'yes';
		$guest_checkout_enabled      = get_option( 'woocommerce_enable_guest_checkout' ) === 'yes';
		$signup_login_enabled        = get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) === 'yes';

		if ( ! empty( $data ) && class_exists( 'WC_Subscriptions' ) && ! $guest_checkout_enabled && ! $signup_login_enabled ) {
			return $data['is_subscription_product'] && $signup_subscription_enabled ? true : false;
		}

		return $cart_has_subs ? $signup_login_enabled || $signup_subscription_enabled : ( $guest_checkout_enabled || $signup_login_enabled );
	}

	/**
	 * Shipping address update in wallet page.
	 *
	 * @since 12.4.0
	 */
	public function novalnet_shipping_address_update() {
		global $woocommerce;

		if ( in_array( $this->request['source_page'], array( 'mini_cart_page_googlepay_button', 'mini_cart_page_applepay_button', 'product_page_googlepay_button', 'product_page_applepay_button' ), true ) ) {
			if ( empty( $this->request['variable_variant_id'] ) ) {
				$product_id = array();
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$product_id[] = $cart_item['product_id'];
				}
				if ( isset( $this->request['simple_product_id'] ) && ! in_array( (int) $this->request['simple_product_id'], $product_id, true ) ) {
					WC()->cart->add_to_cart( $this->request['simple_product_id'] );
				}
			}

			if ( ! empty( $this->request['variable_product_id'] ) && ! empty( $this->request['variable_variant_id'] ) ) {
				$variation_id = array();
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$variation_id[] = $cart_item['variation_id'];
				}

				if ( isset( $this->request['variable_variant_id'] ) && ! in_array( (int) $this->request['variable_variant_id'], $variation_id, true ) ) {
					WC()->cart->add_to_cart( $this->request['variable_product_id'], 1, $this->request['variable_variant_id'] );
				}
			}
		}

		$items           = $woocommerce->cart->get_cart();
		$article_details = array();
		foreach ( $items as $item => $values ) {
			$_product = wc_get_product( $values['data']->get_id() );

			if ( wc_prices_include_tax() ) {
				$product_price = wc_get_price_excluding_tax( $_product );
				$product_price = wc_novalnet_amount( $product_price );
			} else {
				$product_price = $_product->get_price();
				$product_price = wc_novalnet_amount( $product_price );
			}

			$total           = $product_price * $values['quantity'];
			$product_details = $_product->get_name() . ' (' . $values['quantity'] . ' X ' . $product_price . ')';

			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation' ), true ) ) {
				$signup_fee = $_product->get_meta( '_subscription_sign_up_fee' );
				if ( $signup_fee > 0 ) {
					$article_details[] = array(
						'label'  => 'Signup Fee',
						'amount' => wc_novalnet_amount_as_string( $signup_fee ),
						'type'   => 'SUBTOTAL',
					);
				}
			}
			$article_details[] = array(
				'label'  => $product_details,
				'amount' => wc_novalnet_amount_as_string( $total ),
				'type'   => 'SUBTOTAL',
			);
		}

		$received_shipping_address = json_decode( $this->request['shippingInfo'], true );

		WC()->customer->set_shipping_city( wc_clean( $received_shipping_address['address']['locality'] ) );
		WC()->customer->set_shipping_postcode( wc_clean( $received_shipping_address['address']['postalCode'] ) );
		WC()->customer->set_shipping_country( wc_clean( $received_shipping_address['address']['countryCode'] ) );
		WC()->customer->set_shipping_state( wc_clean( $received_shipping_address['address']['administrativeArea'] ) );
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		$packages = WC()->shipping()->get_packages();

		$shipping_details = array();
		$count            = 1;

		foreach ( $packages as $package ) {
			foreach ( $package['rates'] as $values ) {
				$shipping_total = wc_novalnet_amount( $values->cost );
				if ( 1 === $count ) {
					$article_details[] = array(
						'label'  => $values->label,
						'amount' => wc_novalnet_amount_as_string( $shipping_total ),
						'type'   => 'SUBTOTAL',
					);
					WC()->session->set( 'chosen_shipping_methods', array( $values->id ) );
				}
				$shipping_details[] = array(
					'label'      => $values->label,
					'amount'     => wc_novalnet_amount_as_string( $shipping_total ),
					'identifier' => $values->id,
					'detail'     => '',
				);
				$count++;
			}
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$cart_tax_amount = 0;
		foreach ( WC()->cart->get_taxes() as $tax_amount ) {
			$cart_tax_amount += $tax_amount;
		}

		if ( $cart_tax_amount > 0 ) {
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

		$total = wc_novalnet_amount( WC()->cart->total );
		if ( WC()->cart->show_shipping() && ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscriptions_needing_shipping() ) ) {
			$recurring_carts  = WC()->cart->recurring_carts;
			$recurring_amount = 0;
			if ( ! empty( $recurring_carts ) ) {
				foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
					$recurring_amount += $recurring_cart->total;
				}
			}
			WC_Subscriptions_Cart::calculate_subscription_totals( $recurring_amount, WC()->cart->recurring_carts );
			foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {
				// This ensures we get the correct package IDs (these are filtered by WC_Subscriptions_Cart).
				WC_Subscriptions_Cart::set_calculation_type( 'recurring_total' );
				WC_Subscriptions_Cart::set_recurring_cart_key( $recurring_cart_key );
				WC_Subscriptions_Cart::set_cached_recurring_cart( $recurring_cart );

				$shipping_packages = array();
				// Allow third parties to filter whether the recurring cart has a shipment.
				$cart_has_next_shipment = apply_filters( 'woocommerce_subscriptions_cart_has_next_shipment', 0 !== $recurring_cart->next_payment_date, $recurring_cart );
				if ( $cart_has_next_shipment && WC_Subscriptions_Cart::cart_contains_subscriptions_needing_shipping( $recurring_cart ) ) {
					foreach ( $recurring_cart->get_shipping_packages() as $recurring_cart_package_key => $recurring_cart_package ) {
						if ( version_compare( WC_Subscriptions::$version, '4.9.0', '<' ) ) {
							$package = WC_Subscriptions_Cart::get_calculated_shipping_for_package( $recurring_cart_package );
						} else {
							$package = WC()->shipping->calculate_shipping_for_package( $recurring_cart_package );
						}
						$shipping_packages = $package['rates'];
					}
				}
			}
			$methods_id = array();
			foreach ( $shipping_details as $shipments ) {
				$methods_id[] = $shipments['identifier'];
			}
			$count = 1;
			foreach ( $shipping_packages as $method ) {
				$shipping_total = wc_novalnet_amount( $method->cost );
				if ( ! empty( $shipping_details ) ) {
					if ( ! in_array( $method->id, $methods_id, true ) ) {
						$shipping_details[] = array(
							'label'      => $method->label,
							'amount'     => wc_novalnet_amount_as_string( $shipping_total ),
							'identifier' => $method->id,
							'detail'     => '',
						);
					}
				} else {
					if ( 1 === $count ) {
						$article_details[] = array(
							'label'  => $method->label,
							'amount' => wc_novalnet_amount_as_string( $shipping_total ),
							'type'   => 'SUBTOTAL',
						);
					}
					$shipping_details[] = array(
						'label'      => $method->label,
						'amount'     => wc_novalnet_amount_as_string( $shipping_total ),
						'identifier' => $method->id,
						'detail'     => '',
					);
				}
			}
		}

		$shipping_address_change = array(
			'amount'           => wc_novalnet_amount_as_string( $total ),
			'shipping_address' => $shipping_details,
			'article_details'  => $article_details,
		);
		wp_send_json( $shipping_address_change );
	}

	/**
	 * Shipping method update in wallet page.
	 *
	 * @since 12.4.0
	 */
	public function novalnet_shipping_method_update() {
		global $woocommerce;

		$received_shipping_method = json_decode( $this->request['shippingInfo'], true );
		$items                    = $woocommerce->cart->get_cart();

		$article_details = array();
		foreach ( $items as $item => $values ) {
			$_product = wc_get_product( $values['data']->get_id() );

			if ( wc_prices_include_tax() ) {
				$product_price = wc_get_price_excluding_tax( $_product );
				$product_price = wc_novalnet_amount( $product_price );
			} else {
				$product_price = $_product->get_price();
				$product_price = wc_novalnet_amount( $product_price );
			}

			$total           = $product_price * $values['quantity'];
			$total           = wc_novalnet_amount( $total );
			$product_details = $_product->get_name() . ' (' . $values['quantity'] . ' X ' . $product_price . ')';
			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation' ), true ) ) {
				$signup_fee = $_product->get_meta( '_subscription_sign_up_fee' );
				if ( $signup_fee > 0 ) {
					$article_details[] = array(
						'label'  => 'Signup Fee',
						'amount' => wc_novalnet_amount_as_string( $signup_fee ),
						'type'   => 'SUBTOTAL',
					);
				}
			}
			$article_details[] = array(
				'label'  => $product_details,
				'amount' => wc_novalnet_amount_as_string( $total ),
				'type'   => 'SUBTOTAL',
			);
		}

		if ( count( WC()->session->get( 'shipping_for_package_0' )['rates'] ) > 0 ) {
			foreach ( WC()->session->get( 'shipping_for_package_0' )['rates'] as $rate_id => $rate ) {
				if ( $rate->id === $received_shipping_method['shippingMethod']['identifier'] ) {
					$default_rate_id = array( $rate_id );
					break;
				}
			}
			WC()->session->set( 'chosen_shipping_methods', $default_rate_id );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$cart_tax_amount = 0;
		foreach ( WC()->cart->get_taxes() as $tax_amount ) {
			$cart_tax_amount += $tax_amount;
		}

		if ( $cart_tax_amount > 0 ) {
			$article_details[] = array(
				'label'  => 'Tax',
				'amount' => wc_novalnet_amount_as_string( $cart_tax_amount ),
				'type'   => 'SUBTOTAL',
			);
		}

		$article_details[] = array(
			'label'  => $received_shipping_method['shippingMethod']['label'],
			'amount' => wc_novalnet_amount_as_string( $received_shipping_method['shippingMethod']['amount'] ),
			'type'   => 'SUBTOTAL',
		);

		$applied_coupon = WC()->cart->get_applied_coupons();
		foreach ( $applied_coupon as $coupon ) {
			if ( ! empty( $coupon ) ) {
				$coupon_obj        = new WC_Coupon( $coupon );
				$article_details[] = array(
					'label'  => 'discount(' . $coupon . ')',
					'amount' => '-' . wc_novalnet_amount_as_string( $coupon_obj->get_amount() ),
					'type'   => 'SUBTOTAL',
				);
			}
		}

		$total                  = wc_novalnet_amount( WC()->cart->total );
		$shipping_method_change = array(
			'amount'     => wc_novalnet_amount_as_string( $total ),
			'order_info' => $article_details,
		);
		wp_send_json( $shipping_method_change );
	}

	/**
	 * Add virtual product in cart.
	 *
	 * @since 12.4.0
	 */
	public function add_virtual_product_in_cart() {
		if ( empty( $this->request['variable_variant_id'] ) ) {
			$product_id = array();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id[] = $cart_item['product_id'];
			}

			if ( ! in_array( (int) $this->request['simple_product_id'], $product_id, true ) ) {
				WC()->cart->add_to_cart( $this->request['simple_product_id'] );
			}
		}

		if ( ! empty( $this->request['variable_product_id'] ) && ! empty( $this->request['variable_variant_id'] ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$variation_id[] = $cart_item['variation_id'];
			}

			if ( ! in_array( (int) $this->request['variable_variant_id'], $variation_id, true ) ) {
				WC()->cart->add_to_cart( $this->request['variable_product_id'], 1, $this->request['variable_variant_id'] );
			}
		}
	}

	/**
	 * Order creation using wallet.
	 *
	 * @since 12.0.0
	 */
	public function novalnet_order_creation() {
		global $woocommerce;

		$token_name = 'novalnet_' . $this->request['payment'] . '_token';
		if ( WC()->session->__isset( $token_name ) ) {
			WC()->session->__unset( $token_name );
		}
		
		if ( WC()->session->__isset( 'googlepay_do_redirect' ) ) {
			WC()->session->__unset( 'googlepay_do_redirect' );
		}
		
		$payment_method = 'novalnet_' . $this->request['payment'];
		if ( isset( $this->request['pay_for_order_id'] ) && ! empty( $this->request['pay_for_order_id'] ) ) {
			
			WC()->session->set( $token_name, $this->request['variable_name']['response']['transaction']['token'] );
			if ( ! empty( $this->request['variable_name']['response']['transaction']['doRedirect'] ) ) {
				WC()->session->set( 'googlepay_do_redirect', $this->request['variable_name']['response']['transaction']['doRedirect'] );
			}
			// Process Payment.
			$available_gateways   = WC()->payment_gateways->get_available_payment_gateways();
			$settings             = WC_Novalnet_Configuration::get_payment_settings( $payment_method );
			$payment_text         = WC_Novalnet_Configuration::get_payment_text( $payment_method );
			$payment_method_title = wc_novalnet_get_payment_text( $settings, $payment_text, wc_novalnet_shop_language(), $payment_method, 'title' );
			$wc_order             = wc_get_order( wc_clean( $this->request['pay_for_order_id'] ) );
			$wc_order->set_payment_method_title( $payment_method_title );
			$wc_order->set_payment_method( $payment_method );
			$wc_order->save();

			$result = $available_gateways[ $payment_method ]->process_payment( $this->request['pay_for_order_id'] );
			wp_send_json( $result );
		} else {
			$required_fields = array( 'firstName', 'lastName', 'addressLines', 'postalCode', 'locality', 'countryCode' );
			$missing_fields  = false;
			$address         = '';

			if ( isset( $this->request['is_virtual_order'] ) && 'applepay' === (string) $this->request['payment'] && 1 === (int) $this->request['is_virtual_order'] ) {
				$required_fields = array( 'email' );
			}

			$received_address = $this->request['variable_name']['response']['order'];

			foreach ( $required_fields as $key ) {
				if ( 'addressLines' === $key ) {
					if ( empty( trim( $received_address['billing']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Billing';
						$missing_fields = true;
					}
					if ( isset( $received_address['shipping']['contact'] ) && empty( trim( $received_address['shipping']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Shipping';
						$missing_fields = true;
					}
				} else {
					if ( 'email' !== $key && empty( trim( $received_address['billing']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Billing';
						$missing_fields = true;
					}
					if ( isset( $received_address['shipping']['contact'] ) && empty( trim( $received_address['shipping']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Shipping';
						$missing_fields = true;
					}
				}

				if ( $missing_fields ) {
					wp_send_json(
						array(
							'result'   => 'error',
							'redirect' => $address . ' ' . ucwords( preg_replace( '/(?<!\ )[A-Z]/', ' $0', $key ) ) . ' is required fields',
						)
					);
				}
			}

			$billing_firstname = ! empty( $received_address['billing']['contact']['firstName'] ) ? $received_address['billing']['contact']['firstName'] : '';
			$billing_lastname  = ! empty( $received_address['billing']['contact']['lastName'] ) ? $received_address['billing']['contact']['lastName'] : '';

			if ( isset( $received_address['shipping']['contact']['firstName'] ) || isset( $received_address['shipping']['contact']['lastName'] ) ) {
				$shipping_firstname = ! empty( $received_address['shipping']['contact']['firstName'] ) ? $received_address['shipping']['contact']['firstName'] : '';
				$shipping_lastname  = ! empty( $received_address['shipping']['contact']['lastName'] ) ? $received_address['shipping']['contact']['lastName'] : '';
			}
		}

		$customer_billing  = array();
		$customer_shipping = array();
		$phone_number      = ( ! empty( $received_address['billing']['contact']['phoneNumber'] ) ) ? $received_address['billing']['contact']['phoneNumber'] : ( ( ! empty( $received_address['shipping']['contact']['phoneNumber'] ) ) ? $received_address['shipping']['contact']['phoneNumber'] : '' );

		if ( is_user_logged_in() ) {

			// Update billing address from Applepay sheet.
			WC()->customer->set_billing_first_name( wc_clean( $billing_firstname ) );
			WC()->customer->set_billing_last_name( wc_clean( $billing_lastname ) );
			WC()->customer->set_billing_address_1( wc_clean( ( ! empty( $received_address['billing']['contact']['addressLines'] ) ) ? $received_address['billing']['contact']['addressLines'] : WC()->customer->get_billing_address_1() ) );
			WC()->customer->set_billing_city( wc_clean( $received_address['billing']['contact']['locality'] ) );
			WC()->customer->set_billing_postcode( wc_clean( $received_address['billing']['contact']['postalCode'] ) );
			WC()->customer->set_billing_state( wc_clean( $received_address['billing']['contact']['administrativeArea'] ) );
			WC()->customer->set_billing_country( wc_clean( $received_address['billing']['contact']['countryCode'] ) );
			WC()->customer->set_billing_phone( wc_clean( $phone_number ) );
			$customer_billing['company'] = WC()->customer->get_billing_company();
			$customer_billing['state']   = WC()->customer->get_billing_state();

			if ( isset( $shipping_firstname ) || isset( $shipping_lastname ) ) {
				// Update shipping address from Applepay sheet.
				WC()->customer->set_shipping_first_name( wc_clean( $shipping_firstname ) );
				WC()->customer->set_shipping_last_name( wc_clean( $shipping_lastname ) );
				WC()->customer->set_shipping_address_1( wc_clean( ( ! empty( $received_address['shipping']['contact']['addressLines'] ) ) ? $received_address['shipping']['contact']['addressLines'] : WC()->customer->get_billing_address_1() ) );
				WC()->customer->set_shipping_city( wc_clean( $received_address['shipping']['contact']['locality'] ) );
				WC()->customer->set_shipping_postcode( wc_clean( $received_address['shipping']['contact']['postalCode'] ) );
				WC()->customer->set_shipping_country( wc_clean( $received_address['shipping']['contact']['countryCode'] ) );
				$customer_shipping['company'] = WC()->customer->get_shipping_company();
				$customer_shipping['state']   = WC()->customer->get_shipping_state();
			}
		}

		// Customer billing information details (from Applepay sheet).
		$customer_billing['first_name'] = wc_clean( $billing_firstname );
		$customer_billing['last_name']  = wc_clean( $billing_lastname );
		$customer_billing['address_1']  = $received_address['billing']['contact']['addressLines'];
		$customer_billing['city']       = $received_address['billing']['contact']['locality'];
		$customer_billing['email']      = ( ! empty( $received_address['billing']['contact']['email'] ) ) ? $received_address['billing']['contact']['email'] : $received_address['shipping']['contact']['email'];
		$customer_billing['postcode']   = $received_address['billing']['contact']['postalCode'];
		$customer_billing['country']    = $received_address['billing']['contact']['countryCode'];
		$customer_billing['phone']      = $phone_number;

		if ( isset( $shipping_firstname ) || isset( $shipping_lastname ) ) {
			// Customer shipping information details (from Applepay sheet).
			$customer_shipping['first_name'] = wc_clean( $shipping_firstname );
			$customer_shipping['last_name']  = wc_clean( $shipping_lastname );
			$customer_shipping['address_1']  = $received_address['shipping']['contact']['addressLines'];
			$customer_shipping['email']      = ( ! empty( $received_address['billing']['contact']['email'] ) ) ? $received_address['billing']['contact']['email'] : $received_address['shipping']['contact']['email'];
			$customer_shipping['city']       = $received_address['shipping']['contact']['locality'];
			$customer_shipping['postcode']   = $received_address['shipping']['contact']['postalCode'];
			$customer_shipping['country']    = $received_address['shipping']['contact']['countryCode'];
			$customer_shipping['phone']      = $received_address['shipping']['contact']['phoneNumber'];
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
		$checkout               = WC()->checkout();
		$data['payment_method'] = $payment_method;
		$items                  = $woocommerce->cart->get_cart();
		$cart_has_subs          = 0;
		foreach ( $items as $item => $values ) {
			$_product = wc_get_product( $values['data']->get_id() );
			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
				$cart_has_subs = 1;
			}
		}

		// Check subscription condition.
		if ( $cart_has_subs ) {
			if ( is_user_logged_in() ) {
				if ( ! class_exists( 'WC_Subscriptions_Checkout' ) ) {
					wp_send_json(
						array(
							'result'   => 'error',
							'redirect' => __( 'WooCommerce Subscription not installed properly.','woocommerce-novalnet-gateway' ),
						)
					);
				}
			}
		} elseif ( wc_novalnet_amount( WC()->cart->total ) > 0 ) {
			$data['payment_method'] = $payment_method;
		}

		if ( WC()->cart->needs_shipping() ) {
			$chosen_shipping_methods = ( WC()->session !== null ) ? wc()->session->get( 'chosen_shipping_methods' ) : array();
			if ( ! is_array( $chosen_shipping_methods ) || empty( $chosen_shipping_methods ) ) {
				wp_send_json(
					array(
						'result'   => 'error',
						'redirect' => __( 'Sorry, this order requires a shipping option.', 'woocommerce-novalnet-gateway' ),
					)
				);
			}

			foreach ( $chosen_shipping_methods as $chosen_shipping_method ) {
				if ( empty( $chosen_shipping_method ) || false === $chosen_shipping_method ) {
					wp_send_json(
						array(
							'result'   => 'error',
							'redirect' => __( 'Sorry, this order requires a shipping option.', 'woocommerce-novalnet-gateway' ),
						)
					);
				}
			}
		}

		$data['billing_email'] = $customer_billing['email'];

		if ( ! is_user_logged_in() && ( get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) === 'yes' || ($cart_has_subs && class_exists( 'WC_Subscriptions' ) && get_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions' ) === 'yes' ) ) ) {

			$customer_id = apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );

			$customer_id = wc_create_new_customer(
				$data['billing_email'],
				'',
				'',
				array(
					'first_name' => ! empty( $customer_billing['first_name'] ) ? $customer_billing['first_name'] : '',
					'last_name'  => ! empty( $customer_billing['last_name'] ) ? $customer_billing['last_name'] : '',
				)
			);

			if ( is_wp_error( $customer_id ) ) {
				wp_send_json(array(
					'result'   => 'error',
					'redirect' => ($customer_id->get_error_code() === 'registration-error-email-exists' )
						? sprintf(
							  /* translators: %s: Customer's email address. */
							__( 'An account is already registered with %s. Please log in or use a different email address.', 'woocommerce-novalnet-gateway' ),
							$data['billing_email']
						)
						: '',
				));

			}

			wc_set_customer_auth_cookie( $customer_id );

			WC()->session->set( 'reload_checkout', true );

			WC()->cart->calculate_totals();
		}

		WC()->session->set( $token_name, $this->request['variable_name']['response']['transaction']['token'] );
		if ( ! empty( $this->request['variable_name']['response']['transaction']['doRedirect'] ) ) {
			WC()->session->set( 'googlepay_do_redirect', $this->request['variable_name']['response']['transaction']['doRedirect'] );
		}

		$order_id = $checkout->create_order( $data );

		if ( is_wp_error( $order_id ) ) {
			wp_send_json(
				array(
					'result'   => 'error',
					'redirect' => $order_id->errors['checkout-error'][0],
				)
			);
		}

		$wc_order = wc_get_order( $order_id );

		$wc_order->set_address( $customer_billing, 'billing' );
		$wc_order->set_address( $customer_shipping, 'shipping' );
		$wc_order->set_currency( get_woocommerce_currency() );
		$wc_order->update_meta_data( '_customer_user', get_current_user_id() );
		if ( 'billing' === get_option( 'woocommerce_tax_based_on' ) ) {
			$wc_order->calculate_totals( ! empty( $wc_order->get_shipping_tax() ) ? true : false );
		}

		// Check subscription condition.
		if ( $cart_has_subs ) {
			if ( is_user_logged_in() ) {
				if ( ! class_exists( 'WC_Subscriptions_Checkout' ) ) {
					$wc_order->set_customer_note( str_replace( PHP_EOL, '<\br>', 'WooCommerce Subscription not installed properly.' ) );
					wp_send_json(
						array(
							'result'   => 'error',
							'redirect' => 'WooCommerce Subscription not installed properly.',
						)
					);
				} else {
					$wc_order->save();
					// Create manual subcription for the wallet.
					WC_Subscriptions_Checkout::process_checkout($wc_order, $_POST); // phpcs:ignore.
				}
			} else {
				$wc_order->update_status( 'failed' );
				$wc_order->add_order_note( 'To purchase subscription product signin your account then try again.' );
				$wc_order->set_customer_note( str_replace( PHP_EOL, '<\br>', 'To purchase subscription product signin your account then try again.' ) );
				wp_send_json(
					array(
						'result'   => 'error',
						'redirect' => __('You must be logged in to checkout.', 'woocommerce-novalnet-gateway'),
					)
				);
			}
		}

		$wc_order->save();
		// Process Payment.
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$result             = $available_gateways[ $payment_method ]->process_payment( $wc_order->get_id() );
		$gateway = $available_gateways[ $payment_method ];
        $order_success_status = $gateway->settings['order_success_status'];
        $gateway_id = $available_gateways[ $payment_method ]->id;
        $active_plugins = get_option('active_plugins');
		if (($gateway_id == 'novalnet_googlepay' || $gateway_id == 'novalnet_applepay') && $order_success_status == 'wc-completed' && in_array('woocommerce-germanized/woocommerce-germanized.php', $active_plugins)  ) {
	     WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger( $wc_order->get_id() );
		}
			wp_send_json( $result );
	}

	/**
	 * Add Novalnet function scripts in front-end.
	 *
	 * @since 12.0.0
	 */
	public function enqueue_script() {
		if ( is_checkout() ) {
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, true );
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script-payment', 'https://cdn.novalnet.de/js/v3/payment.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, true );
		} else {
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script-payment', 'https://cdn.novalnet.de/js/v3/payment.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		}

		// Enqueue script.
		wp_enqueue_script( 'woocommerce-novalnet-gateway-wallet-script', novalnet()->plugin_url . '/assets/js/novalnet-wallet.min.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );

		wp_localize_script(
			'woocommerce-novalnet-gateway-wallet-script',
			'my_ajax_object',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'applepay_setting'  => WC_Novalnet_Configuration::get_payment_settings( 'novalnet_applepay' ),
				'googlepay_setting' => WC_Novalnet_Configuration::get_payment_settings( 'novalnet_googlepay' ),
				'locale'            => get_locale(),
				'client_key'        => WC_Novalnet_Configuration::get_global_settings( 'client_key' ),
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			)
		);

		// Enqueue script in front-end.
		wp_enqueue_script( 'woocommerce-novalnet-gateway-script', $this->plugin_url . '/assets/js/novalnet.min.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		wp_enqueue_style( 'woocommerce-novalnet-gateway-css', $this->plugin_url . '/assets/css/novalnet.min.css', array(), NOVALNET_VERSION, false );
		wp_localize_script(
			'woocommerce-novalnet-gateway-script',
			'wc_novalnet_data',
			array(
				'dob_error'             => __( 'Please enter valid birth date', 'woocommerce-novalnet-gateway' ),
				'sepa_account_error'    => __( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ),
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'gzdp_multistep_error'  => __( 'Unfortunately, previously chosen payment method is not available. Please choose another payment type.', 'woocommerce-novalnet-gateway' ),
				'bic_allowed_countries' => novalnet()->helper()->get_bic_allowed_countries(),
				'is_pay_for_order_page' => is_wc_endpoint_url( 'order-pay' ) ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Customize the script data.
	 *
	 * @since 12.6.3 Integrity attribute added.
	 * @param string $tag    The script tag.
	 * @param string $handle The handler.
	 * @param string $src    The script source.
	 *
	 * @return string
	 */
	public function customize_script( $tag, $handle, $src ) {
		$sri = array(
			'woocommerce-novalnet-gateway-script'        => 'sha384-8ZYWJ8Q/m/pjhu2t/z2KTywdiMNrCkltZbLWohy3W6jCtxiuKkc5IHhqyxCV9LtF',
			'woocommerce-novalnet-gateway-admin-script'  => 'sha384-RP5Sq75QLT68HQ5cNqhKaq5H2G4SHhmAWNEiUKkWQMdsQkhdjjiQVIcousFJFhbb',
			'woocommerce-novalnet-gateway-cc-script'     => 'sha384-3ZHfvOQB6dn7UBPIsOKcDZktjQz0NSPoInNhZOtXv4f75IggqcBGB1RrV+93utiI',
			'woocommerce-novalnet-gateway-subscription-script' => 'sha384-5frcLecRDKnrzRRixoMQPeMmZ6KrO736KrXk3k5Va0FjhCJT6yMi/0R3qnp9eLDn',
			'woocommerce-novalnet-gateway-wallet-script' => 'sha384-CXS8pk5VPzsnxdT47+VBfB8N4DGNWbK9HV3JMk4PwcDttR/pR9mnu2Q+4CMLcqZH',
			// WC Block JS integrity.
			'wc-novalnet-block-inputs'                   => 'sha384-EskEihDi274GJc7sSW9GwGrHQE3Ka1C5hv9XOLKKNXH8i5XjpbKuFWT2wXmt5pe+',
			'wc-novalnet-ach-blocks-integration'         => 'sha384-Bmy92vsHBrvobxOMCCTPPKEOGEcyA7e0sArshNPVJuvOeFcxOpWE/ydwLgbvPOE9',
			'wc-novalnet-alipay-blocks-integration'      => 'sha384-QtCElQkKcvHcCYrk8uLP90WzMJ+qZiW4OC6cdGpe7lp7W7XmRMLzWu+rTOSla6uv',
			'wc-novalnet-applepay-blocks-integration'    => 'sha384-vi4VykNLzIjISVf6DUmG3FuNBCL1kXuILy0u2Locf0lRXO775WNyAz78WhINWmGL',
			'wc-novalnet-bancontact-blocks-integration'  => 'sha384-KPM7PQGu/mQaXTThAFZ5h7wnAfnmuIo+H/48RasycappxtLjUmx1OPYZgot//726',
			'wc-novalnet-cc-blocks-integration'          => 'sha384-F8zD2dTXrgnyUIPNkh9p6FYTRoPYHb/HXhj5FIzLsbNh33vFnp+tfYIIDLbsNGLC',
			'wc-novalnet-eps-blocks-integration'         => 'sha384-htnO0AI56CywkfrmXJmONTPGkEvpp2OIFCCoifPtGWtEJGubsxixOxgAxIC+DkSV',
			'wc-novalnet-twint-blocks-integration'       => 'sha384-Ex8xPFmmKdPHtXN61aJu10neu12WQK6PkpSSEunQJrFwOjuH5VuP5KWbeUu8HeTr',
			'wc-novalnet-googlepay-blocks-integration'   => 'sha384-Bz2pPowHvJ8CySS3nJFkIBuzWbMd7PIZWHjrCW5ZvndnYgrM+AJTHf3kQSdCkxmZ',
			'wc-novalnet-guaranteed-invoice-blocks-integration' => 'sha384-Ztzv1UF02W+v8nwcAG4t3V3oHBKXqM1vO+H2FYnhUS642+xlCHpX6WDC3JnJ6/pB',
			'wc-novalnet-guaranteed-sepa-blocks-integration' => 'sha384-TXRV9d7xcB0qQc24AjC2aYsqhMr2k74lf0JrEPnd66Xo3U1emMQ4bP5FP3Vo87QT',
			'wc-novalnet-ideal-blocks-integration'       => 'sha384-vxnvoHpxeQGivWXOfGkKNZenC7BHs79kRfehwvV6mtr2QObmpdyiboY++iD/1vmC',
			'wc-novalnet-instalment-invoice-blocks-integration' => 'sha384-NDxghaccAxWnVWyfmI5iufH+3/f0/xfchxQQ+J0yScx3P6wDoLGOjlMed/01nkEe',
			'wc-novalnet-instalment-sepa-blocks-integration' => 'sha384-r7I31jkLgXc/bkRuBSrwGdXxQArbEQBDeb7B2P6QaKhZjvNagyiEKISlvHVjh2rh',
			'wc-novalnet-invoice-blocks-integration'     => 'sha384-5v91/uq+TH4FS9RMKZJgBwD49INgIRUr9Wx42fSkVKEcMrzNPW6CbBwQTMqxVHbr',
			'wc-novalnet-multibanco-blocks-integration'  => 'sha384-Xu+zQJjhaoOHrFvsAJyggsOJ5/o4ll6dXY7iCQLygNG0KYKBjfHXZ2yioziMid9u',
			'wc-novalnet-online-bank-transfer-blocks-integration' => 'sha384-v0HJByHPsgI9PcWMSj3YtGm/YgciRQH06w1+3vz9nPVIdz6+1WP1/v7cDVTio4XW',
			'wc-novalnet-paypal-blocks-integration'      => 'sha384-HDzF5F3aE+1v5OFkrpZtf648N3kaSVBIj+JvzF4URSow3VLfpnVdr/UHDVrYQ+dh',
			'wc-novalnet-postfinance-blocks-integration' => 'sha384-i4M730XUFTgE+SKcZ0oh2p/OOKmWjcc8wZibIy4FKoLZhiYbgO+tRKQ+Zk6Yk3Xu',
			'wc-novalnet-postfinance-card-blocks-integration' => 'sha384-wi2xNtJsnzEJH0HrELn7jaMEPmrvcYtHJmKj/xwRprZ9HO0xMhUN2N3aRMVlHWVy',
			'wc-novalnet-prepayment-blocks-integration'  => 'sha384-arywJ0d3AkvjZqFBIAA6cHHNuzsDbfzxW5LePjw214hjuWCKmmJzV1foFg2omwc+',
			'wc-novalnet-przelewy24-blocks-integration'  => 'sha384-Nk6kffPZbnDdGkR42K58jDbg0+8nsnXzD6v0ZkSCZ5HH7sam9ecTLKllvwHa2R9I',
			'wc-novalnet-sepa-blocks-integration'        => 'sha384-dA7zl0Nq+77ixr4dJO1XV0uyt3c0btaILEOOZIapU2wlp1h3hTYN4NS/UAEG3hWe',
			'wc-novalnet-trustly-blocks-integration'     => 'sha384-+CrMzrjzg4bK4Udj+NmXMspiC+SF+CC24zMX0dRTtp41wlHKMhIWHUnFhVTdZXKY',
			'wc-novalnet-wechatpay-blocks-integration'   => 'sha384-qqs/HxfDcF0/oR56J9Nyko9ZpahoAPjaPhKUO7P/dBxFxcwVdl6f2m9HqUVJ0LZu',
			'wc-novalnet-mbway-blocks-integration'       => 'sha384-eScqKFqdUvh9DlBYBjjRFmtZAMmY98yPNFteFzMrgWbmUkODDGatm1z0g3XiH9fQ',
			'wc-novalnet-payconiq-blocks-integration'    => 'sha384-YvqEgrxo1HFh1JKqY8yDc78vN12fRdNkXQvssxxl+UaK+KrsN2RUBoYdWNOWOSGp',
			'wc-novalnet-blik-blocks-integration'        => 'sha384-cnRPjj/mX4+pF3Oxr41ubDFU/vQrPbFVGkBJvHPqiGn/4Kx1yy38rrA72nK/S96p',
		);
		
		if ( in_array( $handle, array_keys( $sri ), true ) && isset( $sri[ $handle ] ) ) {
			return preg_replace( '/(<script\b[^><]*)>/i', '$1 integrity="' . $sri[ $handle ] . '" crossorigin="anonymous">', $tag );
		}

		return $tag;
	}

	/**
	 * Customize the style sheet data.
	 *
	 * @since 12.6.3
	 * @param string $tag    The link tag.
	 * @param string $handle The handler.
	 * @param string $href   The style sheet src.
	 * @param string $media  The media.
	 *
	 * @return string
	 */
	public function customize_style_sheet( $tag, $handle, $href, $media ) {
		if ( 'woocommerce-novalnet-gateway-css' === $handle ) {
			return preg_replace( '/(<link\b[^><]*)>/i', '$1  integrity="sha384-tIaHiCP3FuQ0+i5HmhBxwwRr/5mc2XqulFazRE/ZLe+utg2J7t4rbjCn8jKe4QN1" crossorigin="anonymous">', $tag );
		}
		return $tag;
	}

         /**
	 * Restrict instant order email.
	 *
	 * @since 12.0.0
	 * @param string $value  Return value.
	 *
	 * @return string
	 */
	public function restrict_instant_email( $value ) {

		if (
			( isset( $this->request['payment_method'] ) && WC_Novalnet_Validation::check_string( $this->request['payment_method'] ) )
			|| isset( $this->request['tid'] )
			|| ( isset( $this->request['action'] ) && 'novalnet_order_creation' === $this->request['action'] )
		) {
			$value = false;
		}


		return $value;
	}


	/**
	 * Retrieve the Novalnet payment type.
	 *
	 * @since 12.0.0
	 * @param string $payment_type The payment type value.
	 *
	 * @return array
	 */
	public function get_payment_types( $payment_type = '' ) {

		if ( '' !== $payment_type ) {
			return $this->payments[ $payment_type ];
		}
		return $this->payments;
	}

	/**
	 * Returns the supported Novalnet payment based on process
	 *
	 * @since 12.0.0
	 * @param string $process      The process/feature.
	 * @param string $payment_type The payment type need to be checked.
	 *
	 * @return array
	 */
	public function get_supports( $process, $payment_type = '' ) {

		if ( ! empty( $this->supports[ $process ] ) ) {
			if ( '' !== $payment_type ) {
				return in_array( $payment_type, $this->supports[ $process ], true );
			}
			return $this->supports[ $process ];
		}
		return array();
	}

	/**
	 * Adds Novalnet gateway to WooCommerce.
	 *
	 * @since 12.0.0
	 *
	 * @param  array $methods The gateway methods.
	 * @return array
	 */
	public function add_novalnet_payments( $methods ) {

		// Set Available Novalnet gateways.
		$payment_types = array_keys( $this->get_payment_types() );
		foreach ( $payment_types as $payment_type ) {
			$novalnet_methods[] = wc_novalnet_get_class_name( $payment_type );
		}

		$methods = array_merge( $novalnet_methods, $methods );
		return $methods;
	}

	/**
	 * Including Webhook Hanlder
	 *
	 * @since 12.0.0
	 */
	public function handle_webhook_process() {
		include_once dirname( __FILE__ ) . '/includes/class-wc-novalnet-webhook.php';
	}

	/**
	 * Align transaction info in "myaccount" page.
	 *
	 * @since 12.0.0
	 * @param int      $item_id The item id.
	 * @param array    $item    The item data.
	 * @param WC_Order $order   The order object.
	 */
	public function align_transaction_info( $item_id, $item, $order ) {

		if ( WC_Novalnet_Validation::check_string( $order->get_payment_method() ) && $order->get_customer_note() ) {
			$order->set_customer_note( wpautop( $order->get_customer_note() ) );
		}
	}

	/**
	 * Get helper function class.
	 *
	 * @since 12.0.0
	 *
	 * @return WC_Novalnet_Helper
	 */
	public function helper() {
		return WC_Novalnet_Helper::instance();
	}

	/**
	 * Get DB class.
	 *
	 * @since 12.0.0
	 *
	 * @return WC_Novalnet_DB_Handler
	 */
	public function db() {
		return WC_Novalnet_DB_Handler::instance();
	}
}
