<?php
/**
 * Test script for chunk processing issue
 *
 * Run via WP-CLI: wp eval-file test-chunk-processing.php
 * Or access via browser with admin authentication.
 *
 * @package EDD_Recurring_Subscription_Sync
 */

// Security check for browser access.
if ( ! defined( 'WP_CLI' ) ) {
	// Require WordPress.
	require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

	// Require admin access.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized access.' );
	}
}

echo "=== Testing Chunk Processing Issue ===\n\n";

// Initialize processor
$processor = new EDD_Recurring_Sync_Processor();

// Test 1: Get total count
echo "TEST 1: Get total subscription count\n";
echo "---------------------------------------\n";
$all_subs = $processor->get_affected_subscriptions( 'all_active' );
$total_count = count( $all_subs );
echo "Total subscriptions returned by get_affected_subscriptions(): $total_count\n\n";

// Test 2: Direct database query
echo "TEST 2: Direct database query\n";
echo "---------------------------------------\n";
global $wpdb;
$db_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions
	WHERE gateway = 'stripe'
	AND profile_id != ''"
);
echo "Direct DB count: $db_count\n\n";

// Test 3: Initialize a session and check transients
echo "TEST 3: Initialize sync session\n";
echo "---------------------------------------\n";
$processor->initialize_log( true, 'all_active', '' );

$stored_mode = get_transient( 'edd_recurring_sync_mode' );
$stored_date = get_transient( 'edd_recurring_sync_date' );

echo "Stored mode: " . ( $stored_mode ?: 'EMPTY' ) . "\n";
echo "Stored date: " . ( $stored_date ?: 'EMPTY' ) . "\n\n";

// Test 4: Process chunks and see what happens
echo "TEST 4: Process multiple chunks\n";
echo "---------------------------------------\n";

$chunks_to_test = 5;
$chunk_size = 10;

for ( $i = 0; $i < $chunks_to_test; $i++ ) {
	$offset = $i * $chunk_size;
	echo "Processing chunk $i (offset: $offset, limit: $chunk_size)\n";

	$result = $processor->process_chunk( $offset, $chunk_size, true );

	echo "  - Processed: {$result['processed']}\n";
	echo "  - Success: {$result['success']}\n";
	echo "  - Errors: {$result['errors']}\n";

	if ( $result['processed'] === 0 ) {
		echo "  ⚠ WARNING: Chunk returned 0 results at offset $offset\n";

		// Debug: Check what the actual query returns
		$sync_mode = get_transient( 'edd_recurring_sync_mode' );
		$date = get_transient( 'edd_recurring_sync_date' );

		$base_sql = "SELECT * FROM {$wpdb->prefix}edd_subscriptions
			WHERE gateway = 'stripe'
			AND profile_id != ''";

		if ( ! empty( $date ) ) {
			$sql = $wpdb->prepare(
				$base_sql . " AND date_modified >= %s ORDER BY id ASC LIMIT %d OFFSET %d",
				$date,
				$chunk_size,
				$offset
			);
		} else {
			$sql = $wpdb->prepare(
				$base_sql . " ORDER BY id ASC LIMIT %d OFFSET %d",
				$chunk_size,
				$offset
			);
		}

		echo "  - SQL Query: $sql\n";
		$debug_results = $wpdb->get_results( $sql );
		echo "  - Direct query returned: " . count( $debug_results ) . " results\n";

		if ( ! empty( $debug_results ) ) {
			echo "  - First ID in result: {$debug_results[0]->id}\n";
		}

		break;
	}

	if ( ! empty( $result['results'] ) ) {
		$first_id = $result['results'][0]['id'];
		$last_id = $result['results'][ count( $result['results'] ) - 1 ]['id'];
		echo "  - ID range: $first_id to $last_id\n";
	}

	echo "\n";
}

// Test 5: Check if there's a gap in IDs
echo "TEST 5: Check for ID gaps\n";
echo "---------------------------------------\n";

$first_30_ids = $wpdb->get_col(
	"SELECT id FROM {$wpdb->prefix}edd_subscriptions
	WHERE gateway = 'stripe'
	AND profile_id != ''
	ORDER BY id ASC
	LIMIT 30"
);

echo "First 30 IDs: " . implode( ', ', $first_30_ids ) . "\n";

$next_10_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}edd_subscriptions
		WHERE gateway = 'stripe'
		AND profile_id != ''
		ORDER BY id ASC
		LIMIT 10 OFFSET %d",
		30
	)
);

echo "IDs 31-40: " . ( ! empty( $next_10_ids ) ? implode( ', ', $next_10_ids ) : 'EMPTY' ) . "\n\n";

// Test 6: Check AJAX get_count endpoint
echo "TEST 6: Simulate AJAX get_count call\n";
echo "---------------------------------------\n";

// Simulate what the AJAX handler does
$ajax_processor = new EDD_Recurring_Sync_Processor();
$ajax_subs = $ajax_processor->get_affected_subscriptions( 'all_active', '' );
$ajax_count = count( $ajax_subs );

echo "Count returned for AJAX: $ajax_count\n";
echo "This is what JavaScript receives as totalSubs\n\n";

// Test 7: Check JavaScript loop condition
echo "TEST 7: Simulate JavaScript processing loop\n";
echo "---------------------------------------\n";

$total_subs = $ajax_count; // What JavaScript gets
$chunk_size = 10;
$offset = 0;
$chunks_processed = 0;

echo "JavaScript will process chunks while offset < $total_subs\n";
echo "With chunk_size = $chunk_size\n\n";

while ( $offset < $total_subs && $chunks_processed < 10 ) {
	$chunks_processed++;
	echo "Chunk $chunks_processed: offset = $offset\n";

	// Simulate processNextChunk
	$result = $processor->process_chunk( $offset, $chunk_size, true );

	if ( $result['processed'] === 0 ) {
		echo "  ⚠ Got 0 results - JavaScript would stop here!\n";
		break;
	}

	echo "  - Processed: {$result['processed']} subscriptions\n";
	$offset += $chunk_size;
}

echo "\nTotal chunks that would be processed: $chunks_processed\n";
echo "Expected chunks: " . ceil( $total_subs / $chunk_size ) . "\n\n";

echo "=== Test Complete ===\n";
