/**
 * EDD Recurring Subscription Sync - Admin JavaScript
 */

(function($) {
	'use strict';

	const EDDRecurringSync = {
		totalSubs: 0,
		processedSubs: 0,
		updatedSubs: 0,
		skippedSubs: 0,
		errorSubs: 0,
		isDryRun: true,
		isProcessing: false,
		allResults: [],
		currentMode: 'expired_future',
		currentDate: '',

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Tab switching
			$('.nav-tab').on('click', this.switchTab.bind(this));

			// Date filter
			$('#use-date-filter').on('change', this.toggleDateFilter.bind(this));
			$('#use-last-sync-date').on('click', this.useLastSyncDate.bind(this));

			// Sync buttons
			$('.edd-sync-dry-run').on('click', this.startDryRun.bind(this));
			$('.edd-sync-live').on('click', this.startLiveSync.bind(this));
			$('#download-log').on('click', this.downloadLog.bind(this));
		},

		/**
		 * Switch tabs
		 */
		switchTab: function(e) {
			e.preventDefault();
			
			const $tab = $(e.currentTarget);
			const tabId = $tab.data('tab');

			// Update tab active state
			$('.nav-tab').removeClass('nav-tab-active');
			$tab.addClass('nav-tab-active');

			// Show/hide tab content
			$('.tab-content').hide();
			$('#tab-' + tabId).show();

			// Update current mode
			this.currentMode = tabId.replace('-', '_');
		},

		/**
		 * Toggle date filter
		 */
		toggleDateFilter: function(e) {
			const $checkbox = $(e.currentTarget);
			const isChecked = $checkbox.is(':checked');

			$('#sync-date-filter').prop('disabled', !isChecked);
			$('#use-last-sync-date').prop('disabled', !isChecked);
		},

		/**
		 * Use last sync date
		 */
		useLastSyncDate: function(e) {
			e.preventDefault();
			const lastSyncDate = $('#sync-date-filter').data('last-sync');
			if (lastSyncDate) {
				const datePart = lastSyncDate.split(' ')[0]; // Get Y-m-d part
				$('#sync-date-filter').val(datePart);
			}
		},

		/**
		 * Get current date filter
		 */
		getDateFilter: function() {
			if ($('#use-date-filter').is(':checked')) {
				return $('#sync-date-filter').val() || '';
			}
			return '';
		},

		/**
		 * Start dry run
		 */
		startDryRun: function(e) {
			e.preventDefault();

			if (this.isProcessing) {
				return;
			}

			const $button = $(e.currentTarget);
			this.currentMode = $button.data('mode');
			this.currentDate = this.getDateFilter();

			this.isDryRun = true;
			this.resetCounters();
			this.initializeSync();
		},

		/**
		 * Start live sync
		 */
		startLiveSync: function(e) {
			e.preventDefault();

			if (this.isProcessing) {
				return;
			}

			if (!confirm(eddRecurringSync.strings.confirm_sync)) {
				return;
			}

			const $button = $(e.currentTarget);
			this.currentMode = $button.data('mode');
			this.currentDate = this.getDateFilter();

			this.isDryRun = false;
			this.resetCounters();
			this.initializeSync();
		},

		/**
		 * Reset counters
		 */
		resetCounters: function() {
			this.totalSubs = 0;
			this.processedSubs = 0;
			this.updatedSubs = 0;
			this.skippedSubs = 0;
			this.errorSubs = 0;
			this.allResults = [];
			this.updateProgressDisplay();
		},

		/**
		 * Initialize sync
		 */
		initializeSync: function() {
			this.isProcessing = true;
			this.showProgress();
			this.disableButtons();

			$.ajax({
				url: eddRecurringSync.ajax_url,
				type: 'POST',
				data: {
					action: 'edd_sync_initialize',
					nonce: eddRecurringSync.nonce,
					dry_run: this.isDryRun,
					sync_mode: this.currentMode,
					date: this.currentDate
				},
				success: (response) => {
					if (response.success) {
						this.getSubscriptionCount();
					} else {
						this.handleError(response.data.message || eddRecurringSync.strings.error);
					}
				},
				error: () => {
					this.handleError(eddRecurringSync.strings.error);
				}
			});
		},

		/**
		 * Get subscription count
		 */
		getSubscriptionCount: function() {
			$.ajax({
				url: eddRecurringSync.ajax_url,
				type: 'POST',
				data: {
					action: 'edd_sync_get_count',
					nonce: eddRecurringSync.nonce,
					sync_mode: this.currentMode,
					date: this.currentDate
				},
				success: (response) => {
					if (response.success) {
						this.totalSubs = response.data.total;
						this.processNextChunk(0);
					} else {
						this.handleError(response.data.message || eddRecurringSync.strings.error);
					}
				},
				error: () => {
					this.handleError(eddRecurringSync.strings.error);
				}
			});
		},

		/**
		 * Process next chunk
		 */
		processNextChunk: function(offset) {
			console.log('processNextChunk called:', {
				offset: offset,
				totalSubs: this.totalSubs,
				willStop: offset >= this.totalSubs
			});

			if (offset >= this.totalSubs) {
				console.log('Stopping: offset >= totalSubs');
				this.handleComplete();
				return;
			}

			$.ajax({
				url: eddRecurringSync.ajax_url,
				type: 'POST',
				data: {
					action: 'edd_sync_process_chunk',
					nonce: eddRecurringSync.nonce,
					offset: offset,
					dry_run: this.isDryRun
				},
				success: (response) => {
					console.log('Chunk response:', response);

					if (response.success) {
						this.processedSubs += response.data.processed;
						this.updatedSubs += response.data.success;
						this.errorSubs += response.data.errors;
						this.skippedSubs += (response.data.processed - response.data.success - response.data.errors);

						this.allResults = this.allResults.concat(response.data.results);
						this.updateProgressDisplay();
						this.displayResults(response.data.results);

						// Check if we got 0 results
						if (response.data.processed === 0) {
							console.warn('Got 0 results from chunk. Debug info:', response.data.debug);
							this.handleComplete();
							return;
						}

						this.processNextChunk(parseInt(offset) + parseInt(eddRecurringSync.chunk_size));
					} else {
						this.handleError(response.data.message || eddRecurringSync.strings.error);
					}
				},
				error: () => {
					this.handleError(eddRecurringSync.strings.error);
				}
			});
		},

		/**
		 * Update progress display
		 */
		updateProgressDisplay: function() {
			const percentage = this.totalSubs > 0 ? Math.round((this.processedSubs / this.totalSubs) * 100) : 0;
			
			$('.progress-bar-fill').css('width', percentage + '%');
			$('.progress-text').text(percentage + '%');
			$('#stat-processed').text(this.processedSubs);
			$('#stat-updated').text(this.updatedSubs);
			$('#stat-skipped').text(this.skippedSubs);
			$('#stat-errors').text(this.errorSubs);
		},

		/**
		 * Display results
		 */
		displayResults: function(results) {
			const $log = $('#sync-log');
			
			results.forEach((result) => {
				let className = 'log-entry';
				if (result.action === 'updated') {
					className += ' log-updated';
				} else if (result.action === 'error') {
					className += ' log-error';
				}

				const $entry = $('<div>')
					.addClass(className)
					.html('<strong>ID ' + result.id + ':</strong> ' + result.message);
				
				$log.append($entry);
			});

			// Auto-scroll to bottom
			$log.scrollTop($log[0].scrollHeight);
		},

		/**
		 * Handle completion
		 */
		handleComplete: function() {
			this.isProcessing = false;
			$('#sync-progress-title').text(eddRecurringSync.strings.complete);
			
			if (this.isDryRun) {
				$('.edd-sync-live[data-mode="' + this.currentMode + '"]').prop('disabled', false);
			}

			$('#download-log').show();
			this.enableButtons();
		},

		/**
		 * Handle error
		 */
		handleError: function(message) {
			this.isProcessing = false;
			alert(message);
			this.enableButtons();
		},

		/**
		 * Show progress section
		 */
		showProgress: function() {
			$('.edd-recurring-sync-progress').show();
			$('#sync-log').empty();
			$('#download-log').hide();
			$('#sync-progress-title').text(eddRecurringSync.strings.processing);
		},

		/**
		 * Disable buttons
		 */
		disableButtons: function() {
			$('.edd-sync-dry-run, .edd-sync-live').prop('disabled', true);
		},

		/**
		 * Enable buttons
		 */
		enableButtons: function() {
			$('.edd-sync-dry-run').prop('disabled', false);
		},

		/**
		 * Download log
		 */
		downloadLog: function(e) {
			e.preventDefault();

			$.ajax({
				url: eddRecurringSync.ajax_url,
				type: 'POST',
				data: {
					action: 'edd_sync_download_log',
					nonce: eddRecurringSync.nonce
				},
				success: (response) => {
					if (response.success) {
						const blob = new Blob([response.data.log], { type: 'text/plain' });
						const url = window.URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = response.data.filename;
						document.body.appendChild(a);
						a.click();
						window.URL.revokeObjectURL(url);
						document.body.removeChild(a);
					}
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		EDDRecurringSync.init();
	});

})(jQuery);
