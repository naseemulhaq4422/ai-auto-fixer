<?php
/**
 * Partial: Smart ALT Generator & Confidence Modal.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="aaf-smart-alt-modal" class="aaf-modal" style="display: none;">
	<div class="aaf-modal-overlay"></div>
	<div class="aaf-modal-container">
		<div class="aaf-modal-header">
			<div class="aaf-modal-title-group">
				<span class="dashicons dashicons-format-image"></span>
				<h3><?php esc_html_e( 'Smart Contextual ALT Text Generator', 'ai-auto-fixer' ); ?></h3>
			</div>
			<button type="button" class="aaf-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-auto-fixer' ); ?>">&times;</button>
		</div>

		<div class="aaf-modal-body">
			<div class="aaf-smart-alt-preview-card">
				<div class="aaf-smart-alt-img-wrap">
					<img id="aaf-smart-alt-thumb" src="" alt="" />
				</div>
				<div class="aaf-smart-alt-details">
					<h4 id="aaf-smart-alt-filename">image.jpg</h4>
					<div class="aaf-confidence-meter">
						<span class="aaf-confidence-label"><?php esc_html_e( 'Confidence Score:', 'ai-auto-fixer' ); ?> <strong id="aaf-smart-alt-confidence-val">0%</strong></span>
						<div class="aaf-confidence-bar">
							<div id="aaf-smart-alt-confidence-fill" class="aaf-confidence-fill" style="width: 0%;"></div>
						</div>
					</div>
					<p id="aaf-smart-alt-sources" class="aaf-sources-text"></p>
				</div>
			</div>

			<div class="aaf-form-group" style="margin-top: 20px;">
				<label for="aaf-smart-alt-input">
					<strong><?php esc_html_e( 'Proposed Image ALT Text:', 'ai-auto-fixer' ); ?></strong>
				</label>
				<input type="text" id="aaf-smart-alt-input" class="aaf-input-field" placeholder="<?php esc_attr_e( 'Generating recommendation...', 'ai-auto-fixer' ); ?>" />
				<p class="description"><?php esc_html_e( 'Provides accessible context for screen readers and search engines (WCAG 2.1 & Google guidelines).', 'ai-auto-fixer' ); ?></p>
			</div>

			<div class="aaf-checkbox-group" style="margin-top: 14px;">
				<label>
					<input type="checkbox" id="aaf-smart-alt-decorative" />
					<span><?php esc_html_e( 'Mark as Decorative (alt="" - appropriate for background shapes and dividers)', 'ai-auto-fixer' ); ?></span>
				</label>
			</div>
		</div>

		<div class="aaf-modal-footer">
			<input type="hidden" id="aaf-smart-alt-attachment-id" value="" />
			<button type="button" class="aaf-btn aaf-btn-secondary aaf-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-auto-fixer' ); ?></button>
			<button type="button" id="aaf-smart-alt-save-btn" class="aaf-btn aaf-btn-primary">
				<span class="dashicons dashicons-saved"></span>
				<span class="aaf-btn-label"><?php esc_html_e( 'Save & Apply ALT Text', 'ai-auto-fixer' ); ?></span>
			</button>
		</div>
	</div>
</div>
