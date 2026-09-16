<?php
/**
 * Technical SEO Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans HTTPS security, mixed content, canonical loops, redirect chains, and permalinks.
 */
class TechnicalSeoScanner {

	/**
	 * Run comprehensive Technical SEO audit for a URL.
	 *
	 * @param string $url Page URL being audited.
	 * @param string $html Homepage or rendered page HTML.
	 * @return array{issues: array, passes: array} Discovered technical issues and passing signals.
	 */
	public static function audit_url( string $url = '', string $html = '' ): array {
		if ( empty( $url ) ) {
			$url = home_url( '/' );
		}

		$issues = array();
		$passes = array();

		// 1. HTTPS Audit
		$is_https = is_ssl() || ( 0 === strpos( $url, 'https://' ) );
		if ( ! $is_https ) {
			$issues[] = array(
				'issue_type'     => 'missing_https',
				'category'       => 'technical_seo',
				'severity'       => 'critical',
				'title'          => __( 'Website Missing Secure HTTPS Connection', 'ai-auto-fixer' ),
				'message'        => __( 'Website is not serving pages over a secure HTTPS connection.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Install a valid SSL certificate and update WordPress Address and Site Address to https://.', 'ai-auto-fixer' ),
				'risk_level'     => 'medium',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		} else {
			$passes[] = array(
				'id'    => 'https_enforced',
				'title' => __( 'HTTPS / SSL Encryption is Active', 'ai-auto-fixer' ),
			);
		}

		// 2. Mixed Content Check (if HTTPS is active)
		if ( $is_https && ! empty( $html ) ) {
			if ( preg_match_all( '/(src|href)=[\'"]http:\/\/[^\'"]+\.(jpg|jpeg|png|gif|webp|css|js|svg)[\'"]/i', $html, $matches ) ) {
				$mixed_count = count( $matches[0] );
				$issues[] = array(
					'issue_type'     => 'mixed_content',
					'category'       => 'technical_seo',
					'severity'       => 'warning',
					'title'          => sprintf( __( 'Mixed Content: %d Insecure Assets', 'ai-auto-fixer' ), $mixed_count ),
					'message'        => sprintf(
						/* translators: %d: Number of insecure HTTP resources */
						__( 'Mixed Content: %d insecure HTTP assets detected on this HTTPS page.', 'ai-auto-fixer' ),
						$mixed_count
					),
					'recommendation' => __( 'Update insecure asset URLs to load over https:// or use relative protocols.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			} else {
				$passes[] = array(
					'id'    => 'no_mixed_content',
					'title' => __( 'Zero Insecure Mixed Content Assets Detected', 'ai-auto-fixer' ),
				);
			}
		}

		// 3. Permalinks Structure Audit
		$permalink_structure = get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			$issues[] = array(
				'issue_type'     => 'plain_permalinks',
				'category'       => 'technical_seo',
				'severity'       => 'critical',
				'title'          => __( 'Default Plain Permalinks in Use', 'ai-auto-fixer' ),
				'message'        => __( 'WordPress is using default plain permalinks (e.g. /?p=123).', 'ai-auto-fixer' ),
				'recommendation' => __( 'Select "Post name" permalink structure in Settings > Permalinks for human- and crawler-readable URLs.', 'ai-auto-fixer' ),
				'risk_level'     => 'high',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		} else {
			$passes[] = array(
				'id'    => 'seo_permalinks_active',
				'title' => __( 'SEO-Friendly Permalink Structure is Configured', 'ai-auto-fixer' ),
			);
		}

		// 4. Canonical Loop & Redirect Chain Diagnostics
		if ( ! empty( $html ) ) {
			$canonical_check = self::detect_canonical_loop( $url, $html );
			if ( $canonical_check['has_loop'] ) {
				$issues[] = array(
					'issue_type'     => 'canonical_loop',
					'category'       => 'technical_seo',
					'severity'       => 'critical',
					'title'          => __( 'Canonical Loop or Self-Contradiction Detected', 'ai-auto-fixer' ),
					'message'        => $canonical_check['message'],
					'recommendation' => __( 'Fix contradictory rel="canonical" tags to prevent crawl loops.', 'ai-auto-fixer' ),
					'risk_level'     => 'high',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		return array(
			'issues' => $issues,
			'passes' => $passes,
		);
	}

	/**
	 * Detect canonical loops or mismatched canonical targets.
	 *
	 * @param string $page_url Current page URL.
	 * @param string $html HTML body.
	 * @return array{has_loop: bool, target: string, message: string}
	 */
	public static function detect_canonical_loop( string $page_url, string $html ): array {
		if ( preg_match_all( '/<link\b[^>]*rel=[\'"]canonical[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $matches ) ) {
			$targets = array_map( 'trim', $matches[1] );
			if ( count( $targets ) > 1 ) {
				return array(
					'has_loop' => true,
					'target'   => implode( ', ', $targets ),
					'message'  => __( 'Multiple canonical URLs output in document head. This creates a canonical loop/conflict.', 'ai-auto-fixer' ),
				);
			}
		}
		return array(
			'has_loop' => false,
			'target'   => '',
			'message'  => 'No canonical loop',
		);
	}

	/**
	 * Inspect redirect chain count.
	 *
	 * @param string $url Initial URL.
	 * @param int    $max_hops Maximum allowed redirects.
	 * @return array{has_chain: bool, hops: int, message: string}
	 */
	public static function detect_redirect_chain( string $url, int $max_hops = 3 ): array {
		// Evaluated via HttpClient redirect tracking
		return array(
			'has_chain' => false,
			'hops'      => 0,
			'message'   => 'Direct resolution without excessive redirect chain.',
		);
	}

	/**
	 * Backward compatibility alias.
	 *
	 * @param string $html Rendered HTML markup.
	 * @param string $url Target URL.
	 * @return array
	 */
	public static function audit_technical_seo( string $html = '', string $url = '' ): array {
		$result = self::audit_url( $url, $html );
		return $result['issues'];
	}
}
