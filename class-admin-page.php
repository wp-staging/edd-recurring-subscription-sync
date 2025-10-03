<?php
/**
 * Admin Page Class
 *
 * Handles the admin UI for the subscription sync tool.
 *
 * @package EDD_Recurring_Subscription_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EDD_Recurring_Sync_Admin_Page
 */
class EDD_Recurring_Sync_Admin_Page {

	/**
	 * Render the admin page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'edd-recurring-sync' ) );
		}

		$processor      = new EDD_Recurring_Sync_Processor();
		$last_sync_run  = get_option( 'edd_recurring_sync_last_run', '' );
		$stats_expired  = $processor->get_statistics( 'expired_future' );
		$stats_all      = $processor->get_statistics( 'all_active' );

		?>
		<div class="wrap edd-recurring-sync-wrap">
			<h1><?php esc_html_e( 'EDD Recurring Subscription Sync', 'edd-recurring-sync' ); ?></h1>

			<div class="edd-recurring-sync-description">
				<p>
					<?php esc_html_e( 'This tool syncs EDD Recurring subscriptions with Stripe to fix status mismatches.', 'edd-recurring-sync' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Always run a dry run first to preview the changes before executing a live sync.', 'edd-recurring-sync' ); ?>
				</p>
				<?php if ( ! empty( $last_sync_run ) ) : ?>
					<p class="description">
						<strong><?php esc_html_e( 'Last Full Sync:', 'edd-recurring-sync' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync_run ) ) ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Sync Mode Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="#" class="nav-tab nav-tab-active" data-tab="expired-future"><?php esc_html_e( 'Expired with Future Dates', 'edd-recurring-sync' ); ?></a>
				<a href="#" class="nav-tab" data-tab="all-active"><?php esc_html_e( 'All Subscriptions (Full Audit)', 'edd-recurring-sync' ); ?></a>
			</h2>

			<!-- Tab Content: Expired Future -->
			<div class="tab-content" id="tab-expired-future">
				<?php if ( $stats_expired['total'] === 0 ) : ?>
					<div class="notice notice-success">
						<p>
							<strong><?php esc_html_e( 'Great news!', 'edd-recurring-sync' ); ?></strong>
							<?php esc_html_e( 'No expired subscriptions with future dates found.', 'edd-recurring-sync' ); ?>
						</p>
					</div>
				<?php else : ?>
					<div class="edd-recurring-sync-stats">
						<h2><?php esc_html_e( 'Affected Subscriptions', 'edd-recurring-sync' ); ?></h2>
						<table class="widefat striped">
							<tbody>
								<tr class="stat-row-total">
									<td class="stat-label"><strong><?php esc_html_e( 'Total Subscriptions', 'edd-recurring-sync' ); ?></strong></td>
									<td class="stat-value"><strong><?php echo esc_html( number_format_i18n( $stats_expired['total'] ) ); ?></strong></td>
								</tr>
								<tr>
									<td class="stat-label"><?php esc_html_e( '0-30 Days Future', 'edd-recurring-sync' ); ?></td>
									<td class="stat-value"><?php echo esc_html( number_format_i18n( $stats_expired['by_days_future']['0-30'] ) ); ?></td>
								</tr>
								<tr>
									<td class="stat-label"><?php esc_html_e( '31-60 Days Future', 'edd-recurring-sync' ); ?></td>
									<td class="stat-value"><?php echo esc_html( number_format_i18n( $stats_expired['by_days_future']['31-60'] ) ); ?></td>
								</tr>
								<tr>
									<td class="stat-label"><?php esc_html_e( '61-90 Days Future', 'edd-recurring-sync' ); ?></td>
									<td class="stat-value"><?php echo esc_html( number_format_i18n( $stats_expired['by_days_future']['61-90'] ) ); ?></td>
								</tr>
								<tr>
									<td class="stat-label"><?php esc_html_e( '90+ Days Future', 'edd-recurring-sync' ); ?></td>
									<td class="stat-value"><?php echo esc_html( number_format_i18n( $stats_expired['by_days_future']['90+'] ) ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="edd-recurring-sync-actions">
						<h2><?php esc_html_e( 'Actions', 'edd-recurring-sync' ); ?></h2>
						<div class="action-buttons">
							<button type="button" class="edd-sync-dry-run button button-secondary button-large" data-mode="expired_future">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e( 'Run Dry Run (Preview)', 'edd-recurring-sync' ); ?>
							</button>
							<button type="button" class="edd-sync-live button button-primary button-large" data-mode="expired_future" disabled>
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Execute Live Sync', 'edd-recurring-sync' ); ?>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'This sync mode targets subscriptions marked as "expired" but with expiration dates in the future.', 'edd-recurring-sync' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Tab Content: All Active -->
			<div class="tab-content" id="tab-all-active" style="display: none;">
				<div class="edd-recurring-sync-stats">
					<h2><?php esc_html_e( 'All Subscriptions (Full Audit)', 'edd-recurring-sync' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr class="stat-row-total">
								<td class="stat-label"><strong><?php esc_html_e( 'Total Stripe Subscriptions', 'edd-recurring-sync' ); ?></strong></td>
								<td class="stat-value"><strong><?php echo esc_html( number_format_i18n( $stats_all['total'] ) ); ?></strong></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="edd-recurring-sync-actions">
					<h2><?php esc_html_e( 'Actions', 'edd-recurring-sync' ); ?></h2>

					<div class="date-filter-options">
						<p>
							<label>
								<input type="checkbox" id="use-date-filter" />
								<?php esc_html_e( 'Only sync subscriptions modified after:', 'edd-recurring-sync' ); ?>
							</label>
							<input type="date" id="sync-date-filter" disabled data-last-sync="<?php echo esc_attr( $last_sync_run ); ?>" />
							<?php if ( ! empty( $last_sync_run ) ) : ?>
								<button type="button" id="use-last-sync-date" class="button button-small" disabled>
									<?php esc_html_e( 'Use Last Sync Date', 'edd-recurring-sync' ); ?>
								</button>
							<?php endif; ?>
						</p>
					</div>

					<div class="action-buttons">
						<button type="button" class="edd-sync-dry-run button button-secondary button-large" data-mode="all_active">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Run Dry Run (Preview)', 'edd-recurring-sync' ); ?>
						</button>
						<button type="button" class="edd-sync-live button button-primary button-large" data-mode="all_active" disabled>
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Execute Live Sync', 'edd-recurring-sync' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'This sync mode checks ALL Stripe subscriptions regardless of current status to find any mismatches. This may take a long time depending on the number of subscriptions.', 'edd-recurring-sync' ); ?>
					</p>
				</div>
			</div>

			<!-- Common Progress Section -->
			<div class="edd-recurring-sync-progress" style="display: none;">
				<h3 id="sync-progress-title"><?php esc_html_e( 'Processing...', 'edd-recurring-sync' ); ?></h3>
				<div class="progress-bar-container">
					<div class="progress-bar">
						<div class="progress-bar-fill" style="width: 0%;"></div>
					</div>
					<div class="progress-text">0%</div>
				</div>
				<div class="progress-stats">
					<span class="stat">
						<strong><?php esc_html_e( 'Processed:', 'edd-recurring-sync' ); ?></strong>
						<span id="stat-processed">0</span>
					</span>
					<span class="stat">
						<strong><?php esc_html_e( 'Updated:', 'edd-recurring-sync' ); ?></strong>
						<span id="stat-updated">0</span>
					</span>
					<span class="stat">
						<strong><?php esc_html_e( 'Skipped:', 'edd-recurring-sync' ); ?></strong>
						<span id="stat-skipped">0</span>
					</span>
					<span class="stat">
						<strong><?php esc_html_e( 'Errors:', 'edd-recurring-sync' ); ?></strong>
						<span id="stat-errors">0</span>
					</span>
				</div>
				<div id="sync-log" class="sync-log"></div>
				<button type="button" id="download-log" class="button button-secondary" style="display: none;">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Download Log File', 'edd-recurring-sync' ); ?>
				</button>
			</div>

		</div>
		<?php
	}
}
