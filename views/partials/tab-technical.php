<?php
/**
 * Partial: Tab Technical SEO & Broken Links with Parent Sources.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-technical-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Technical SEO & Broken Link Intelligence', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Monitors HTTPS enforcement, redirect chains, canonical loops, and tracks exact parent source pages for all broken links.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- Technical Sub-grid -->
	<div class="aaf-tech-grid">
		<div class="aaf-card aaf-tech-card">
			<div class="aaf-tech-header">
				<span class="dashicons dashicons-lock"></span>
				<h3><?php esc_html_e( 'HTTPS & Mixed Content', 'ai-auto-fixer' ); ?></h3>
			</div>
			<div class="aaf-tech-status">
				<?php if ( is_ssl() ) : ?>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'HTTPS Active', 'ai-auto-fixer' ); ?></span>
					<p><?php esc_html_e( 'Site is serving content securely over SSL/TLS.', 'ai-auto-fixer' ); ?></p>
				<?php else : ?>
					<span class="aaf-badge aaf-badge-critical"><?php esc_html_e( 'Insecure HTTP', 'ai-auto-fixer' ); ?></span>
					<p><?php esc_html_e( 'Site is not forcing HTTPS connections.', 'ai-auto-fixer' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="aaf-card aaf-tech-card">
			<div class="aaf-tech-header">
				<span class="dashicons dashicons-admin-links"></span>
				<h3><?php esc_html_e( 'Permalink Structure', 'ai-auto-fixer' ); ?></h3>
			</div>
			<div class="aaf-tech-status">
				<?php
				$structure = get_option( 'permalink_structure' );
				if ( ! empty( $structure ) ) :
				?>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'SEO Friendly', 'ai-auto-fixer' ); ?></span>
					<p><code><?php echo esc_html( $structure ); ?></code></p>
				<?php else : ?>
					<span class="aaf-badge aaf-badge-warning"><?php esc_html_e( 'Default Plain Permalinks', 'ai-auto-fixer' ); ?></span>
					<p><?php esc_html_e( 'Using query-string URLs (?p=123). Switch to Post name permalinks.', 'ai-auto-fixer' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Broken Links Table -->
	<div class="aaf-card" style="margin-top: 24px;">
		<div class="aaf-card-header">
			<h3><?php esc_html_e( 'Broken Links & Parent Source Attribution', 'ai-auto-fixer' ); ?></h3>
			<p><?php esc_html_e( 'All dead (404/5xx) internal and external links discovered with their parent referring page.', 'ai-auto-fixer' ); ?></p>
		</div>
		<table class="aaf-table aaf-links-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Broken URL', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'HTTP Status', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Anchor Text', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Discovered On (Parent Page)', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Policy', 'ai-auto-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody id="aaf-links-tbody">
				<tr>
					<td colspan="5" class="aaf-loading-cell">
						<span class="dashicons dashicons-update aaf-spin-icon"></span>
						<?php esc_html_e( 'Auditing links...', 'ai-auto-fixer' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
