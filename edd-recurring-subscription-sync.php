<?php
/**
 * Plugin Name: EDD Recurring Subscription Sync
 * Plugin URI: https://wp-staging.com
 * Description: Syncs EDD Recurring subscriptions with Stripe to fix expired status mismatches
 * Version: 1.0.0
 * Author: WP Staging
 * Author URI: https://wp-staging.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'EDD_RECURRING_SYNC_VERSION', '1.0.0' );
define( 'EDD_RECURRING_SYNC_FILE', __FILE__ );
define( 'EDD_RECURRING_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'EDD_RECURRING_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'EDD_RECURRING_SYNC_LOGS_DIR', EDD_RECURRING_SYNC_PATH . 'logs/' );

/**
 * Main plugin class.
 */
class EDD_Recurring_Subscription_Sync {

	/**
	 * Single instance of the class.
	 *
	 * @var EDD_Recurring_Subscription_Sync
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return EDD_Recurring_Subscription_Sync
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once EDD_RECURRING_SYNC_PATH . 'includes/class-sync-processor.php';
		require_once EDD_RECURRING_SYNC_PATH . 'includes/class-admin-page.php';
		require_once EDD_RECURRING_SYNC_PATH . 'includes/class-ajax-handler.php';
	}

	/**
	 * Setup hooks.
	 */
	private function hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'check_dependencies' ) );

		// Initialize AJAX handler.
		EDD_Recurring_Sync_Ajax_Handler::instance();
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Subscription Sync', 'edd-recurring-sync' ),
			__( 'Subscription Sync', 'edd-recurring-sync' ),
			'manage_options',
			'edd-subscription-sync',
			array( 'EDD_Recurring_Sync_Admin_Page', 'render' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'tools_page_edd-subscription-sync' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'edd-recurring-sync-admin',
			EDD_RECURRING_SYNC_URL . 'assets/css/admin.css',
			array(),
			EDD_RECURRING_SYNC_VERSION
		);

		wp_enqueue_script(
			'edd-recurring-sync-admin',
			EDD_RECURRING_SYNC_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			EDD_RECURRING_SYNC_VERSION,
			true
		);

		wp_localize_script(
			'edd-recurring-sync-admin',
			'eddRecurringSync',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'edd_recurring_sync_nonce' ),
				'chunk_size'     => 10,
				'strings'        => array(
					'processing'       => __( 'Processing...', 'edd-recurring-sync' ),
					'complete'         => __( 'Complete!', 'edd-recurring-sync' ),
					'error'            => __( 'An error occurred', 'edd-recurring-sync' ),
					'confirm_sync'     => __( 'Are you sure you want to sync subscriptions? This will update the database.', 'edd-recurring-sync' ),
					'dry_run_first'    => __( 'Please run a dry run first to preview changes.', 'edd-recurring-sync' ),
				),
			)
		);
	}

	/**
	 * Check plugin dependencies.
	 */
	public function check_dependencies() {
		$screen = get_current_screen();
		if ( ! $screen || 'tools_page_edd-subscription-sync' !== $screen->id ) {
			return;
		}

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

		if ( ! empty( $missing ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'EDD Recurring Subscription Sync:', 'edd-recurring-sync' ); ?></strong>
					<?php
					printf(
						/* translators: %s: comma-separated list of missing plugins */
						esc_html__( 'The following required plugins are missing: %s', 'edd-recurring-sync' ),
						esc_html( implode( ', ', $missing ) )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		// Create logs directory.
		if ( ! file_exists( EDD_RECURRING_SYNC_LOGS_DIR ) ) {
			wp_mkdir_p( EDD_RECURRING_SYNC_LOGS_DIR );
		}

		// Create .htaccess to protect logs.
		$htaccess_file = EDD_RECURRING_SYNC_LOGS_DIR . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, 'Deny from all' );
		}

		// Create index.php to prevent directory listing.
		$index_file = EDD_RECURRING_SYNC_LOGS_DIR . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		// Clean up transients.
		delete_transient( 'edd_recurring_sync_stats' );
	}
}

// Activation/Deactivation hooks.
register_activation_hook( __FILE__, array( 'EDD_Recurring_Subscription_Sync', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EDD_Recurring_Subscription_Sync', 'deactivate' ) );

// Initialize plugin.
function edd_recurring_subscription_sync() {
	return EDD_Recurring_Subscription_Sync::instance();
}

// Start the plugin.
add_action( 'plugins_loaded', 'edd_recurring_subscription_sync', 20 );
