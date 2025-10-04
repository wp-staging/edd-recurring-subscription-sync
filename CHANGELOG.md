# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-04

### Added
- Initial release of EDD Recurring Subscription Sync
- New option to process failing subscriptions
- Comprehensive test suite with sample data simulation
- Documentation for testing and setup

### Fixed
- Resolved offset drift causing sync to stop at 50%
- Fixed count mismatch causing sync to stop at 60%
- Resolved wpdb->prepare() array parameter bug
- Fixed issue where in live mode only 50% of data is processed

### Changed
- Simplified test suite to single comprehensive test
- Moved test files to tests/ directory
- Updated documentation with count mismatch fix