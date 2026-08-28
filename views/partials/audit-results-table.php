<?php
/**
 * Partial: Audit Results Table & Issue Cards.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-audit-section">
	<!-- Filter Controls -->
	<div class="aaf-filter-bar">
		<div class="aaf-filter-buttons">
			<button class="aaf-filter-btn active" data-filter="all">
				<?php esc_html_e( 'All Issues', 'ai-auto-fixer' ); ?>
				<span class="aaf-badge"><?php echo count( $issues ); ?></span>
			</button>
			<button class="aaf-filter-btn" data-filter="critical">
				<?php esc_html_e( 'Critical', 'ai-auto-fixer' ); ?>
				<span class="aaf-badge aaf-badge-critical"><?php echo esc_html( $counts['critical'] ?? 0 ); ?></span>
			</button>
			<button class="aaf-filter-btn" data-filter="warning">
				<?php esc_html_e( 'Warnings', 'ai-auto-fixer' ); ?>
				<span class="aaf-badge aaf-badge-warning"><?php echo esc_html( $counts['warning'] ?? 0 ); ?></span>
			</button>
			<button class="aaf-filter-btn" data-filter="passed">
				<?php esc_html_e( 'Passed', 'ai-auto-fixer' ); ?>
				<span class="aaf-badge aaf-badge-passed"><?php echo esc_html( $counts['passed'] ?? 0 ); ?></span>
			</button>
		</div>
	</div>

	<!-- Issues Container -->
	<div class="aaf-issues-list" id="aaf-issues-container">
		<?php if ( empty( $issues ) ) : ?>
			<div class="aaf-card aaf-empty-card">
				<div class="aaf-empty-icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<h3><?php esc_html_e( 'No Critical SEO or AI Crawler Issues Found!', 'ai-auto-fixer' ); ?></h3>
				<p><?php esc_html_e( 'Your site satisfies the baseline criteria for search engine discovery and GEO indexing.', 'ai-auto-fixer' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $issues as $issue ) : ?>
				<?php
				$severity_class = 'aaf-issue-' . esc_attr( $issue['severity'] ?? 'info' );
				$category_label = ucfirst( str_replace( '_', ' ', $issue['category'] ?? 'General' ) );
				$auto_fixable   = ! empty( $issue['auto_fixable'] );
				$fix_action     = $issue['fix_action'] ?? '';
				?>
				<div class="aaf-card aaf-issue-card <?php echo esc_attr( $severity_class ); ?>" data-severity="<?php echo esc_attr( $issue['severity'] ?? 'info' ); ?>">
					<div class="aaf-issue-header">
						<div class="aaf-issue-badges">
							<span class="aaf-severity-badge aaf-severity-<?php echo esc_attr( $issue['severity'] ?? 'info' ); ?>">
								<?php echo esc_html( strtoupper( $issue['severity'] ?? 'INFO' ) ); ?>
							</span>
							<span class="aaf-category-badge"><?php echo esc_html( $category_label ); ?></span>
						</div>
						<h3 class="aaf-issue-title"><?php echo esc_html( $issue['title'] ?? '' ); ?></h3>
					</div>

					<div class="aaf-issue-body">
						<p class="aaf-issue-desc"><?php echo esc_html( $issue['description'] ?? '' ); ?></p>

						<?php if ( ! empty( $issue['recommendation'] ) ) : ?>
							<div class="aaf-manual-fix-box">
								<strong><?php esc_html_e( 'Manual Fix:', 'ai-auto-fixer' ); ?></strong>
								<span><?php echo esc_html( $issue['recommendation'] ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<div class="aaf-issue-footer">
						<?php if ( $auto_fixable ) : ?>
							<?php if ( $is_pro ) : ?>
								<button class="aaf-btn aaf-btn-primary aaf-execute-autofix-btn" data-action="<?php echo esc_attr( $fix_action ); ?>">
									<span class="dashicons dashicons-admin-tools"></span>
									<span class="aaf-btn-label"><?php esc_html_e( '1-Click Auto-Fix', 'ai-auto-fixer' ); ?></span>
								</button>
							<?php else : ?>
								<button class="aaf-btn aaf-btn-pro-locked aaf-open-upgrade-modal-btn" data-action="<?php echo esc_attr( $fix_action ); ?>">
									<span class="dashicons dashicons-lock"></span>
									<span class="aaf-btn-label"><?php esc_html_e( 'Upgrade to Auto-Fix', 'ai-auto-fixer' ); ?></span>
									<span class="aaf-pill-pro-small">PRO</span>
								</button>
							<?php endif; ?>
						<?php else : ?>
							<span class="aaf-manual-only-tag"><?php esc_html_e( 'Requires Manual Configuration', 'ai-auto-fixer' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- Passed Checks Section -->
		<div class="aaf-passed-checks-wrapper" id="aaf-passed-container">
			<h3 class="aaf-section-subtitle">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Passing Audit Signals', 'ai-auto-fixer' ); ?>
			</h3>
			<div class="aaf-passed-grid">
				<?php foreach ( $passes as $pass ) : ?>
					<div class="aaf-passed-item">
						<span class="dashicons dashicons-yes"></span>
						<span><?php echo esc_html( $pass['title'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
