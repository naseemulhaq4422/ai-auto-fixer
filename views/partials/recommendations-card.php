<?php
/**
 * Partial: AI / GEO Recommendations Cards (100% Free & Standalone).
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-recommendations-section">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Generative AI & Search Engine Optimization Blueprint', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Built-in AI optimization blueprints to maximize your website authority across Google Search, ChatGPT, Perplexity, and Claude.', 'ai-auto-fixer' ); ?></p>
	</div>

	<div class="aaf-rec-grid">
		<?php if ( ! empty( $recommendations ) && is_array( $recommendations ) ) : ?>
			<?php foreach ( $recommendations as $rec ) : ?>
				<div class="aaf-card aaf-rec-card">
					<div class="aaf-rec-badge"><?php echo esc_html( $rec['badge'] ?? 'AI Search' ); ?></div>
					<h3 class="aaf-rec-title"><?php echo esc_html( $rec['title'] ?? '' ); ?></h3>
					<p class="aaf-rec-desc"><?php echo esc_html( $rec['description'] ?? '' ); ?></p>

					<div class="aaf-rec-footer">
						<button class="aaf-btn aaf-btn-primary aaf-execute-autofix-btn" data-action="<?php echo esc_attr( $rec['action_key'] ?? 'inject_geo_schema' ); ?>">
							<span class="dashicons dashicons-admin-tools"></span>
							<span><?php echo esc_html( $rec['action_label'] ?? __( 'Apply Optimization', 'ai-auto-fixer' ) ); ?></span>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
