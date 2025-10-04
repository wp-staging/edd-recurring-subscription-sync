# Testing the Bug Fixes

## Quick Test
Run the comprehensive test:
```bash
php tests/test.php
```

This test verifies all four bug fixes are working correctly.

## Manual Testing in WordPress

### Before Testing
1. Ensure you have subscriptions with `status='expired'` and future expiration dates
2. Ideally have 100+ subscriptions to properly test the pagination

### Test Procedure

#### Test 1: Dry Run
1. Go to **Tools > Subscription Sync**
2. Select "Expired with Future Dates"
3. Click **Dry Run**
4. **Expected Result**: Process should go from 0% to 100%
5. Check that all subscriptions are listed in the log

#### Test 2: Live Sync
1. Click **Start Sync** (after successful dry run)
2. **Expected Result**:
   - Progress goes from 0% to 100%
   - All subscriptions are updated (not just 50%)
   - Check the database to verify all expired subscriptions with future dates were updated

#### Test 3: Verify No Offset Drift
1. Run a live sync on 100 subscriptions
2. Note the "Updated" count in the stats
3. **Expected Result**:
   - If 100 subscriptions needed updating, all 100 should be updated
   - Not 50, not 75, but 100% of them

### What to Look For

**Signs the bug is fixed:**
- ✓ Progress bar reaches 100%
- ✓ "Processed" count matches the total count
- ✓ All subscriptions appear in the sync log
- ✓ Database shows all matching subscriptions were updated

**Signs the bug still exists:**
- ✗ Progress stops at 50% and shows "Complete"
- ✗ Only half the subscriptions are in the log
- ✗ Database shows only ~50% of subscriptions were updated

## Technical Verification

### Check Transient Storage
In WordPress admin, you can verify IDs are being stored:

```php
// Add this temporarily to the admin page
$sync_ids = get_transient( 'edd_recurring_sync_ids' );
echo '<pre>';
echo 'Stored IDs: ' . count($sync_ids) . "\n";
echo 'First 10: ' . implode(', ', array_slice($sync_ids, 0, 10));
echo '</pre>';
```

### Check Database Queries
Enable WordPress query logging and verify that:
1. Initial query uses `SELECT id FROM` (getting IDs only)
2. Chunk queries use `WHERE id IN (...)` (not `WHERE status='expired' ... OFFSET`)

## Troubleshooting

### If tests still fail at 50%

1. **Check transient storage:**
   - Are IDs being stored? Check `edd_recurring_sync_ids` transient
   - Are IDs persisting between chunks?

2. **Check object cache:**
   - If using Redis/Memcached, flush the cache
   - Transients might not be persisting

3. **Check the logs:**
   - Look in `/logs/` directory
   - See which subscription IDs are being processed
   - Check for gaps in the ID sequence

4. **Check for SQL errors:**
   - Enable WordPress debug logging
   - Look for `WHERE id IN ()` with empty placeholders
