<?php
/**
 * Partial: Before vs After Fix Preview Modal.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="aaf-preview-modal" class="aaf-modal" style="display: none;">
	<div class="aaf-modal-overlay"></div>
	<div class="aaf-modal-container">
		<div class="aaf-modal-header">
			<div class="aaf-modal-title-group">
				<span class="dashicons dashicons-visibility"></span>
				<h3 id="aaf-preview-title"><?php esc_html_e( 'Pre-Execution Fix Preview & Safety Diff', 'ai-auto-fixer' ); ?></h3>
			</div>
			<button type="button" class="aaf-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-auto-fixer' ); ?>">&times;</button>
		</div>

		<div class="aaf-modal-body">
			<div class="aaf-preview-alert">
				<span class="dashicons dashicons-shield"></span>
				<p><?php esc_html_e( 'Review proposed changes below. A transactional snapshot will be automatically saved to enable 1-Click Rollback at any time.', 'ai-auto-fixer' ); ?></p>
			</div>

			<div class="aaf-diff-comparison">
				<div class="aaf-diff-column aaf-diff-before">
					<div class="aaf-diff-label">
						<span class="dashicons dashicons-minus"></span>
						<?php esc_html_e( 'Current State (Before)', 'ai-auto-fixer' ); ?>
					</div>
					<pre id="aaf-diff-before-content" class="aaf-diff-code"><code>...</code></pre>
				</div>
				<div class="aaf-diff-column aaf-diff-after">
					<div class="aaf-diff-label">
						<span class="dashicons dashicons-plus"></span>
						<?php esc_html_e( 'Target State (After Fix)', 'ai-auto-fixer' ); ?>
					</div>
					<pre id="aaf-diff-after-content" class="aaf-diff-code"><code>...</code></pre>
				</div>
			</div>

			<div class="aaf-preview-meta">
				<span id="aaf-preview-risk-badge" class="aaf-risk-badge aaf-risk-low"><?php esc_html_e( 'Risk Level: Low', 'ai-auto-fixer' ); ?></span>
				<span id="aaf-preview-message" class="aaf-preview-note"></span>
			</div>
		</div>

		<div class="aaf-modal-footer">
			<button type="button" class="aaf-btn aaf-btn-secondary aaf-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-auto-fixer' ); ?></button>
			<button type="button" id="aaf-preview-confirm-btn" class="aaf-btn aaf-btn-primary">
				<span class="dashicons dashicons-saved"></span>
				<span class="aaf-btn-label"><?php esc_html_e( 'Confirm & Apply Safe Fix', 'ai-auto-fixer' ); ?></span>
			</button>
		</div>
	</div>
</div>
