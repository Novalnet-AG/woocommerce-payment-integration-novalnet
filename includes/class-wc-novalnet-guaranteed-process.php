<?php
/**
 * Handling Novalnet instalment/guarantee process.
 *
 * @class    WC_Novalnet_Guaranteed_Process
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Guaranteed_Process Class.
 */
class WC_Novalnet_Guaranteed_Process {

	/**
	 * Allowed countries
	 *
	 * @var array
	 */
	private $allowed_countries = array(
		'AT',
		'DE',
		'CH',
	);
	/**
	 * Novalnet_Guaranteed_Process Constructor.
	 */
	public function __construct() {

		// Show instalment details in myaccount & Thank you page.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_instalment_details' ) );

		add_action( 'novalnet_instalment_details', array( $this, 'add_instalment_details' ), 10, 2 );

		add_filter( 'novalnet_store_instalment_data', array( &$this, 'store_instalment_data' ) );

		add_filter( 'novalnet_get_stored_instalment_data', array( $this, 'get_stored_instalment_data' ) );

		add_filter( 'novalnet_get_instalment_cycles', array( $this, 'get_instalment_cycles' ), 10, 2 );

		add_filter( 'novalnet_store_instalment_data_webhook', array( $this, 'store_instalment_data_webhook' ), 10, 2 );

		add_filter( 'woocommerce_single_product_summary', array( $this, 'show_instalment_suggestions' ), 10, 2 );

		add_filter( 'novalnet_allowed_guaranteed_countries', array( $this, 'allowed_countries' ) );

		add_action( 'woocommerce_email_classes', array( $this, 'add_emails' ) );

		add_action( 'woocommerce_email_customer_details', array( $this, 'add_instalment_details' ) );

	}

	/**
	 * Add instalment email classes.
	 *
	 * @param string $email_classes The email class.
	 *
	 * @since 12.0.0
	 */
	public function add_emails( $email_classes ) {
		include_once novalnet()->plugin_dir_path . '/includes/emails/class-wc-novalnet-email-new-instalment.php';
		$email_classes['WC_Novalnet_Email_New_Instalment'] = new WC_Novalnet_Email_New_Instalment();

		return $email_classes;
	}

	/**
	 * Display details for Instalment plan.
	 *
	 * @since 12.0.0
	 */
	public function show_instalment_suggestions() {

		global $product;

		if ( ! ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) && $product->get_price() && 'EUR' === get_woocommerce_currency() ) {
			foreach ( novalnet()->get_supports( 'instalment' ) as $supports_instalment ) {
				$instalment_settings[ $supports_instalment ] = WC_Novalnet_Configuration::get_payment_settings( $supports_instalment );

				if ( wc_novalnet_check_isset( $instalment_settings[ $supports_instalment ], 'enabled', 'yes' ) && wc_novalnet_check_isset( $instalment_settings[ $supports_instalment ], 'instalment_plan_on_product_detail_page', 'yes' ) && ( $instalment_settings[ $supports_instalment ]['min_amount'] <= ( $product->get_price() * 100 ) ) ) {
					$cycles = $this->get_instalment_cycles( $instalment_settings[ $supports_instalment ], $product->get_price() );
					if ( ! empty( $cycles ) ) {
						$data ['payment_types'] [ $supports_instalment ]['settings'] = $instalment_settings[ $supports_instalment ];
						$data ['payment_types'] [ $supports_instalment ]['cycles']   = $cycles;
						$data ['amount'] = wc_price( $product->get_price() );
					}
				}
			}
			if ( ! empty( $data ) ) {
				novalnet()->helper()->load_template( 'render-instalment-suggestions-form.php', $data );
			}
		}
	}

	/**
	 * Display details for Instalment plan on my account.
	 *
	 * @param WC_Order $wc_order The order object.
	 *
	 * @since 12.0.0
	 */
	public function add_instalment_details( $wc_order ) {

		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) && novalnet()->get_supports( 'instalment', $wc_order->get_payment_method() ) ) {
			$transaction_details = novalnet()->db()->get_transaction_details( $wc_order->get_id() );
			$instalments         = $this->get_stored_instalment_data( $wc_order->get_id() );
			if ( ! empty( $instalments ) ) {
				$content                       = array(
					'instalments' => $instalments,
					'transaction' => $transaction_details,
				);
				$instalments[1]['order_total'] = $wc_order->get_total();
				if ( 'woocommerce_order_details_after_order_table' === current_action() ) {
					novalnet()->helper()->load_template( 'render-instalment-details.php', $content, $wc_order->get_payment_method(), 'myaccount' );
				} else {
					novalnet()->helper()->load_template( 'render-instalment-details.php', $content, $wc_order->get_payment_method(), 'emails' );
				}
			}
		}
	}

	/**
	 * Store instalment data.
	 *
	 * @param array $data The instalment data.
	 * @since 12.0.0
	 *
	 * @return array
	 */
	public function store_instalment_data( $data ) {

		if ( ! empty( $data['instalment'] ) ) {
			$order_amount = novalnet()->db()->get_entry_by_tid( $data ['transaction'] ['tid'], 'amount' );
			if ( ! empty( $order_amount ) ) {
				$data['transaction']['amount'] = $order_amount;
			}

			$instalment = $data['instalment'];
			if ( ! empty( $instalment['cycles_executed'] ) ) {
				$instalment_details ['instalment_cycle_amount'] = $instalment['cycle_amount'];
				$instalment_details ['instalment_total_cycles'] = count( $instalment['cycle_dates'] );
				$last_cycle_amount                              = 0;
				for ( $i = 1; $i <= $instalment_details['instalment_total_cycles']; $i++ ) {
					$instalment_details [ 'instalment' . $i ] = array();
					if ( 1 < $i && $i < $instalment_details ['instalment_total_cycles'] ) {
						$instalment_details [ 'instalment' . $i ] ['amount'] = $instalment['cycle_amount'];
					} elseif ( $i === $instalment_details ['instalment_total_cycles'] ) {
						$instalment_details [ 'instalment' . $i ] ['amount'] = $data['transaction']['amount'] - $last_cycle_amount;
					}
					$last_cycle_amount += $instalment['cycle_amount'];
					if ( ! empty( $instalment['cycle_dates'] [ $i + 1 ] ) ) {
						$instalment_dates [] = $i . '-' . $instalment['cycle_dates'] [ $i + 1 ];
					}
					$instalment_details [ 'instalment' . $instalment['cycles_executed'] ]['tid']                  = $data ['transaction'] ['tid'];
					$instalment_details [ 'instalment' . $instalment['cycles_executed'] ]['paid_date']            = $instalment ['cycle_dates'][ $instalment['cycles_executed'] ];
					$instalment_details [ 'instalment' . $instalment['cycles_executed'] ]['next_instalment_date'] = $instalment ['cycle_dates'] [ $instalment['cycles_executed'] + 1 ];

					foreach ( array(
						'instalment_cycles_executed' => 'cycles_executed',
						'due_instalment_cycles'      => 'pending_cycles',
						'amount'                     => 'cycle_amount',
					) as $key => $value ) {
						if ( ! empty( $instalment[ $value ] ) ) {
							$instalment_details [ 'instalment' . $instalment['cycles_executed'] ][ $key ] = $instalment[ $value ];
							$instalment_details [ $key ] = $instalment[ $value ];
						}
					}
				}
				$instalment_details ['future_instalment_dates'] = implode( '|', $instalment_dates );
			}
		}
		if ( ! empty( $instalment_details ) ) {
			return wc_novalnet_serialize_data( $instalment_details );
		}
	}

	/**
	 * Get Store instalment data.
	 *
	 * @param array $post_id The post id.
	 * @since 12.0.0
	 *
	 * @return array
	 */
	public function get_stored_instalment_data( $post_id ) {

		$instalment_rows = array();
		$instalment      = novalnet()->db()->get_entry_by_order_id( $post_id, 'additional_info' );

		if ( ! empty( $instalment ) && ! empty( $instalment['instalment_total_cycles'] ) ) {

			$future_dates = explode( '|', $instalment['future_instalment_dates'] );
			foreach ( $future_dates as $future_date ) {
				$date_array = explode( '-', $future_date );
				$cycle      = $date_array['0'];
				unset( $date_array['0'] );
				$future_instalment_dates [ $cycle ] = implode( '-', $date_array );
			}

			for ( $i = 1; $i <= $instalment['instalment_total_cycles']; $i++ ) {
				$instalment_rows[ $i ]           = array(
					'status' => ( ! empty( $instalment['is_instalment_cancelled'] ) && 1 === (int) $instalment['is_instalment_cancelled'] ) ? 'cancelled' : 'pending',
				);
				$instalment_rows[ $i ]['amount'] = 0;
				if ( 1 === $i && ! empty( $instalment['instalment']['amount'] ) ) {
					$instalment_rows[ $i ]['amount'] = $instalment['instalment']['amount'];
				} elseif ( ! empty( $instalment[ "instalment$i" ]['amount'] ) ) {
					$instalment_rows[ $i ]['amount'] = $instalment[ "instalment$i" ]['amount'];
				}

				if ( ! empty( $instalment[ "instalment$i" ]['paid_date'] ) ) {
					$instalment_rows[ $i ]['date']   = $instalment[ "instalment$i" ]['paid_date'];
					$instalment_rows[ $i ]['amount'] = $instalment_rows[ $i ]['amount'];

				}
				if ( ! empty( $instalment[ "instalment$i" ]['tid'] ) ) {
					$instalment_rows[ $i ]['tid']    = $instalment[ "instalment$i" ]['tid'];
					$instalment_rows[ $i ]['status'] = 'completed';
					if ( ! empty( $instalment['is_full_cancelled'] ) && ( 1 === (int) $instalment['is_full_cancelled'] ) ) {
						$instalment_rows[ $i ]['amount'] = 0;
					}
				}

				if ( empty( $instalment_rows[ $i ]['date'] ) && ! empty( $future_instalment_dates [ $i - 1 ] ) ) {
					$instalment_rows[ $i ]['date'] = $future_instalment_dates [ $i - 1 ];
				}

				$instalment_rows[ $i ]['date']        = wc_novalnet_formatted_date( $instalment_rows[ $i ]['date'] );
				$instalment_rows[ $i ]['status_text'] = wc_get_order_status_name( $instalment_rows[ $i ]['status'] );
				$instalment_rows[ $i ]['status_text'] = wc_get_order_status_name( $instalment_rows[ $i ]['status'] );

			}

			if ( ! empty( $instalment['is_instalment_cancelled'] ) ) {
				$instalment_rows['is_full_cancelled']       = $instalment['is_full_cancelled'];
				$instalment_rows['is_instalment_cancelled'] = $instalment['is_instalment_cancelled'];
			}
		}
		return $instalment_rows;
	}

	/**
	 * Get Instalment Cycles from Instalment payment settings.
	 *
	 * @param array  $settings   The payment settings.
	 * @param string $amount     Instalment cycle_amount.
	 * @since 12.0.0
	 *
	 * @return array
	 */
	public function get_instalment_cycles( $settings, $amount ) {

		$total_period = $settings['instalment_total_period'];
		$i            = 0;
		$cycles       = array();

		foreach ( $total_period as $period ) {
			$cycle_amount = sprintf( '%0.2f', $amount / $period );
			if ( 9.99 <= $cycle_amount ) {
				/* translators: %1$s: period %2$s: amount */
				$cycles ['period'][ $period ] = sprintf( __( '%1$s x %2$s (per month)', 'woocommerce-novalnet-gateway' ), $period, wc_novalnet_shop_amount_format( $cycle_amount * 100 ) );
				$i++;
				$cycles ['attributes'][ $period ]['amount'] = wc_novalnet_shop_amount_format( $cycle_amount * 100 );
			}
		}
		return $cycles;
	}

	/**
	 * Get Instalment date
	 *
	 * @param array $data  The data.
	 *
	 * @since 12.0.0
	 */
	public function store_instalment_data_webhook( $data ) {
		$instalment_details = novalnet()->db()->get_entry_by_tid( $data['event']['parent_tid'], 'additional_info' );
		if ( ! empty( $instalment_details ) ) {
			$cycles_executed                                       = $data['instalment']['cycles_executed'];
			$instalment ['tid']                                    = $data['transaction']['tid'];
			$instalment ['amount']                                 = $data['instalment']['cycle_amount'];
			$instalment ['paid_date']                              = date( 'Y-m-d H:i:s' );
			$instalment ['next_instalment_date']                   = wc_novalnet_next_cycle_date( array( 'next_cycle_date' ) );
			$instalment ['instalment_cycles_executed']             = $data['instalment']['cycles_executed'];
			$instalment ['due_instalment_cycles']                  = $data['instalment']['pending_cycles'];
			$instalment_details[ 'instalment' . $cycles_executed ] = $instalment;
		}
		return wc_novalnet_serialize_data( $instalment_details );
	}

	/**
	 * Get Allowed countries
	 *
	 * @param string $payment_type The payment type.
	 *
	 * @since 12.0.0
	 */
	public function allowed_countries( $payment_type = '' ) {
		if ( ! is_admin() && wc_novalnet_check_session() && ! WC()->session->__isset( $payment_type . '_show_dob' ) ) {
			$this->allowed_countries = array_merge( $this->allowed_countries, WC()->countries->get_european_union_countries() );
		} elseif ( is_admin() ) {
			$this->allowed_countries = array(
				'b2c' => $this->allowed_countries,
				'b2b' => array_merge( $this->allowed_countries, WC()->countries->get_european_union_countries() ),
			);
		}

		return $this->allowed_countries;
	}
}
new WC_Novalnet_Guaranteed_Process();
