<?php
/**
 * Novalnet_Cc payment method integration
 *
 * @since 12.6.2
 * @package  woocommerce-novalnet-gateway/includes/wc-blocks
 * @category Class
 * @author   Novalnet
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

/**
 * Novalnet_Ideal class.
 *
 * @extends AbstractPaymentMethodType
 */
final class Novalnet_Cc extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'novalnet_cc';

	/**
	 * Novalnet invoice gateway object
	 *
	 * @var WC_Gateway_Novalnet_Cc
	 */
	private $gateway;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'add_card_data_novanlet_request' ), 8, 2 );
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	/**
	 * Add payment request data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function add_card_data_novanlet_request( PaymentContext $context, PaymentResult &$result ) {
		if ( WC_Novalnet_Validation::check_string( $context->payment_method ) ) {
			novalnet()->request = array_merge( novalnet()->request, $context->payment_data );
		}
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_handle = novalnet()->helper()->register_payment_script( $this->name, true );
		return array( $script_handle );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$payment_method_data = novalnet()->helper()->get_payment_method_block_data( $this->name );
		if ( ! empty( $payment_method_data ) ) {
			$payment_method_data['settings']           = array_merge(
				$this->settings,
				array(
					'client_key' => WC_Novalnet_Configuration::get_global_settings( 'client_key' ),
				)
			);
			$payment_method_data['enableTokenization'] = wc_novalnet_check_isset( $this->settings, 'tokenization', 'yes' );
			$payment_method_data['lang']               = wc_novalnet_shop_language();
		}
		return $payment_method_data;
	}
}
