<?php
/**
 * AJAX Handler Class
 *
 * Handles AJAX requests for subscription syncing.
 *
 * @package EDD_Recurring_Subscription_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EDD_Recurring_Sync_Ajax_Handler
 */
class EDD_Recurring_Sync_Ajax_Handler {

	/**
	 * Single instance of the class.
	 *
	 * @var EDD_Recurring_Sync_Ajax_Handler
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return EDD_Recurring_Sync_Ajax_Handler
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_edd_sync_get_count', array( $this, 'get_subscription_count' ) );
		add_action( 'wp_ajax_edd_sync_process_chunk', array( $this, 'process_chunk' ) );
		add_action( 'wp_ajax_edd_sync_download_log', array( $this, 'download_log' ) );
		add_action( 'wp_ajax_edd_sync_initialize', array( $this, 'initialize_sync' ) );
	}

	/**
	 * Verify AJAX request.
	 *
	 * @return bool
	 */
	private function verify_request() {
		if ( ! check_ajax_referer( 'edd_recurring_sync_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'edd-recurring-sync' ),
			) );
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'edd-recurring-sync' ),
			) );
			return false;
		}

		return true;
	}

	/**
	 * Initialize a new sync session.
	 */
	public function initialize_sync() {
		$this->verify_request();

		$dry_run   = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === 'true';
		$sync_mode = isset( $_POST['sync_mode'] ) ? sanitize_text_field( $_POST['sync_mode'] ) : 'expired_future';
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';

		// Validate sync mode.
		if ( ! in_array( $sync_mode, array( 'expired_future', 'failing', 'all_active' ), true ) ) {
			$sync_mode = 'expired_future';
		}

		// Validate date format if provided.
		if ( ! empty( $date ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = '';
		}

		$processor = new EDD_Recurring_Sync_Processor();
		$processor->initialize_log( $dry_run, $sync_mode, $date );

		wp_send_json_success( array(
			'message'  => __( 'Sync session initialized.', 'edd-recurring-sync' ),
			'log_file' => $processor->get_log_filename(),
		) );
	}

	/**
	 * Get subscription count.
	 */
	public function get_subscription_count() {
		$this->verify_request();

		// IMPORTANT: Get the count from stored IDs, not a fresh database query.
		// The IDs were captured during initialize_sync() and stored in a transient.
		// Using a separate COUNT query here can return a different number if:
		// 1. Records were updated between initialize and this call
		// 2. Timing issues with the queries
		// This mismatch causes the JavaScript to stop early (e.g., at 60%).
		$subscription_ids = get_transient( 'edd_recurring_sync_ids' );

		if ( empty( $subscription_ids ) || ! is_array( $subscription_ids ) ) {
			wp_send_json_success( array(
				'total' => 0,
			) );
			return;
		}

		wp_send_json_success( array(
			'total' => count( $subscription_ids ),
		) );
	}

	/**
	 * Process a chunk of subscriptions.
	 */
	public function process_chunk() {
		$this->verify_request();

		$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === 'true';

		$processor = new EDD_Recurring_Sync_Processor();
		$results   = $processor->process_chunk( $offset, 10, $dry_run );

		// Debug logging
		error_log( "EDD Sync - process_chunk called: offset=$offset, dry_run=$dry_run, processed={$results['processed']}" );

		wp_send_json_success( array(
			'processed' => $results['processed'],
			'success'   => $results['success'],
			'errors'    => $results['errors'],
			'results'   => $results['results'],
			'debug'     => array(
				'offset'     => $offset,
				'sync_mode'  => get_transient( 'edd_recurring_sync_mode' ),
				'sync_date'  => get_transient( 'edd_recurring_sync_date' ),
			),
		) );
	}

	/**
	 * Download log file.
	 */
	public function download_log() {
		$this->verify_request();

		$processor = new EDD_Recurring_Sync_Processor();
		$log       = $processor->get_log_contents();

		if ( empty( $log ) ) {
			wp_send_json_error( array(
				'message' => __( 'No log file found.', 'edd-recurring-sync' ),
			) );
		}

		wp_send_json_success( array(
			'log'      => $log,
			'filename' => $processor->get_log_filename(),
		) );
	}
}
