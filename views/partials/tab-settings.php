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
$settings             = $settings ?? get_option( 'ai_auto_fixer_settings', array() );
?>

<div class="aaf-settings-wrap">
	<!-- Saved notification banner -->
	<?php if ( ! empty( $settings_saved ) ) : ?>
		<div class="aaf-alert-banner aaf-alert-success">
			<div class="aaf-alert-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
			</div>
			<div class="aaf-alert-content">
				<strong><?php esc_html_e( 'Settings Saved Successfully!', 'ai-auto-fixer' ); ?></strong>
				<span><?php esc_html_e( 'Your site optimization preferences have been updated and applied in real-time.', 'ai-auto-fixer' ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Dynamic AJAX alert container -->
	<div id="aaf-settings-ajax-alert" class="aaf-alert-banner aaf-alert-success" style="display: none;">
		<div class="aaf-alert-icon">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
		</div>
		<div class="aaf-alert-content">
			<strong><?php esc_html_e( 'Preferences Saved & Applied!', 'ai-auto-fixer' ); ?></strong>
			<span id="aaf-settings-ajax-msg"><?php esc_html_e( 'Your site optimization directives are active.', 'ai-auto-fixer' ); ?></span>
		</div>
	</div>

	<div class="aaf-section-header">
		<div class="aaf-section-title-group">
			<span class="aaf-pill-badge aaf-pill-accent"><?php esc_html_e( 'Local Intelligence Engine', 'ai-auto-fixer' ); ?></span>
			<h2><?php esc_html_e( 'Auto-Fix Settings & Compatibility Matrix', 'ai-auto-fixer' ); ?></h2>
		</div>
		<p class="aaf-section-desc"><?php esc_html_e( 'Manage persistent technical SEO automations, AI search visibility, and monitor seamless coexistence with installed plugins.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- Continuous Background Optimizations Form Card -->
	<div class="aaf-card aaf-settings-card">
		<div class="aaf-card-header">
			<div class="aaf-card-header-icon aaf-icon-gradient-purple">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
					<circle cx="12" cy="12" r="4"/>
				</svg>
			</div>
			<div>
				<h3><?php esc_html_e( 'Continuous Background Optimizations', 'ai-auto-fixer' ); ?></h3>
				<p class="aaf-card-subtitle"><?php esc_html_e( 'Toggle safe, automated on-page injections and crawler directives to improve search and AI citation rankings.', 'ai-auto-fixer' ); ?></p>
			</div>
		</div>

		<div class="aaf-card-body">
			<form id="aaf-settings-form" method="post" action="">
				<?php wp_nonce_field( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' ); ?>

				<div class="aaf-settings-list">
					<!-- Option 1: AI Search Crawlers (GEO) -->
					<div class="aaf-setting-row <?php echo ! empty( $settings['enable_ai_robots'] ) ? 'aaf-setting-active' : ''; ?>">
						<div class="aaf-setting-icon-box aaf-icon-purple">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="3" y="11" width="18" height="10" rx="2"/>
								<circle cx="12" cy="5" r="2"/>
								<path d="M12 7v4"/>
								<line x1="8" y1="16" x2="8" y2="16"/>
								<line x1="16" y1="16" x2="16" y2="16"/>
							</svg>
						</div>
						<div class="aaf-setting-content">
							<div class="aaf-setting-headline">
								<h4 class="aaf-setting-title"><?php esc_html_e( 'Enable AI Search Crawlers in robots.txt (GEO)', 'ai-auto-fixer' ); ?></h4>
								<span class="aaf-tag aaf-tag-purple"><?php esc_html_e( 'Generative AI Discovery', 'ai-auto-fixer' ); ?></span>
								<span class="aaf-status-pill <?php echo ! empty( $settings['enable_ai_robots'] ) ? 'aaf-pill-active' : 'aaf-pill-inactive'; ?>">
									<?php echo ! empty( $settings['enable_ai_robots'] ) ? esc_html__( 'Active', 'ai-auto-fixer' ) : esc_html__( 'Disabled', 'ai-auto-fixer' ); ?>
								</span>
							</div>
							<p class="aaf-setting-description">
								<?php esc_html_e( 'Permits top AI search models (ChatGPT/GPTBot, Anthropic ClaudeBot, and PerplexityBot) to index your public content, boosting your chances of being cited as a trusted source in AI conversational answers.', 'ai-auto-fixer' ); ?>
							</p>
						</div>
						<div class="aaf-setting-action">
							<label class="aaf-toggle-switch" title="<?php esc_attr_e( 'Toggle AI search crawlers', 'ai-auto-fixer' ); ?>">
								<input type="checkbox" name="enable_ai_robots" class="aaf-setting-checkbox" value="1" <?php checked( ! empty( $settings['enable_ai_robots'] ) ); ?>>
								<span class="aaf-toggle-slider"></span>
							</label>
						</div>
					</div>

					<!-- Option 2: Schema.org Entity Graph (AEO) -->
					<div class="aaf-setting-row <?php echo ! empty( $settings['enable_geo_schema'] ) ? 'aaf-setting-active' : ''; ?>">
						<div class="aaf-setting-icon-box aaf-icon-emerald">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="16 18 22 12 16 6"/>
								<polyline points="8 6 2 12 8 18"/>
								<circle cx="12" cy="12" r="2"/>
							</svg>
						</div>
						<div class="aaf-setting-content">
							<div class="aaf-setting-headline">
								<h4 class="aaf-setting-title"><?php esc_html_e( 'Inject Schema.org JSON-LD Entity Graph (AEO)', 'ai-auto-fixer' ); ?></h4>
								<span class="aaf-tag aaf-tag-emerald"><?php esc_html_e( 'Recommended for SEO', 'ai-auto-fixer' ); ?></span>
								<span class="aaf-status-pill <?php echo ! empty( $settings['enable_geo_schema'] ) ? 'aaf-pill-active' : 'aaf-pill-inactive'; ?>">
									<?php echo ! empty( $settings['enable_geo_schema'] ) ? esc_html__( 'Active', 'ai-auto-fixer' ) : esc_html__( 'Disabled', 'ai-auto-fixer' ); ?>
								</span>
							</div>
							<p class="aaf-setting-description">
								<?php esc_html_e( 'Injects clean, Google-validated Organization and WebSite entity graphs. Automatically respects existing SEO plugins (Yoast, Rank Math, AIOSEO) with zero duplicate schema risk.', 'ai-auto-fixer' ); ?>
							</p>
						</div>
						<div class="aaf-setting-action">
							<label class="aaf-toggle-switch" title="<?php esc_attr_e( 'Toggle Schema.org structured data', 'ai-auto-fixer' ); ?>">
								<input type="checkbox" name="enable_geo_schema" class="aaf-setting-checkbox" value="1" <?php checked( ! empty( $settings['enable_geo_schema'] ) ); ?>>
								<span class="aaf-toggle-slider"></span>
							</label>
						</div>
					</div>

					<!-- Option 3: OpenGraph & Social Cards -->
					<div class="aaf-setting-row <?php echo ! empty( $settings['enable_opengraph'] ) ? 'aaf-setting-active' : ''; ?>">
						<div class="aaf-setting-icon-box aaf-icon-blue">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
								<polyline points="16 6 12 2 8 6"/>
								<line x1="12" y1="2" x2="12" y2="15"/>
							</svg>
						</div>
						<div class="aaf-setting-content">
							<div class="aaf-setting-headline">
								<h4 class="aaf-setting-title"><?php esc_html_e( 'Enable OpenGraph Social & AI Summary Meta Tags', 'ai-auto-fixer' ); ?></h4>
								<span class="aaf-tag aaf-tag-blue"><?php esc_html_e( 'Rich Social Previews', 'ai-auto-fixer' ); ?></span>
								<span class="aaf-status-pill <?php echo ! empty( $settings['enable_opengraph'] ) ? 'aaf-pill-active' : 'aaf-pill-inactive'; ?>">
									<?php echo ! empty( $settings['enable_opengraph'] ) ? esc_html__( 'Active', 'ai-auto-fixer' ) : esc_html__( 'Disabled', 'ai-auto-fixer' ); ?>
								</span>
							</div>
							<p class="aaf-setting-description">
								<?php esc_html_e( 'Generates high-fidelity OpenGraph titles, descriptions, and site identity for rich conversational AI card previews and social platforms (WhatsApp, Facebook, Twitter/X, LinkedIn).', 'ai-auto-fixer' ); ?>
							</p>
						</div>
						<div class="aaf-setting-action">
							<label class="aaf-toggle-switch" title="<?php esc_attr_e( 'Toggle OpenGraph metadata', 'ai-auto-fixer' ); ?>">
								<input type="checkbox" name="enable_opengraph" class="aaf-setting-checkbox" value="1" <?php checked( ! empty( $settings['enable_opengraph'] ) ); ?>>
								<span class="aaf-toggle-slider"></span>
							</label>
						</div>
					</div>
				</div>

				<!-- Save Preferences Action Bar -->
				<div class="aaf-settings-footer">
					<button type="submit" id="aaf-save-settings-btn" name="ai_auto_fixer_save_settings" class="aaf-btn aaf-btn-primary aaf-btn-lg">
						<span class="dashicons dashicons-saved aaf-btn-icon"></span>
						<span class="aaf-btn-label"><?php esc_html_e( 'Save Optimization Preferences', 'ai-auto-fixer' ); ?></span>
					</button>
					<span class="aaf-settings-save-hint">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
						<?php esc_html_e( 'Directives are applied instantly and safely without reloading or caching conflicts.', 'ai-auto-fixer' ); ?>
					</span>
				</div>
			</form>
		</div>
	</div>

	<!-- Compatibility Matrix Card -->
	<div class="aaf-card aaf-compatibility-card" style="margin-top: 24px;">
		<div class="aaf-card-header">
			<div class="aaf-card-header-icon aaf-icon-gradient-blue">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polygon points="12 2 2 7 12 12 22 7 12 2"/>
					<polyline points="2 17 12 22 22 17"/>
					<polyline points="2 12 12 17 22 12"/>
				</svg>
			</div>
			<div>
				<h3><?php esc_html_e( 'Active Ecosystem & Coexistence Matrix', 'ai-auto-fixer' ); ?></h3>
				<p class="aaf-card-subtitle"><?php esc_html_e( 'AI Auto-Fixer dynamically analyzes installed plugins and page builders to ensure 100% harmonious coexistence.', 'ai-auto-fixer' ); ?></p>
			</div>
		</div>
		<div class="aaf-card-body">
			<div class="aaf-compat-grid">
				<div class="aaf-compat-item">
					<div class="aaf-compat-header">
						<span class="aaf-compat-label"><?php esc_html_e( 'Active SEO Engine', 'ai-auto-fixer' ); ?></span>
					</div>
					<div class="aaf-compat-val">
						<?php if ( ! empty( $detected_seo_plugins ) ) : ?>
							<?php $first_seo = reset( $detected_seo_plugins ); ?>
							<span class="aaf-badge aaf-badge-passed">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php echo esc_html( $first_seo['name'] ?? 'Detected SEO Engine' ); ?>
							</span>
						<?php else : ?>
							<span class="aaf-badge aaf-badge-info">
								<span class="dashicons dashicons-wordpress"></span>
								<?php esc_html_e( 'WordPress Core (Native)', 'ai-auto-fixer' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<p class="aaf-compat-desc"><?php esc_html_e( 'Meta and canonical generation safely yield to active SEO engines.', 'ai-auto-fixer' ); ?></p>
				</div>

				<div class="aaf-compat-item">
					<div class="aaf-compat-header">
						<span class="aaf-compat-label"><?php esc_html_e( 'Page Builders', 'ai-auto-fixer' ); ?></span>
					</div>
					<div class="aaf-compat-val">
						<?php
						$builders = array();
						if ( ! empty( $detected_conflicts['elementor']['active'] ) ) {
							$builders[] = 'Elementor';
						}
						if ( ! empty( $detected_conflicts['divi']['active'] ) ) {
							$builders[] = 'Divi';
						}
						if ( empty( $builders ) ) {
							$builders[] = 'Gutenberg (Block Editor)';
						}
						?>
						<span class="aaf-badge aaf-badge-builder">
							<span class="dashicons dashicons-layout"></span>
							<?php echo esc_html( implode( ', ', $builders ) ); ?>
						</span>
					</div>
					<p class="aaf-compat-desc"><?php esc_html_e( 'Remediation engine protects dynamic visual sections and shortcodes.', 'ai-auto-fixer' ); ?></p>
				</div>

				<div class="aaf-compat-item">
					<div class="aaf-compat-header">
						<span class="aaf-compat-label"><?php esc_html_e( 'WooCommerce Store', 'ai-auto-fixer' ); ?></span>
					</div>
					<div class="aaf-compat-val">
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<span class="aaf-badge aaf-badge-passed">
								<span class="dashicons dashicons-cart"></span>
								<?php esc_html_e( 'Active & Protected', 'ai-auto-fixer' ); ?>
							</span>
						<?php else : ?>
							<span class="aaf-badge aaf-badge-neutral">
								<?php esc_html_e( 'Not Installed', 'ai-auto-fixer' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<p class="aaf-compat-desc"><?php esc_html_e( 'Product schema, SKU integrity, and price attributes are safely audited.', 'ai-auto-fixer' ); ?></p>
				</div>

				<div class="aaf-compat-item">
					<div class="aaf-compat-header">
						<span class="aaf-compat-label"><?php esc_html_e( 'Schema Ownership Policy', 'ai-auto-fixer' ); ?></span>
					</div>
					<div class="aaf-compat-val">
						<span class="aaf-badge aaf-badge-passed">
							<span class="dashicons dashicons-shield"></span>
							<?php esc_html_e( 'Zero Duplicates Guard', 'ai-auto-fixer' ); ?>
						</span>
					</div>
					<p class="aaf-compat-desc"><?php esc_html_e( 'Never injects an entity if already claimed by an active SEO plugin or theme.', 'ai-auto-fixer' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>

