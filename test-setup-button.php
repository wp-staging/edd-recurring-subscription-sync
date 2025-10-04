<?php
/**
 * Temporary Test Button - Setup Test Data
 *
 * WARNING: This modifies the database! Only use on test/staging sites!
 *
 * This file adds a button to the sync page that changes 300 active
 * subscriptions to expired status for testing purposes.
 *
 * To activate: Include this file in edd-recurring-subscription-sync.php
 * To remove: Delete this file and remove the include
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add test setup button to admin page
 */
add_action( 'edd_recurring_sync_after_tabs', 'edd_recurring_sync_test_setup_button' );
function edd_recurring_sync_test_setup_button() {
	?>
	<div class="edd-recurring-sync-test-setup" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
		<h3 style="margin-top: 0; color: #856404;">⚠️ Test Data Setup (STAGING ONLY)</h3>
		<p style="color: #856404;">
			<strong>WARNING:</strong> This will modify the database by changing active subscriptions to expired status.
			Only use this on test/staging environments!
		</p>
		<button type="button" id="edd-test-setup-expired" class="button button-secondary" style="background: #ffc107; border-color: #ffc107; color: #000;">
			Change 300 Active → Expired (for testing)
		</button>
		<button type="button" id="edd-test-restore-active" class="button button-secondary" style="margin-left: 10px;">
			Restore Last 300 → Active
		</button>
		<div id="edd-test-setup-result" style="margin-top: 10px;"></div>
	</div>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Setup expired subscriptions
		$('#edd-test-setup-expired').on('click', function(e) {
			e.preventDefault();

			if (!confirm('⚠️ WARNING: This will change 300 active subscriptions to expired status!\n\nOnly proceed on test/staging sites!\n\nContinue?')) {
				return;
			}

			var $button = $(this);
			var $result = $('#edd-test-setup-result');

			$button.prop('disabled', true).text('Setting up test data...');
			$result.html('<p style="color: #856404;">Processing...</p>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'edd_test_setup_expired',
					nonce: '<?php echo wp_create_nonce( 'edd_test_setup_nonce' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						$result.html(
							'<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;">' +
							'<strong>✓ Success!</strong><br>' +
							response.data.message +
							'</div>'
						);
					} else {
						$result.html(
							'<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">' +
							'<strong>✗ Error:</strong> ' + response.data.message +
							'</div>'
						);
					}
					$button.prop('disabled', false).text('Change 300 Active → Expired (for testing)');
				},
				error: function() {
					$result.html(
						'<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">' +
						'<strong>✗ Error:</strong> AJAX request failed' +
						'</div>'
					);
					$button.prop('disabled', false).text('Change 300 Active → Expired (for testing)');
				}
			});
		});

		// Restore active subscriptions
		$('#edd-test-restore-active').on('click', function(e) {
			e.preventDefault();

			if (!confirm('Restore the last 300 modified subscriptions back to active status?')) {
				return;
			}

			var $button = $(this);
			var $result = $('#edd-test-setup-result');

			$button.prop('disabled', true).text('Restoring...');
			$result.html('<p style="color: #856404;">Processing...</p>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'edd_test_restore_active',
					nonce: '<?php echo wp_create_nonce( 'edd_test_setup_nonce' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						$result.html(
							'<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;">' +
							'<strong>✓ Success!</strong><br>' +
							response.data.message +
							'</div>'
						);
					} else {
						$result.html(
							'<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">' +
							'<strong>✗ Error:</strong> ' + response.data.message +
							'</div>'
						);
					}
					$button.prop('disabled', false).text('Restore Last 300 → Active');
				},
				error: function() {
					$result.html(
						'<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">' +
						'<strong>✗ Error:</strong> AJAX request failed' +
						'</div>'
					);
					$button.prop('disabled', false).text('Restore Last 300 → Active');
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * AJAX handler: Change active subscriptions to expired
 */
add_action( 'wp_ajax_edd_test_setup_expired', 'edd_test_setup_expired_handler' );
function edd_test_setup_expired_handler() {
	// Security check
	if ( ! check_ajax_referer( 'edd_test_setup_nonce', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	global $wpdb;

	// Get 300 active subscriptions with future expiration dates
	$subscriptions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, status
			FROM {$wpdb->prefix}edd_subscriptions
			WHERE status = 'active'
			AND expiration > %s
			AND gateway = 'stripe'
			AND profile_id != ''
			ORDER BY id ASC
			LIMIT 300",
			current_time( 'mysql' )
		)
	);

	if ( empty( $subscriptions ) ) {
		wp_send_json_error( array(
			'message' => 'No active subscriptions found to modify. You may need active subscriptions with future expiration dates first.'
		) );
	}

	$count = count( $subscriptions );
	$ids = array_map( function( $sub ) { return $sub->id; }, $subscriptions );

	// Store the IDs so we can restore them later
	set_transient( 'edd_test_modified_ids', $ids, DAY_IN_SECONDS );

	// Change status to expired
	$ids_string = implode( ',', array_map( 'intval', $ids ) );
	$updated = $wpdb->query(
		"UPDATE {$wpdb->prefix}edd_subscriptions
		SET status = 'expired'
		WHERE id IN ($ids_string)"
	);

	if ( $updated === false ) {
		wp_send_json_error( array( 'message' => 'Database update failed' ) );
	}

	wp_send_json_success( array(
		'message' => sprintf(
			'Changed %d subscriptions from active to expired.<br>' .
			'IDs: %s<br><br>' .
			'<strong>Next step:</strong> Run a live sync to test the fix!',
			$count,
			implode( ', ', array_slice( $ids, 0, 20 ) ) . ( $count > 20 ? '...' : '' )
		),
		'count' => $count,
		'ids' => $ids
	) );
}

/**
 * AJAX handler: Restore subscriptions to active
 */
add_action( 'wp_ajax_edd_test_restore_active', 'edd_test_restore_active_handler' );
function edd_test_restore_active_handler() {
	// Security check
	if ( ! check_ajax_referer( 'edd_test_setup_nonce', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	global $wpdb;

	// Get stored IDs
	$ids = get_transient( 'edd_test_modified_ids' );

	if ( empty( $ids ) ) {
		wp_send_json_error( array(
			'message' => 'No stored IDs found. Run "Change 300 Active → Expired" first.'
		) );
	}

	// Restore to active
	$ids_string = implode( ',', array_map( 'intval', $ids ) );
	$updated = $wpdb->query(
		"UPDATE {$wpdb->prefix}edd_subscriptions
		SET status = 'active'
		WHERE id IN ($ids_string)"
	);

	if ( $updated === false ) {
		wp_send_json_error( array( 'message' => 'Database update failed' ) );
	}

	// Clean up transient
	delete_transient( 'edd_test_modified_ids' );

	wp_send_json_success( array(
		'message' => sprintf(
			'Restored %d subscriptions back to active status.<br>' .
			'IDs: %s',
			count( $ids ),
			implode( ', ', array_slice( $ids, 0, 20 ) ) . ( count( $ids ) > 20 ? '...' : '' )
		),
		'count' => count( $ids )
	) );
}
