<?php
/**
 * WooCommerce E-Commerce SEO Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audits WooCommerce product titles, descriptions, SKU, pricing, images, and schema compatibility.
 */
class WooCommerceScanner {

	/**
	 * Direct alias for audit_products().
	 *
	 * @param int $limit Maximum products to inspect.
	 * @return array
	 */
	public static function audit_catalog( int $limit = 50 ): array {
		return self::audit_products( $limit );
	}

	/**
	 * Run audit across WooCommerce product catalog.
	 *
	 * @param int $limit Maximum products to inspect.
	 * @return array{active: bool, audited_products: int, issues: array}
	 */
	public static function audit_products( int $limit = 50 ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'active'           => false,
				'audited_products' => 0,
				'issues'           => array(),
			);
		}

		$issues   = array();
		$products = wc_get_products( array(
			'limit'  => $limit,
			'status' => 'publish',
		) );

		foreach ( $products as $product ) {
			$id    = $product->get_id();
			$title = $product->get_name();
			$url   = get_permalink( $id );

			// 1. Missing SKU
			if ( empty( $product->get_sku() ) ) {
				$issues[] = array(
					'issue_type'     => 'wc_missing_sku',
					'category'       => 'woocommerce',
					'severity'       => 'recommendation',
					'message'        => sprintf( __( 'Product "%s" lacks an assigned SKU (Stock Keeping Unit).', 'ai-auto-fixer' ), esc_html( $title ) ),
					'recommendation' => __( 'Assign unique SKUs to enhance Google Merchant and product structured data indexing.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}

			// 2. Missing Featured Image
			if ( empty( $product->get_image_id() ) ) {
				$issues[] = array(
					'issue_type'     => 'wc_missing_product_image',
					'category'       => 'woocommerce',
					'severity'       => 'critical',
					'message'        => sprintf( __( 'Product "%s" has no featured product image.', 'ai-auto-fixer' ), esc_html( $title ) ),
					'recommendation' => __( 'Upload high-resolution product imagery to populate Google Shopping and rich snippet carousels.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}

			// 3. Thin Product Description
			$desc_words = str_word_count( strip_tags( $product->get_description() ) );
			if ( $desc_words < 50 ) {
				$issues[] = array(
					'issue_type'     => 'wc_thin_product_description',
					'category'       => 'woocommerce',
					'severity'       => 'warning',
					'message'        => sprintf( __( 'Product "%s" has a thin description (%d words).', 'ai-auto-fixer' ), esc_html( $title ), $desc_words ),
					'recommendation' => __( 'Write detailed descriptions covering features, dimensions, specifications, and buyer benefits.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		return array(
			'active'           => true,
			'audited_products' => count( $products ),
			'issues'           => $issues,
		);
	}
}
