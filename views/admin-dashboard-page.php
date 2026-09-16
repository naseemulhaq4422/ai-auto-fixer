<?php
/**
 * Master Admin Dashboard Page Template (Multi-Tab & Modular Enterprise Layout).
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_score     = isset( $audit_results['seo_health_score'] ) ? (int) $audit_results['seo_health_score'] : ( (int) ( $audit_results['score'] ?? 0 ) );
$safety_score  = isset( $audit_results['safety_score'] ) ? (int) $audit_results['safety_score'] : 90;
$issues        = isset( $audit_results['issues'] ) && is_array( $audit_results['issues'] ) ? $audit_results['issues'] : array();
$passes        = isset( $audit_results['passes'] ) && is_array( $audit_results['passes'] ) ? $audit_results['passes'] : array();
$counts        = isset( $audit_results['counts'] ) ? $audit_results['counts'] : array( 'critical' => 0, 'warning' => 0, 'info' => 0, 'passed' => 0 );
$last_scan     = isset( $audit_results['scan_date'] ) ? $audit_results['scan_date'] : __( 'Never', 'ai-auto-fixer' );
$fix_history   = isset( $fix_history ) && is_array( $fix_history ) ? $fix_history : array();
$settings      = isset( $settings ) && is_array( $settings ) ? $settings : array();
$current_tab   = ! empty( $current_tab ) ? $current_tab : 'overview';
$safe_fixes    = isset( $audit_results['safe_fixes'] ) ? (int) $audit_results['safe_fixes'] : 0;
?>

<div class="wrap ai-auto-fixer-admin-wrap" data-mode="audit">
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
				<p class="aaf-subtitle"><?php esc_html_e( 'Enterprise Website X-Ray, Technical SEO, Indexability, Image SEO, Schema/AEO & Safe Local Remediation', 'ai-auto-fixer' ); ?></p>
			</div>
		</div>

		<div class="aaf-header-actions">
			<!-- Mode Toggle: Audit Mode vs Fix Mode -->
			<div class="aaf-mode-toggle-group">
				<span class="aaf-mode-label"><?php esc_html_e( 'Mode:', 'ai-auto-fixer' ); ?></span>
				<div class="aaf-mode-toggle">
					<button type="button" class="aaf-mode-btn active" data-mode="audit">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Audit Mode', 'ai-auto-fixer' ); ?>
					</button>
					<button type="button" class="aaf-mode-btn" data-mode="fix">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Fix Mode', 'ai-auto-fixer' ); ?>
					</button>
				</div>
			</div>

			<!-- Re-Scan Button -->
			<button id="aaf-trigger-rescan-btn" class="aaf-btn aaf-btn-secondary">
				<span class="dashicons dashicons-update aaf-spin-icon"></span>
				<span class="aaf-btn-text"><?php esc_html_e( 'Run Site Audit', 'ai-auto-fixer' ); ?></span>
			</button>

			<!-- 1-Click Fix All Safe Issues Button -->
			<button id="aaf-fix-all-btn" class="aaf-btn aaf-btn-gradient" <?php echo empty( $issues ) ? 'style="display:none;"' : ''; ?>>
				<span class="dashicons dashicons-shield"></span>
				<span class="aaf-btn-text"><?php esc_html_e( '1-Click Fix All Safe Issues', 'ai-auto-fixer' ); ?></span>
			</button>
		</div>
	</header>

	<!-- Live Scan Progress Bar (Hidden unless crawling) -->
	<div id="aaf-scan-progress-bar-wrap" class="aaf-progress-card" style="display: none;">
		<div class="aaf-progress-info">
			<span id="aaf-progress-status-text"><?php esc_html_e( 'Discovering URLs & crawling website...', 'ai-auto-fixer' ); ?></span>
			<span id="aaf-progress-percent-text">0%</span>
		</div>
		<div class="aaf-progress-track">
			<div id="aaf-progress-bar-fill" class="aaf-progress-fill" style="width: 0%;"></div>
		</div>
	</div>

	<!-- Dual Health & Metric Cards Grid -->
	<section class="aaf-metrics-grid">
		<!-- SEO Health Score Card -->
		<div class="aaf-card aaf-score-card">
			<div class="aaf-score-wrapper">
				<div class="aaf-score-circle aaf-seo-circle" data-score="<?php echo esc_attr( $seo_score ); ?>">
					<svg viewBox="0 0 36 36" class="aaf-circular-chart">
						<path class="aaf-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
						<path class="aaf-circle aaf-circle-seo" stroke-dasharray="<?php echo esc_attr( $seo_score ); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
						<text x="18" y="20.35" class="aaf-score-text"><?php echo esc_html( $seo_score ); ?>%</text>
					</svg>
				</div>
				<div class="aaf-score-details">
					<h3><?php esc_html_e( 'Website SEO Health', 'ai-auto-fixer' ); ?></h3>
					<p class="aaf-meta-text"><?php printf( esc_html__( 'Last Scanned: %s', 'ai-auto-fixer' ), '<span id="aaf-last-scan-date">' . esc_html( $last_scan ) . '</span>' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Website Safety Score Card -->
		<div class="aaf-card aaf-score-card">
			<div class="aaf-score-wrapper">
				<div class="aaf-score-circle aaf-safety-circle" data-score="<?php echo esc_attr( $safety_score ); ?>">
					<svg viewBox="0 0 36 36" class="aaf-circular-chart">
						<path class="aaf-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
						<path class="aaf-circle aaf-circle-safety" stroke-dasharray="<?php echo esc_attr( $safety_score ); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
						<text x="18" y="20.35" class="aaf-score-text"><?php echo esc_html( $safety_score ); ?>%</text>
					</svg>
				</div>
				<div class="aaf-score-details">
					<h3><?php esc_html_e( 'Website Safety Score', 'ai-auto-fixer' ); ?></h3>
					<p class="aaf-meta-text"><?php esc_html_e( 'SSL, Security Headers, Permissions', 'ai-auto-fixer' ); ?></p>
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
				<span class="aaf-stat-label"><?php esc_html_e( 'Warnings', 'ai-auto-fixer' ); ?></span>
			</div>
		</div>
	</section>

	<!-- Master Tab Navigation -->
	<nav class="aaf-tabs-nav">
		<a href="#tab-overview" class="aaf-tab-link <?php echo 'overview' === $current_tab ? 'active' : ''; ?>" data-tab="tab-overview">
			<span class="dashicons dashicons-dashboard"></span>
			<?php esc_html_e( 'Overview & Fixes', 'ai-auto-fixer' ); ?>
			<span class="aaf-tab-count" id="aaf-total-issues-badge"><?php echo count( $issues ); ?></span>
		</a>
		<a href="#tab-indexation" class="aaf-tab-link <?php echo 'indexation' === $current_tab ? 'active' : ''; ?>" data-tab="tab-indexation">
			<span class="dashicons dashicons-admin-site-alt3"></span>
			<?php esc_html_e( 'Indexation Health', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-images" class="aaf-tab-link <?php echo 'images' === $current_tab ? 'active' : ''; ?>" data-tab="tab-images">
			<span class="dashicons dashicons-format-image"></span>
			<?php esc_html_e( 'Image SEO & Smart ALT', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-media" class="aaf-tab-link <?php echo 'media' === $current_tab ? 'active' : ''; ?>" data-tab="tab-media">
			<span class="dashicons dashicons-trash"></span>
			<?php esc_html_e( 'Media Cleanup', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-technical" class="aaf-tab-link <?php echo 'technical' === $current_tab ? 'active' : ''; ?>" data-tab="tab-technical">
			<span class="dashicons dashicons-admin-tools"></span>
			<?php esc_html_e( 'Technical & Links', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-schema" class="aaf-tab-link <?php echo 'schema' === $current_tab ? 'active' : ''; ?>" data-tab="tab-schema">
			<span class="dashicons dashicons-share"></span>
			<?php esc_html_e( 'Schema & AI/GEO', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-woocommerce" class="aaf-tab-link <?php echo 'woocommerce' === $current_tab ? 'active' : ''; ?>" data-tab="tab-woocommerce">
			<span class="dashicons dashicons-cart"></span>
			<?php esc_html_e( 'WooCommerce SEO', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-history" class="aaf-tab-link <?php echo 'history' === $current_tab ? 'active' : ''; ?>" data-tab="tab-history">
			<span class="dashicons dashicons-backup"></span>
			<?php esc_html_e( 'Fix History & Rollbacks', 'ai-auto-fixer' ); ?>
		</a>
		<a href="#tab-settings" class="aaf-tab-link <?php echo 'settings' === $current_tab ? 'active' : ''; ?>" data-tab="tab-settings">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php esc_html_e( 'Settings & Compatibility', 'ai-auto-fixer' ); ?>
		</a>
	</nav>

	<!-- Tab 1: Overview & Findings -->
	<section id="tab-overview" class="aaf-tab-content <?php echo 'overview' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-overview.php'; ?>
	</section>

	<!-- Tab 2: Indexation Health -->
	<section id="tab-indexation" class="aaf-tab-content <?php echo 'indexation' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-indexation.php'; ?>
	</section>

	<!-- Tab 3: Image SEO & Smart ALT -->
	<section id="tab-images" class="aaf-tab-content <?php echo 'images' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-images.php'; ?>
	</section>

	<!-- Tab 4: Unlinked Media Cleanup -->
	<section id="tab-media" class="aaf-tab-content <?php echo 'media' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-media.php'; ?>
	</section>

	<!-- Tab 5: Technical SEO & Links -->
	<section id="tab-technical" class="aaf-tab-content <?php echo 'technical' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-technical.php'; ?>
	</section>

	<!-- Tab 6: Schema & AI/GEO -->
	<section id="tab-schema" class="aaf-tab-content <?php echo 'schema' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-schema.php'; ?>
	</section>

	<!-- Tab 7: WooCommerce SEO -->
	<section id="tab-woocommerce" class="aaf-tab-content <?php echo 'woocommerce' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-woocommerce.php'; ?>
	</section>

	<!-- Tab 8: Fix History & Rollbacks -->
	<section id="tab-history" class="aaf-tab-content <?php echo 'history' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-history.php'; ?>
	</section>

	<!-- Tab 9: Settings & Compatibility -->
	<section id="tab-settings" class="aaf-tab-content <?php echo 'settings' === $current_tab ? 'active' : ''; ?>">
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/tab-settings.php'; ?>
	</section>

	<!-- Modals -->
	<?php include AI_AUTO_FIXER_PATH . 'views/partials/modal-preview.php'; ?>
	<?php include AI_AUTO_FIXER_PATH . 'views/partials/modal-smart-alt.php'; ?>
</div>
