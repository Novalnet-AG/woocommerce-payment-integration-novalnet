<?php
/**
 * Novalnet Payment Tokens class.
 *
 * Representation of a payment token for applicable Novalnet Payments.
 *
 * @class       WC_Payment_Token_Novalnet
 * @extends     WC_Payment_Token
 * @package     woocommerce-novalnet-gateway/includes/
 * @category    Final Class
 * @author      Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Payment_Token_Novalnet Class.
 */
class WC_Payment_Token_Novalnet extends WC_Payment_Token {

	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'Novalnet';

	/**
	 * Stores Novalnet payments token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'reference_tid'   => '',
		'reference_token' => '',
		'card_type'       => '',
		'last4'           => '',
		'expiry_month'    => '',
		'expiry_year'     => '',
		'iban'            => '',
		'paypal_account'  => '',
	);

	/**
	 * Returns the reference PAYPAL_ACCOUNT.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return int Reference TID
	 */
	public function get_paypal_account( $context = 'view' ) {
		return $this->get_prop( 'paypal_account', $context );
	}

	/**
	 * Set the reference PAYPAL_ACCOUNT.
	 *
	 * @since 12.0.0
	 * @param string $reference_token The Reference Token.
	 */
	public function set_paypal_account( $reference_token ) {
		$this->set_prop( 'paypal_account', $reference_token );
	}

	/**
	 * Validate Novalnet payment tokens.
	 *
	 * @since 12.0.0
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}
		return true;
	}

	/**
	 * Returns the reference TID.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return int Reference TID
	 */
	public function get_reference_tid( $context = 'view' ) {
		return $this->get_prop( 'reference_tid', $context );
	}

	/**
	 * Returns the reference TOKEN.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return int Reference TID
	 */
	public function get_reference_token( $context = 'view' ) {
		return $this->get_prop( 'reference_token', $context );
	}

	/**
	 * Set the reference TOKEN.
	 *
	 * @since 12.0.0
	 * @param string $reference_token The Reference Token.
	 */
	public function set_reference_token( $reference_token ) {
		$this->set_prop( 'reference_token', $reference_token );
	}

	/**
	 * Set the reference TID.
	 *
	 * @since 12.0.0
	 * @param string $reference_tid The Reference TID.
	 */
	public function set_reference_tid( $reference_tid ) {
		$this->set_prop( 'reference_tid', $reference_tid );
	}

	/**
	 * Returns the card type.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Card type
	 */
	public function get_card_type( $context = 'view' ) {
		return $this->get_prop( 'card_type', $context );
	}

	/**
	 * Set the card type.
	 *
	 * @since 12.0.0
	 * @param string $type The card type.
	 */
	public function set_card_type( $type ) {
		$this->set_prop( 'card_type', str_pad( $type, 2, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Returns the last four digits of the card.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits of the card.
	 *
	 * @since 12.0.0
	 * @param string $last4 Credit card last four digits.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the card expiration month.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Expiration month
	 */
	public function get_expiry_month( $context = 'view' ) {
		return $this->get_prop( 'expiry_month', $context );
	}

	/**
	 * Set the expiration month for the card.
	 *
	 * @since 12.0.0
	 * @param string $expiry_month The expiration month.
	 */
	public function set_expiry_month( $expiry_month ) {
		$this->set_prop( 'expiry_month', $expiry_month );
	}

	/**
	 * Returns the card expiration year.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Expiration year
	 */
	public function get_expiry_year( $context = 'view' ) {
		return $this->get_prop( 'expiry_year', $context );
	}

	/**
	 * Set the expiration year for the card.
	 *
	 * @since 12.0.0
	 * @param string $expiry_year The expiration year.
	 */
	public function set_expiry_year( $expiry_year ) {
		$this->set_prop( 'expiry_year', $expiry_year );
	}

	/**
	 * Returns the Account IBAN.
	 *
	 * @since  12.0.0
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Account IBAN
	 */
	public function get_iban( $context = 'view' ) {
		return $this->get_prop( 'iban', $context );
	}

	/**
	 * Set the Account IBAN.
	 *
	 * @since 12.0.0
	 * @param string $iban Account IBAN.
	 */
	public function set_iban( $iban ) {
		$this->set_prop( 'iban', $iban );
	}

	/**
	 * Get String to be display to user.
	 *
	 * @since  12.0.0
	 *
	 * @param array $deprecated default value.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = '';
		if ( 'novalnet_cc' === $this->get_gateway_id() ) {
			$icon    = novalnet()->plugin_url . '/assets/images/novalnet_cc_' . strtolower( $this->get_card_type() ) . '.png';
			$brand   = $this->get_card_type();
			$brand   = "<img src='$icon' alt='" . $brand . "' title='" . $brand . "' />";
			$display = sprintf(
				/* translators: %1$s: credit card type %2$s: last 4 digits %3$s: expiry month %4$s: expiry year */
				__( '%1$s ending in %2$s (expires %3$s/%4$s)', 'woocommerce' ),
				$brand,
				$this->get_last4(),
				$this->get_expiry_month(),
				substr( $this->get_expiry_year(), 2 )
			);
		} elseif ( in_array( $this->get_gateway_id(), array( 'novalnet_sepa', 'novalnet_guaranteed_sepa', 'novalnet_instalment_sepa' ), true ) ) {
			$display = sprintf(
				/* translators: IBAN */
				__( 'IBAN %s', 'woocommerce-novalnet-gateway' ),
				$this->get_iban()
			);
		} elseif ( 'novalnet_paypal' === $this->get_gateway_id() && ! empty( $this->get_paypal_account() ) ) {
			$display = sprintf(
				/* translators: Paypal account email */
				__( 'PayPal account: %s', 'woocommerce-novalnet-gateway' ),
				$this->get_paypal_account()
			);
		}
		return $display;
	}

	/**
	 * Gets the saved payment token list.
	 *
	 * @since 12.0.0
	 * @param  array            $item          The attributes of the token.
	 * @param  WC_Payment_Token $payment_token The payment token.
	 * @return array
	 */
	public static function saved_payment_methods_list_item( $item, $payment_token ) {

		if ( 'novalnet_cc' === $payment_token->get_gateway_id() ) {
			$card_type               = $payment_token->get_card_type();
			$item['method']['last4'] = substr( $payment_token->get_last4(), -4 );
			$item['method']['brand'] = '';
			if ( ! empty( $card_type ) ) {
				$icon                    = novalnet()->plugin_url . '/assets/images/novalnet_cc_' . strtolower( $payment_token->get_card_type() ) . '.png';
				$item['method']['brand'] = $payment_token->get_card_type();
			}
			$item['expires'] = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 );
		} elseif ( in_array(
			$payment_token->get_gateway_id(),
			array(
				'novalnet_sepa',
				'novalnet_guaranteed_sepa',
				'novalnet_instalment_sepa',
			),
			true
		) ) {
			$item['method']['last4'] = substr( $payment_token->get_iban(), -4 );
			$item['method']['brand'] = 'IBAN';
			$item['expires']         = 'N/A';
		} elseif ( 'novalnet_paypal' === $payment_token->get_gateway_id() ) {
			$item['method']['brand'] = sprintf(
				/* translators: Paypal account email */
				__( 'PayPal account: %s', 'woocommerce-novalnet-gateway' ),
				$payment_token->get_paypal_account()
			);
			$item['expires'] = 'N/A';
		}
		return $item;
	}

	/**
	 * Delete duplicate token(s)
	 *
	 * @since 12.0.0
	 *
	 * @param string $data          The response data.
	 * @param string $payment_type  the payment_type.
	 */
	public function delete_duplicate_tokens( $data, $payment_type ) {

		if ( in_array( $payment_type, array( 'novalnet_sepa', 'novalnet_guaranteed_sepa', 'novalnet_instalment_sepa' ), true ) ) {
			$users_tokens = array_merge( WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_guaranteed_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_instalment_sepa' ) );
		} else {
			$users_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $payment_type );
		}

		foreach ( $users_tokens as $user_token ) {
			if ( in_array( $payment_type, array( 'novalnet_sepa', 'novalnet_guaranteed_sepa', 'novalnet_instalment_sepa' ), true ) && in_array( $user_token->get_gateway_id(), array( 'novalnet_sepa', 'novalnet_guaranteed_sepa', 'novalnet_instalment_sepa' ), true ) && wc_novalnet_check_isset( $data, 'iban', $user_token->get_iban() ) ) {
				WC_Payment_Tokens::delete( $user_token->get_id() );
			} elseif ( $payment_type === $user_token->get_gateway_id() ) {

				if ( 'novalnet_cc' === $payment_type ) {
					$data ['card_number'] = substr( $data ['card_number'], -4 );
					if (
						wc_novalnet_check_isset( $data, 'card_number', $user_token->get_last4() ) &&
						! empty( $data ['card_expiry_month'] ) && (int) $data ['card_expiry_month'] === (int) $user_token->get_expiry_month() &&
						wc_novalnet_check_isset( $data, 'card_expiry_year', (int) $user_token->get_expiry_year() ) &&
						wc_novalnet_check_isset( $data, 'card_brand', $user_token->get_card_type() )
					) {
						WC_Payment_Tokens::delete( $user_token->get_id() );
					}
				} elseif ( wc_novalnet_check_isset( $data, 'paypal_account', $user_token->get_paypal_account() ) ) {
					WC_Payment_Tokens::delete( $user_token->get_id() );
				}
			}
		}
	}

	/**
	 * Store token data.
	 *
	 * @since 12.0.0
	 *
	 * @param string $payment_type The payment type.
	 * @param string $data         The payment data.
	 */
	public function store_token_data( $payment_type, $data ) {
		if ( 'novalnet_cc' === $payment_type ) {
			if ( ! empty( $data['card_brand'] ) ) {
				$this->set_card_type( $data['card_brand'] );
			}
			if ( ! empty( $data['card_number'] ) ) {
				$this->set_last4( substr( $data['card_number'], -4 ) );
			}
			if ( ! empty( $data['card_expiry_month'] ) ) {

				if ( 1 === strlen( $data['card_expiry_month'] ) ) {
					$data['card_expiry_month'] = '0' . $data['card_expiry_month'];
				}
				$this->set_expiry_month( $data['card_expiry_month'] );
			}
			if ( ! empty( $data['card_expiry_year'] ) ) {
				$this->set_expiry_year( $data['card_expiry_year'] );
			}
		} elseif ( ! empty( $data['paypal_account'] ) ) {
			$this->set_paypal_account( $data['paypal_account'] );
		} elseif ( ! empty( $data['iban'] ) ) {
			$this->set_iban( $data['iban'] );
		}
	}

}
