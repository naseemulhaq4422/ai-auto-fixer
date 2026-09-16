<?php
/**
 * Partial: Tab Settings & Compatibility Matrix.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detected_seo_plugins = $detected_seo_plugins ?? \AiAutoFixer\Detectors\SeoPluginDetector::detect();
$detected_conflicts   = $detected_conflicts ?? \AiAutoFixer\Detectors\ConflictDetector::detect_conflicts();
?>

<div class="aaf-settings-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Auto-Fix Settings & Compatibility Matrix', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Manage persistent site optimizations and inspect real-time compatibility with active SEO plugins and page builders.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- Compatibility Matrix Card -->
	<div class="aaf-card aaf-compatibility-card">
		<div class="aaf-card-header">
			<h3><?php esc_html_e( 'Active Ecosystem & Coexistence Matrix', 'ai-auto-fixer' ); ?></h3>
			<p><?php esc_html_e( 'AI Auto-Fixer detects existing plugins and adapts its behavior to prevent redundant meta tags or conflicting schema.', 'ai-auto-fixer' ); ?></p>
		</div>
		<div class="aaf-card-body">
			<div class="aaf-compat-grid">
				<div class="aaf-compat-item">
					<span class="aaf-compat-label"><?php esc_html_e( 'Active SEO Engine:', 'ai-auto-fixer' ); ?></span>
					<?php if ( ! empty( $detected_seo_plugins['primary'] ) ) : ?>
						<span class="aaf-badge aaf-badge-passed">
							<?php echo esc_html( $detected_seo_plugins['primary']['name'] ?? 'Detected Plugin' ); ?>
						</span>
					<?php else : ?>
						<span class="aaf-badge aaf-badge-info"><?php esc_html_e( 'No Dedicated SEO Plugin (WordPress Core)', 'ai-auto-fixer' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="aaf-compat-item">
					<span class="aaf-compat-label"><?php esc_html_e( 'Page Builders:', 'ai-auto-fixer' ); ?></span>
					<?php
					$builders = array();
					if ( ! empty( $detected_conflicts['elementor']['active'] ) ) {
						$builders[] = 'Elementor';
					}
					if ( ! empty( $detected_conflicts['divi']['active'] ) ) {
						$builders[] = 'Divi';
					}
					if ( empty( $builders ) ) {
						$builders[] = 'Block Editor (Gutenberg)';
					}
					?>
					<span class="aaf-badge"><?php echo esc_html( implode( ', ', $builders ) ); ?></span>
				</div>

				<div class="aaf-compat-item">
					<span class="aaf-compat-label"><?php esc_html_e( 'WooCommerce Store:', 'ai-auto-fixer' ); ?></span>
					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
						<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Active (Catalog Protected)', 'ai-auto-fixer' ); ?></span>
					<?php else : ?>
						<span class="aaf-badge"><?php esc_html_e( 'Not Installed', 'ai-auto-fixer' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="aaf-compat-item">
					<span class="aaf-compat-label"><?php esc_html_e( 'Schema Policy:', 'ai-auto-fixer' ); ?></span>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Non-Destructive Coexistence (Zero Duplicates)', 'ai-auto-fixer' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Settings Form Card -->
	<div class="aaf-card" style="margin-top: 24px;">
		<div class="aaf-card-header">
			<h3><?php esc_html_e( 'Continuous Background Optimizations', 'ai-auto-fixer' ); ?></h3>
			<p><?php esc_html_e( 'Enable automatic header injections and crawler directives for your site.', 'ai-auto-fixer' ); ?></p>
		</div>
		<div class="aaf-card-body">
			<form id="aaf-settings-form" method="post" action="">
				<?php wp_nonce_field( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' ); ?>

				<div class="aaf-toggle-option">
					<label>
						<input type="checkbox" name="enable_ai_robots" value="1" <?php checked( ! empty( $settings['enable_ai_robots'] ) ); ?>>
						<strong><?php esc_html_e( 'Enable AI Search Crawlers in robots.txt (GEO)', 'ai-auto-fixer' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Allows ChatGPT (GPTBot), Claude (ClaudeBot), and PerplexityBot to index your public content.', 'ai-auto-fixer' ); ?></p>
				</div>

				<div class="aaf-toggle-option" style="margin-top: 18px;">
					<label>
						<input type="checkbox" name="enable_geo_schema" value="1" <?php checked( ! empty( $settings['enable_geo_schema'] ) ); ?>>
						<strong><?php esc_html_e( 'Inject Schema.org JSON-LD Entity Graph (AEO)', 'ai-auto-fixer' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Outputs structured Organization and WebSite schema if no existing plugin owns this entity.', 'ai-auto-fixer' ); ?></p>
				</div>

				<div class="aaf-toggle-option" style="margin-top: 18px;">
					<label>
						<input type="checkbox" name="enable_opengraph" value="1" <?php checked( ! empty( $settings['enable_opengraph'] ) ); ?>>
						<strong><?php esc_html_e( 'Enable OpenGraph Social & AI Summary Meta Tags', 'ai-auto-fixer' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Generates OpenGraph titles, descriptions, and site identity for rich conversational AI previews.', 'ai-auto-fixer' ); ?></p>
				</div>

				<div style="margin-top: 24px;">
					<button type="submit" name="ai_auto_fixer_save_settings" class="aaf-btn aaf-btn-primary">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Save Optimization Preferences', 'ai-auto-fixer' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
