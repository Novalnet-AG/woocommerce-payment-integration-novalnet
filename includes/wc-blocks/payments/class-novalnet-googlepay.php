<?php
/**
 * Novalnet_Googlepay payment method integration
 *
 * @since 12.6.2
 * @package  woocommerce-novalnet-gateway/includes/wc-blocks
 * @category Class
 * @author   Novalnet
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Novalnet_Googlepay class.
 *
 * @extends AbstractPaymentMethodType
 */
final class Novalnet_Googlepay extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'novalnet_googlepay';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
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
		$script_handle = novalnet()->helper()->register_payment_script( $this->name, true, true );
		return array( $script_handle );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$payment_method_data = novalnet()->helper()->get_payment_method_block_data( $this->name );
		if ( ! empty( $payment_method_data ) && ! is_admin() ) {
			$payment_method_data['settings'] = array_merge(
				$this->settings,
				array(
					'client_key' => WC_Novalnet_Configuration::get_global_settings( 'client_key' ),
				)
			);
			return novalnet()->helper()->update_wallet_payment_block_data( $this->name, $payment_method_data );
		}
		return $payment_method_data;
	}
}
