<?php
/**
 * New Instalment Email
 *
 * An email to en customer to notify about the new instalment.
 *
 * @class    WC_Novalnet_Email_New_Instalment
 * @extends  WC_Email_Customer_Completed_Order
 * @package  woocommerce-novalnet-gateway/includes/emails/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Email_New_Instalment Class.
 */
class WC_Novalnet_Email_New_Instalment extends WC_Email_Customer_Completed_Order {

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->id             = 'novalnet_new_instalment';
		$this->customer_email = true;
		$this->title          = __( 'New Instalment', 'woocommerce-novalnet-gateway' );
		$this->description    = __( 'New instalment cycle email is sent when a new instalment cycle is processed', 'woocommerce-novalnet-gateway' );

		$this->heading = __( 'New Instalment for the order', 'woocommerce-novalnet-gateway' );
		$this->subject = __( '[{blogname}] New Instalment for the order', 'woocommerce-novalnet-gateway' );

		$this->template_html = 'emails/customer-new-instalment-order.php';
		$this->template_base = novalnet()->plugin_dir_path . '/templates/';

		// Triggers for this email.
		add_action( 'novalnet_send_instalment_notification_to_customer', array( $this, 'trigger' ), 10, 2 );

		// We want all the parent's methods, with none of its properties, so call its parent's constructor, rather than my parent constructor.
		WC_Email::__construct();
	}

	/**
	 *
	 * Trigger function.
	 *
	 * We need to override WC_Email_New_Order's trigger
	 *
	 * @param int    $order_id The post id.
	 * @param object $order The order.
	 *
	 * @access public
	 * @return void
	 */
	public function trigger( $order_id, $order = false ) {
		if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
			$this->setup_locale();
		}

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object                            = $order;
			$this->recipient                         = $this->object->get_billing_email();
			$this->placeholders['{order_date}']      = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{order_number}']    = $this->object->get_order_number();
			$this->placeholders['{instalment_date}'] = wc_novalnet_formatted_date();
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
			$this->restore_locale();
		}
	}


	/**
	 * Get_content_html function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Get email subject.
	 *
	 * @since  3.1.0
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{blogname}] New Instalment for the order', 'woocommerce-novalnet-gateway' );
	}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
	public function get_default_heading() {
		return __( 'New Instalment for the order', 'woocommerce-novalnet-gateway' );
	}
}
