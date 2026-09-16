<?php
/**
 * Partial: Tab Image SEO & Smart ALT.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-images-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Image SEO & Smart Contextual ALT Engine', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Audits image accessibility, filename quality, dimensions, and generates contextual ALT text with confidence scoring. Physical file renaming is kept for manual review.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- Image Filter Sub-bar -->
	<div class="aaf-image-filter-bar">
		<button class="aaf-filter-btn active" data-img-filter="all"><?php esc_html_e( 'All Images', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-img-filter="missing_alt"><?php esc_html_e( 'Missing ALT', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-img-filter="generic_alt"><?php esc_html_e( 'Generic ALT', 'ai-auto-fixer' ); ?></button>
		<button class="aaf-filter-btn" data-img-filter="missing_dimensions"><?php esc_html_e( 'Missing Dimensions', 'ai-auto-fixer' ); ?></button>
	</div>

	<!-- Image SEO Table -->
	<div class="aaf-card" style="margin-top: 20px;">
		<table class="aaf-table aaf-images-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Image Preview', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Filename / Source', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Current ALT', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Audit Issue', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ai-auto-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody id="aaf-images-tbody">
				<tr>
					<td colspan="5" class="aaf-loading-cell">
						<span class="dashicons dashicons-update aaf-spin-icon"></span>
						<?php esc_html_e( 'Auditing media images...', 'ai-auto-fixer' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Pagination Footer -->
		<div class="aaf-table-pagination" id="aaf-images-pagination">
			<span class="aaf-pagination-info" id="aaf-img-pagination-info"><?php esc_html_e( 'Page 1', 'ai-auto-fixer' ); ?></span>
			<div class="aaf-pagination-controls">
				<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary" id="aaf-img-prev-btn" disabled><?php esc_html_e( 'Previous', 'ai-auto-fixer' ); ?></button>
				<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary" id="aaf-img-next-btn"><?php esc_html_e( 'Next', 'ai-auto-fixer' ); ?></button>
			</div>
		</div>
	</div>
</div>
