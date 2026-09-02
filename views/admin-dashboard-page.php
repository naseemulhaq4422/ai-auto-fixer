<?php
/**
 * Admin Dashboard Page Template (100% Free & Standalone).
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$score         = isset( $audit_results['score'] ) ? (int) $audit_results['score'] : 0;
$issues        = isset( $audit_results['issues'] ) && is_array( $audit_results['issues'] ) ? $audit_results['issues'] : array();
$passes        = isset( $audit_results['passes'] ) && is_array( $audit_results['passes'] ) ? $audit_results['passes'] : array();
$counts        = isset( $audit_results['counts'] ) ? $audit_results['counts'] : array( 'critical' => 0, 'warning' => 0, 'info' => 0, 'passed' => 0 );
$last_scan     = isset( $audit_results['scan_date'] ) ? $audit_results['scan_date'] : __( 'Never', 'ai-auto-fixer' );
$fix_history   = isset( $fix_history ) && is_array( $fix_history ) ? $fix_history : array();
$settings      = isset( $settings ) && is_array( $settings ) ? $settings : array();
?>

<div class="wrap ai-auto-fixer-admin-wrap">
	<!-- Top App Header -->
	<header class="aaf-header">
		<div class="aaf-brand-group">
			<div class="aaf-brand-icon">
				<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
					<circle cx="12" cy="12" r="4"/>
					<path d="m15 9-6 6"/>
				</svg>
			</div>
			<div>
				<h1 class="aaf-title">
					<?php esc_html_e( 'AI Auto-Fixer', 'ai-auto-fixer' ); ?>
					<span class="aaf-version-badge">v<?php echo esc_html( AI_AUTO_FIXER_VERSION ); ?></span>
					<span class="aaf-free-badge"><?php esc_html_e( '100% Free & Standalone', 'ai-auto-fixer' ); ?></span>
				</h1>
				<p class="aaf-subtitle"><?php esc_html_e( 'Free SEO, Generative Engine (GEO) & Answer Engine (AEO) Auditor with 1-Click Automated Fixes', 'ai-auto-fixer' ); ?></p>
			</div>
		</div>

		<div class="aaf-header-actions">
			<!-- Re-Scan Button -->
			<button id="aaf-trigger-rescan-btn" class="aaf-btn aaf-btn-secondary">
				<span class="dashicons dashicons-update aaf-spin-icon"></span>
				<span class="aaf-btn-text"><?php esc_html_e( 'Run Site Audit', 'ai-auto-fixer' ); ?></span>
			</button>

			<!-- 1-Click Fix All Button -->
			<?php if ( ! empty( $issues ) ) : ?>
				<button id="aaf-fix-all-btn" class="aaf-btn aaf-btn-gradient">
					<span class="dashicons dashicons-superhero"></span>
					<span class="aaf-btn-text"><?php esc_html_e( '1-Click Fix All Issues', 'ai-auto-fixer' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</header>

	<!-- Metric Cards Grid -->
	<section class="aaf-metrics-grid">
		<!-- Site Health Score Card -->
		<div class="aaf-card aaf-score-card">
			<div class="aaf-score-wrapper">
				<div class="aaf-score-circle" data-score="<?php echo esc_attr( $score ); ?>">
					<svg viewBox="0 0 36 36" class="aaf-circular-chart">
						<path class="aaf-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
						<path class="aaf-circle" stroke-dasharray="<?php echo esc_attr( $score ); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
						<text x="18" y="20.35" class="aaf-score-text"><?php echo esc_html( $score ); ?>%</text>
					</svg>
				</div>
				<div class="aaf-score-details">
					<h3><?php esc_html_e( 'Site Health & GEO Score', 'ai-auto-fixer' ); ?></h3>
					<p class="aaf-meta-text"><?php printf( esc_html__( 'Last Scanned: %s', 'ai-auto-fixer' ), '<span id="aaf-last-scan-date">' . esc_html( $last_scan ) . '</span>' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Critical Errors Count -->
		<div class="aaf-card aaf-stat-card aaf-stat-critical">
			<div class="aaf-stat-icon"><span class="dashicons dashicons-dismiss"></span></div>
			<div class="aaf-stat-content">
				<span class="aaf-stat-number" id="aaf-count-critical"><?php echo esc_html( $counts['critical'] ?? 0 ); ?></span>
				<span class="aaf-stat-label"><?php esc_html_e( 'Critical Errors', 'ai-auto-fixer' ); ?></span>
			</div>
		</div>

		<!-- Warnings Count -->
		<div class="aaf-card aaf-stat-card aaf-stat-warning">
			<div class="aaf-stat-icon"><span class="dashicons dashicons-warning"></span></div>
			<div class="aaf-stat-content">
				<span class="aaf-stat-number" id="aaf-count-warning"><?php echo esc_html( $counts['warning'] ?? 0 ); ?></span>
				<span class="aaf-stat-label"><?php esc_html_e( 'Audit Warnings', 'ai-auto-fixer' ); ?></span>
			</div>
		</div>

		<!-- Passed Checks Count -->
		<div class="aaf-card aaf-stat-card aaf-stat-passed">
			<div class="aaf-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
			<div class="aaf-stat-content">
				<span class="aaf-stat-number" id="aaf-count-passed"><?php echo esc_html( $counts['passed'] ?? 0 ); ?></span>
				<span class="aaf-stat-label"><?php esc_html_e( 'Passed Signals', 'ai-auto-fixer' ); ?></span>
			</div>
		</div>
	</section>

	<!-- Tab Navigation -->
	<nav class="aaf-tabs-nav">
		<a href="#tab-audit" class="aaf-tab-link active" data-tab="tab-audit">
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Audit Findings & Auto-Fixes', 'ai-auto-fixer' ); ?>
			<span class="aaf-tab-count" id="aaf-total-issues-badge"><?php echo count( $issues ); ?></span>
		</a>
		<a href="#tab-recommendations" class="aaf-tab-link" data-tab="tab-recommendations">
			<span class="dashicons dashicons-lightbulb"></span>
			<?php esc_html_e( 'AI & GEO Optimization Blueprint', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-history" class="aaf-tab-link" data-tab="tab-history">
			<span class="dashicons dashicons-backup"></span>
			<?php esc_html_e( 'Fix Execution Log', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-settings" class="aaf-tab-link" data-tab="tab-settings">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php esc_html_e( 'Active Optimizations', 'ai-auto-fixer' ); ?>
		</a>
	</nav>

	<!-- Tab 1: Audit Findings -->
	<section id="tab-audit" class="aaf-tab-content active">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/audit-results-table.php'; ?>
	</section>

	<!-- Tab 2: AI & GEO Recommendations -->
	<section id="tab-recommendations" class="aaf-tab-content">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/recommendations-card.php'; ?>
	</section>

	<!-- Tab 3: Fix History -->
	<section id="tab-history" class="aaf-tab-content">
		<div class="aaf-card">
			<div class="aaf-card-header">
				<h2><?php esc_html_e( 'Automated Fix Execution Log', 'ai-auto-fixer' ); ?></h2>
				<p><?php esc_html_e( 'Record of all automated fixes applied directly to this WordPress website.', 'ai-auto-fixer' ); ?></p>
			</div>
			<div class="aaf-card-body">
				<?php if ( empty( $fix_history ) ) : ?>
					<div class="aaf-empty-state">
						<span class="dashicons dashicons-clock"></span>
						<p><?php esc_html_e( 'No automated fixes have been executed yet.', 'ai-auto-fixer' ); ?></p>
					</div>
				<?php else : ?>
					<table class="aaf-history-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date & Time', 'ai-auto-fixer' ); ?></th>
								<th><?php esc_html_e( 'Action', 'ai-auto-fixer' ); ?></th>
								<th><?php esc_html_e( 'Result Details', 'ai-auto-fixer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $fix_history as $item ) : ?>
								<tr>
									<td><code><?php echo esc_html( $item['date'] ?? '' ); ?></code></td>
									<td><strong><?php echo esc_html( $item['action'] ?? '' ); ?></strong></td>
									<td><?php echo esc_html( $item['message'] ?? '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- Tab 4: Active Optimizations / Settings -->
	<section id="tab-settings" class="aaf-tab-content">
		<div class="aaf-card aaf-settings-card">
			<div class="aaf-card-header">
				<h2><?php esc_html_e( 'Active AI & SEO Optimizations', 'ai-auto-fixer' ); ?></h2>
				<p><?php esc_html_e( 'Manage continuous automatic frontend injections for AI search crawlers and structured data.', 'ai-auto-fixer' ); ?></p>
			</div>
			<div class="aaf-card-body">
				<form id="aaf-settings-form" method="post" action="">
					<?php wp_nonce_field( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' ); ?>

					<div class="aaf-toggle-option">
						<label>
							<input type="checkbox" name="enable_ai_robots" value="1" <?php checked( ! empty( $settings['enable_ai_robots'] ) ); ?>>
							<strong><?php esc_html_e( 'Enable AI Search Crawlers in robots.txt (GEO)', 'ai-auto-fixer' ); ?></strong>
						</label>
						<p class="description"><?php esc_html_e( 'Automatically allows ChatGPT (GPTBot), Claude (ClaudeBot), Perplexity, and CCBot to index public content.', 'ai-auto-fixer' ); ?></p>
					</div>

					<div class="aaf-toggle-option">
						<label>
							<input type="checkbox" name="enable_geo_schema" value="1" <?php checked( ! empty( $settings['enable_geo_schema'] ) ); ?>>
							<strong><?php esc_html_e( 'Inject Schema.org JSON-LD Entity Graph (AEO)', 'ai-auto-fixer' ); ?></strong>
						</label>
						<p class="description"><?php esc_html_e( 'Outputs structured Organization, WebSite, and SearchAction JSON-LD in the HTML head.', 'ai-auto-fixer' ); ?></p>
					</div>

					<div class="aaf-toggle-option">
						<label>
							<input type="checkbox" name="enable_opengraph" value="1" <?php checked( ! empty( $settings['enable_opengraph'] ) ); ?>>
							<strong><?php esc_html_e( 'Enable OpenGraph Social & AI Summary Meta Tags', 'ai-auto-fixer' ); ?></strong>
						</label>
						<p class="description"><?php esc_html_e( 'Generates OpenGraph titles, descriptions, and URL meta tags for accurate entity previewing.', 'ai-auto-fixer' ); ?></p>
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
	</section>
</div>
