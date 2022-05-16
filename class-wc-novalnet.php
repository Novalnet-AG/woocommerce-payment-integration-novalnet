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
		'novalnet_sepa'               => 'DIRECT_DEBIT_SEPA',
		'novalnet_guaranteed_sepa'    => 'GUARANTEED_DIRECT_DEBIT_SEPA',
		'novalnet_cc'                 => 'CREDITCARD',
		'novalnet_applepay'           => 'APPLEPAY',
		'novalnet_invoice'            => 'INVOICE',
		'novalnet_guaranteed_invoice' => 'GUARANTEED_INVOICE',
		'novalnet_prepayment'         => 'PREPAYMENT',
		'novalnet_ideal'              => 'IDEAL',
		'novalnet_instantbank'        => 'ONLINE_TRANSFER',
		'novalnet_giropay'            => 'GIROPAY',
		'novalnet_barzahlen'          => 'CASHPAYMENT',
		'novalnet_przelewy24'         => 'PRZELEWY24',
		'novalnet_eps'                => 'EPS',
		'novalnet_instalment_invoice' => 'INSTALMENT_INVOICE',
		'novalnet_instalment_sepa'    => 'INSTALMENT_DIRECT_DEBIT_SEPA',
		'novalnet_paypal'             => 'PAYPAL',
		'novalnet_postfinance_card'   => 'POSTFINANCE_CARD',
		'novalnet_postfinance'        => 'POSTFINANCE',
		'novalnet_bancontact'         => 'BANCONTACT',
		'novalnet_multibanco'         => 'MULTIBANCO',
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
			'novalnet_paypal',
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
		),
		'authorize'        => array(
			'novalnet_cc',
			'novalnet_applepay',
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
	protected static $_instance = null;

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

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
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
		$this->request = $_REQUEST; // WPCS: input var okay, CSRF ok.

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

		// register the ajax action for authenticated users.
		add_action('wp_ajax_novalnet_order_creation', array( $this, 'novalnet_order_creation'));
		add_action('wp_ajax_nopriv_novalnet_order_creation', array( $this, 'novalnet_order_creation'));

		// Check shipping address.
		add_action('wp_ajax_novalnet_shipping_address_update', array( $this, 'novalnet_shipping_address_update'));
		add_action('wp_ajax_nopriv_novalnet_shipping_address_update', array( $this, 'novalnet_shipping_address_update'));
		
		// register the ajax action for authenticated users.
		add_action('wp_ajax_novalnet_shipping_method_update', array( $this, 'novalnet_shipping_method_update'));
		add_action('wp_ajax_nopriv_novalnet_shipping_method_update', array( $this, 'novalnet_shipping_method_update'));

		// Get applepay settings.
		$settings = WC_Novalnet_Configuration::get_payment_settings( 'novalnet_applepay' );
		if( 'yes' == $settings['enabled'] && ( is_array( $settings['display_applepay_button_on'] ) && in_array( 'mini_cart_page', $settings['display_applepay_button_on'] ) ) ) {
			// Add applepay button in minicart page.
			add_action( 'woocommerce_widget_shopping_cart_after_buttons', array( $this, 'add_minicart_apple_pay_button'), 1000 );
		}

		if( 'yes' == $settings['enabled'] && ( is_array( $settings['display_applepay_button_on'] ) && in_array( 'product_page', $settings['display_applepay_button_on'] ) ) ) {
			// Add applepay button in product page.
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'add_product_page_apple_pay_button'), 1000 );
		}

		if( 'yes' == $settings['enabled'] && ( is_array( $settings['display_applepay_button_on'] ) && in_array( 'guest_checkout_page', $settings['display_applepay_button_on'] ) ) ) {
			// Add applepay button in myaccount page.
			add_action( 'woocommerce_before_customer_login_form', array( $this, 'add_myaccount_page_apple_pay_button'), 1000 );
		}

		if( 'yes' == $settings['enabled']  && ( is_array( $settings['display_applepay_button_on'] ) && in_array( 'checkout_page', $settings['display_applepay_button_on'] ) ) ) {
			// Add applepay button in checkout page.
			add_action( 'woocommerce_before_checkout_form', array( $this, 'add_checkout_page_apple_pay_button'), 1000 );
		}

		if( 'yes' == $settings['enabled'] && ( is_array( $settings['display_applepay_button_on'] ) && in_array( 'shopping_cart_page', $settings['display_applepay_button_on'] ) ) ) {
			// Add applepay button in cart page.
			add_action( 'woocommerce_proceed_to_checkout', array( $this, 'woocommerce_cart_applepay_button' ), 1000);
		}
	}

	public function woocommerce_cart_applepay_button(){
		$data['apple_pay_button'] = 'cart_page_apple_pay_button';
		novalnet()->helper()->load_template( 'render-apple-pay-button.php', $data );
	}

	public function add_minicart_apple_pay_button() {
		$data['apple_pay_button'] = 'minicart_apple_pay_button';
		novalnet()->helper()->load_template( 'render-apple-pay-button.php', $data );
	}

	public function add_checkout_page_apple_pay_button() {
		$data['apple_pay_button'] = 'checkout_apple_pay_button';
		novalnet()->helper()->load_template( 'render-apple-pay-button.php', $data );
	}

	public function add_product_page_apple_pay_button() {

		global $product;
		if( 'subscription' != $product->get_type() ) {
			$data['apple_pay_button'] = 'product_details_page_apple_pay_button';
			novalnet()->helper()->load_template( 'render-apple-pay-button.php', $data );
		}
	}

	public function add_myaccount_page_apple_pay_button() {
		if( 0 < WC()->cart->total ) {
			$data['apple_pay_button'] = 'myaccount_page_apple_pay_button';
			novalnet()->helper()->load_template( 'render-apple-pay-button.php', $data );
		}
	}

	public function novalnet_shipping_address_update(){

		global $woocommerce;

		if( 'product_details_page_apple_pay_button' == novalnet()->request['source_page'] && ! empty ( novalnet()->request['variable_product_id'] ) && ! empty ( novalnet()->request['variable_variant_id'] ) ) {
			foreach( WC()->cart->get_cart() as $cart_item ){
				$variation_id[] = $cart_item['variation_id'];
			}

			if( ! in_array( novalnet()->request['variable_variant_id'], $variation_id) ) {
				WC()->cart->add_to_cart( novalnet()->request['variable_product_id'], 1, novalnet()->request['variable_variant_id'] );
			}
		} else if ( ! empty ( novalnet()->request['simple_product_id'] ) ){
			$product_id = [];
			foreach( WC()->cart->get_cart() as $cart_item ){
				$product_id[] = $cart_item['product_id'];
			}
			if( ! in_array( novalnet()->request['simple_product_id'], $product_id) ) {
				WC()->cart->add_to_cart( novalnet()->request['simple_product_id'] );
			}
		}

		$items = $woocommerce->cart->get_cart();
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

			$articleDetails[] = array('label'=> $product_detals, 'amount' => wc_novalnet_applepay_amount_as_string( $total ));
		}

		$received_shipping_address = json_decode(novalnet()->request['shippingInfo'], true);

		WC()->customer->set_shipping_city(wc_clean( $received_shipping_address['address']['locality'] )); 
		WC()->customer->set_shipping_postcode(wc_clean( $received_shipping_address['address']['postalCode'] )); 
		WC()->customer->set_shipping_country(wc_clean( $received_shipping_address['address']['countryCode'] ));
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		$packages = WC()->shipping()->get_packages();

		$shippingDetails = [];
		$count = 1;

		foreach ( $packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			foreach ( $package['rates'] as $values) {
				$shipping_total = wc_novalnet_applepay_amount( $values->cost );
				if ( $count == 1 ) {
					$articleDetails[] = array('label'=> $values->label, 'amount' => wc_novalnet_applepay_amount_as_string( $shipping_total ) );
					WC()->session->set('chosen_shipping_methods', array( $values->id ) );
				}
				$shippingDetails[] = array('label'=> $values->label, 'amount' => wc_novalnet_applepay_amount_as_string( $shipping_total ), 'identifier' => $values->id, 'detail' => '' );
				$count++;
			}
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$cart_tax_amount = 0;
		foreach ( WC()->cart->get_taxes() as $tax_amount ) {
			$cart_tax_amount +=  $tax_amount;
		}

		if ( $cart_tax_amount > 0 ) {
			$articleDetails[] = array('label'=> 'Tax', 'amount' => wc_novalnet_applepay_amount_as_string( $cart_tax_amount ) );
		}

		$applied_coupon = WC()->cart->get_applied_coupons();
		foreach( $applied_coupon as $coupon )
		if( ! empty ( $coupon ) ) {
			$coupon_obj = new WC_Coupon($coupon);
			$articleDetails[]=array('label'=> 'discount('.$coupon.')', 'amount' => '-'.wc_novalnet_applepay_amount_as_string( WC()->cart->get_coupon_discount_amount( $coupon_obj->get_code(), WC()->cart->display_cart_ex_tax ) ) );
		}

		$total = wc_novalnet_applepay_amount( WC()->cart->total );

		$shipping_address_change = array( 'amount'=> wc_novalnet_applepay_amount_as_string( $total ), 'shipping_address' => $shippingDetails, 'article_details' => $articleDetails );
		wp_send_json($shipping_address_change);
	}

	public function novalnet_shipping_method_update(){

		global $woocommerce;

		$received_shipping_method = json_decode(novalnet()->request['shippingInfo'], true);
		$items = $woocommerce->cart->get_cart();

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
			$total = wc_novalnet_applepay_amount( $total ); 
			$product_detals = $_product->get_name() . ' (' . $values['quantity'].' X '. $product_price . ')';
			$articleDetails[] = array('label'=> $product_detals, 'amount' => wc_novalnet_applepay_amount_as_string( $total ) );
		}

		if( count( WC()->session->get('shipping_for_package_0')['rates'] ) > 0 ){
			foreach( WC()->session->get('shipping_for_package_0')['rates'] as $rate_id =>$rate) {
				if($rate->id == $received_shipping_method['shippingMethod']['identifier']){
					$default_rate_id = array( $rate_id );
					break;
				}
			}

			WC()->session->set('chosen_shipping_methods', $default_rate_id );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$cart_tax_amount = 0;
		foreach ( WC()->cart->get_taxes() as $tax_amount ) {
			$cart_tax_amount +=  $tax_amount;
		}

		if ( $cart_tax_amount > 0 ) { 
			$articleDetails[] = array( 'label'=> 'Tax', 'amount' => wc_novalnet_applepay_amount_as_string( $cart_tax_amount ) );
		} 

		$articleDetails[] = array( 'label'=> $received_shipping_method['shippingMethod']['label'], 'amount' => wc_novalnet_applepay_amount_as_string( $received_shipping_method['shippingMethod']['amount'] ) );

		$applied_coupon = WC()->cart->get_applied_coupons();
		foreach( $applied_coupon as $coupon )
		if( ! empty ( $coupon ) ) {
			$coupon_obj = new WC_Coupon($coupon);
			$articleDetails[]=array('label'=> 'discount('.$coupon.')', 'amount' => '-'.wc_novalnet_applepay_amount_as_string( $coupon_obj->amount ) );
		}

		$total = wc_novalnet_applepay_amount( WC()->cart->total );
		$shipping_method_change = array( 'amount'=> wc_novalnet_applepay_amount_as_string( $total ), 'order_info' => $articleDetails );
		wp_send_json($shipping_method_change);
	}

	public function novalnet_order_creation(){

		global $woocommerce;
		$requiredFields = array('givenName', 'familyName', 'addressLines', 'postalCode', 'locality', 'countryCode');
		$missing_fields = false;

		foreach ($requiredFields as $key) {
			if( 'addressLines' == $key ) {
				if( empty( trim( novalnet()->request['variable_name']['response']['wallet']['billing'][$key][0], " " ) ) ) {
					$missing_fields = true;
				}
				if( empty( trim( novalnet()->request['variable_name']['response']['wallet']['shipping'][$key][0], " " ) ) ) {
					$missing_fields = true;
				}
			} else {
				if( empty( trim( novalnet()->request['variable_name']['response']['wallet']['billing'][$key], " " ) ) ) {
					$missing_fields = true;
				}
				if(empty( trim( novalnet()->request['variable_name']['response']['wallet']['shipping'][$key], " " ) ) ) {
					$missing_fields = true;
				}
			}

			if( $missing_fields ) {
				wp_send_json(
					array(
						'result'   => 'error',
						'message' => $key . ' is required fields',
					)
				);
			}
		}

		$received_address = novalnet()->request['variable_name']['response']['wallet'];
		$customer_billing = [];
		$customer_shipping = [];

		if ( is_user_logged_in() ) {

			// Update billing address from Applepay sheet
			WC()->customer->set_billing_first_name(wc_clean( $received_address['billing']['givenName'] ));
			WC()->customer->set_billing_last_name(wc_clean( $received_address['billing']['familyName'] ));
			WC()->customer->set_billing_address_1(wc_clean((! empty( $received_address['billing']['addressLines'][0] ) ) ? $received_address['billing']['addressLines'][0] : WC()->customer->get_billing_address_1())); 
			WC()->customer->set_billing_address_2(wc_clean( (! empty( $received_address['billing']['addressLines'][1] ) ) ? $received_address['billing']['addressLines'][1] : WC()->customer->get_billing_address_2()) ); 
			WC()->customer->set_billing_city(wc_clean($received_address['billing']['locality'])); 
			WC()->customer->set_billing_postcode(wc_clean( $received_address['billing']['postalCode'])); 
			WC()->customer->set_billing_country(wc_clean( $received_address['billing']['countryCode'])); 

			// Update shipping address from Applepay sheet 
			WC()->customer->set_shipping_first_name(wc_clean( $received_address['shipping']['givenName'] )); 
			WC()->customer->set_shipping_last_name(wc_clean( $received_address['shipping']['familyName'] )); 
			WC()->customer->set_shipping_address_1(wc_clean((! empty( $received_address['shipping']['addressLines'][0] ) ) ? $received_address['shipping']['addressLines'][0] : WC()->customer->get_billing_address_1())); 
			WC()->customer->set_shipping_address_2(wc_clean( (! empty( $received_address['shipping']['addressLines'][1] ) ) ? $received_address['shipping']['addressLines'][1] : WC()->customer->get_shipping_address_2()) );
			WC()->customer->set_shipping_city(wc_clean( $received_address['shipping']['locality'] )); 
			WC()->customer->set_shipping_postcode(wc_clean( $received_address['shipping']['postalCode'] )); 
			WC()->customer->set_shipping_country(wc_clean( $received_address['shipping']['countryCode'] ));

			$customer_billing['company']    = WC()->customer->get_billing_company();
			$customer_shipping['state']      = WC()->customer->get_billing_state();
			$customer_shipping['company']    = WC()->customer->get_shipping_company(); ;
			$customer_shipping['state']      =  WC()->customer->get_shipping_state();
		}

		// Customer billing information details (from Applepay sheet)
		$customer_billing['first_name'] = $received_address['billing']['givenName'];
		$customer_billing['last_name']  = $received_address['billing']['familyName'];
		$customer_billing['address_1']  = $received_address['billing']['addressLines'][0];
		$customer_billing['address_2']  = (! empty($received_address['billing']['addressLines'][1])) ? $received_address['billing']['addressLines'][1] : '';
		$customer_billing['city']       = $received_address['billing']['locality'];
		$customer_billing['email']      = $received_address['shipping']['emailAddress'];
		$customer_billing['postcode']   = $received_address['billing']['postalCode'];
		$customer_billing['country']    = $received_address['billing']['countryCode'];

		// Customer shipping information details (from Applepay sheet)
		$customer_shipping['first_name'] = $received_address['shipping']['givenName'];
		$customer_shipping['last_name']  = $received_address['shipping']['familyName'];
		$customer_shipping['address_1']  = $received_address['shipping']['addressLines'][0];
		$customer_shipping['address_2']  = (! empty($received_address['shipping']['addressLines'][1])) ? $received_address['shipping']['addressLines'][1] : '';
		$customer_shipping['email']      = $received_address['shipping']['emailAddress'];
		$customer_shipping['city']       = $received_address['shipping']['locality'];
		$customer_shipping['postcode']   = $received_address['shipping']['postalCode'];
		$customer_shipping['country']    = $received_address['shipping']['countryCode'];

		WC()->session->set( 'cart_page_applepay_token', novalnet()->request['variable_name']['response']['transaction']['token'] );

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
		$checkout = WC()->checkout();

		$data['payment_method'] = 'novalnet_applepay';
		$order_id = $checkout->create_order($data);
		$wc_order = wc_get_order($order_id);

		$wc_order->set_address( $customer_billing, 'billing' );
		$wc_order->set_address( $customer_shipping, 'shipping' );
		$wc_order->set_currency(get_woocommerce_currency());

		update_post_meta($wc_order->id, '_customer_user', get_current_user_id());

		if( 'billing' == get_option( 'woocommerce_tax_based_on' ) ) {
			$wc_order->calculate_totals();
		}

		// Process Payment
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$result = $available_gateways[ 'novalnet_applepay' ]->process_payment( $wc_order->id );

		wp_send_json($result);
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
		} else {
			wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script', 'https://cdn.novalnet.de/js/v2/NovalnetUtility.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		}

		// Enqueue script.
		wp_enqueue_script( 'woocommerce-novalnet-gateway-applepay-script', novalnet()->plugin_url . '/assets/js/novalnet-applepay.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );

		WC_Novalnet_Configuration::get_payment_settings( 'novalnet_applepay' );

		wp_localize_script( 'woocommerce-novalnet-gateway-applepay-script', 'my_ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'payment_setting' => WC_Novalnet_Configuration::get_payment_settings( 'novalnet_applepay' ),'client_key'     => WC_Novalnet_Configuration::get_global_settings( 'client_key' ) ) );

		// Enqueue script in front-end.
		wp_enqueue_script( 'woocommerce-novalnet-gateway-script', $this->plugin_url . '/assets/js/novalnet.js', array( 'jquery', 'jquery-payment' ), NOVALNET_VERSION, false );
		wp_enqueue_style( 'woocommerce-novalnet-gateway-css', $this->plugin_url . '/assets/css/novalnet.css', array(), NOVALNET_VERSION, false );
		wp_localize_script(
			'woocommerce-novalnet-gateway-script',
			'wc_novalnet_data',
			array(
				'dob_error'          => __( 'Please enter valid birth date', 'woocommerce-novalnet-gateway' ),
				'sepa_account_error' => __( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ),
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
