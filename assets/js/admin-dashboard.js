/**
 * AI Auto-Fixer: Admin Dashboard Interactivity & 100% Free Auto-Fix Client
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
			this.bindFixAll();
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
		 * Issue filter buttons (All, Critical, Warnings, Passed)
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
							}, 500);
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
		 * Individual 1-Click Auto-Fix Execution
		 */
		bindAutoFix: function() {
			const self = this;

			$(document).on('click', '.aaf-execute-autofix-btn', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const action = $btn.data('action');
				const $card = $btn.closest('.aaf-issue-card');

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
									const remaining = $('.aaf-issue-card:visible').length;
									$('#aaf-total-issues-badge').text(remaining);
									if (remaining === 0) {
										window.location.reload();
									}
								});
							}, 800);
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
		 * Master "1-Click Fix All Issues" Execution
		 */
		bindFixAll: function() {
			const self = this;

			$('#aaf-fix-all-btn').on('click', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const $icon = $btn.find('.dashicons');
				const $text = $btn.find('.aaf-btn-text');

				if (!confirm(self.config.i18n.confirmFixAll || 'Apply all recommended automated fixes to this site?')) {
					return;
				}

				$btn.prop('disabled', true);
				$icon.addClass('is-spinning');
				$text.text(self.config.i18n.fixingAll || 'Applying All Fixes...');

				$.ajax({
					url: self.config.restUrl + '/autofix/all',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							$text.text(self.config.i18n.fixAllSuccess || 'All Fixed!');
							$('.aaf-issue-card').css('border-left-color', 'var(--aaf-success)');
							setTimeout(function() {
								window.location.reload();
							}, 1000);
						} else {
							alert(response.message || self.config.i18n.genericError);
							$btn.prop('disabled', false);
							$icon.removeClass('is-spinning');
							$text.text('1-Click Fix All Issues');
						}
					},
					error: function() {
						alert(self.config.i18n.genericError);
						$btn.prop('disabled', false);
						$icon.removeClass('is-spinning');
						$text.text('1-Click Fix All Issues');
					}
				});
			});
		}
	};

	$(document).ready(function() {
		App.init();
	});

})(jQuery);
