<?php
/**
 * Broken Links & Internal Navigation Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts links, tests accessibility, and reports broken internal/external references with parent sources.
 */
class LinkScanner {

	/**
	 * Extract and audit links discovered inside HTML content.
	 *
	 * @param string $html Content or page markup.
	 * @param string $source_url URL of the page containing the links.
	 * @param int    $max_links_to_test Maximum links to verify over HTTP in a single pass.
	 * @return array{
	 *     total_links: int,
	 *     internal_links: int,
	 *     external_links: int,
	 *     broken_links: array,
	 *     issues: array
	 * }
	 */
	public static function audit_links( string $html, string $source_url = '', int $max_links_to_test = 20 ): array {
		$broken_links   = array();
		$issues         = array();
		$internal_count = 0;
		$external_count = 0;

		$home_domain = wp_parse_url( home_url(), PHP_URL_HOST );

		// Extract all anchor links
		if ( preg_match_all( '/<a\b[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER ) ) {
			$tested = 0;

			foreach ( $matches as $match ) {
				$raw_url = trim( $match[1] );
				$anchor  = trim( strip_tags( $match[2] ) );

				// Skip anchors, javascript, mailto, tel
				if ( empty( $raw_url ) || 0 === strpos( $raw_url, '#' ) || 0 === strpos( $raw_url, 'javascript:' ) || 0 === strpos( $raw_url, 'mailto:' ) || 0 === strpos( $raw_url, 'tel:' ) ) {
					continue;
				}

				// Normalize relative URLs to absolute
				$absolute_url = ( 0 === strpos( $raw_url, '/' ) ) ? home_url( $raw_url ) : $raw_url;
				$link_domain  = wp_parse_url( $absolute_url, PHP_URL_HOST );

				$is_internal = empty( $link_domain ) || ( $link_domain === $home_domain );

				if ( $is_internal ) {
					$internal_count++;
				} else {
					$external_count++;
				}

				// Only verify HTTP on valid web URLs up to limit per page
				if ( $tested < $max_links_to_test && ( 0 === strpos( $absolute_url, 'http://' ) || 0 === strpos( $absolute_url, 'https://' ) ) ) {
					$tested++;
					$status = self::test_link( $absolute_url );

					if ( $status['code'] >= 400 || $status['code'] === 0 ) {
						$broken_links[] = array(
							'target_url' => $absolute_url,
							'source_url' => $source_url ?: home_url( '/' ),
							'anchor'     => $anchor ?: __( '(No text)', 'ai-auto-fixer' ),
							'type'       => $is_internal ? 'internal' : 'external',
							'http_code'  => $status['code'],
							'error'      => $status['error'],
						);
					}
				}
			}
		}

		if ( ! empty( $broken_links ) ) {
			foreach ( $broken_links as $broken ) {
				$issues[] = array(
					'issue_type'     => 'broken_link',
					'category'       => 'links',
					'severity'       => 'critical',
					'message'        => sprintf(
						/* translators: 1: Target broken URL, 2: HTTP status code, 3: Parent source URL */
						__( 'Broken %4$s link detected: %1$s (HTTP %2$d) on page %3$s.', 'ai-auto-fixer' ),
						esc_url( $broken['target_url'] ),
						$broken['http_code'],
						esc_url( $broken['source_url'] ),
						$broken['type']
					),
					'recommendation' => __( 'Update or remove the broken hyperlink to preserve crawl efficiency and user trust.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		return array(
			'total_links'    => $internal_count + $external_count,
			'internal_links' => $internal_count,
			'external_links' => $external_count,
			'broken_links'   => $broken_links,
			'issues'         => $issues,
		);
	}

	/**
	 * Perform a safe HEAD request to verify link response.
	 *
	 * @param string $url URL to test.
	 * @return array{code: int, error: string}
	 */
	public static function test_link( string $url ): array {
		$response = HttpClient::safe_head( $url, array( 'timeout' => 3 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'code'  => 0,
				'error' => $response->get_error_message(),
			);
		}

		return array(
			'code'  => (int) wp_remote_retrieve_response_code( $response ),
			'error' => '',
		);
	}
}
