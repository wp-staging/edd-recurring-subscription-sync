# Test Suite

Comprehensive test suite for the EDD Recurring Subscription Sync bug fixes.

## Quick Start

Run all tests:
```bash
php tests/test-all.php
```

## Individual Tests

### 1. test-fix.php
Tests for LIMIT 500 removal and ID-based processing implementation.

**What it verifies:**
- No hard-coded LIMIT 500 in `get_affected_subscriptions()`
- `process_chunk()` uses ID-based processing
- IDs are stored at initialization

```bash
php tests/test-fix.php
```

### 2. test-offset-drift.php
Tests for offset drift prevention.

**What it verifies:**
- `get_subscription_ids()` method exists
- IDs are stored in transient during initialization
- `process_chunk()` retrieves stored IDs
- `array_slice()` is used for chunking
- `WHERE id IN (...)` query is used
- Explanation comments present

```bash
php tests/test-offset-drift.php
```

### 3. test-count-mismatch.php
Tests for count mismatch fix.

**What it verifies:**
- `get_subscription_count()` uses stored IDs from transient
- Uses `count()` on stored IDs array
- No direct database COUNT queries
- Explanation comments present

```bash
php tests/test-count-mismatch.php
```

### 4. test-wpdb-fix.php
Tests for wpdb->prepare() array parameter bug fix.

**What it verifies:**
- Not using `wpdb->prepare()` with placeholders for IN clause
- Using `array_map('intval')` for SQL injection safety
- Using `implode()` to build comma-separated ID list
- Building IN clause directly with sanitized IDs
- Explanation comments present

```bash
php tests/test-wpdb-fix.php
```

### 5. test-with-sample-data.php
Comprehensive simulation test using sample subscription data.

**What it tests:**
- Loads 50 sample subscriptions from `sample-data.txt`
- Filters expired subscriptions with future dates
- Simulates ID capture and storage
- Simulates chunk processing (10 per chunk)
- Verifies 100% completion
- Demonstrates the wpdb bug scenario
- Tests with different chunk sizes (5, 10, 15, 20)
- Verifies SQL injection safety with `array_map('intval')`

```bash
php tests/test-with-sample-data.php
```

## Sample Data

`sample-data.txt` contains 50 test subscriptions in the format:
```
ID|status|expiration|gateway|profile_id
```

Example:
```
1|expired|2025-12-31 23:59:59|stripe|sub_1234567890ABC
2|active|2025-11-30 23:59:59|stripe|sub_1234567890ABD
```

This data is used by `test-with-sample-data.php` to simulate real-world scenarios.

## Additional Test Files

### test-wpdb-prepare.php
Explains the wpdb->prepare() bug with detailed examples.

**Purpose:** Educational - demonstrates why the bug occurred

```bash
php tests/test-wpdb-prepare.php
```

## Test Results

All tests should pass with this output:

```
╔════════════════════════════════════════════════════════════╗
║  🎉 ALL TESTS PASSED! 🎉                                  ║
╚════════════════════════════════════════════════════════════╝

The fix is complete and verified:
  ✓ Bug #1: LIMIT 500 removed
  ✓ Bug #2: Offset drift prevented with ID-based processing
  ✓ Bug #3: Count mismatch fixed with single data source
  ✓ Bug #4: wpdb->prepare() array parameter bug fixed
  ✓ Bonus: Sample data simulation demonstrates 100% completion
```

## Understanding the Bugs

See `BUGFIX-ANALYSIS.md` in the tests directory for a complete technical analysis of all four bugs.

## Files

- `test-all.php` - Master test suite runner
- `test-fix.php` - LIMIT 500 & ID-based processing tests
- `test-offset-drift.php` - Offset drift prevention tests
- `test-count-mismatch.php` - Count mismatch fix tests
- `test-wpdb-fix.php` - wpdb array parameter bug tests
- `test-wpdb-prepare.php` - Educational explanation of wpdb bug
- `test-with-sample-data.php` - Comprehensive simulation test
- `sample-data.txt` - 50 sample subscriptions for testing
- `BUGFIX-ANALYSIS.md` - Complete technical analysis
- `README.md` - This file

## Requirements

- PHP 5.6+ (for basic tests)
- No WordPress installation required
- Tests run standalone

## CI/CD Integration

Add to your CI/CD pipeline:

```yaml
- name: Run Tests
  run: php tests/test-all.php
```

Exit code 0 = all tests passed
Exit code 1 = some tests failed
