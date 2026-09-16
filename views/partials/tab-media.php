<?php
/**
 * Partial: Tab Unlinked Media Cleanup (10-Source Dependency Defense).
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-media-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Unlinked Media Cleanup & 10-Source Dependency Defense', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Inspects media references across 10 database tables including post content, featured images, WooCommerce galleries, Elementor JSON, Gutenberg blocks, and menus. UNKNOWN status items strictly block trashing.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- Media Filter Sub-bar -->
	<div class="aaf-media-filter-bar">
		<button class="aaf-filter-btn active" data-media-filter="all"><?php esc_html_e( 'All Media', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-media-filter="safe_to_review"><?php esc_html_e( 'Safe to Review', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-media-filter="possibly_unused"><?php esc_html_e( 'Possibly Unused', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-media-filter="unknown"><?php esc_html_e( 'Unknown State (Protected)', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-media-filter="in_use"><?php esc_html_e( 'In Use', 'ai-auto-fixer' ); ?></button>
	</div>

	<!-- Media Table -->
	<div class="aaf-card" style="margin-top: 20px;">
		<table class="aaf-table aaf-media-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Asset Preview', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'File / ID', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Safety Status', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( '10-Source Dependency Report', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Action', 'ai-auto-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody id="aaf-media-tbody">
				<tr>
					<td colspan="5" class="aaf-loading-cell">
						<span class="dashicons dashicons-update aaf-spin-icon"></span>
						<?php esc_html_e( 'Scanning 10 database reference sources...', 'ai-auto-fixer' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Pagination Footer -->
		<div class="aaf-table-pagination" id="aaf-media-pagination">
			<span class="aaf-pagination-info" id="aaf-media-pagination-info"><?php esc_html_e( 'Page 1', 'ai-auto-fixer' ); ?></span>
			<div class="aaf-pagination-controls">
				<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary" id="aaf-media-prev-btn" disabled><?php esc_html_e( 'Previous', 'ai-auto-fixer' ); ?></button>
				<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary" id="aaf-media-next-btn"><?php esc_html_e( 'Next', 'ai-auto-fixer' ); ?></button>
			</div>
		</div>
	</div>
</div>
