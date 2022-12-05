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
		'novalnet_alipay'               => 'ALIPAY',
		'novalnet_multibanco'           => 'MULTIBANCO',
	);

	/**
	 * Supported payment types based on process.
	 *
	 * @var array
	 */
	private $supports = array(
		'tokenization'     => array(
			'novalnet_cc',
			'novalnet_sepa',
			'novalnet_guaranteed_sepa',
			'novalnet_instalment_sepa',
		),
		'subscription'     => array(
			'novalnet_cc',
			'novalnet_sepa',
			'novalnet_guaranteed_sepa',
			'novalnet_paypal',
			'novalnet_invoice',
			'novalnet_guaranteed_invoice',
			'novalnet_prepayment',
			'novalnet_googlepay',
			'novalnet_applepay',
		),
		'authorize'        => array(
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
		'amount_update'    => array(
			'novalnet_guaranteed_sepa',
			'novalnet_guaranteed_invoice',
			'novalnet_sepa',
			'novalnet_invoice',
			'novalnet_prepayment',
			'novalnet_barzahlen',
		),
		'instalment'       => array(
			'novalnet_instalment_sepa',
			'novalnet_instalment_invoice',
		),
		'guarantee'        => array(
			'novalnet_guaranteed_sepa',
			'novalnet_guaranteed_invoice',
		),
		'pay_later'        => array(
			'novalnet_invoice',
			'novalnet_prepayment',
			'novalnet_barzahlen',
			'novalnet_multibanco',
		),
		'invoice_payments' => array(
			'novalnet_invoice',
			'novalnet_instalment_invoice',
			'novalnet_guaranteed_invoice',
		),
		'sepa_payments'    => array(
			'novalnet_sepa',
			'novalnet_instalment_sepa',
			'novalnet_guaranteed_sepa',
		),

	);

	/**
	 * Novalnet plugin URL
	 *
	 * @var $plugin_url
	 */
	public $plugin_url;

	/**
	 * Store the POST/GET request
	 *
	 * @var array
	 */
	public $request;

	/**
	 * The single instance of the class.
	 *
	 * @var Novalnet The single instance of the class.
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

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action( 'admin_notices', array( $this, 'add_admin_notices' ) );
			return;
		}

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

		// Add plugin scripts (front-end).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );

		// Restrict instant mail from Germanized plugin.
		add_filter( 'woocommerce_gzd_instant_order_confirmation', array( $this, 'restrict_instant_email' ) );

		// Update Novalnet settings.
		add_action( 'woocommerce_update_options_novalnet-settings', array( 'WC_Novalnet_Configuration', 'update_novalnet_settings' ) );

		// Add Novalnet settings tab.
		add_action( 'woocommerce_settings_tabs_novalnet-settings', array( 'WC_Novalnet_Configuration', 'novalnet_settings_page' ) );

		// Customize script data.
		add_filter( 'script_loader_tag', array( $this, 'customize_script' ), 10, 3 );

		// add virtual product in to the cart.
		add_action( 'wp_ajax_add_virtual_product_in_cart', array( $this, 'add_virtual_product_in_cart' ) );
		add_action( 'wp_ajax_nopriv_add_virtual_product_in_cart', array( $this, 'add_virtual_product_in_cart' ) );

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
		// Get wallet settings.
		$data['wallet_area']       = 'product_page';
		$data['available_wallets'] = get_available_wallets( 'product_page' );
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
		$data['available_wallets'] = get_available_wallets( 'guest_checkout_page' );
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
		$data['available_wallets'] = get_available_wallets( 'checkout_page' );
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
		$data['available_wallets'] = get_available_wallets( 'mini_cart_page' );
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
		$data['available_wallets'] = get_available_wallets( 'shopping_cart_page' );
		if ( count( $data['available_wallets'] ) > 0 ) {
			novalnet()->helper()->load_template( 'render-wallet-button.php', $data );
		}
	}

	/**
	 * Shipping address update in wallet page.
	 *
	 * @since 12.4.0
	 */
	public function novalnet_shipping_address_update() {

		global $woocommerce;

		if ( in_array( novalnet()->request['source_page'], array( 'mini_cart_page_googlepay_button', 'mini_cart_page_applepay_button', 'product_page_googlepay_button', 'product_page_applepay_button' ), true ) ) {
			if ( empty( novalnet()->request['variable_variant_id'] ) ) {
				$product_id = array();
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$product_id[] = $cart_item['product_id'];
				}
				if ( ! in_array( (int) novalnet()->request['simple_product_id'], $product_id, true ) ) {
					WC()->cart->add_to_cart( novalnet()->request['simple_product_id'] );
				}
			}

			if ( ! empty( novalnet()->request['variable_product_id'] ) && ! empty( novalnet()->request['variable_variant_id'] ) ) {
				$variation_id = array();
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$variation_id[] = $cart_item['variation_id'];
				}

				if ( ! in_array( (int) novalnet()->request['variable_variant_id'], $variation_id, true ) ) {
					WC()->cart->add_to_cart( novalnet()->request['variable_product_id'], 1, novalnet()->request['variable_variant_id'] );
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
				$signup_fee = get_post_meta( $values['data']->get_id(), '_subscription_sign_up_fee', 1 );
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

		$received_shipping_address = json_decode( novalnet()->request['shippingInfo'], true );

		WC()->customer->set_shipping_city( wc_clean( $received_shipping_address['address']['locality'] ) );
		WC()->customer->set_shipping_postcode( wc_clean( $received_shipping_address['address']['postalCode'] ) );
		WC()->customer->set_shipping_country( wc_clean( $received_shipping_address['address']['countryCode'] ) );
		WC()->customer->set_shipping_state( wc_clean( $received_shipping_address['address']['administrativeArea'] ) );
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		$packages = WC()->shipping()->get_packages();

		$shipping_details = array();
		$count            = 1;

		foreach ( $packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
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

		$shipping_ids = array();
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
						$package           = WC_Subscriptions_Cart::get_calculated_shipping_for_package( $recurring_cart_package );
						$shipping_packages = $package['rates'];
					}
				}
			}
			$methods_id = array();
			foreach ( $shipping_details as $shipments ) {
				$methods_id [] = $shipments['identifier'];
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

		$received_shipping_method = json_decode( novalnet()->request['shippingInfo'], true );
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
				$signup_fee = get_post_meta( $values['data']->get_id(), '_subscription_sign_up_fee', 1 );
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
					'amount' => '-' . wc_novalnet_amount_as_string( $coupon_obj->amount ),
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

		if ( empty( novalnet()->request['variable_variant_id'] ) ) {
			$product_id = array();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id[] = $cart_item['product_id'];
			}

			if ( ! in_array( (int) novalnet()->request['simple_product_id'], $product_id, true ) ) {
				WC()->cart->add_to_cart( novalnet()->request['simple_product_id'] );
			}
		}

		if ( ! empty( novalnet()->request['variable_product_id'] ) && ! empty( novalnet()->request['variable_variant_id'] ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$variation_id[] = $cart_item['variation_id'];
			}

			if ( ! in_array( (int) novalnet()->request['variable_variant_id'], $variation_id, true ) ) {
				WC()->cart->add_to_cart( novalnet()->request['variable_product_id'], 1, novalnet()->request['variable_variant_id'] );
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

		$token_name = 'novalnet_' . novalnet()->request['payment'] . '_token';
		if ( WC()->session->__isset( $token_name ) ) {
			WC()->session->__unset( $token_name );
		}
		WC()->session->set( $token_name, novalnet()->request['variable_name']['response']['transaction']['token'] );

		if ( WC()->session->__isset( 'googlepay_do_redirect' ) ) {
			WC()->session->__unset( 'googlepay_do_redirect' );
		}
		if ( ! empty( novalnet()->request['variable_name']['response']['transaction']['doRedirect'] ) ) {
			WC()->session->set( 'googlepay_do_redirect', novalnet()->request['variable_name']['response']['transaction']['doRedirect'] );
		}

		if ( isset( novalnet()->request['pay_for_order_id'] ) && ! empty( novalnet()->request['pay_for_order_id'] ) ) {
			// Process Payment.
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$payment_method     = 'novalnet_' . novalnet()->request['payment'];

			$settings             = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_' . novalnet()->request['payment'] );
			$payment_text         = WC_Novalnet_Configuration::get_payment_text( 'novalnet_' . novalnet()->request['payment'] );
			$payment_method_title = wc_novalnet_get_payment_text( $settings, $payment_text, wc_novalnet_shop_language(), 'novalnet_' . novalnet()->request['payment'], 'title' );
			$wc_order             = wc_get_order( wc_clean( novalnet()->request['pay_for_order_id'] ) );
			$wc_order->set_payment_method_title( $payment_method_title );
			$wc_order->set_payment_method( 'novalnet_' . novalnet()->request['payment'] );
			$wc_order->save();

			$result = $available_gateways[ $payment_method ]->process_payment( novalnet()->request['pay_for_order_id'] );
			wp_send_json( $result );
		} else {
			$required_fields = array( 'firstName', 'lastName', 'addressLines', 'postalCode', 'locality', 'countryCode' );
			$missing_fields  = false;
			$address         = '';

			foreach ( $required_fields as $key ) {
				if ( 'addressLines' === $key ) {
					if ( empty( trim( novalnet()->request['variable_name']['response']['order']['billing']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Billing';
						$missing_fields = true;
					}
					if ( isset( novalnet()->request['variable_name']['response']['order']['shipping']['firstname'] ) && empty( trim( novalnet()->request['variable_name']['response']['order']['shipping']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Shipping';
						$missing_fields = true;
					}
				} else {
					if ( empty( trim( novalnet()->request['variable_name']['response']['order']['billing']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Billing';
						$missing_fields = true;
					}
					if ( isset( novalnet()->request['variable_name']['response']['order']['shipping']['firstname'] ) && empty( trim( novalnet()->request['variable_name']['response']['order']['shipping']['contact'][ $key ], ' ' ) ) ) {
						$address        = 'Shipping';
						$missing_fields = true;
					}
				}

				if ( $missing_fields ) {
					wp_send_json(
						array(
							'result'   => 'error',
							'redirect' => $address . ' ' . $key . ' is required fields',
						)
					);
				}
			}

			$received_address = novalnet()->request['variable_name']['response']['order'];

			$billing_firstname = ! empty( novalnet()->request['variable_name']['response']['order']['billing']['contact']['firstName'] ) ? novalnet()->request['variable_name']['response']['order']['billing']['contact']['firstName'] : '';
			$billing_lastname  = ! empty( novalnet()->request['variable_name']['response']['order']['billing']['contact']['lastName'] ) ? novalnet()->request['variable_name']['response']['order']['billing']['contact']['lastName'] : '';

			if ( isset( novalnet()->request['variable_name']['response']['order']['shipping']['contact']['firstName'] ) || isset( novalnet()->request['variable_name']['response']['order']['shipping']['contact']['lastName'] ) ) {
				$shipping_firstname = ! empty( novalnet()->request['variable_name']['response']['order']['shipping']['contact']['firstName'] ) ? novalnet()->request['variable_name']['response']['order']['shipping']['contact']['firstName'] : '';
				$shipping_lastname  = ! empty( novalnet()->request['variable_name']['response']['order']['shipping']['contact']['lastName'] ) ? novalnet()->request['variable_name']['response']['order']['shipping']['contact']['lastName'] : '';
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
		$payment_method         = 'novalnet_' . novalnet()->request['payment'];
		$data['payment_method'] = $payment_method;

		$items                = $woocommerce->cart->get_cart();
		$cart_has_subs        = 0;
		$subscription_product = '';
		foreach ( $items as $item => $values ) {
			$_product = wc_get_product( $values['data']->get_id() );
			if ( in_array( $_product->get_type(), array( 'subscription', 'subscription_variation', 'variable-subscription' ), true ) ) {
				$cart_has_subs        = 1;
				$subscription_product = $_product;
			}
		}

			// Check subscription condition.
		if ( $cart_has_subs ) {
			if ( is_user_logged_in() ) {
				if ( ! class_exists( 'WC_Subscriptions_Checkout' ) ) {
					wp_send_json(
						array(
							'result'   => 'error',
							'redirect' => 'WooCommerce Subscription not installed properly.',
						)
					);
				}
			} else {
				wp_send_json(
					array(
						'result'   => 'error',
						'redirect' => 'Please login and try again.',
					)
				);
			}
		} elseif ( wc_novalnet_amount( WC()->cart->total ) > 0 ) {
			$payment_method         = 'novalnet_' . novalnet()->request['payment'];
			$data['payment_method'] = $payment_method;
		}

		$data['billing_email'] = $customer_billing['email'];
		$order_id              = $checkout->create_order( $data );

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

		update_post_meta( $wc_order->get_id(), '_customer_user', get_current_user_id() );

		if ( 'billing' === get_option( 'woocommerce_tax_based_on' ) ) {
			$wc_order->calculate_totals();
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
					// Create manual subcription for the wallet.
					WC_Subscriptions_Checkout::process_checkout( $wc_order, $_POST ); // phpcs:ignore.
				}
			} else {
				$wc_order->update_status( 'failed' );
				$wc_order->add_order_note( 'To purchase subscription product signin your account then try again.' );
				$wc_order->set_customer_note( str_replace( PHP_EOL, '<\br>', 'To purchase subscription product signin your account then try again.' ) );
				wp_send_json(
					array(
						'result'   => 'error',
						'redirect' => 'Please login and try again.',
					)
				);
			}
		}

		// Process Payment.
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$result             = $available_gateways[ $payment_method ]->process_payment( $wc_order->get_id() );
		wp_send_json( $result );
	}


	/**
	 * Show notice if WooCommerce is not active.
	 *
	 * @since 12.0.0
	 */
	public function add_admin_notices() {
		echo '<div id="notice" class="error"><p>' . sprintf(
			wp_kses(
				/* translators: %1$s: anchor tag starts %2$s: anchor tag ends */
				__( 'The <b>Novalnet payment plugin</b> for WooCommerce will function only if the WooCommerce plugin is active.Please %1$s install and activate WooCommerce plugin %2$s ', 'woocommerce-novalnet-gateway' ),
				array(
					'b' => array(),
				)
			),
			'<a href="http://www.woothemes.com/woocommerce/" target="_new">',
			'</a>'
		) . '</p></div>';
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
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script', 'https://cdn.novalnet.de/js/v3/payment.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script-payment', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		}

		// Enqueue script.
		wp_enqueue_script( 'woocommerce-novalnet-gateway-wallet-script', novalnet()->plugin_url . '/assets/js/novalnet-wallet.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );

		wp_localize_script(
			'woocommerce-novalnet-gateway-wallet-script',
			'my_ajax_object',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'applepay_setting'  => WC_Novalnet_Configuration::get_payment_settings( 'novalnet_applepay' ),
				'googlepay_setting' => WC_Novalnet_Configuration::get_payment_settings( 'novalnet_googlepay' ),
				'locale'            => get_locale(),
				'client_key'        => WC_Novalnet_Configuration::get_global_settings( 'client_key' ),
			)
		);

		// Enqueue script in front-end.
		wp_enqueue_script( 'woocommerce-novalnet-gateway-script', $this->plugin_url . '/assets/js/novalnet.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		wp_enqueue_style( 'woocommerce-novalnet-gateway-css', $this->plugin_url . '/assets/css/novalnet.css', array(), NOVALNET_VERSION, false );
		wp_localize_script(
			'woocommerce-novalnet-gateway-script',
			'wc_novalnet_data',
			array(
				'dob_error'          => __( 'Please enter valid birth date', 'woocommerce-novalnet-gateway' ),
				'sepa_account_error' => __( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ),
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Restrict instant order email.
	 *
	 * @since 12.0.0
	 * @param string $tag    The script tag.
	 * @param string $handle The handler.
	 * @param string $src    The script source.
	 *
	 * @return string
	 */
	public function customize_script( $tag, $handle, $src ) {

		if ( 'woocommerce-novalnet-gateway-external-script-barzahlen' === $handle ) {
			$data = explode( '?', $src );
			if ( ! empty( $data['1'] ) ) {
				$args = array();
				wp_parse_str( $data['1'], $args );
				if ( ! empty( $args['token'] ) ) {
					$tag = str_replace( ' src=', ' data-token="' . $args['token'] . '" class="bz-checkout" src=', $tag );
				}
			}
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

		if ( ( isset( $this->request['payment_method'] ) && WC_Novalnet_Validation::check_string( $this->request['payment_method'] ) ) || isset( $this->request['tid'] ) ) {
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
			return $this->payments [ $payment_type ];
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
			$novalnet_methods [] = wc_novalnet_get_class_name( $payment_type );
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
	 * @return Novalnet_Helper
	 */
	public function helper() {

		return WC_Novalnet_Helper::instance();
	}

	/**
	 * Get DB class.
	 *
	 * @since 12.0.0
	 *
	 * @return Novalnet_Helper
	 */
	public function db() {

		return WC_Novalnet_DB_Handler::instance();
	}
}
