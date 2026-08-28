<?php
/**
 * Partial: AI / GEO Recommendations Cards.
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
		<p><?php esc_html_e( 'Tailored AI strategies to maximize your website authority across Google Search, ChatGPT, Perplexity, and Claude.', 'ai-auto-fixer' ); ?></p>
	</div>

	<div class="aaf-rec-grid">
		<?php if ( ! empty( $recommendations ) && is_array( $recommendations ) ) : ?>
			<?php foreach ( $recommendations as $rec ) : ?>
				<div class="aaf-card aaf-rec-card">
					<div class="aaf-rec-badge"><?php echo esc_html( $rec['badge'] ?? 'AI Search' ); ?></div>
					<h3 class="aaf-rec-title"><?php echo esc_html( $rec['title'] ?? '' ); ?></h3>
					<p class="aaf-rec-desc"><?php echo esc_html( $rec['description'] ?? '' ); ?></p>

					<div class="aaf-rec-footer">
						<?php if ( ! empty( $rec['is_pro'] ) && ! $is_pro ) : ?>
							<button class="aaf-btn aaf-btn-pro-locked aaf-open-upgrade-modal-btn">
								<span class="dashicons dashicons-lock"></span>
								<span><?php echo esc_html( $rec['action_label'] ?? __( 'Auto-Apply Recommendation', 'ai-auto-fixer' ) ); ?></span>
								<span class="aaf-pill-pro-small">PRO</span>
							</button>
						<?php else : ?>
							<button class="aaf-btn aaf-btn-primary aaf-execute-autofix-btn" data-action="inject_geo_schema">
								<span class="dashicons dashicons-admin-tools"></span>
								<span><?php echo esc_html( $rec['action_label'] ?? __( 'Apply Fix', 'ai-auto-fixer' ) ); ?></span>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
