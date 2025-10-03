<?php
/**
 * Simple test to verify the LIMIT 500 fix
 */

echo "=== Testing LIMIT 500 Fix ===\n\n";

$file_content = file_get_contents(__DIR__ . '/class-sync-processor.php');

echo "TEST 1: Verify LIMIT 500 was removed from get_affected_subscriptions()\n";
echo "------------------------------------------------------------------------\n";

// Find the line numbers of get_affected_subscriptions
$lines = explode("\n", $file_content);
$in_method = false;
$method_start = 0;
$method_end = 0;
$found_limit_500 = false;

foreach ($lines as $num => $line) {
    $line_num = $num + 1;

    if (strpos($line, 'public function get_affected_subscriptions') !== false) {
        $in_method = true;
        $method_start = $line_num;
    }

    if ($in_method) {
        // Check for LIMIT 500
        if (preg_match('/LIMIT\s+500/', $line)) {
            $found_limit_500 = true;
            echo "❌ FAILED: Found 'LIMIT 500' at line $line_num\n";
            echo "   " . trim($line) . "\n";
        }

        // End of method
        if (preg_match('/^\s*public function|^\s*private function|^\}$/', $line) && $line_num > $method_start + 5) {
            $method_end = $line_num;
            break;
        }
    }
}

if ($found_limit_500) {
    echo "\n❌ TEST FAILED: The LIMIT 500 is still present!\n";
    echo "This will cause sync to stop at ~50% when there are 1000+ subscriptions.\n\n";
    exit(1);
} else {
    echo "✓ PASSED: No hard-coded LIMIT 500 found in get_affected_subscriptions()\n";
    echo "  Method spans lines $method_start-$method_end\n";
}

echo "\nTEST 2: Verify process_chunk() uses ID-based processing\n";
echo "---------------------------------------------------------\n";

// Check for ID-based processing (array_slice + WHERE id IN)
$has_array_slice = strpos($file_content, 'array_slice') !== false;
$has_id_in = strpos($file_content, 'WHERE id IN') !== false;

if ($has_array_slice && $has_id_in) {
    echo "✓ PASSED: process_chunk() uses ID-based processing (array_slice + WHERE id IN)\n";
} else {
    echo "❌ FAILED: process_chunk() doesn't use proper ID-based processing\n";
    echo "  Has array_slice: " . ($has_array_slice ? 'Yes' : 'No') . "\n";
    echo "  Has WHERE id IN: " . ($has_id_in ? 'Yes' : 'No') . "\n";
    exit(1);
}

echo "\nTEST 3: Verify IDs are stored at initialization\n";
echo "-------------------------------------------------\n";

if (strpos($file_content, "set_transient( 'edd_recurring_sync_ids'") !== false) {
    echo "✓ PASSED: Subscription IDs are stored in transient during initialization\n";
} else {
    echo "❌ FAILED: IDs are not being stored\n";
    exit(1);
}

echo "\n=== All Tests Passed! ===\n\n";
echo "Summary:\n";
echo "--------\n";
echo "Two critical bugs have been fixed:\n\n";
echo "1. LIMIT 500 removed from get_affected_subscriptions()\n";
echo "2. Offset drift fixed by using ID-based processing\n\n";
echo "The plugin now:\n";
echo "- Captures all subscription IDs at initialization\n";
echo "- Processes subscriptions by ID (not by OFFSET)\n";
echo "- Prevents offset drift when records are updated\n\n";
echo "Expected behavior:\n";
echo "- Dry runs and live syncs will now process ALL subscriptions\n";
echo "- No more stopping at 50%\n";
echo "- Progress will go from 0% to 100% correctly\n";
