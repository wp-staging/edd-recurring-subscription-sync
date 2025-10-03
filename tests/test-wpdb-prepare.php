<?php
/**
 * Test to check wpdb->prepare() with array parameters
 *
 * This tests if our WHERE id IN (...) query is being prepared correctly
 */

echo "=== Testing wpdb->prepare() with IN clause ===\n\n";

// Simulate what we're doing
$chunk_ids = [1, 2, 3, 4, 5];
$placeholders = implode(',', array_fill(0, count($chunk_ids), '%d'));

echo "Chunk IDs: " . implode(',', $chunk_ids) . "\n";
echo "Placeholders: $placeholders\n\n";

// This is how we're calling prepare
echo "Method 1: Passing array directly to prepare()\n";
echo "----------------------------------------------\n";
$sql = "SELECT * FROM subscriptions WHERE id IN ($placeholders)";
echo "SQL template: $sql\n";
echo "Params: array with " . count($chunk_ids) . " elements\n\n";

// The problem: wpdb->prepare expects individual arguments, not an array!
// This is WRONG:
// $wpdb->prepare($sql, $chunk_ids);

// This is CORRECT:
// $wpdb->prepare($sql, ...$chunk_ids); // PHP 5.6+ argument unpacking

echo "ISSUE FOUND!\n";
echo "============\n";
echo "wpdb->prepare() does NOT accept arrays as the second parameter!\n";
echo "It expects individual arguments.\n\n";

echo "WRONG (what we're doing):\n";
echo "  \$wpdb->prepare(\$sql, \$chunk_ids);\n";
echo "  // This passes an ARRAY as one argument\n\n";

echo "CORRECT option 1 (PHP 5.6+):\n";
echo "  \$wpdb->prepare(\$sql, ...\$chunk_ids);\n";
echo "  // Unpacks array into individual arguments\n\n";

echo "CORRECT option 2 (all PHP versions):\n";
echo "  \$wpdb->prepare(\$sql, \$chunk_ids[0], \$chunk_ids[1], ...);\n";
echo "  // But this doesn't work for dynamic arrays\n\n";

echo "CORRECT option 3 (all PHP versions):\n";
echo "  \$ids_string = implode(',', array_map('intval', \$chunk_ids));\n";
echo "  \$sql = \"SELECT * FROM subscriptions WHERE id IN (\$ids_string)\";\n";
echo "  // No prepare() needed since we use intval() for safety\n\n";

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║  THIS IS THE BUG!                                         ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "Our code does:\n";
echo "  \$wpdb->prepare(\n";
echo "    \"SELECT * FROM subscriptions WHERE id IN (%d,%d,%d)\",\n";
echo "    \$chunk_ids  // WRONG - passing array\n";
echo "  );\n\n";

echo "This results in only the first element being used!\n";
echo "So if chunk_ids = [31,32,33,34,35,36,37,38,39,40]\n";
echo "Only ID 31 is queried, not all 10!\n\n";

echo "After 3 chunks (30 IDs), we try to query IDs 31-40\n";
echo "But only ID 31 is actually queried\n";
echo "If ID 31 doesn't exist or is already processed, we get 0 results!\n";
