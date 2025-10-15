<?php

/**
 * Novalnet DB handler
 *
 * This file have all the DB related query and prepare statements
 *
 * @class    WC_Novalnet_DB_Handler
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * WC_Novalnet_DB_Handler Class.
 *
 * @class   WC_Novalnet_DB_Handler
 */
class WC_Novalnet_DB_Handler {


	/**
	 * Main Novalnet_DB_Handler Instance.
	 *
	 * Ensures only one instance of Novalnet is loaded.
	 *
	 * @since  12.0.0
	 * @static
	 * @var $instance
	 * @see    Novalnet_DB_Handler()
	 * @return Novalnet_DB_Handler - Main instance.
	 */
	protected static $instance = null;

	/**
	 * Main Novalnet_Helper Instance.
	 *
	 * Ensures only one instance of Novalnet_Helper is loaded or can be loaded.
	 *
	 * @since  12.0.0
	 * @static
	 * @return Novalnet_Api_Callback Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Handles the query error while exception occurs.
	 *
	 * @since 12.0.0
	 * @param string $query The processed query.
	 *
	 * @throws Exception For query process.
	 *
	 * @return string
	 */
	public function handle_query( $query ) {
		global $wpdb;
		$query_return = '';
		try {
			// Checking for query error.
			if ( $wpdb->last_error ) {
				throw new Exception( $wpdb->last_error );
			}
			$query_return = $query;
		} catch ( Exception $e ) {
			$novalnet_log = wc_novalnet_logger();
			$novalnet_log->add( 'novalneterrorlog', 'Database error occured: ' . $e->getMessage() );
		}
		return $query;
	}

	/**
	 * Get order item ID.
	 *
	 * @since 12.0.0
	 * @param int $post_id The post id.
	 *
	 * @return int
	 */
	public function get_order_item_id( $post_id ) {
		global $wpdb;
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT order_item_id FROM `{$wpdb->prefix}woocommerce_order_items` WHERE order_id=%s AND order_item_type='line_item'", $post_id ) ) ); // db call ok; no-cache ok.
	}

	/**
	 * Returns the order post_id.
	 *
	 * @since 12.0.0
	 * @since 12.6.2 Update the to HPOS compatibility.
	 *
	 * @param int $order_number The order number.
	 *
	 * @return array
	 */
	public function get_post_id_by_order_number( $order_number ) {

		global $wpdb;

		if ( $this->is_valid_column( 'order_number_formatted' ) ) {
			$post_id = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT order_no FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_number_formatted=%s", $order_number ) ) ); // db call ok; no-cache ok.
			if ( ! empty( $post_id ) ) {
				return $post_id;
			}
		}

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			foreach ( array( '_novalnet_order_number', '_order_number_formatted', '_order_number' ) as $meta_key ) {
				$wc_orders = wc_get_orders(
					array(
						'limit'        => 1,
						'meta_key'     => $meta_key,
						'meta_value'   => $order_number,
						'meta_compare' => '=',
					)
				);

				$wc_order_id = ( is_array( $wc_orders ) && current( $wc_orders ) ) ? current( $wc_orders )->get_id() : false;
				if ( ! empty( $wc_order_id ) ) {
					return $wc_order_id;
				}
			}
		}

		// Get order post id.
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `{$wpdb->postmeta}` WHERE meta_value=%s AND (meta_key='_order_number_formatted' OR meta_key='_order_number' OR meta_key='_novalnet_order_number' )", $order_number ) ) ); // db call ok; no-cache ok.
	}

	/**
	 * Get post ID of the given meta
	 *
	 * @since 12.0.0
	 * @since 12.8.1 Update the to HPOS compatibility.
	 * @param string $meta_value The meta value.
	 * @param string $meta_key The meta Key.
	 *
	 * @return string
	 */
	public function get_post_id_by_meta_data( $meta_value, $meta_key = '' ) {
		global $wpdb;

		if ( ! empty( $meta_key ) && ! empty( $meta_value ) ) {
			if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$wc_orders = wc_get_orders(
					array(
						'limit'        => 1,
						'meta_key'     => $meta_key,
						'meta_value'   => $meta_value,
						'meta_compare' => '=',
					)
				);

				$wc_order_id = ( is_array( $wc_orders ) && current( $wc_orders ) ) ? current( $wc_orders )->get_id() : false;
				if ( ! empty( $wc_order_id ) ) {
					return $wc_order_id;
				}
			} else {
				return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ) ) ); // db call ok; no-cache ok.
			}
		}
		// Check for column exists.
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value =%s", $meta_value ) ) ); // db call ok; no-cache ok.
	}

	/**
	 * Returns the transaction details
	 *
	 * @since 12.0.0
	 * @param int $post_id The post id.
	 * @param int $tid     The TID value.
	 * @param int $subs_id The subscription order id.
	 * @return array
	 */
	public function get_transaction_details( $post_id, $tid = '', $subs_id = '' ) {
		global $wpdb;
		$result = array();

		// Select transaction details based on TID or post_id.
		if ( '' !== $tid ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, payment_type, amount, callback_amount, refunded_amount, gateway_status, tid, additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ), ARRAY_A ) ); // db call ok; no-cache ok.
		}

		if ( empty( $result ) && ! empty( $post_id ) ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, payment_type, amount, callback_amount, refunded_amount, gateway_status, tid, additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s OR order_no=%s", $tid, $post_id ), ARRAY_A ) ); // db call ok; no-cache ok.
		}

		if ( empty( $result ) && ! empty( $subs_id ) ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, payment_type, amount, callback_amount, refunded_amount, gateway_status, tid, additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s OR order_no=%s", $tid, $subs_id ), ARRAY_A ) ); // db call ok; no-cache ok.
		}

		if ( ! empty( $result['gateway_status'] ) ) {
			novalnet()->helper()->status_mapper( $result['gateway_status'] );
		}
		return $result;
	}

	/**
	 * Returns the subscription details
	 *
	 * @since 12.0.0
	 * @param int $value The TID or order id value.
	 * @return array
	 */
	public function get_subscription_details( $value = '' ) {

		global $wpdb;
		$result = array();

		// Select transaction details based on TID.
		if ( '' !== $value ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, subs_order_no, recurring_payment_type, tid, recurring_tid, subs_id, shop_based_subs, termination_reason, termination_at FROM `{$wpdb->prefix}novalnet_subscription_details` WHERE recurring_tid=%s OR tid=%s OR subs_order_no=%s", $value, $value, $value ), ARRAY_A ) ); // db call ok; no-cache ok.
		}

		return $result;
	}

	/**
	 * Returns the subscription details by given post_id
	 *
	 * @since 12.0.0
	 * @param int     $post_id         The post id.
	 * @param int     $subscription_id The subscription order number.
	 * @param int     $column          The column name.
	 * @param boolean $use_parent_id The flag to get data by using parent id.
	 *
	 * @return array
	 */
	public function get_subs_data_by_order_id( $post_id, $subscription_id, $column = 'tid', $use_parent_id = true ) {
		global $wpdb;

		$result = array();
		// Select transaction details based on post_id.
		if ( ! empty( $post_id ) && in_array( $column, array( 'tid', 'nn_txn_token', 'shop_based_subs', 'recurring_tid', 'subs_order_no', 'subs_id' ), true ) ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT {$column} FROM `{$wpdb->prefix}novalnet_subscription_details` WHERE order_no=%s AND subs_order_no=%s", $post_id, $subscription_id ) ) ); // db call ok; no-cache ok.

			if ( 'shop_based_subs' === (string) $column && in_array( (int) $result, array( 0, 1 ), true ) ) {
				return $result;
			}

			if ( empty( $result ) && $use_parent_id ) {
				$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT {$column} FROM `{$wpdb->prefix}novalnet_subscription_details` WHERE order_no=%s", $post_id ) ) ); // db call ok; no-cache ok.
			}
		}
		return $result;
	}

	/**
	 * Returns the transaction details by given post_id
	 *
	 * @since 12.0.0
	 * @param int $post_id The post id.
	 * @param int $column The column name.
	 *
	 * @return array
	 */
	public function get_entry_by_order_id( $post_id, $column = 'tid' ) {
		global $wpdb;

		$result = array();
		// Select transaction details based on post_id.
		if ( 'tid' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT tid FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) ); // db call ok; no-cache ok.
		} elseif ( 'gateway_status' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT gateway_status FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) ); // db call ok; no-cache ok.
			if ( ! empty( $result ) ) {
				novalnet()->helper()->status_mapper( $result );
			}
		} elseif ( 'additional_info' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) ); // db call ok; no-cache ok.
			if ( ! empty( $result ) ) {
				$result = wc_novalnet_unserialize_data( $result );
			}
		} elseif ( 'amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) ); // db call ok; no-cache ok.
		} elseif ( 'refunded_amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) ); // db call ok; no-cache ok.
		}
		return $result;
	}

	/**
	 * Returns the transaction details by given tid
	 *
	 * @since 12.0.0
	 * @param int $tid    The TID value.
	 * @param int $column The column name.
	 *
	 * @return array
	 */
	public function get_entry_by_tid( $tid, $column = 'gateway_status' ) {
		global $wpdb;

		$result = array();
		// Select transaction details based on TID.
		if ( 'gateway_status' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT gateway_status FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ) ) ); // db call ok; no-cache ok.

			if ( ! empty( $result ) ) {
				novalnet()->helper()->status_mapper( $result );
			}
		} elseif ( 'additional_info' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ) ) ); // db call ok; no-cache ok.
			$result = wc_novalnet_unserialize_data( $result );
		} elseif ( 'amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ) ) ); // db call ok; no-cache ok.
		}
		return $result;
	}

	/**
	 * Handling db insert operation.
	 *
	 * @since 12.0.0
	 * @param array  $insert_value The values to be insert in the given table.
	 * @param string $table   The table name.
	 */
	public function insert( $insert_value, $table ) {
		global $wpdb;

		// Perform query action.
		$this->handle_query( $wpdb->insert( "{$wpdb->prefix}$table", $insert_value ) ); // db call ok.
	}

	/**
	 * Handling db update operation.
	 *
	 * @since 12.0.0
	 * @param array  $update_value The update values.
	 * @param array  $where_array  The where condition query.
	 * @param string $table   The table name.
	 */
	public function update( $update_value, $where_array, $table = 'novalnet_transaction_detail' ) {
		global $wpdb;

		// Perform query action.
		$this->handle_query( $wpdb->update( "{$wpdb->prefix}$table", $update_value, $where_array ) ); // db call ok; no-cache ok.
	}

	/**
	 * Check for table availablity.
	 *
	 * @since 12.0.0
	 *
	 * @return booolean
	 */
	public function is_valid_table() {
		global $wpdb;

		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables where table_name = %s', $wpdb->prefix . 'novalnet_transaction_detail' ) ) ); // db call ok; no-cache ok.
	}

	/**
	 * Run the alter table query.
	 *
	 * @since 12.0.0
	 * @param array $columns The column names.
	 */
	public function alter_table( $columns ) {
		global $wpdb;

		if ( $this->is_valid_table() ) {
			foreach ( $columns as $column ) {
				if ( $this->is_valid_column( $column ) ) {
					$this->handle_query($wpdb->query("ALTER TABLE `{$wpdb->prefix}novalnet_transaction_detail` DROP COLUMN $column")); // phpcs:ignore.
				}
			}
		}
	}

	/**
	 * Change column name.
	 *
	 * @since 12.0.0
	 * @param array $columns The column names.
	 */
	public function rename_column( $columns ) {
		global $wpdb;

		if ( $this->is_valid_table() ) {
			foreach ( $columns as $column => $to_change ) {

				if ( $this->is_valid_column( $column ) ) {
					$this->handle_query($wpdb->query("ALTER TABLE `{$wpdb->prefix}novalnet_transaction_detail` CHANGE COLUMN `$column` $to_change")); // phpcs:ignore.
				}
			}
		}
	}

	/**
	 * Check for column availablity.
	 *
	 * @since 12.0.0
	 * @param string $column The column name.
	 *
	 * @return boolean
	 */
	public function is_valid_column( $column ) {
		global $wpdb;
		// Check for column exists.
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}novalnet_transaction_detail` LIKE %s", $column ) ) ); // db call ok; no-cache ok.
	}

	/**
	 * Delete Novalnet related configuration from the table.
	 *
	 * @since 12.0.0
	 */
	public function delete_plugin_option() {
		global $wpdb;
		$this->handle_query( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '%novalnet_%' ) ) ); // db call ok; no-cache ok.
	}
}
