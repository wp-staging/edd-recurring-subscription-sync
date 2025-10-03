# Fix: Processing Stops at 50-60% in Live Mode

## Problem
When running the subscription sync in Live mode (expired with future dates), the processing would stop at various percentages (50%, 60%, 75%, etc.) even though there were more subscriptions to process. The percentage varied unpredictably.

## Root Causes (3 Distinct Bugs)

### Issue #1: Hard-coded LIMIT 500
In `class-sync-processor.php`, the `get_affected_subscriptions()` method had a hard-coded `LIMIT 500` on line 55:

```php
$sql = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}edd_subscriptions
    WHERE status = 'expired'
    AND expiration > %s
    AND gateway = 'stripe'
    AND profile_id != ''
    ORDER BY id ASC
    LIMIT 500",  // ← This was the problem
    $current_date
);
```

This caused a mismatch:
1. The count query (in `class-ajax-handler.php`) would return the actual total (e.g., 1000 subscriptions)
2. But `get_affected_subscriptions()` would only load 500 subscriptions maximum
3. When `process_chunk()` tried to query with offset > 500, it would get 0 results
4. JavaScript would detect 0 results and stop processing at ~50%

## Solution
Removed the `LIMIT 500` from line 55 in the `get_affected_subscriptions()` method. This method is only used for counting and initial queries - the actual pagination is properly implemented in the `process_chunk()` method which uses `LIMIT %d OFFSET %d`.

### Changed Code
**Before:**
```php
ORDER BY id ASC
LIMIT 500",
```

**After:**
```php
ORDER BY id ASC",
```

## Testing
Created `test-fix.php` to verify:
1. ✓ No hard-coded LIMIT 500 in `get_affected_subscriptions()`
2. ✓ Proper pagination with `LIMIT %d OFFSET %d` in `process_chunk()`
3. ✓ No other hard-coded limits in the codebase

## Expected Behavior After Fix
- Dry runs and live syncs will now process ALL subscriptions
- No more stopping at 50% (500 out of 1000)
- Progress will correctly go from 0% to 100%
- The chunk processor will continue until all subscriptions are processed

### Issue #2: Offset Drift (CRITICAL)
**This was the main cause of the 50% bug!**

When using OFFSET-based pagination with a dynamic WHERE clause, updating records during processing causes "offset drift":

**Example of the problem:**
1. Initial query: `WHERE status = 'expired' LIMIT 10 OFFSET 0` returns IDs [1-10]
2. Process these 10 subscriptions, update them from `expired` to `active`
3. Next query: `WHERE status = 'expired' LIMIT 10 OFFSET 10`
4. **Problem**: The original IDs 1-10 no longer match `status = 'expired'`, so they're removed from the result set
5. The query with OFFSET 10 now actually returns IDs 21-30 (skipping 11-20!)
6. This continues, processing only half the records

## Solutions

### Fix #1: Remove LIMIT 500
**Changed Code:**
```php
// BEFORE:
ORDER BY id ASC
LIMIT 500",

// AFTER:
ORDER BY id ASC",
```

### Fix #2: ID-based Processing (Prevents Offset Drift)
**New method added:**
```php
private function get_subscription_ids( $mode = 'expired_future', $date = '' ) {
    // Captures all subscription IDs upfront
    // Returns array of IDs, e.g., [1, 2, 3, ..., 1000]
}
```

**Updated initialize_log():**
```php
// Store all subscription IDs upfront to prevent offset drift
$subscription_ids = $this->get_subscription_ids( $sync_mode, $date );
set_transient( 'edd_recurring_sync_ids', $subscription_ids, HOUR_IN_SECONDS );
```

**Updated process_chunk():**
```php
// Get the stored subscription IDs for this session
$all_ids = get_transient( 'edd_recurring_sync_ids' );

// Get the chunk of IDs to process
$chunk_ids = array_slice( $all_ids, $offset, $limit );

// Fetch subscriptions by ID (not by status criteria)
$sql = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}edd_subscriptions
    WHERE id IN ($placeholders)
    ORDER BY id ASC",
    $chunk_ids
);
```

**Why this works:**
- IDs are captured at the start before any updates
- Each chunk processes specific IDs using `WHERE id IN (...)`
- Even if a subscription's status changes, it's still processed
- No offset drift because we're not using OFFSET anymore

### Issue #3: Count Mismatch (THE CRITICAL BUG) 🔴
**This was the main cause of the 60% bug!**

The count and IDs came from separate database queries at different times:

**The Problem:**
1. `initialize_sync()` at time T1 → Queries DB, stores 600 IDs
2. `get_subscription_count()` at time T2 → Queries DB, returns 1000
3. JavaScript expects 1000, but only 600 IDs stored
4. After 600 processed, array_slice returns empty
5. Progress: 600/1000 = 60% and stops

**Why different results?**
- Database state changed between T1 and T2
- Different current_time() calculations
- Other processes updating records
- Race conditions

**Fix:**
```php
// BEFORE - get_subscription_count() in class-ajax-handler.php
$count = $wpdb->get_var("SELECT COUNT(*) ..."); // Fresh query!

// AFTER
$ids = get_transient('edd_recurring_sync_ids'); // Use stored IDs
return count($ids); // Always matches what we'll process
```

## Files Modified
- `class-sync-processor.php`:
  - Line 55: Removed LIMIT 500
  - Added `get_subscription_ids()` method
  - Updated `initialize_log()` to store IDs
  - Completely rewrote `process_chunk()` to use ID-based processing

- `class-ajax-handler.php`:
  - Rewrote `get_subscription_count()` to use stored IDs
  - Removed separate database COUNT query
  - Ensures count always matches stored IDs

## Files Created
- `tests/test-fix.php` - Verification test for LIMIT 500 fix
- `tests/test-offset-drift.php` - Verification test for offset drift fix
- `tests/test-count-mismatch.php` - Verification test for count mismatch fix
- `tests/BUGFIX-ANALYSIS.md` - Complete analysis of all three bugs
- `FIX-NOTES.md` - This documentation
