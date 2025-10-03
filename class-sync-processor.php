<?php
/**
 * Sync Processor Class
 *
 * Handles the core logic for syncing subscriptions with Stripe.
 *
 * @package EDD_Recurring_Subscription_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EDD_Recurring_Sync_Processor
 */
class EDD_Recurring_Sync_Processor {

	/**
	 * Current log file path.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_file = $this->get_log_file_path();
	}

	/**
	 * Get affected subscriptions.
	 *
	 * @param string $mode Sync mode: 'expired_future' or 'all_active'.
	 * @param string $date Optional. Only sync subscriptions updated after this date (Y-m-d H:i:s format).
	 * @return array
	 */
	public function get_affected_subscriptions( $mode = 'expired_future', $date = '' ) {
		global $wpdb;

		$current_date = current_time( 'mysql' );

		if ( 'expired_future' === $mode ) {
			// Original mode: expired subscriptions with future expiration dates.
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}edd_subscriptions
				WHERE status = 'expired'
				AND expiration > %s
				AND gateway = 'stripe'
				AND profile_id != ''
				ORDER BY id ASC",
				$current_date
			);
		} else {
			// New mode: all subscriptions (any status) to verify against Stripe.
			$base_sql = "SELECT * FROM {$wpdb->prefix}edd_subscriptions
				WHERE gateway = 'stripe'
				AND profile_id != ''";

			// Add date filter if provided.
			if ( ! empty( $date ) ) {
				$sql = $wpdb->prepare(
					$base_sql . " AND date_modified >= %s ORDER BY id ASC",
					$date
				);
			} else {
				$sql = $base_sql . " ORDER BY id ASC";
			}
		}

		$results = $wpdb->get_results( $sql );

		return $results ? $results : array();
	}

	/**
	 * Get subscription IDs for a sync session.
	 *
	 * @param string $mode Sync mode: 'expired_future' or 'all_active'.
	 * @param string $date Optional. Only sync subscriptions updated after this date.
	 * @return array Array of subscription IDs.
	 */
	private function get_subscription_ids( $mode = 'expired_future', $date = '' ) {
		global $wpdb;

		$current_date = current_time( 'mysql' );

		if ( 'expired_future' === $mode ) {
			// Original mode: expired subscriptions with future expiration dates.
			$sql = $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}edd_subscriptions
				WHERE status = 'expired'
				AND expiration > %s
				AND gateway = 'stripe'
				AND profile_id != ''
				ORDER BY id ASC",
				$current_date
			);
		} else {
			// New mode: all subscriptions (any status) to verify against Stripe.
			$base_sql = "SELECT id FROM {$wpdb->prefix}edd_subscriptions
				WHERE gateway = 'stripe'
				AND profile_id != ''";

			// Add date filter if provided.
			if ( ! empty( $date ) ) {
				$sql = $wpdb->prepare(
					$base_sql . " AND date_modified >= %s ORDER BY id ASC",
					$date
				);
			} else {
				$sql = $base_sql . " ORDER BY id ASC";
			}
		}

		$results = $wpdb->get_col( $sql );

		return $results ? array_map( 'intval', $results ) : array();
	}

	/**
	 * Get statistics about affected subscriptions.
	 *
	 * @param string $mode Sync mode: 'expired_future' or 'all_active'.
	 * @param string $date Optional. Only count subscriptions updated after this date.
	 * @return array
	 */
	public function get_statistics( $mode = 'expired_future', $date = '' ) {
		global $wpdb;
		$current_date = current_time( 'mysql' );

		// Use COUNT query instead of loading all subscriptions.
		if ( 'expired_future' === $mode ) {
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions
					WHERE status = 'expired'
					AND expiration > %s
					AND gateway = 'stripe'
					AND profile_id != ''",
					$current_date
				)
			);
		} else {
			$base_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions
				WHERE gateway = 'stripe'
				AND profile_id != ''";

			if ( ! empty( $date ) ) {
				$total = $wpdb->get_var(
					$wpdb->prepare( $base_sql . " AND date_modified >= %s", $date )
				);
			} else {
				$total = $wpdb->get_var( $base_sql );
			}
		}

		$stats = array(
			'total'          => intval( $total ),
			'by_days_future' => array(
				'0-30'  => 0,
				'31-60' => 0,
				'61-90' => 0,
				'90+'   => 0,
			),
		);

		// Only calculate day breakdowns for expired_future mode.
		if ( 'expired_future' === $mode && $total > 0 ) {
			$now = current_time( 'timestamp' );

			// Get the counts by date ranges using SQL.
			$ranges = array(
				'0-30'  => array( 0, 30 ),
				'31-60' => array( 31, 60 ),
				'61-90' => array( 61, 90 ),
				'90+'   => array( 91, 36500 ), // Max 100 years
			);

			foreach ( $ranges as $key => $range ) {
				$start_date = date( 'Y-m-d H:i:s', strtotime( "+{$range[0]} days" ) );
				$end_date   = date( 'Y-m-d H:i:s', strtotime( "+{$range[1]} days" ) );

				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions
						WHERE status = 'expired'
						AND expiration > %s
						AND expiration >= %s
						AND expiration <= %s
						AND gateway = 'stripe'
						AND profile_id != ''",
						$current_date,
						$start_date,
						$end_date
					)
				);

				$stats['by_days_future'][ $key ] = intval( $count );
			}
		}

		return $stats;
	}

	/**
	 * Process a chunk of subscriptions.
	 *
	 * @param int  $offset   Offset for query.
	 * @param int  $limit    Number to process.
	 * @param bool $dry_run  Whether this is a dry run.
	 * @return array Results of processing.
	 */
	public function process_chunk( $offset = 0, $limit = 10, $dry_run = true ) {
		global $wpdb;

		// Get the stored subscription IDs for this session.
		$all_ids = get_transient( 'edd_recurring_sync_ids' );

		if ( empty( $all_ids ) || ! is_array( $all_ids ) ) {
			// Fallback: If IDs aren't stored, return empty.
			return array(
				'processed' => 0,
				'success'   => 0,
				'errors'    => 0,
				'results'   => array(),
			);
		}

		// Get the chunk of IDs to process.
		$chunk_ids = array_slice( $all_ids, $offset, $limit );

		// Debug logging
		error_log( sprintf(
			'EDD Sync - array_slice: total_ids=%d, offset=%d, limit=%d, chunk_ids_count=%d, chunk_ids=%s',
			count( $all_ids ),
			$offset,
			$limit,
			count( $chunk_ids ),
			implode( ',', $chunk_ids )
		) );

		if ( empty( $chunk_ids ) ) {
			return array(
				'processed' => 0,
				'success'   => 0,
				'errors'    => 0,
				'results'   => array(),
			);
		}

		// Fetch subscriptions by ID.
		// This prevents offset drift when records are updated and no longer match WHERE clauses.
		// IMPORTANT: Build IN clause directly with intval() for safety, since wpdb->prepare()
		// doesn't accept arrays. We use array_map('intval') to ensure all IDs are integers.
		$ids_string = implode( ',', array_map( 'intval', $chunk_ids ) );
		$sql        = "SELECT * FROM {$wpdb->prefix}edd_subscriptions
			WHERE id IN ($ids_string)
			ORDER BY id ASC";

		$subscriptions = $wpdb->get_results( $sql );

		// Debug logging
		error_log( sprintf(
			'EDD Sync - DB query: chunk_ids=%s, returned=%d subscriptions',
			implode( ',', $chunk_ids ),
			count( $subscriptions )
		) );

		if ( empty( $subscriptions ) ) {
			return array(
				'processed' => 0,
				'success'   => 0,
				'errors'    => 0,
				'results'   => array(),
			);
		}

		$results = array(
			'processed' => 0,
			'success'   => 0,
			'errors'    => 0,
			'results'   => array(),
		);

		// Get sync mode for processing.
		$sync_mode = get_transient( 'edd_recurring_sync_mode' );
		if ( empty( $sync_mode ) ) {
			$sync_mode = 'expired_future';
		}

		foreach ( $subscriptions as $sub_data ) {
			$result = $this->process_single_subscription( $sub_data, $dry_run, $sync_mode );
			$results['processed']++;

			if ( $result['success'] ) {
				$results['success']++;
			} else {
				$results['errors']++;
			}

			$results['results'][] = $result;
		}

		return $results;
	}

	/**
	 * Process a single subscription.
	 *
	 * @param object $sub_data  Subscription data from database.
	 * @param bool   $dry_run   Whether this is a dry run.
	 * @param string $sync_mode Sync mode: 'expired_future' or 'all_active'.
	 * @return array Result of processing.
	 */
	private function process_single_subscription( $sub_data, $dry_run = true, $sync_mode = 'expired_future' ) {
		$result = array(
			'id'                 => $sub_data->id,
			'profile_id'         => $sub_data->profile_id,
			'current_status'     => $sub_data->status,
			'current_expiration' => $sub_data->expiration,
			'stripe_status'      => null,
			'stripe_expiration'  => null,
			'new_status'         => null,
			'new_expiration'     => null,
			'action'             => 'none',
			'success'            => false,
			'message'            => '',
		);

		// Get Stripe subscription.
		try {
			$stripe_sub = edds_api_request( 'Subscription', 'retrieve', $sub_data->profile_id );

			if ( ! $stripe_sub ) {
				throw new Exception( 'Subscription not found in Stripe' );
			}

			$result['stripe_status'] = $stripe_sub->status;

			// Map Stripe status to EDD status.
			$new_status = $this->map_stripe_status( $stripe_sub->status );

			// Calculate new expiration with grace period.
			$new_expiration = null;
			if ( ! empty( $stripe_sub->current_period_end ) ) {
				$grace_period   = HOUR_IN_SECONDS * 1.5;
				$expiration_ts  = $stripe_sub->current_period_end + $grace_period;
				$new_expiration = date( 'Y-m-d H:i:s', $expiration_ts );
			}

			$result['stripe_expiration'] = $new_expiration;
			$result['new_status']        = $new_status;
			$result['new_expiration']    = $new_expiration;

			// Determine if update is needed.
			$needs_update = false;
			$changes      = array();

			// Always show status (even if identical)
			if ( $new_status !== $sub_data->status ) {
				$needs_update = true;
				$changes[]    = sprintf( 'status: %s → %s', $sub_data->status, $new_status );
			} else {
				$changes[]    = sprintf( 'status: %s (no change)', $sub_data->status );
			}

			if ( $new_expiration && $new_expiration !== $sub_data->expiration ) {
				// In dry run mode, always show expiration changes
				if ( $dry_run ) {
					$needs_update = true;
					$changes[]    = sprintf( 'expiration: %s → %s', $sub_data->expiration, $new_expiration );
				}
				// In live mode, only update expiration for expired_future mode
				elseif ( $sync_mode === 'expired_future' ) {
					$needs_update = true;
					$changes[]    = sprintf( 'expiration: %s → %s', $sub_data->expiration, $new_expiration );
				}
				// In all_active live mode, show difference but don't update
				else {
					$changes[]    = sprintf( 'expiration: %s → %s (not synced in full audit mode)', $sub_data->expiration, $new_expiration );
				}
			}

			if ( $needs_update ) {
				$result['action'] = 'update';

				if ( $dry_run ) {
					$result['message'] = 'Would update: ' . implode( ', ', $changes );
					$result['success'] = true;
				} else {
					// Create backup before update.
					$this->backup_subscription( $sub_data );

					// Update the subscription.
					$subscription = new EDD\Recurring\Subscriptions\Subscription( $sub_data->id );

					$update_args = array();
					if ( $new_status !== $sub_data->status ) {
						$update_args['status'] = $new_status;
					}
					// Only update expiration in expired_future mode
					if ( $new_expiration && $new_expiration !== $sub_data->expiration && $sync_mode === 'expired_future' ) {
						$update_args['expiration'] = $new_expiration;
					}

					$updated = $subscription->update( $update_args );

					if ( $updated ) {
						$subscription->add_note(
							sprintf(
								'Subscription synced with Stripe. Changes: %s',
								implode( ', ', $changes )
							)
						);
						$result['message'] = 'Updated: ' . implode( ', ', $changes );
						$result['success'] = true;
					} else {
						$result['message'] = 'Failed to update subscription in database';
						$result['success'] = false;
					}
				}
			} else {
				$result['action']  = 'skip';
				$result['message'] = 'Already in sync: ' . implode( ', ', $changes );
				$result['success'] = true;
			}

			$this->log_result( $result, $dry_run );

		} catch ( Exception $e ) {
			$result['message'] = 'Error: ' . $e->getMessage();
			$result['success'] = false;
			$this->log_result( $result, $dry_run );
		}

		return $result;
	}

	/**
	 * Map Stripe subscription status to EDD status.
	 *
	 * @param string $stripe_status Stripe subscription status.
	 * @return string EDD subscription status.
	 */
	private function map_stripe_status( $stripe_status ) {
		$status_map = array(
			'active'             => 'active',
			'trialing'           => 'trialling',
			'canceled'           => 'cancelled',
			'past_due'           => 'failing',
			'unpaid'             => 'failing',  // Unpaid means failed payment, not expired
			'incomplete'         => 'pending',
			'incomplete_expired' => 'expired',
		);

		return isset( $status_map[ $stripe_status ] ) ? $status_map[ $stripe_status ] : $stripe_status;
	}

	/**
	 * Backup subscription data.
	 *
	 * @param object $sub_data Subscription data.
	 */
	private function backup_subscription( $sub_data ) {
		$backup_file = EDD_RECURRING_SYNC_LOGS_DIR . 'backup-' . date( 'Y-m-d-His' ) . '.log';
		$backup_data = sprintf(
			"[Backup] ID: %d | Status: %s | Expiration: %s | Profile: %s\n",
			$sub_data->id,
			$sub_data->status,
			$sub_data->expiration,
			$sub_data->profile_id
		);

		file_put_contents( $backup_file, $backup_data, FILE_APPEND );
	}

	/**
	 * Log processing result.
	 *
	 * @param array $result  Processing result.
	 * @param bool  $dry_run Whether this is a dry run.
	 */
	private function log_result( $result, $dry_run ) {
		$mode = $dry_run ? 'DRY RUN' : 'LIVE SYNC';

		$log_entry = sprintf(
			"[%s] %s\n" .
			"  ID: %d\n" .
			"  Profile ID: %s\n" .
			"  Current Status: %s | Current Expiration: %s\n" .
			"  Stripe Status: %s | Stripe Expiration: %s\n" .
			"  Action: %s\n" .
			"  Message: %s\n" .
			"  Success: %s\n" .
			"---\n",
			$mode,
			current_time( 'Y-m-d H:i:s' ),
			$result['id'],
			$result['profile_id'],
			$result['current_status'],
			$result['current_expiration'],
			$result['stripe_status'] ?? 'N/A',
			$result['stripe_expiration'] ?? 'N/A',
			$result['action'],
			$result['message'],
			$result['success'] ? 'YES' : 'NO'
		);

		file_put_contents( $this->log_file, $log_entry, FILE_APPEND );
	}

	/**
	 * Get log file path.
	 *
	 * @return string
	 */
	private function get_log_file_path() {
		$session_id = get_transient( 'edd_recurring_sync_session' );

		if ( ! $session_id ) {
			$session_id = date( 'Y-m-d-His' );
			set_transient( 'edd_recurring_sync_session', $session_id, HOUR_IN_SECONDS );
		}

		return EDD_RECURRING_SYNC_LOGS_DIR . 'sync-' . $session_id . '.log';
	}

	/**
	 * Get log file contents.
	 *
	 * @return string
	 */
	public function get_log_contents() {
		if ( file_exists( $this->log_file ) ) {
			return file_get_contents( $this->log_file );
		}

		return '';
	}

	/**
	 * Initialize new log file.
	 *
	 * @param bool   $dry_run Whether this is a dry run.
	 * @param string $sync_mode Sync mode: 'expired_future' or 'all_active'.
	 * @param string $date Optional. Only sync subscriptions updated after this date.
	 */
	public function initialize_log( $dry_run = true, $sync_mode = 'expired_future', $date = '' ) {
		// Create new session ID BEFORE deleting the old one.
		$session_id = date( 'Y-m-d-His' );
		delete_transient( 'edd_recurring_sync_session' );
		set_transient( 'edd_recurring_sync_session', $session_id, HOUR_IN_SECONDS );

		$this->log_file = $this->get_log_file_path();

		// Store the sync parameters for this session.
		set_transient( 'edd_recurring_sync_mode', $sync_mode, HOUR_IN_SECONDS );
		set_transient( 'edd_recurring_sync_date', $date, HOUR_IN_SECONDS );

		// CRITICAL: Store all subscription IDs upfront to prevent offset drift
		// When we update subscriptions (e.g., expired -> active), they may no longer
		// match the WHERE clause, causing pagination to skip records.
		$subscription_ids = $this->get_subscription_ids( $sync_mode, $date );
		set_transient( 'edd_recurring_sync_ids', $subscription_ids, HOUR_IN_SECONDS );

		$mode_label = $dry_run ? 'DRY RUN' : 'LIVE SYNC';
		$sync_type  = 'expired_future' === $sync_mode ? 'Expired with Future Dates' : 'All Subscriptions (Full Audit)';

		$header = sprintf(
			"=== EDD Recurring Subscription Sync Log ===\n" .
			"Mode: %s\n" .
			"Sync Type: %s\n" .
			"Date Filter: %s\n" .
			"Date: %s\n" .
			"===\n\n",
			$mode_label,
			$sync_type,
			! empty( $date ) ? $date : 'None',
			current_time( 'Y-m-d H:i:s' )
		);

		file_put_contents( $this->log_file, $header );

		// Update last sync run timestamp for all_active mode.
		if ( 'all_active' === $sync_mode && ! $dry_run ) {
			update_option( 'edd_recurring_sync_last_run', current_time( 'mysql' ) );
		}
	}

	/**
	 * Get current log file name.
	 *
	 * @return string
	 */
	public function get_log_filename() {
		return basename( $this->log_file );
	}
}
