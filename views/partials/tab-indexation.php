<?php
/**
 * Partial: Tab Indexation Health & Deep Diagnostics.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-indexation-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Indexation Health & Deep Diagnostics', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Comprehensive 8-stage indexability analysis distinguishing local technical indexability from live Google Search Console status.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- Indexation Filter Sub-bar -->
	<div class="aaf-index-filter-bar">
		<button class="aaf-filter-btn active" data-index-filter="all"><?php esc_html_e( 'All URLs', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-index-filter="indexable"><?php esc_html_e( 'Indexable', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-index-filter="noindex"><?php esc_html_e( 'Noindex', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-index-filter="robots_blocked"><?php esc_html_e( 'Robots Blocked', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-index-filter="canonicalized"><?php esc_html_e( 'Canonicalized', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-index-filter="orphan"><?php esc_html_e( 'Orphan Pages', 'ai-auto-fixer' ); ?></button>
	</div>

	<!-- Indexation Table -->
	<div class="aaf-card" style="margin-top: 20px;">
		<table class="aaf-table aaf-indexation-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Technical Status', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Origin / Source', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Root Cause & Diagnostic Breakdown', 'ai-auto-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody id="aaf-indexation-tbody">
				<tr>
					<td colspan="4" class="aaf-loading-cell">
						<span class="dashicons dashicons-update aaf-spin-icon"></span>
						<?php esc_html_e( 'Loading indexability diagnostics...', 'ai-auto-fixer' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Pagination Footer -->
		<div class="aaf-table-pagination" id="aaf-indexation-pagination">
			<span class="aaf-pagination-info" id="aaf-index-pagination-info"><?php esc_html_e( 'Page 1', 'ai-auto-fixer' ); ?></span>
			<div class="aaf-pagination-controls">
				<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary" id="aaf-index-prev-btn" disabled><?php esc_html_e( 'Previous', 'ai-auto-fixer' ); ?></button>
				<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary" id="aaf-index-next-btn"><?php esc_html_e( 'Next', 'ai-auto-fixer' ); ?></button>
			</div>
		</div>
	</div>
</div>
