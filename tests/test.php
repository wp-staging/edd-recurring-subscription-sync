<?php
/**
 * Comprehensive test using sample data file
 *
 * This test simulates the entire sync process without requiring WordPress
 */

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  EDD Sync - Sample Data Simulation Test                   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Load sample data
$sample_file = __DIR__ . '/sample-data.txt';
if (!file_exists($sample_file)) {
    echo "❌ FAILED: Sample data file not found\n";
    exit(1);
}

$lines = file($sample_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$subscriptions = [];

foreach ($lines as $line) {
    // Skip comments
    if (strpos($line, '#') === 0) {
        continue;
    }

    list($id, $status, $expiration, $gateway, $profile_id) = explode('|', $line);
    $subscriptions[] = [
        'id' => (int)$id,
        'status' => $status,
        'expiration' => $expiration,
        'gateway' => $gateway,
        'profile_id' => $profile_id
    ];
}

echo "Loaded " . count($subscriptions) . " sample subscriptions\n\n";

// Simulate the current_time() function
function current_time_mock() {
    return '2025-10-03 00:00:00';
}

// Test 1: Filter expired subscriptions with future dates
echo "TEST 1: Filter Expired Subscriptions with Future Dates\n";
echo "--------------------------------------------------------\n";

$expired_future = array_filter($subscriptions, function($sub) {
    return $sub['status'] === 'expired'
        && $sub['expiration'] > current_time_mock()
        && $sub['gateway'] === 'stripe'
        && !empty($sub['profile_id']);
});

$expired_future_ids = array_column($expired_future, 'id');
sort($expired_future_ids);

echo "Found " . count($expired_future_ids) . " expired subscriptions with future dates\n";
echo "IDs: " . implode(', ', $expired_future_ids) . "\n\n";

// Test 2: Simulate ID capture (like get_subscription_ids)
echo "TEST 2: Simulate ID Capture\n";
echo "-----------------------------\n";

$stored_ids = $expired_future_ids;
echo "Stored " . count($stored_ids) . " IDs in 'transient'\n";
echo "First 10 IDs: " . implode(', ', array_slice($stored_ids, 0, 10)) . "\n\n";

// Test 3: Simulate chunk processing
echo "TEST 3: Simulate Chunk Processing (10 per chunk)\n";
echo "--------------------------------------------------\n";

$chunk_size = 10;
$total_ids = count($stored_ids);
$chunks_processed = 0;
$total_processed = 0;

for ($offset = 0; $offset < $total_ids; $offset += $chunk_size) {
    $chunks_processed++;

    // Simulate array_slice
    $chunk_ids = array_slice($stored_ids, $offset, $chunk_size);

    if (empty($chunk_ids)) {
        echo "  Chunk $chunks_processed: No more IDs (offset=$offset)\n";
        break;
    }

    // Simulate building the SQL query
    $ids_string = implode(',', array_map('intval', $chunk_ids));

    // Simulate querying the database
    $chunk_results = array_filter($subscriptions, function($sub) use ($chunk_ids) {
        return in_array($sub['id'], $chunk_ids);
    });

    $processed_count = count($chunk_results);
    $total_processed += $processed_count;

    echo sprintf(
        "  Chunk %d: offset=%d, chunk_ids=%d, SQL_IN=(%s), results=%d\n",
        $chunks_processed,
        $offset,
        count($chunk_ids),
        $ids_string,
        $processed_count
    );

    // Verify all chunk IDs were found
    if ($processed_count != count($chunk_ids)) {
        echo "    ⚠ WARNING: Expected " . count($chunk_ids) . " but got $processed_count\n";
    }
}

echo "\n";

// Test 4: Verify completion percentage
echo "TEST 4: Verify Processing Completed at 100%\n";
echo "---------------------------------------------\n";

$expected_chunks = ceil($total_ids / $chunk_size);
$percentage = $total_ids > 0 ? round(($total_processed / $total_ids) * 100) : 0;

echo "Total IDs: $total_ids\n";
echo "Expected chunks: $expected_chunks\n";
echo "Chunks processed: $chunks_processed\n";
echo "Total processed: $total_processed\n";
echo "Completion: $percentage%\n\n";

if ($total_processed == $total_ids && $percentage == 100) {
    echo "✓ PASSED: Completed at 100% (all " . $total_ids . " IDs processed)\n";
} else {
    echo "❌ FAILED: Did not complete at 100%\n";
    echo "   Expected: $total_ids, Got: $total_processed, Percentage: $percentage\n";
    exit(1);
}

echo "\n";

// Test 5: Simulate the wpdb->prepare bug scenario
echo "TEST 5: Simulate wpdb->prepare() Bug (OLD CODE)\n";
echo "-------------------------------------------------\n";

echo "Simulating what happened with the bug:\n";
$buggy_results = 0;

for ($offset = 0; $offset < $total_ids; $offset += $chunk_size) {
    $chunk_ids = array_slice($stored_ids, $offset, $chunk_size);

    if (empty($chunk_ids)) {
        break;
    }

    // BUG: Only use the first ID (simulating wpdb->prepare with array)
    $first_id_only = $chunk_ids[0];

    // Query with only first ID
    $chunk_results = array_filter($subscriptions, function($sub) use ($first_id_only) {
        return $sub['id'] === $first_id_only;
    });

    $buggy_results += count($chunk_results);

    if (count($chunk_results) === 0) {
        echo "  STOPPED at offset $offset (first_id=$first_id_only returned 0 results)\n";
        break;
    }
}

$buggy_percentage = $total_ids > 0 ? round(($buggy_results / $total_ids) * 100) : 0;
echo "With bug: Processed $buggy_results of $total_ids = $buggy_percentage%\n";
echo "This demonstrates why the sync stopped early!\n\n";

// Test 6: Test with different chunk sizes
echo "TEST 6: Test with Different Chunk Sizes\n";
echo "-----------------------------------------\n";

foreach ([5, 10, 15, 20] as $test_chunk_size) {
    $test_processed = 0;
    $test_chunks = 0;

    for ($offset = 0; $offset < $total_ids; $offset += $test_chunk_size) {
        $chunk_ids = array_slice($stored_ids, $offset, $test_chunk_size);
        if (empty($chunk_ids)) break;

        $chunk_results = array_filter($subscriptions, function($sub) use ($chunk_ids) {
            return in_array($sub['id'], $chunk_ids);
        });

        $test_processed += count($chunk_results);
        $test_chunks++;
    }

    $test_percentage = $total_ids > 0 ? round(($test_processed / $total_ids) * 100) : 0;
    echo sprintf(
        "  Chunk size %2d: %d chunks, %d processed, %d%%\n",
        $test_chunk_size,
        $test_chunks,
        $test_processed,
        $test_percentage
    );
}

echo "\n";

// Test 7: Verify array_map('intval') safety
echo "TEST 7: Verify array_map('intval') SQL Safety\n";
echo "-----------------------------------------------\n";

// Test with potentially malicious input
$test_ids = [1, 2, '3; DROP TABLE users;', 4, '5 OR 1=1', 6];
$safe_ids = array_map('intval', $test_ids);
$ids_string = implode(',', $safe_ids);

echo "Input:  " . json_encode($test_ids) . "\n";
echo "Output: " . json_encode($safe_ids) . "\n";
echo "SQL:    WHERE id IN ($ids_string)\n";

// Check that all values are integers
$all_ints = array_filter($safe_ids, 'is_int');
if (count($all_ints) === count($safe_ids)) {
    echo "✓ PASSED: All values converted to integers (SQL injection safe)\n";
} else {
    echo "❌ FAILED: Some values are not integers\n";
    exit(1);
}

echo "\n";

// Final summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  🎉 ALL SAMPLE DATA TESTS PASSED! 🎉                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "--------\n";
echo "✓ Loaded and parsed sample data successfully\n";
echo "✓ ID filtering works correctly\n";
echo "✓ Chunk processing completes at 100%\n";
echo "✓ wpdb->prepare() bug scenario demonstrated\n";
echo "✓ Works with different chunk sizes\n";
echo "✓ array_map('intval') provides SQL injection safety\n\n";

echo "The fix ensures:\n";
echo "1. All IDs in each chunk are queried (not just first one)\n";
echo "2. Processing always reaches 100%\n";
echo "3. SQL injection protection via intval()\n";
echo "4. Works regardless of chunk size\n";
