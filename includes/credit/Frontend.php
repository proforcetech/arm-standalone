<?php
/**
 * Credit Account Frontend
 *
 * @package ARM_Repair_Estimates
 */

namespace ARM\Credit;

/**
 * Customer-facing credit account functionality.
 */
class Frontend {
	/**
	 * Bootstrap the module.
	 */
	public static function boot() {
		add_shortcode( 'arm_credit_account', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'arm_payment_form', array( __CLASS__, 'render_payment_form' ) );
		add_action( 'ajax_arm_customer_credit_history', array( __CLASS__, 'ajax_get_history' ) );
		add_action( 'ajax_arm_submit_payment', array( __CLASS__, 'ajax_submit_payment' ) );
	}

	/**
	 * Render credit account shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to view your credit account.', 'arm-repair-estimates' ) . '</p>';
		}

		global $db;

		$user        = get_current_user();
		$customer_id = self::get_customer_id_from_user( $user->ID );

		if ( ! $customer_id ) {
			return '<p>' . __( 'No customer account found.', 'arm-repair-estimates' ) . '</p>';
		}

		// Get credit account
		$account = $db->get_row(
			$db->prepare(
				"SELECT * FROM {$db->prefix}arm_credit_accounts WHERE customer_id = %d",
				$customer_id
			)
		);

		if ( ! $account ) {
			return '<p>' . __( 'You do not have a credit account. Please contact us to set up credit terms.', 'arm-repair-estimates' ) . '</p>';
		}

		// Get recent transactions
		$transactions = $db->get_results(
			$db->prepare(
				"SELECT * FROM {$db->prefix}arm_credit_transactions
				WHERE account_id = %d
				ORDER BY transaction_date DESC
				LIMIT 50",
				$account->id
			)
		);

		// Get payment summary
		$payment_summary = $db->get_row(
			$db->prepare(
				"SELECT
					COUNT(*) as total_payments,
					SUM(amount) as total_paid
				FROM {$db->prefix}arm_credit_payments
				WHERE account_id = %d AND status = 'completed'",
				$account->id
			)
		);

		ob_start();
		include ARM_RE_PLUGIN_DIR . 'templates/customer/credit-account.php';
		return ob_get_clean();
	}

	/**
	 * Get customer ID from user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int|null Customer ID or null.
	 */
	private static function get_customer_id_from_user( $user_id ) {
		global $db;
		$customer = $db->get_row(
			$db->prepare(
				"SELECT id FROM {$db->prefix}arm_customers WHERE email = (SELECT user_email FROM {$db->users} WHERE ID = %d)",
				$user_id
			)
		);
		return $customer ? $customer->id : null;
	}

	/**
	 * AJAX: Get credit history.
	 */
	public static function ajax_get_history() {
		if ( ! is_user_logged_in() ) {
			send_json_error( array( 'message' => __( 'Unauthorized', 'arm-repair-estimates' ) ) );
		}

		check_ajax_referer( 'arm_credit_customer_ajax', 'nonce' );

		global $db;
		$user        = get_current_user();
		$customer_id = self::get_customer_id_from_user( $user->ID );

		if ( ! $customer_id ) {
			send_json_error( array( 'message' => __( 'Customer not found.', 'arm-repair-estimates' ) ) );
		}

		$account = $db->get_row(
			$db->prepare(
				"SELECT id FROM {$db->prefix}arm_credit_accounts WHERE customer_id = %d",
				$customer_id
			)
		);

		if ( ! $account ) {
			send_json_error( array( 'message' => __( 'Account not found.', 'arm-repair-estimates' ) ) );
		}

		$limit  = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 50;
		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

		$transactions = $db->get_results(
			$db->prepare(
				"SELECT * FROM {$db->prefix}arm_credit_transactions
				WHERE account_id = %d
				ORDER BY transaction_date DESC
				LIMIT %d OFFSET %d",
				$account->id,
				$limit,
				$offset
			)
		);

		send_json_success( $transactions );
	}

	/**
	 * Render payment form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_payment_form( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			$login_url = login_url( get_permalink() );
			return '<p>' . sprintf( __( 'Please <a href="%s">log in</a> to make a payment.', 'arm-repair-estimates' ), esc_url( $login_url ) ) . '</p>';
		}

		global $db;

		$user        = get_current_user();
		$customer_id = self::get_customer_id_from_user( $user->ID );

		if ( ! $customer_id ) {
			return '<p>' . __( 'No customer account found.', 'arm-repair-estimates' ) . '</p>';
		}

		// Get credit account
		$account = $db->get_row(
			$db->prepare(
				"SELECT * FROM {$db->prefix}arm_credit_accounts WHERE customer_id = %d",
				$customer_id
			)
		);

		if ( ! $account ) {
			return '<p>' . __( 'You do not have a credit account. Please contact us to set up credit terms.', 'arm-repair-estimates' ) . '</p>';
		}

		ob_start();
		include ARM_RE_PLUGIN_DIR . 'templates/customer/payment-form.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: Submit payment.
	 */
	public static function ajax_submit_payment() {
		if ( ! is_user_logged_in() ) {
			send_json_error( array( 'message' => __( 'Unauthorized', 'arm-repair-estimates' ) ) );
		}

		check_ajax_referer( 'arm_payment_form', 'nonce' );

		global $db;
		$user        = get_current_user();
		$customer_id = self::get_customer_id_from_user( $user->ID );

		if ( ! $customer_id ) {
			send_json_error( array( 'message' => __( 'Customer not found.', 'arm-repair-estimates' ) ) );
		}

		$account = $db->get_row(
			$db->prepare(
				"SELECT * FROM {$db->prefix}arm_credit_accounts WHERE customer_id = %d",
				$customer_id
			)
		);

		if ( ! $account ) {
			send_json_error( array( 'message' => __( 'Account not found.', 'arm-repair-estimates' ) ) );
		}

		// Validate payment data
		$amount         = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( $_POST['payment_method'] ) : '';
		$reference      = isset( $_POST['reference'] ) ? sanitize_text_field( $_POST['reference'] ) : '';
		$notes          = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

		if ( $amount <= 0 ) {
			send_json_error( array( 'message' => __( 'Invalid payment amount.', 'arm-repair-estimates' ) ) );
		}

		if ( empty( $payment_method ) ) {
			send_json_error( array( 'message' => __( 'Payment method is required.', 'arm-repair-estimates' ) ) );
		}

		// Insert payment record
		$payment_data = array(
			'account_id'       => $account->id,
			'customer_id'      => $customer_id,
			'payment_method'   => $payment_method,
			'amount'           => $amount,
			'payment_date'     => current_time( 'mysql' ),
			'reference_number' => $reference,
			'notes'            => $notes,
			'processed_by'     => $user->ID,
			'status'           => 'pending', // Mark as pending for admin approval
		);

		$inserted = $db->insert(
			$db->prefix . 'arm_credit_payments',
			$payment_data,
			array( '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			send_json_error( array( 'message' => __( 'Failed to submit payment. Please try again.', 'arm-repair-estimates' ) ) );
		}

		send_json_success(
			array(
				'message' => __( 'Payment submitted successfully and is pending approval.', 'arm-repair-estimates' ),
			)
		);
	}
}
