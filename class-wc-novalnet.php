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
		}

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
