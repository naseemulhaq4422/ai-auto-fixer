<?php
/**
 * Partial: Tab WooCommerce Catalog SEO.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woo_active = class_exists( 'WooCommerce' );
?>

<div class="aaf-woocommerce-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'WooCommerce E-Commerce SEO & Catalog Health', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Audits product title length, missing gallery ALT attributes, missing SKUs, structured pricing data, and product schema markup.', 'ai-auto-fixer' ); ?></p>
	</div>

	<?php if ( ! $woo_active ) : ?>
		<div class="aaf-card aaf-empty-card">
			<div class="aaf-empty-icon"><span class="dashicons dashicons-cart"></span></div>
			<h3><?php esc_html_e( 'WooCommerce Is Not Currently Active', 'ai-auto-fixer' ); ?></h3>
			<p><?php esc_html_e( 'Activate WooCommerce to automatically audit product catalog metadata, product schema, SKUs, and gallery image SEO.', 'ai-auto-fixer' ); ?></p>
		</div>
	<?php else : ?>
		<div class="aaf-card">
			<div class="aaf-card-header">
				<h3><?php esc_html_e( 'Product Catalog SEO Health', 'ai-auto-fixer' ); ?></h3>
				<p><?php esc_html_e( 'Real-time inspection of your online store products.', 'ai-auto-fixer' ); ?></p>
			</div>
			<table class="aaf-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'SKU & Price', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'Image SEO Status', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'Product Schema', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'Action', 'ai-auto-fixer' ); ?></th>
					</tr>
				</thead>
				<tbody id="aaf-woo-tbody">
					<tr>
						<td colspan="5" class="aaf-loading-cell">
							<span class="dashicons dashicons-update aaf-spin-icon"></span>
							<?php esc_html_e( 'Scanning WooCommerce catalog...', 'ai-auto-fixer' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
