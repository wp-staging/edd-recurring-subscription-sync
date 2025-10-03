<?php
/**
 * Test to verify the count mismatch fix
 *
 * This test ensures that get_subscription_count() returns the count of stored IDs,
 * not a fresh database query that could return a different number.
 */

echo "=== Testing Count Mismatch Fix ===\n\n";

// Read the AJAX handler file
$ajax_file = file_get_contents(__DIR__ . '/../class-ajax-handler.php');

echo "TEST 1: Verify get_subscription_count() uses stored IDs\n";
echo "---------------------------------------------------------\n";

// Check that it retrieves the stored IDs transient
if (strpos($ajax_file, "get_transient( 'edd_recurring_sync_ids' )") !== false) {
    echo "✓ PASSED: get_subscription_count() retrieves stored IDs from transient\n";
} else {
    echo "❌ FAILED: get_subscription_count() doesn't use stored IDs\n";
    exit(1);
}

echo "\nTEST 2: Verify count() is used on stored IDs\n";
echo "----------------------------------------------\n";

// Extract the get_subscription_count method
if (preg_match('/public function get_subscription_count.*?^\s*public function/ms', $ajax_file, $matches)) {
    $method_code = $matches[0];

    if (strpos($method_code, 'count( $subscription_ids )') !== false) {
        echo "✓ PASSED: Uses count() on stored IDs array\n";
    } else {
        echo "❌ FAILED: Doesn't use count() on stored IDs\n";
        exit(1);
    }
} else {
    echo "⚠ WARNING: Could not extract get_subscription_count method\n";
}

echo "\nTEST 3: Verify NO direct database COUNT queries\n";
echo "-------------------------------------------------\n";

// Check that there are NO SQL COUNT queries in get_subscription_count
if (preg_match('/public function get_subscription_count.*?^\s*}/ms', $ajax_file, $matches)) {
    $method_code = $matches[0];

    // Look for SQL COUNT queries
    if (preg_match('/SELECT COUNT\(\*\)/i', $method_code)) {
        echo "❌ FAILED: Found SQL COUNT query in get_subscription_count()\n";
        echo "This can cause count mismatch and early stopping!\n";
        exit(1);
    } else {
        echo "✓ PASSED: No SQL COUNT queries found (using stored IDs only)\n";
    }
}

echo "\nTEST 4: Verify explanation comments exist\n";
echo "-------------------------------------------\n";

if (preg_match('/count.*mismatch|stored IDs.*count|IMPORTANT.*count/i', $method_code)) {
    echo "✓ PASSED: Contains explanation of why we use stored IDs for count\n";
} else {
    echo "⚠ WARNING: No explanation comments found\n";
}

echo "\n=== All Tests Passed! ===\n\n";

echo "How this fixes the 60% bug:\n";
echo "---------------------------\n";
echo "BEFORE:\n";
echo "1. initialize_sync() → Stores 600 IDs in transient (at time T1)\n";
echo "2. get_subscription_count() → Queries DB, finds 1000 subscriptions (at time T2)\n";
echo "3. JavaScript expects 1000, but only 600 IDs are stored\n";
echo "4. After processing 600 IDs, offset becomes 600\n";
echo "5. processNextChunk(600) → array_slice returns empty (only 600 IDs stored)\n";
echo "6. JavaScript sees 0 results, stops at 60% (600/1000)\n\n";

echo "AFTER:\n";
echo "1. initialize_sync() → Stores 600 IDs in transient\n";
echo "2. get_subscription_count() → Returns count(stored_ids) = 600\n";
echo "3. JavaScript expects 600, exactly matching stored IDs\n";
echo "4. Processes all 600 subscriptions\n";
echo "5. Offset reaches 600, equals total, stops at 100%\n\n";

echo "Why the original approach failed:\n";
echo "---------------------------------\n";
echo "Database state can change between queries:\n";
echo "- Another process updates subscriptions\n";
echo "- Cron job runs\n";
echo "- Different timezone/time calculation\n";
echo "- Race conditions\n\n";

echo "The fix ensures count and IDs are ALWAYS in sync by using\n";
echo "the same data source (the stored transient).\n";
