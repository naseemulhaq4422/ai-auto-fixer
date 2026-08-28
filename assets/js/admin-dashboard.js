/**
 * AI Auto-Fixer: Admin Dashboard Interactivity & REST API Client
 */

(function($) {
	'use strict';

	const App = {
		config: window.aiAutoFixerData || {},

		init: function() {
			this.bindTabs();
			this.bindFilters();
			this.bindRescan();
			this.bindAutoFix();
			this.bindModal();
			this.bindLicenseVerification();
		},

		/**
		 * Tab navigation switching
		 */
		bindTabs: function() {
			$('.aaf-tab-link').on('click', function(e) {
				e.preventDefault();
				const targetTab = $(this).data('tab');

				$('.aaf-tab-link').removeClass('active');
				$(this).addClass('active');

				$('.aaf-tab-content').removeClass('active');
				$('#' + targetTab).addClass('active');

				if (history.pushState) {
					history.pushState(null, null, '#' + targetTab);
				}
			});

			// Handle direct hash navigation
			if (window.location.hash) {
				const hash = window.location.hash.substring(1);
				const $targetTabLink = $('.aaf-tab-link[data-tab="' + hash + '"]');
				if ($targetTabLink.length) {
					$targetTabLink.trigger('click');
				}
			}
		},

		/**
		 * Issue filter buttons (Critical, Warnings, Passed)
		 */
		bindFilters: function() {
			$('.aaf-filter-btn').on('click', function() {
				const filter = $(this).data('filter');

				$('.aaf-filter-btn').removeClass('active');
				$(this).addClass('active');

				if (filter === 'all') {
					$('.aaf-issue-card').show();
					$('#aaf-passed-container').show();
				} else if (filter === 'passed') {
					$('.aaf-issue-card').hide();
					$('#aaf-passed-container').show();
				} else {
					$('#aaf-passed-container').hide();
					$('.aaf-issue-card').hide();
					$('.aaf-issue-card[data-severity="' + filter + '"]').show();
				}
			});
		},

		/**
		 * Live Site Audit Trigger
		 */
		bindRescan: function() {
			const self = this;
			$('#aaf-trigger-rescan-btn').on('click', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const $icon = $btn.find('.dashicons');
				const $text = $btn.find('.aaf-btn-text');

				$btn.prop('disabled', true);
				$icon.addClass('is-spinning');
				$text.text(self.config.i18n.scanning || 'Scanning...');

				$.ajax({
					url: self.config.restUrl + '/scan',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							$text.text(self.config.i18n.scanComplete || 'Complete!');
							setTimeout(function() {
								window.location.reload();
							}, 600);
						} else {
							alert(response.message || self.config.i18n.genericError);
							$btn.prop('disabled', false);
							$icon.removeClass('is-spinning');
							$text.text('Run Site Audit');
						}
					},
					error: function() {
						alert(self.config.i18n.genericError);
						$btn.prop('disabled', false);
						$icon.removeClass('is-spinning');
						$text.text('Run Site Audit');
					}
				});
			});
		},

		/**
		 * 1-Click Auto-Fix Execution
		 */
		bindAutoFix: function() {
			const self = this;

			$(document).on('click', '.aaf-execute-autofix-btn', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const action = $btn.data('action');
				const $card = $btn.closest('.aaf-issue-card');

				if (!self.config.isPro) {
					$('#aaf-upgrade-modal').fadeIn(200);
					return;
				}

				if (!confirm(self.config.i18n.confirmFix || 'Apply this automated fix now?')) {
					return;
				}

				$btn.prop('disabled', true);
				$btn.find('.aaf-btn-label').text(self.config.i18n.applyingFix || 'Applying...');

				$.ajax({
					url: self.config.restUrl + '/autofix',
					method: 'POST',
					data: {
						fix_action: action,
						context: {}
					},
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							$btn.removeClass('aaf-btn-primary').addClass('aaf-btn-secondary');
							$btn.html('<span class="dashicons dashicons-yes text-success"></span> ' + (self.config.i18n.fixSuccess || 'Fixed!'));
							$card.css('border-left-color', 'var(--aaf-success)');
							
							setTimeout(function() {
								$card.fadeOut(400, function() {
									$(this).remove();
								});
							}, 1200);
						} else if (response && response.requires_upgrade) {
							$('#aaf-upgrade-modal').fadeIn(200);
							$btn.prop('disabled', false);
							$btn.find('.aaf-btn-label').text('1-Click Auto-Fix');
						} else {
							alert(response.message || self.config.i18n.genericError);
							$btn.prop('disabled', false);
							$btn.find('.aaf-btn-label').text('1-Click Auto-Fix');
						}
					},
					error: function() {
						alert(self.config.i18n.genericError);
						$btn.prop('disabled', false);
						$btn.find('.aaf-btn-label').text('1-Click Auto-Fix');
					}
				});
			});
		},

		/**
		 * Freemium Upgrade Modal Handling
		 */
		bindModal: function() {
			$(document).on('click', '.aaf-open-upgrade-modal-btn', function(e) {
				e.preventDefault();
				$('#aaf-upgrade-modal').fadeIn(200);
			});

			$('#aaf-modal-close-btn, .aaf-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$('#aaf-upgrade-modal').fadeOut(150);
				}
			});

			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('#aaf-upgrade-modal').fadeOut(150);
				}
			});
		},

		/**
		 * License Key Verification in Settings & Modal
		 */
		bindLicenseVerification: function() {
			const self = this;

			function verifyKey(key, $btn, $feedback) {
				if (!key) {
					$feedback.removeClass('is-success').addClass('is-error').text('Please enter a valid API key.').show();
					return;
				}

				$btn.prop('disabled', true).text(self.config.i18n.verifyingKey || 'Verifying...');
				$feedback.hide();

				$.ajax({
					url: self.config.restUrl + '/license/verify',
					method: 'POST',
					data: {
						api_key: key
					},
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						$btn.prop('disabled', false).text('Verify Key');
						if (response && response.success) {
							$feedback.removeClass('is-error').addClass('is-success').text(response.message || 'Key Activated!').show();
							setTimeout(function() {
								window.location.reload();
							}, 1000);
						} else {
							$feedback.removeClass('is-success').addClass('is-error').text(response.message || 'Invalid license key.').show();
						}
					},
					error: function() {
						$btn.prop('disabled', false).text('Verify Key');
						$feedback.removeClass('is-success').addClass('is-error').text(self.config.i18n.genericError).show();
					}
				});
			}

			// Settings Page Verification
			$('#aaf-verify-key-btn').on('click', function(e) {
				e.preventDefault();
				const key = $('#aaf-api-key-input').val().trim();
				verifyKey(key, $(this), $('#aaf-license-feedback'));
			});

			// Modal Dialog Verification
			$('#aaf-modal-activate-btn').on('click', function(e) {
				e.preventDefault();
				const key = $('#aaf-modal-api-key').val().trim();
				verifyKey(key, $(this), $('#aaf-modal-feedback'));
			});
		}
	};

	$(document).ready(function() {
		App.init();
	});

})(jQuery);
