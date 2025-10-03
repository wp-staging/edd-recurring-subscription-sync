<?php
/**
 * Test script for EDD Recurring Subscription Sync plugin.
 *
 * This script tests the plugin functionality without making actual changes.
 * Run via WP-CLI: wp eval-file test-sync.php
 * Or access via browser with admin authentication.
 *
 * @package EDD_Recurring_Subscription_Sync
 */

// Security check for browser access.
if ( ! defined( 'WP_CLI' ) ) {
	// Require WordPress.
	require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';

	// Require admin access.
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( 'Unauthorized access.' );
	}
}

/**
 * Test Suite for Subscription Sync Plugin
 */
class EDD_Recurring_Sync_Test {

	private $results = array();
	private $processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		echo "\n=== EDD Recurring Subscription Sync - Test Suite ===\n\n";
		$this->run_all_tests();
		$this->display_results();
	}

	/**
	 * Run all tests.
	 */
	private function run_all_tests() {
		$this->test_plugin_loaded();
		$this->test_dependencies();
		$this->test_classes_exist();
		$this->test_processor_initialization();
		$this->test_get_affected_subscriptions();
		$this->test_statistics();
		$this->test_stripe_status_mapping();
		$this->test_subscription_query();
		$this->test_log_file_creation();
		$this->test_ajax_handlers();
		$this->test_sample_subscription_processing();
	}

	/**
	 * Test: Plugin loaded.
	 */
	private function test_plugin_loaded() {
		$this->start_test( 'Plugin Loaded' );

		if ( defined( 'EDD_RECURRING_SYNC_VERSION' ) ) {
			$this->pass( 'Plugin constants defined' );
		} else {
			$this->fail( 'Plugin constants not defined' );
		}
	}

	/**
	 * Test: Dependencies check.
	 */
	private function test_dependencies() {
		$this->start_test( 'Dependencies Check' );

		$missing = array();

		if ( ! defined( 'EDD_VERSION' ) ) {
			$missing[] = 'Easy Digital Downloads';
		}

		if ( ! class_exists( 'EDD_Recurring' ) ) {
			$missing[] = 'EDD Recurring Payments';
		}

		if ( ! defined( 'EDDS_PLUGIN_DIR' ) ) {
			$missing[] = 'EDD Stripe Payment Gateway';
		}

		if ( empty( $missing ) ) {
			$this->pass( 'All dependencies present' );
		} else {
			$this->fail( 'Missing dependencies: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Test: Classes exist.
	 */
	private function test_classes_exist() {
		$this->start_test( 'Class Existence' );

		$classes = array(
			'EDD_Recurring_Subscription_Sync',
			'EDD_Recurring_Sync_Processor',
			'EDD_Recurring_Sync_Admin_Page',
			'EDD_Recurring_Sync_Ajax_Handler',
		);

		$missing = array();
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class ) ) {
				$missing[] = $class;
			}
		}

		if ( empty( $missing ) ) {
			$this->pass( 'All classes loaded' );
		} else {
			$this->fail( 'Missing classes: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Test: Processor initialization.
	 */
	private function test_processor_initialization() {
		$this->start_test( 'Processor Initialization' );

		try {
			$this->processor = new EDD_Recurring_Sync_Processor();

			if ( $this->processor instanceof EDD_Recurring_Sync_Processor ) {
				$this->pass( 'Processor initialized successfully' );
			} else {
				$this->fail( 'Processor initialization returned wrong type' );
			}
		} catch ( Exception $e ) {
			$this->fail( 'Processor initialization failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test: Get affected subscriptions.
	 */
	private function test_get_affected_subscriptions() {
		$this->start_test( 'Query Affected Subscriptions' );

		try {
			$subscriptions = $this->processor->get_affected_subscriptions();

			if ( is_array( $subscriptions ) ) {
				$count = count( $subscriptions );
				$this->pass( sprintf( 'Found %d affected subscriptions', $count ) );

				if ( $count > 0 ) {
					$sample = $subscriptions[0];
					echo "   Sample subscription ID: {$sample->id}\n";
					echo "   Status: {$sample->status}\n";
					echo "   Expiration: {$sample->expiration}\n";
					echo "   Profile ID: {$sample->profile_id}\n";
				}
			} else {
				$this->fail( 'get_affected_subscriptions() did not return an array' );
			}
		} catch ( Exception $e ) {
			$this->fail( 'Query failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test: Statistics generation.
	 */
	private function test_statistics() {
		$this->start_test( 'Statistics Generation' );

		try {
			$stats = $this->processor->get_statistics();

			if ( isset( $stats['total'] ) && isset( $stats['by_days_future'] ) ) {
				$this->pass( sprintf( 'Statistics generated: %d total', $stats['total'] ) );
				echo "   Breakdown:\n";
				foreach ( $stats['by_days_future'] as $range => $count ) {
					echo "   - {$range} days: {$count}\n";
				}
			} else {
				$this->fail( 'Statistics structure incorrect' );
			}
		} catch ( Exception $e ) {
			$this->fail( 'Statistics generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test: Stripe status mapping.
	 */
	private function test_stripe_status_mapping() {
		$this->start_test( 'Stripe Status Mapping' );

		// Use reflection to test private method.
		$reflection = new ReflectionClass( 'EDD_Recurring_Sync_Processor' );
		$method = $reflection->getMethod( 'map_stripe_status' );
		$method->setAccessible( true );

		$test_cases = array(
			'active'             => 'active',
			'trialing'           => 'trialling',
			'canceled'           => 'cancelled',
			'past_due'           => 'failing',
			'unpaid'             => 'expired',
			'incomplete'         => 'pending',
			'incomplete_expired' => 'expired',
		);

		$passed = true;
		foreach ( $test_cases as $stripe_status => $expected_edd_status ) {
			$result = $method->invoke( $this->processor, $stripe_status );
			if ( $result !== $expected_edd_status ) {
				$this->fail( "Mapping failed: {$stripe_status} -> {$result} (expected {$expected_edd_status})" );
				$passed = false;
				break;
			}
		}

		if ( $passed ) {
			$this->pass( 'All status mappings correct' );
		}
	}

	/**
	 * Test: Subscription query parameters.
	 */
	private function test_subscription_query() {
		$this->start_test( 'Subscription Query Parameters' );

		global $wpdb;

		$current_date = current_time( 'mysql' );

		// Test query structure.
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions
			WHERE status = 'expired'
			AND expiration > %s
			AND gateway = 'stripe'
			AND profile_id != ''",
			$current_date
		);

		try {
			$count = $wpdb->get_var( $sql );

			if ( $count !== null ) {
				$this->pass( sprintf( 'Query executed successfully: %d subscriptions match criteria', $count ) );
			} else {
				$this->fail( 'Query returned null' );
			}
		} catch ( Exception $e ) {
			$this->fail( 'Query execution failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test: Log file creation.
	 */
	private function test_log_file_creation() {
		$this->start_test( 'Log File Creation' );

		try {
			$this->processor->initialize_log( true );
			$log_filename = $this->processor->get_log_filename();
			$log_path = EDD_RECURRING_SYNC_LOGS_DIR . $log_filename;

			if ( file_exists( $log_path ) ) {
				$this->pass( "Log file created: {$log_filename}" );

				// Check if log has header.
				$contents = file_get_contents( $log_path );
				if ( strpos( $contents, 'DRY RUN' ) !== false ) {
					echo "   Log header verified\n";
				}
			} else {
				$this->fail( 'Log file not created' );
			}
		} catch ( Exception $e ) {
			$this->fail( 'Log creation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test: AJAX handlers registered.
	 */
	private function test_ajax_handlers() {
		$this->start_test( 'AJAX Handlers' );

		$handlers = array(
			'edd_sync_get_count',
			'edd_sync_process_chunk',
			'edd_sync_download_log',
			'edd_sync_initialize',
		);

		$missing = array();
		foreach ( $handlers as $handler ) {
			if ( ! has_action( "wp_ajax_{$handler}" ) ) {
				$missing[] = $handler;
			}
		}

		if ( empty( $missing ) ) {
			$this->pass( 'All AJAX handlers registered' );
		} else {
			$this->fail( 'Missing AJAX handlers: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Test: Sample subscription processing (dry run).
	 */
	private function test_sample_subscription_processing() {
		$this->start_test( 'Sample Subscription Processing (Dry Run)' );

		try {
			$subscriptions = $this->processor->get_affected_subscriptions();

			if ( empty( $subscriptions ) ) {
				$this->pass( 'No subscriptions to test (none affected)' );
				return;
			}

			// Test processing just 1 subscription.
			$results = $this->processor->process_chunk( 0, 1, true );

			if ( isset( $results['processed'] ) && $results['processed'] > 0 ) {
				$result = $results['results'][0];

				$this->pass( 'Sample processing completed' );
				echo "   Subscription ID: {$result['id']}\n";
				echo "   Profile ID: {$result['profile_id']}\n";
				echo "   Current Status: {$result['current_status']}\n";
				echo "   Stripe Status: " . ( $result['stripe_status'] ?? 'N/A' ) . "\n";
				echo "   Action: {$result['action']}\n";
				echo "   Message: {$result['message']}\n";
				echo "   Success: " . ( $result['success'] ? 'Yes' : 'No' ) . "\n";
			} elseif ( isset( $results['processed'] ) && $results['processed'] === 0 ) {
				$this->pass( 'No subscriptions processed (limit reached or none available)' );
			} else {
				$this->fail( 'Processing returned unexpected structure' );
			}
		} catch ( Exception $e ) {
			$this->fail( 'Processing failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Start a test.
	 */
	private function start_test( $name ) {
		echo "\n[TEST] {$name}\n";
	}

	/**
	 * Mark test as passed.
	 */
	private function pass( $message ) {
		echo "  ✓ PASS: {$message}\n";
		$this->results[] = array( 'status' => 'pass', 'message' => $message );
	}

	/**
	 * Mark test as failed.
	 */
	private function fail( $message ) {
		echo "  ✗ FAIL: {$message}\n";
		$this->results[] = array( 'status' => 'fail', 'message' => $message );
	}

	/**
	 * Display final results.
	 */
	private function display_results() {
		echo "\n=== Test Results ===\n";

		$passed = 0;
		$failed = 0;

		foreach ( $this->results as $result ) {
			if ( $result['status'] === 'pass' ) {
				$passed++;
			} else {
				$failed++;
			}
		}

		$total = $passed + $failed;

		echo "\nTotal Tests: {$total}\n";
		echo "Passed: {$passed}\n";
		echo "Failed: {$failed}\n";

		if ( $failed === 0 ) {
			echo "\n✓ All tests passed! Plugin is ready to use.\n";
		} else {
			echo "\n✗ Some tests failed. Please review the errors above.\n";
		}

		echo "\n=== End of Test Suite ===\n\n";
	}
}

// Run the test suite.
new EDD_Recurring_Sync_Test();
