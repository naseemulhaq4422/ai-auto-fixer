<?php
/**
 * Non-Destructive Performance Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates core frontend performance signals (dimensions, lazy loading, resource weights)
 * while strictly forbidding hazardous automated script/CSS minification or concatenation.
 */
class PerformanceScanner {

	/**
	 * Run non-destructive performance audit on rendered page HTML.
	 *
	 * @param string $html Rendered HTML.
	 * @param string $url Target URL.
	 * @return array List of performance issues found.
	 */
	public static function audit_performance( string $html, string $url = '' ): array {
		$issues = array();

		if ( empty( $html ) ) {
			return $issues;
		}

		// 1. Native Lazy Loading Audit on Images
		if ( preg_match_all( '/<img\b([^>]*)>/is', $html, $matches ) ) {
			$missing_lazy = 0;
			$total_imgs   = count( $matches[1] );

			foreach ( $matches[1] as $attrs ) {
				if ( false === stripos( $attrs, 'loading=' ) ) {
					$missing_lazy++;
				}
			}

			if ( $missing_lazy > 2 && $total_imgs > 3 ) {
				$issues[] = array(
					'issue_type'     => 'missing_lazy_loading',
					'category'       => 'performance',
					'severity'       => 'recommendation',
					'message'        => sprintf(
						/* translators: 1: Count of un-lazied images, 2: Total images */
						__( '%1$d of %2$d images lack native loading="lazy" attributes.', 'ai-auto-fixer' ),
						$missing_lazy,
						$total_imgs
					),
					'recommendation' => __( 'Ensure below-the-fold images leverage native loading="lazy" to reduce initial page load bandwidth.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		// 2. Count External CSS and JS Assets
		$css_count = preg_match_all( '/<link\b[^>]*rel=[\'"]stylesheet[\'"][^>]*>/i', $html );
		$js_count  = preg_match_all( '/<script\b[^>]*src=[\'"][^\'"]+[\'"][^>]*>/i', $html );

		if ( ( $css_count + $js_count ) > 35 ) {
			$issues[] = array(
				'issue_type'     => 'high_http_requests',
				'category'       => 'performance',
				'severity'       => 'warning',
				'message'        => sprintf(
					/* translators: 1: CSS count, 2: JS count */
					__( 'High asset count: Page requests %1$d stylesheets and %2$d external scripts.', 'ai-auto-fixer' ),
					$css_count,
					$js_count
				),
				'recommendation' => __( 'Manual Review Required: Audit active plugins to eliminate redundant assets. Do not use automated minification that breaks layout.', 'ai-auto-fixer' ),
				'risk_level'     => 'medium',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		// 3. Document Size Check
		$html_bytes = strlen( $html );
		if ( $html_bytes > 200 * 1024 ) { // > 200KB
			$issues[] = array(
				'issue_type'     => 'heavy_dom_size',
				'category'       => 'performance',
				'severity'       => 'warning',
				'message'        => sprintf(
					/* translators: %d: HTML size in KB */
					__( 'Large HTML document payload (%d KB). Heavy DOM trees degrade mobile parsing performance.', 'ai-auto-fixer' ),
					round( $html_bytes / 1024 )
				),
				'recommendation' => __( 'Simplify complex nested container hierarchies in page builder templates.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		return $issues;
	}
}
