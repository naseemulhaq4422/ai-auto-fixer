/**
 * AI Auto-Fixer: Enterprise Multi-Tab Admin Dashboard & Remediation Engine
 */

(function($) {
	'use strict';

	const App = {
		config: window.aiAutoFixerData || {},
		state: {
			mode: 'audit',
			indexPage: 1,
			indexFilter: 'all',
			imgPage: 1,
			imgFilter: 'all',
			mediaPage: 1,
			mediaFilter: 'all',
			pendingFixAction: null,
			pendingFixContext: {}
		},

		init: function() {
			this.bindTabs();
			this.bindModeToggle();
			this.bindFilters();
			this.bindRescan();
			this.bindPreviewModal();
			this.bindAutoFix();
			this.bindFixAll();
			this.bindSmartAlt();
			this.bindMediaTrash();
			this.bindRollback();
			this.bindModals();
			this.bindSettingsForm();
			this.checkInitialTab();
		},

		/**
		 * Check initial active tab from hash or DOM
		 */
		checkInitialTab: function() {
			if (window.location.hash) {
				const hash = window.location.hash.substring(1);
				const $targetTabLink = $('.aaf-tab-link[data-tab="' + hash + '"]');
				if ($targetTabLink.length) {
					$targetTabLink.trigger('click');
					return;
				}
			}
			const activeTab = $('.aaf-tab-link.active').data('tab');
			if (activeTab) {
				this.onTabActivated(activeTab);
			}
		},

		/**
		 * Tab navigation switching & dynamic lazy loading
		 */
		bindTabs: function() {
			const self = this;
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

				self.onTabActivated(targetTab);
			});
		},

		/**
		 * Trigger data load when a tab becomes active
		 */
		onTabActivated: function(tabId) {
			switch (tabId) {
				case 'tab-indexation':
					this.loadIndexation();
					break;
				case 'tab-images':
					this.loadImages();
					break;
				case 'tab-media':
					this.loadMedia();
					break;
				case 'tab-technical':
					this.loadTechnical();
					break;
				case 'tab-schema':
					this.loadSchema();
					break;
			}
		},

		/**
		 * Mode Toggle (Audit Mode vs Fix Mode)
		 */
		bindModeToggle: function() {
			const self = this;
			$('.aaf-mode-btn').on('click', function() {
				const mode = $(this).data('mode');
				self.state.mode = mode;

				$('.aaf-mode-btn').removeClass('active');
				$(this).addClass('active');

				$('.ai-auto-fixer-admin-wrap').attr('data-mode', mode);
			});
		},

		/**
		 * Issue filter buttons (All, Critical, Warnings, Passed)
		 */
		bindFilters: function() {
			$('.aaf-filter-btn[data-filter]').on('click', function() {
				const filter = $(this).data('filter');

				$('.aaf-filter-btn[data-filter]').removeClass('active');
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

			// Indexation Sub-filter
			const self = this;
			$('.aaf-filter-btn[data-index-filter]').on('click', function() {
				$('.aaf-filter-btn[data-index-filter]').removeClass('active');
				$(this).addClass('active');
				self.state.indexFilter = $(this).data('index-filter');
				self.state.indexPage = 1;
				self.loadIndexation();
			});

			// Images Sub-filter
			$('.aaf-filter-btn[data-img-filter]').on('click', function() {
				$('.aaf-filter-btn[data-img-filter]').removeClass('active');
				$(this).addClass('active');
				self.state.imgFilter = $(this).data('img-filter');
				self.state.imgPage = 1;
				self.loadImages();
			});

			// Media Sub-filter
			$('.aaf-filter-btn[data-media-filter]').on('click', function() {
				$('.aaf-filter-btn[data-media-filter]').removeClass('active');
				$(this).addClass('active');
				self.state.mediaFilter = $(this).data('media-filter');
				self.state.mediaPage = 1;
				self.loadMedia();
			});
		},

		/**
		 * Live Site Audit Trigger & Progress Polling
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

				$('#aaf-scan-progress-bar-wrap').slideDown(200);
				$('#aaf-progress-bar-fill').css('width', '15%');
				$('#aaf-progress-percent-text').text('15%');

				$.ajax({
					url: self.config.restUrl + '/scan',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						$('#aaf-progress-bar-fill').css('width', '100%');
						$('#aaf-progress-percent-text').text('100%');
						$text.text(self.config.i18n.scanComplete || 'Complete!');

						setTimeout(function() {
							window.location.reload();
						}, 600);
					},
					error: function() {
						alert(self.config.i18n.genericError);
						$btn.prop('disabled', false);
						$icon.removeClass('is-spinning');
						$text.text('Run Site Audit');
						$('#aaf-scan-progress-bar-wrap').slideUp(200);
					}
				});
			});
		},

		/**
		 * Pre-Execution Diff Preview Modal
		 */
		bindPreviewModal: function() {
			const self = this;

			$(document).on('click', '.aaf-execute-autofix-btn', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const action = $btn.data('action');
				const context = $btn.data('context') || {};

				self.state.pendingFixAction = action;
				self.state.pendingFixContext = context;

				// Fetch preview
				$.ajax({
					url: self.config.restUrl + '/autofix/preview',
					method: 'POST',
					data: {
						fix_action: action,
						context: context
					},
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							$('#aaf-diff-before-content code').text(response.before || 'N/A');
							$('#aaf-diff-after-content code').text(response.after || 'N/A');
							$('#aaf-preview-message').text(response.message || '');

							const risk = response.risk_level || 'low';
							$('#aaf-preview-risk-badge')
								.attr('class', 'aaf-risk-badge aaf-risk-' + risk)
								.text('Risk Level: ' + risk.toUpperCase());

							$('#aaf-preview-modal').fadeIn(200);
						} else {
							alert(response.message || self.config.i18n.genericError);
						}
					},
					error: function() {
						alert(self.config.i18n.genericError);
					}
				});
			});

			$('#aaf-preview-confirm-btn').on('click', function() {
				if (!self.state.pendingFixAction) return;

				const $btn = $(this);
				$btn.prop('disabled', true);
				$btn.find('.aaf-btn-label').text(self.config.i18n.applyingFix || 'Applying...');

				$.ajax({
					url: self.config.restUrl + '/autofix',
					method: 'POST',
					data: {
						fix_action: self.state.pendingFixAction,
						context: self.state.pendingFixContext
					},
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						$btn.prop('disabled', false);
						$btn.find('.aaf-btn-label').text('Confirm & Apply Safe Fix');
						$('#aaf-preview-modal').fadeOut(200);

						if (response && response.success) {
							alert(self.config.i18n.fixSuccess || 'Fix applied successfully!');
							window.location.reload();
						} else {
							alert(response.message || self.config.i18n.genericError);
						}
					},
					error: function() {
						$btn.prop('disabled', false);
						$btn.find('.aaf-btn-label').text('Confirm & Apply Safe Fix');
						alert(self.config.i18n.genericError);
					}
				});
			});
		},

		/**
		 * Bulk 1-Click Fix All Safe Issues
		 */
		bindFixAll: function() {
			const self = this;

			$('#aaf-fix-all-btn').on('click', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const $text = $btn.find('.aaf-btn-text');

				if (!confirm(self.config.i18n.confirmFixAll)) {
					return;
				}

				$btn.prop('disabled', true);
				$text.text(self.config.i18n.fixingAll || 'Applying...');

				$.ajax({
					url: self.config.restUrl + '/autofix/all-safe',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							alert(self.config.i18n.fixAllSuccess || 'All safe issues fixed!');
							window.location.reload();
						} else {
							alert(response.message || self.config.i18n.genericError);
							$btn.prop('disabled', false);
							$text.text('1-Click Fix All Safe Issues');
						}
					},
					error: function() {
						alert(self.config.i18n.genericError);
						$btn.prop('disabled', false);
						$text.text('1-Click Fix All Safe Issues');
					}
				});
			});
		},

		/**
		 * Smart ALT Text Generator Modal
		 */
		bindSmartAlt: function() {
			const self = this;

			$(document).on('click', '.aaf-suggest-alt-btn', function(e) {
				e.preventDefault();
				const attachmentId = $(this).data('attachment-id');
				const filename = $(this).data('filename');
				const imgUrl = $(this).data('url');

				$('#aaf-smart-alt-attachment-id').val(attachmentId);
				$('#aaf-smart-alt-filename').text(filename);
				$('#aaf-smart-alt-thumb').attr('src', imgUrl);
				$('#aaf-smart-alt-input').val('Generating recommendation...');
				$('#aaf-smart-alt-confidence-val').text('...');
				$('#aaf-smart-alt-confidence-fill').css('width', '0%');
				$('#aaf-smart-alt-modal').fadeIn(200);

				$.ajax({
					url: self.config.restUrl + '/images/alt-suggest',
					method: 'POST',
					data: { attachment_id: attachmentId },
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							$('#aaf-smart-alt-input').val(response.suggested_alt || '');
							$('#aaf-smart-alt-confidence-val').text(response.confidence + '%');
							$('#aaf-smart-alt-confidence-fill').css('width', response.confidence + '%');
							$('#aaf-smart-alt-sources').text('Synthesized from: ' + (response.sources || []).join(', '));
						}
					}
				});
			});

			$('#aaf-smart-alt-save-btn').on('click', function() {
				const attachmentId = $('#aaf-smart-alt-attachment-id').val();
				const altText = $('#aaf-smart-alt-input').val();
				const isDecorative = $('#aaf-smart-alt-decorative').is(':checked');

				$.ajax({
					url: self.config.restUrl + '/images/alt-fix',
					method: 'POST',
					data: {
						attachment_id: attachmentId,
						alt_text: altText,
						is_decorative: isDecorative
					},
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						$('#aaf-smart-alt-modal').fadeOut(200);
						if (response && response.success) {
							alert('ALT text applied successfully!');
							self.loadImages();
						} else {
							alert(response.message || 'Error saving ALT text');
						}
					}
				});
			});
		},

		/**
		 * Unlinked Media Safe Trash Action
		 */
		bindMediaTrash: function() {
			const self = this;

			$(document).on('click', '.aaf-trash-media-btn', function(e) {
				e.preventDefault();
				const attachmentId = $(this).data('attachment-id');
				const status = $(this).data('status');

				if (status === 'UNKNOWN') {
					alert('Action Blocked: This asset has UNKNOWN references (e.g. CSS background or JS dynamic data). For site safety, automated trashing is strictly disabled.');
					return;
				}

				if (!confirm(self.config.i18n.confirmTrash)) {
					return;
				}

				const $btn = $(this);
				$btn.prop('disabled', true);

				$.ajax({
					url: self.config.restUrl + '/media/trash',
					method: 'POST',
					data: { attachment_id: attachmentId },
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							$btn.closest('tr').fadeOut(400);
							alert('Media moved to Trash. It can be restored at any time.');
						} else {
							alert(response.message || 'Failed to trash media');
							$btn.prop('disabled', false);
						}
					},
					error: function(xhr) {
						const res = xhr.responseJSON;
						alert(res && res.message ? res.message : self.config.i18n.genericError);
						$btn.prop('disabled', false);
					}
				});
			});
		},

		/**
		 * 1-Click Rollback Action
		 */
		bindRollback: function() {
			const self = this;

			$(document).on('click', '.aaf-trigger-rollback-btn', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const uuid = $btn.data('uuid');

				if (!confirm(self.config.i18n.confirmRollback)) {
					return;
				}

				$btn.prop('disabled', true);
				$btn.find('.aaf-btn-label').text(self.config.i18n.rollingBack || 'Reverting...');

				$.ajax({
					url: self.config.restUrl + '/rollback',
					method: 'POST',
					data: { snapshot_uuid: uuid },
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						if (response && response.success) {
							alert(self.config.i18n.rollbackSuccess || 'Rollback successful!');
							window.location.reload();
						} else {
							alert(response.message || 'Rollback failed');
							$btn.prop('disabled', false);
						}
					},
					error: function() {
						alert(self.config.i18n.genericError);
						$btn.prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Modal dismissal
		 */
		bindModals: function() {
			$('.aaf-modal-close, .aaf-modal-cancel, .aaf-modal-overlay').on('click', function() {
				$('.aaf-modal').fadeOut(200);
			});
		},

		/**
		 * Bind Settings form submission via AJAX with immediate visual feedback
		 */
		bindSettingsForm: function() {
			const self = this;

			// Handle real-time toggle visual state changes
			$(document).on('change', '.aaf-setting-checkbox', function() {
				const $row = $(this).closest('.aaf-setting-row');
				const $pill = $row.find('.aaf-status-pill');
				const isChecked = $(this).is(':checked');

				if (isChecked) {
					$row.addClass('aaf-setting-active');
					$pill.removeClass('aaf-pill-inactive').addClass('aaf-pill-active').text('Active');
				} else {
					$row.removeClass('aaf-setting-active');
					$pill.removeClass('aaf-pill-active').addClass('aaf-pill-inactive').text('Disabled');
				}
			});

			// Handle form submission via REST API
			$(document).on('submit', '#aaf-settings-form', function(e) {
				e.preventDefault();

				const $btn = $('#aaf-save-settings-btn');
				const $btnLabel = $btn.find('.aaf-btn-label');
				const $btnIcon = $btn.find('.aaf-btn-icon');
				const $alert = $('#aaf-settings-ajax-alert');
				const $msg = $('#aaf-settings-ajax-msg');

				const payload = {
					enable_ai_robots: $('input[name="enable_ai_robots"]').is(':checked') ? 1 : 0,
					enable_geo_schema: $('input[name="enable_geo_schema"]').is(':checked') ? 1 : 0,
					enable_opengraph: $('input[name="enable_opengraph"]').is(':checked') ? 1 : 0
				};

				$btn.prop('disabled', true).addClass('aaf-btn-loading');
				$btnIcon.removeClass('dashicons-saved').addClass('dashicons-update aaf-spin-icon');
				$btnLabel.text('Saving Preferences...');

				$.ajax({
					url: self.config.restUrl + '/settings',
					method: 'POST',
					contentType: 'application/json',
					data: JSON.stringify(payload),
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
					},
					success: function(response) {
						$btn.prop('disabled', false).removeClass('aaf-btn-loading').addClass('aaf-btn-saved-state');
						$btnIcon.removeClass('dashicons-update aaf-spin-icon').addClass('dashicons-yes-alt');
						$btnLabel.text('Preferences Saved!');

						$alert.slideDown(250);
						$msg.text((response && response.message) ? response.message : 'Optimization preferences have been saved and applied in real-time.');

						setTimeout(function() {
							$btn.removeClass('aaf-btn-saved-state');
							$btnIcon.removeClass('dashicons-yes-alt').addClass('dashicons-saved');
							$btnLabel.text('Save Optimization Preferences');
						}, 3000);

						setTimeout(function() {
							$alert.slideUp(400);
						}, 6000);
					},
					error: function(xhr) {
						$btn.prop('disabled', false).removeClass('aaf-btn-loading');
						$btnIcon.removeClass('dashicons-update aaf-spin-icon').addClass('dashicons-saved');
						$btnLabel.text('Save Optimization Preferences');

						alert('Failed to save settings. Server error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : xhr.statusText));
					}
				});
			});
		},

		/**
		 * Load Indexation Tab Data
		 */
		loadIndexation: function() {
			const self = this;
			const $tbody = $('#aaf-indexation-tbody');

			$.ajax({
				url: self.config.restUrl + '/indexation',
				method: 'GET',
				data: {
					page: self.state.indexPage,
					per_page: 20,
					status: self.state.indexFilter
				},
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
				},
				success: function(response) {
					if (!response || !response.success || !response.items || !response.items.length) {
						$tbody.html('<tr><td colspan="4" class="aaf-empty-cell">No indexation issues detected in this category.</td></tr>');
						return;
					}

					let rows = '';
					response.items.forEach(function(item) {
						const isIndexable = item.is_indexable;
						const badgeClass = isIndexable ? 'aaf-badge-passed' : 'aaf-badge-critical';
						rows += '<tr>' +
							'<td><code>' + $('<div>').text(item.url).html() + '</code></td>' +
							'<td><span class="aaf-badge ' + badgeClass + '">' + $('<div>').text(item.status).html() + '</span></td>' +
							'<td>' + $('<div>').text(item.source || 'Core').html() + '</td>' +
							'<td>' + $('<div>').text((item.reasons || []).join('; ') || 'Valid & Indexable').html() + '</td>' +
							'</tr>';
					});
					$tbody.html(rows);

					$('#aaf-index-pagination-info').text('Page ' + response.page + ' of ' + response.total_pages + ' (' + response.total + ' URLs)');
					$('#aaf-index-prev-btn').prop('disabled', response.page <= 1);
					$('#aaf-index-next-btn').prop('disabled', response.page >= response.total_pages);
				}
			});
		},

		/**
		 * Load Images Tab Data
		 */
		loadImages: function() {
			const self = this;
			const $tbody = $('#aaf-images-tbody');

			$.ajax({
				url: self.config.restUrl + '/images',
				method: 'GET',
				data: {
					page: self.state.imgPage,
					per_page: 20,
					filter: self.state.imgFilter
				},
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
				},
				success: function(response) {
					if (!response || !response.success || !response.items || !response.items.length) {
						$tbody.html('<tr><td colspan="5" class="aaf-empty-cell">All image ALT attributes and dimensions satisfied!</td></tr>');
						return;
					}

					let rows = '';
					response.items.forEach(function(item) {
						const imgId = item.object_id || 0;
						rows += '<tr>' +
							'<td><img src="' + $('<div>').text(item.url).html() + '" style="width:48px;height:48px;object-fit:cover;border-radius:4px;" /></td>' +
							'<td><code>' + $('<div>').text(item.message || 'Image').html() + '</code></td>' +
							'<td><em>' + $('<div>').text(item.current_alt || '(empty)').html() + '</em></td>' +
							'<td><span class="aaf-badge aaf-badge-warning">' + $('<div>').text(item.issue_type || 'Image SEO').html() + '</span></td>' +
							'<td><button type="button" class="aaf-btn aaf-btn-sm aaf-btn-primary aaf-suggest-alt-btn" data-attachment-id="' + imgId + '" data-filename="' + $('<div>').text(item.message).html() + '" data-url="' + $('<div>').text(item.url).html() + '"><span class="dashicons dashicons-format-image"></span> Smart ALT</button></td>' +
							'</tr>';
					});
					$tbody.html(rows);

					$('#aaf-img-pagination-info').text('Page ' + response.page + ' of ' + response.total_pages);
					$('#aaf-img-prev-btn').prop('disabled', response.page <= 1);
					$('#aaf-img-next-btn').prop('disabled', response.page >= response.total_pages);
				}
			});
		},

		/**
		 * Load Unlinked Media Tab Data
		 */
		loadMedia: function() {
			const self = this;
			const $tbody = $('#aaf-media-tbody');

			$.ajax({
				url: self.config.restUrl + '/media/unlinked',
				method: 'GET',
				data: {
					page: self.state.mediaPage,
					per_page: 20,
					status: self.state.mediaFilter
				},
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
				},
				success: function(response) {
					if (!response || !response.success || !response.items || !response.items.length) {
						$tbody.html('<tr><td colspan="5" class="aaf-empty-cell">No unlinked media found in this category.</td></tr>');
						return;
					}

					let rows = '';
					response.items.forEach(function(item) {
						const isUnknown = item.status === 'UNKNOWN';
						const badgeClass = isUnknown ? 'aaf-badge-warning' : (item.can_trash ? 'aaf-badge-passed' : 'aaf-badge-info');

						rows += '<tr>' +
							'<td><img src="' + $('<div>').text(item.url).html() + '" style="width:48px;height:48px;object-fit:cover;border-radius:4px;" /></td>' +
							'<td><strong>' + $('<div>').text(item.filename).html() + '</strong><br><small>ID: ' + item.attachment_id + '</small></td>' +
							'<td><span class="aaf-badge ' + badgeClass + '">' + $('<div>').text(item.status).html() + '</span></td>' +
							'<td><small>' + $('<div>').text(item.dependency_report || 'No references found').html() + '</small></td>' +
							'<td>' + (item.can_trash && !isUnknown
								? '<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary aaf-trash-media-btn" data-attachment-id="' + item.attachment_id + '" data-status="' + item.status + '"><span class="dashicons dashicons-trash"></span> Move to Trash</button>'
								: '<span class="aaf-text-muted"><span class="dashicons dashicons-lock"></span> Protected</span>') +
							'</td>' +
							'</tr>';
					});
					$tbody.html(rows);

					$('#aaf-media-pagination-info').text('Page ' + response.page + ' of ' + response.total_pages);
					$('#aaf-media-prev-btn').prop('disabled', response.page <= 1);
					$('#aaf-media-next-btn').prop('disabled', response.page >= response.total_pages);
				}
			});
		},

		/**
		 * Load Technical SEO Broken Links
		 */
		loadTechnical: function() {
			const self = this;
			const $tbody = $('#aaf-links-tbody');

			$.ajax({
				url: self.config.restUrl + '/broken-links',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
				},
				success: function(response) {
					if (!response || !response.success || !response.items || !response.items.length) {
						$tbody.html('<tr><td colspan="5" class="aaf-empty-cell"><span class="dashicons dashicons-yes text-success"></span> No broken links discovered!</td></tr>');
						return;
					}

					let rows = '';
					response.items.forEach(function(item) {
						rows += '<tr>' +
							'<td><code>' + $('<div>').text(item.url).html() + '</code></td>' +
							'<td><span class="aaf-badge aaf-badge-critical">' + (item.status_code || 404) + '</span></td>' +
							'<td>' + $('<div>').text(item.anchor_text || '(image or empty)').html() + '</td>' +
							'<td><code>' + $('<div>').text(item.source_url || 'Homepage').html() + '</code></td>' +
							'<td><span class="aaf-manual-only-tag">Manual Review</span></td>' +
							'</tr>';
					});
					$tbody.html(rows);
				}
			});
		},

		/**
		 * Load Schema & Compatibility Data
		 */
		loadSchema: function() {
			const self = this;

			$.ajax({
				url: self.config.restUrl + '/compatibility',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', self.config.nonce);
				},
				success: function(response) {
					if (!response || !response.success || !response.compatibility) return;

					const compat = response.compatibility;
					const schema = compat.schema_owners || {};

					if (schema.organization) {
						$('#aaf-schema-org-source').text(schema.organization.owner || 'WordPress Core');
						$('#aaf-schema-org-state').text(schema.organization.state || 'OWNED');
						$('#aaf-schema-org-action').text(schema.organization.action || 'Auditor mode only');
					}
					if (schema.website) {
						$('#aaf-schema-site-source').text(schema.website.owner || 'WordPress Core');
						$('#aaf-schema-site-state').text(schema.website.state || 'OWNED');
						$('#aaf-schema-site-action').text(schema.website.action || 'Auditor mode only');
					}
				}
			});
		},

		bindAutoFix: function() {
			// Handled in preview modal workflow
		}
	};

	$(document).ready(function() {
		App.init();
	});

})(jQuery);
