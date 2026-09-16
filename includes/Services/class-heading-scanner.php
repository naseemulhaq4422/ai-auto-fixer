<?php
/**
 * Heading Hierarchy & False-Positive Protected H1 Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Detectors\ConflictDetector;

/**
 * Evaluates semantic heading structures with false-positive protection for page builders and templates.
 */
class HeadingScanner {

	public const STATE_CONFIRMED      = 'CONFIRMED';
	public const STATE_LIKELY_PRESENT = 'LIKELY_PRESENT';
	public const STATE_MISSING        = 'MISSING';
	public const STATE_MULTIPLE       = 'MULTIPLE';
	public const STATE_UNKNOWN        = 'UNKNOWN';

	/**
	 * Analyze semantic heading structure of rendered HTML and associated post.
	 *
	 * @param string $html Rendered HTML markup.
	 * @param int    $post_id Target post ID (optional).
	 * @return array{
	 *     h1_state: string,
	 *     h1_count: int,
	 *     h1_texts: string[],
	 *     issues: array
	 * }
	 */
	public static function audit_headings( string $html, int $post_id = 0 ): array {
		$h1_texts = array();
		$issues   = array();

		// 1. Extract H1 tags from HTML
		if ( preg_match_all( '/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $content ) {
				$clean = trim( strip_tags( html_entity_decode( $content, ENT_QUOTES, 'UTF-8' ) ) );
				if ( ! empty( $clean ) ) {
					$h1_texts[] = $clean;
				}
			}
		}

		$h1_count = count( $h1_texts );

		// 2. Evaluate H1 state machine
		if ( 1 === $h1_count ) {
			$h1_state = self::STATE_CONFIRMED;
		} elseif ( $h1_count > 1 ) {
			$h1_state = self::STATE_MULTIPLE;
			$issues[] = array(
				'issue_type'     => 'multiple_h1',
				'category'       => 'on_page',
				'severity'       => 'warning',
				'message'        => sprintf(
					/* translators: %d: Count of H1 headings */
					__( 'Multiple <h1> headings detected (%d). Pages should ideally maintain a single primary H1.', 'ai-auto-fixer' ),
					$h1_count
				),
				'recommendation' => __( 'Ensure only the main topic headline uses <h1>, and convert secondary headings to <h2>.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		} else {
			// 0 H1 found in raw HTML -> Run False-Positive Protection
			$is_builder = ConflictDetector::has_page_builder();
			$is_woo_product = false;

			if ( $post_id > 0 ) {
				$post_type = get_post_type( $post_id );
				$is_woo_product = ( 'product' === $post_type && ConflictDetector::is_woocommerce_active() );

				// Check Elementor post meta for title widget
				$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
				if ( ! empty( $elementor_data ) && is_string( $elementor_data ) ) {
					if ( strpos( $elementor_data, '"header_size":"h1"' ) !== false || strpos( $elementor_data, 'theme-post-title' ) !== false ) {
						$h1_state = self::STATE_LIKELY_PRESENT;
					}
				}
			}

			if ( ! isset( $h1_state ) ) {
				if ( $is_woo_product ) {
					// WooCommerce single-product.php template outputs H1 via action hooks
					$h1_state = self::STATE_LIKELY_PRESENT;
				} elseif ( $is_builder ) {
					$h1_state = self::STATE_UNKNOWN;
				} else {
					$h1_state = self::STATE_MISSING;
				}
			}

			// Assign appropriate severity based on false-positive protection
			if ( self::STATE_MISSING === $h1_state ) {
				$issues[] = array(
					'issue_type'     => 'missing_h1',
					'category'       => 'on_page',
					'severity'       => 'critical',
					'message'        => __( 'Page is missing a primary <h1> headline.', 'ai-auto-fixer' ),
					'recommendation' => __( 'Add an informative <h1> heading introducing the main topic of this page.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			} elseif ( in_array( $h1_state, array( self::STATE_LIKELY_PRESENT, self::STATE_UNKNOWN ), true ) ) {
				$issues[] = array(
					'issue_type'     => 'h1_needs_review',
					'category'       => 'on_page',
					'severity'       => 'recommendation',
					'message'        => __( 'H1 heading was not detected in static markup, but dynamic builder or WooCommerce template rendering was identified.', 'ai-auto-fixer' ),
					'recommendation' => __( 'Inspect rendered page visually to confirm the template outputs an H1 in the final browser DOM.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		return array(
			'h1_state' => $h1_state,
			'h1_count' => $h1_count,
			'h1_texts' => $h1_texts,
			'issues'   => $issues,
		);
	}
}
