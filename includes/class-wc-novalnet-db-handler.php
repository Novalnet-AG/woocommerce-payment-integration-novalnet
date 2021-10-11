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
	 * @var $_instance
	 * @see    Novalnet_DB_Handler()
	 * @return Novalnet_DB_Handler - Main instance.
	 */
	protected static $_instance = null;

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

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
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
	 * Returns original post_id based on TID.
	 *
	 * @since 12.0.0
	 * @param int $tid The tid value.
	 *
	 * @return array
	 */
	public function get_post_id_by_tid( $tid ) {
		global $wpdb;

		// Get post id based on TID.
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM `{$wpdb->posts}` WHERE post_excerpt LIKE %s", "%$tid%" ) ) ); // db call ok; no-cache ok.

	}

	/**
	 * Returns the order post_id.
	 *
	 * @since 12.0.0
	 * @param int $order_number The order number.
	 *
	 * @return array
	 */
	public function get_post_id_by_order_number( $order_number ) {

		global $wpdb;

		if ( $this->is_valid_column( 'order_number_formatted' ) ) {
			$post_id = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT order_no FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_number_formatted=%s", $order_number ) ) );// db call ok; no-cache ok.
			if ( ! empty( $post_id ) ) {
				return $post_id;
			}
		}
		// Get order post id.
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `{$wpdb->postmeta}` WHERE meta_value=%s AND (meta_key='_order_number_formatted' OR meta_key='_order_number' OR meta_key='_novalnet_order_number' )", $order_number ) ) ); // db call ok; no-cache ok.
	}

	/**
	 * Returns the transaction details
	 *
	 * @since 12.0.0
	 * @param int $post_id The post id.
	 * @param int $tid     The TID value.
	 *
	 * @return array
	 */
	public function get_transaction_details( $post_id, $tid = '' ) {

		global $wpdb;
		$result = array();

		// Select transaction details based on TID or post_id.
		if ( '' !== $tid ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, payment_type, amount, callback_amount, refunded_amount, gateway_status, tid, additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ), ARRAY_A ) );// db call ok; no-cache ok.
		}

		if ( empty( $result ) && ! empty( $post_id ) ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, payment_type, amount, callback_amount, refunded_amount, gateway_status, tid, additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s OR order_no=%s", $tid, $post_id ), ARRAY_A ) );// db call ok; no-cache ok.
		}

		if ( ! empty( $result ['gateway_status'] ) ) {
			novalnet()->helper()->status_mapper( $result ['gateway_status'] );
		}
		return $result;

	}

	/**
	 * Returns the subscription details
	 *
	 * @since 12.0.0
	 * @param int $tid The TID value.
	 *
	 * @return array
	 */
	public function get_subscription_details( $tid = '' ) {

		global $wpdb;
		$result = array();

		// Select transaction details based on TID.
		if ( '' !== $tid ) {
			$result = $this->handle_query( $wpdb->get_row( $wpdb->prepare( "SELECT order_no, recurring_payment_type, tid, recurring_tid FROM `{$wpdb->prefix}novalnet_subscription_details` WHERE recurring_tid=%s OR tid=%s", $tid, $tid ), ARRAY_A ) );// db call ok; no-cache ok.
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
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT tid FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) );// db call ok; no-cache ok.
		} elseif ( 'gateway_status' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT gateway_status FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) );// db call ok; no-cache ok.
			if ( ! empty( $result ) ) {
				novalnet()->helper()->status_mapper( $result );
			}
		} elseif ( 'additional_info' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) );// db call ok; no-cache ok.
			if ( ! empty( $result ) ) {
				$result = wc_novalnet_unserialize_data( $result );
			}
		} elseif ( 'amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) );// db call ok; no-cache ok.
		} elseif ( 'refunded_amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) );// db call ok; no-cache ok.
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
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT gateway_status FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ) ) );// db call ok; no-cache ok.

			if ( ! empty( $result ) ) {
				novalnet()->helper()->status_mapper( $result );
			}
		} elseif ( 'additional_info' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT additional_info FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ) ) );// db call ok; no-cache ok.
			$result = wc_novalnet_unserialize_data( $result );
		} elseif ( 'refunded_amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT refunded_amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE order_no=%s", $post_id ) ) );// db call ok; no-cache ok.
		} elseif ( 'amount' === $column ) {
			$result = $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM `{$wpdb->prefix}novalnet_transaction_detail` WHERE tid=%s", $tid ) ) );// db call ok; no-cache ok.
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
					$this->handle_query( $wpdb->query( "ALTER TABLE `{$wpdb->prefix}novalnet_transaction_detail` DROP COLUMN $column" ) ); // WPCS: unprepared SQL ok. db call ok; no-cache ok.
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
					$this->handle_query( $wpdb->query( "ALTER TABLE `{$wpdb->prefix}novalnet_transaction_detail` CHANGE COLUMN `$column` $to_change" ) ); // WPCS: unprepared SQL ok. db call ok; no-cache ok.
				}
			}
		}
	}

	/**
	 * Get post ID of the given meta
	 *
	 * @since 12.0.0
	 * @param string $meta The meta value.
	 *
	 * @return string
	 */
	public function get_post_id_by_meta_data( $meta ) {

		global $wpdb;

		// Check for column exists.
		return $this->handle_query( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value =%s", $meta ) ) ); // db call ok; no-cache ok.
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
