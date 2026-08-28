<?php
/**
 * Partial: Freemium Upgrade Modal Dialog.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="aaf-upgrade-modal" class="aaf-modal-overlay" style="display: none;">
	<div class="aaf-modal-dialog">
		<button type="button" class="aaf-modal-close" id="aaf-modal-close-btn">&times;</button>
		
		<div class="aaf-modal-header">
			<div class="aaf-modal-icon-badge">
				<span class="dashicons dashicons-superhero"></span>
			</div>
			<h2><?php esc_html_e( 'Unlock 1-Click AI Auto-Fixes', 'ai-auto-fixer' ); ?></h2>
			<p><?php esc_html_e( 'Upgrade to AI Auto-Fixer Pro or connect your existing SaaS API key to automatically resolve audit errors across your site.', 'ai-auto-fixer' ); ?></p>
		</div>

		<div class="aaf-modal-body">
			<!-- Feature Comparison List -->
			<div class="aaf-pro-feature-list">
				<div class="aaf-pro-feature-item">
					<span class="dashicons dashicons-yes-alt text-success"></span>
					<div>
						<strong><?php esc_html_e( '1-Click Automated Fixes', 'ai-auto-fixer' ); ?></strong>
						<p><?php esc_html_e( 'Instantly fix missing robots.txt, broken sitemaps, and indexing errors without editing code or server files.', 'ai-auto-fixer' ); ?></p>
					</div>
				</div>
				<div class="aaf-pro-feature-item">
					<span class="dashicons dashicons-yes-alt text-success"></span>
					<div>
						<strong><?php esc_html_e( 'Generative Engine Optimization (GEO)', 'ai-auto-fixer' ); ?></strong>
						<p><?php esc_html_e( 'Grant safe indexing to ChatGPT, Claude, and Perplexity crawlers to increase LLM citations.', 'ai-auto-fixer' ); ?></p>
					</div>
				</div>
				<div class="aaf-pro-feature-item">
					<span class="dashicons dashicons-yes-alt text-success"></span>
					<div>
						<strong><?php esc_html_e( 'AEO JSON-LD Schema Entity Graph', 'ai-auto-fixer' ); ?></strong>
						<p><?php esc_html_e( 'Inject rich structured Organization and WebSite entity markup directly into frontend pages.', 'ai-auto-fixer' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Quick Key Activation inside Modal -->
			<div class="aaf-modal-key-box">
				<h4><?php esc_html_e( 'Already have a Pro SaaS License?', 'ai-auto-fixer' ); ?></h4>
				<div class="aaf-input-action-row">
					<input type="password" id="aaf-modal-api-key" placeholder="aaf_live_xxxxxxxxxxxxxxxxxxxxxx" class="aaf-input">
					<button type="button" id="aaf-modal-activate-btn" class="aaf-btn aaf-btn-primary">
						<?php esc_html_e( 'Activate Key', 'ai-auto-fixer' ); ?>
					</button>
				</div>
				<div id="aaf-modal-feedback" class="aaf-feedback-message"></div>
			</div>
		</div>

		<div class="aaf-modal-footer">
			<a href="https://app.creativesdigitalagency.com/" target="_blank" rel="noopener noreferrer" class="aaf-btn aaf-btn-gradient aaf-btn-block">
				<span class="dashicons dashicons-cart"></span>
				<?php esc_html_e( 'Get AI Auto-Fixer Pro License &rarr;', 'ai-auto-fixer' ); ?>
			</a>
		</div>
	</div>
</div>
