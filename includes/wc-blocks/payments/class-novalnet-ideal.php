<?php
/**
 * Novalnet_Ideal payment method integration
 *
 * @since 12.6.2
 * @package  woocommerce-novalnet-gateway/includes/wc-blocks
 * @category Class
 * @author   Novalnet
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Novalnet_Ideal class.
 *
 * @extends AbstractPaymentMethodType
 */
final class Novalnet_Ideal extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'novalnet_ideal';

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
		$script_handle = novalnet()->helper()->register_payment_script( $this->name );
		return array( $script_handle );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return novalnet()->helper()->get_payment_method_block_data( $this->name );
	}
}
