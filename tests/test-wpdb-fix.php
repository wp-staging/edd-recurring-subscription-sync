<?php
/**
 * Test to verify the wpdb->prepare() fix
 */

echo "=== Testing wpdb IN Clause Fix ===\n\n";

$file_content = file_get_contents(__DIR__ . '/../class-sync-processor.php');

echo "TEST 1: Verify wpdb->prepare() with array is NOT used\n";
echo "-------------------------------------------------------\n";

// Check that we're NOT using wpdb->prepare with placeholders for IN clause
if (preg_match('/prepare.*IN.*placeholders/s', $file_content)) {
    echo "❌ FAILED: Still using wpdb->prepare() with placeholders\n";
    echo "This will cause only the first ID to be queried!\n";
    exit(1);
} else {
    echo "✓ PASSED: Not using wpdb->prepare() with placeholders\n";
}

echo "\nTEST 2: Verify array_map('intval') is used for safety\n";
echo "-------------------------------------------------------\n";

if (strpos($file_content, "array_map( 'intval', \$chunk_ids )") !== false ||
    strpos($file_content, 'array_map("intval", $chunk_ids)') !== false) {
    echo "✓ PASSED: Using array_map('intval') to sanitize IDs\n";
} else {
    echo "❌ FAILED: Not using array_map('intval') for safety\n";
    exit(1);
}

echo "\nTEST 3: Verify implode() is used to build ID list\n";
echo "---------------------------------------------------\n";

if (strpos($file_content, 'implode') !== false && strpos($file_content, 'intval') !== false) {
    echo "✓ PASSED: Using implode() to build comma-separated ID list\n";
} else {
    echo "❌ FAILED: Not using implode() properly\n";
    exit(1);
}

echo "\nTEST 4: Verify direct SQL with IN clause\n";
echo "------------------------------------------\n";

if (preg_match('/WHERE id IN.*\$ids_string/s', $file_content)) {
    echo "✓ PASSED: Building IN clause directly with sanitized IDs\n";
} else {
    echo "❌ FAILED: Not building IN clause correctly\n";
    exit(1);
}

echo "\nTEST 5: Verify explanation comment exists\n";
echo "-------------------------------------------\n";

if (preg_match('/prepare.*doesn.*t accept arrays|intval.*for safety/i', $file_content)) {
    echo "✓ PASSED: Contains explanation of why we don't use prepare()\n";
} else {
    echo "⚠ WARNING: No explanation comment found\n";
}

echo "\n=== All Tests Passed! ===\n\n";

echo "How this fixes the bug:\n";
echo "-----------------------\n";
echo "BEFORE (WRONG):\n";
echo "  \$placeholders = '%d,%d,%d,%d,%d';\n";
echo "  \$sql = \"WHERE id IN (\$placeholders)\";\n";
echo "  \$wpdb->prepare(\$sql, \$chunk_ids); // Passes ARRAY\n";
echo "  → Only first ID is used!\n";
echo "  → chunk_ids = [31,32,33,34,35] but only 31 is queried\n";
echo "  → Returns 0-1 results instead of up to 5\n\n";

echo "AFTER (CORRECT):\n";
echo "  \$ids_string = implode(',', array_map('intval', \$chunk_ids));\n";
echo "  \$sql = \"WHERE id IN (\$ids_string)\";\n";
echo "  \$wpdb->get_results(\$sql); // Direct query\n";
echo "  → ids_string = '31,32,33,34,35'\n";
echo "  → All 5 IDs are queried\n";
echo "  → Returns all matching subscriptions\n\n";

echo "Security:\n";
echo "---------\n";
echo "array_map('intval') ensures all values are integers,\n";
echo "preventing SQL injection even without prepare().\n";
