# EDD Recurring Subscription Sync

A WordPress plugin that synchronizes Easy Digital Downloads (EDD) Recurring subscriptions with Stripe to fix expired status mismatches.

## Description

This plugin addresses a common issue where EDD Recurring subscriptions show as "expired" in the WordPress database but are actually still active in Stripe. This mismatch can occur due to webhook failures, timing issues, or synchronization problems between your WordPress site and Stripe.

The plugin provides a safe, admin-friendly interface to:
- Detect subscriptions with mismatched statuses
- Preview changes before applying them (dry run mode)
- Sync subscription statuses with Stripe in batches
- Log all operations for auditing and troubleshooting

## Features

- **Dry Run Mode**: Preview which subscriptions will be updated without making any changes
- **Batch Processing**: Process large numbers of subscriptions efficiently with AJAX-based chunking
- **Two Sync Modes**:
  - **Expired with Future Dates**: Sync subscriptions marked as expired but with future expiration dates
  - **All Active Subscriptions**: Sync all active subscriptions with Stripe
- **Date Filtering**: Only sync subscriptions updated after a specific date
- **Detailed Logging**: All operations are logged with timestamps for easy debugging
- **Progress Tracking**: Real-time progress updates during sync operations
- **Safe Operations**: Verify Stripe status before making any database changes

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Required Plugins**:
  - [Easy Digital Downloads](https://easydigitaldownloads.com/)
  - [EDD Recurring Payments](https://easydigitaldownloads.com/downloads/recurring-payments/)
  - [EDD Stripe Payment Gateway](https://easydigitaldownloads.com/downloads/stripe-gateway/)

## Installation

1. Download the plugin files
2. Upload the `edd-recurring-subscription-sync` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Tools > Subscription Sync** in your WordPress admin

## Usage

### Running a Sync

1. Go to **Tools > Subscription Sync** in your WordPress admin
2. Choose your sync mode:
   - **Expired with Future Dates**: Only sync subscriptions marked as expired but with future expiration dates
   - **All Active Subscriptions**: Sync all active subscriptions
3. (Optional) Enter a date to only sync subscriptions updated after that date
4. Click **Dry Run** to preview the changes without modifying the database
5. Review the dry run results
6. If satisfied, click **Start Sync** to apply the changes

### Understanding the Results

The plugin will display:
- Total subscriptions found
- Number of subscriptions that need updating
- Number of subscriptions already in sync
- Detailed log of each subscription processed
- Any errors encountered during the sync

## How It Works

1. **Detection**: Queries the database for subscriptions based on the selected mode
2. **Verification**: Checks each subscription's status in Stripe via the Stripe API
3. **Comparison**: Compares the Stripe status with the local database status
4. **Update**: Updates local subscription statuses to match Stripe (only in real sync mode)
5. **Logging**: Records all operations to log files for auditing

## Logs

All sync operations are logged to `/wp-content/plugins/edd-recurring-subscription-sync/logs/`

Log files are protected by `.htaccess` and cannot be accessed directly via the web.

## Safety Features

- Dry run mode to preview changes
- Dependency checks ensure required plugins are active
- Detailed logging of all operations
- Batch processing prevents timeouts on large databases
- Nonce verification for security
- Only administrators can access the sync tool

## Development

### File Structure

```
edd-recurring-subscription-sync/
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-admin-page.php      # Admin UI
│   ├── class-ajax-handler.php    # AJAX request handling
│   └── class-sync-processor.php  # Core sync logic
├── logs/                          # Log files (auto-created)
├── edd-recurring-subscription-sync.php
└── README.md
```

### Filters and Hooks

The plugin currently doesn't expose custom filters or hooks but can be extended if needed.

## Support

For issues, questions, or contributions, please visit [WP Staging](https://wp-staging.com).

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2025 WP Staging

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Changelog

### 1.0.0
- Initial release
- Dry run and live sync modes
- Support for expired and active subscription syncing
- Date-based filtering
- Batch processing with AJAX
- Comprehensive logging