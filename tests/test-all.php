<?php
/**
 * Comprehensive test suite - runs all tests
 */

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  EDD Recurring Subscription Sync - Complete Test Suite    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$tests = [
    'test-fix.php' => 'LIMIT 500 & ID-based Processing',
    'test-offset-drift.php' => 'Offset Drift Prevention',
    'test-count-mismatch.php' => 'Count Mismatch Fix',
    'test-wpdb-fix.php' => 'wpdb->prepare() Array Bug Fix'
];

$all_passed = true;
$test_results = [];

foreach ($tests as $test_file => $test_name) {
    echo "Running: $test_name\n";
    echo str_repeat('-', 60) . "\n";

    $output = shell_exec("php " . __DIR__ . "/$test_file 2>&1");

    // Check if all tests passed
    $passed = strpos($output, '=== All Tests Passed! ===') !== false;
    $failed_count = substr_count($output, '❌ FAILED');

    if ($passed && $failed_count === 0) {
        echo "✓ PASSED - All checks successful\n";
        $test_results[$test_name] = 'PASSED';
    } else {
        echo "✗ FAILED - Some checks failed\n";
        echo "Output:\n$output\n";
        $test_results[$test_name] = 'FAILED';
        $all_passed = false;
    }

    echo "\n";
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Test Results Summary                                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

foreach ($test_results as $test_name => $result) {
    $status = $result === 'PASSED' ? '✓' : '✗';
    printf("  %s %s\n", $status, $test_name);
}

echo "\n";

if ($all_passed) {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  🎉 ALL TESTS PASSED! 🎉                                  ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

    echo "The fix is complete and verified:\n";
    echo "  ✓ Bug #1: LIMIT 500 removed\n";
    echo "  ✓ Bug #2: Offset drift prevented with ID-based processing\n";
    echo "  ✓ Bug #3: Count mismatch fixed with single data source\n";
    echo "  ✓ Bug #4: wpdb->prepare() array parameter bug fixed\n\n";

    echo "Next steps:\n";
    echo "  1. Test in WordPress environment with live data\n";
    echo "  2. Verify sync completes at 100% (not 50-60%)\n";
    echo "  3. Push changes and create pull request\n\n";

    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ⚠️  SOME TESTS FAILED  ⚠️                                 ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

    echo "Please review the output above to identify issues.\n\n";
    exit(1);
}
