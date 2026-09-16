<?php
/**
 * Multi-Source Sitemap Discovery & Audit Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Detectors\SeoPluginDetector;

/**
 * Discovers, parses, and validates XML sitemaps across WordPress Core and third-party SEO plugins.
 */
class SitemapScanner {

	/**
	 * Common sitemap endpoints used by WordPress core and major SEO plugins.
	 */
	public const CANDIDATE_SITEMAPS = array(
		'wp_core'   => '/wp-sitemap.xml',
		'yoast'     => '/sitemap_index.xml',
		'rank_math' => '/sitemap_index.xml',
		'aioseo'    => '/sitemap.xml',
		'seopress'  => '/sitemaps.xml',
	);

	/**
	 * Run comprehensive multi-source sitemap discovery and health audit.
	 *
	 * @return array{
	 *     primary_sitemap: string|null,
	 *     active_sitemaps: string[],
	 *     broken_sitemaps: string[],
	 *     duplicate_sitemaps: bool,
	 *     robots_sitemap_declared: bool,
	 *     issues: array
	 * }
	 */
	public static function audit_sitemaps(): array {
		$active_sitemaps = array();
		$broken_sitemaps = array();
		$issues          = array();

		// 1. Check robots.txt for Sitemap declaration
		$robots_declaration = self::find_sitemaps_in_robots();

		// 2. Discover active candidate endpoints
		$candidates = self::get_sitemap_candidates_for_environment();

		foreach ( $candidates as $source => $path ) {
			$url    = home_url( $path );
			$status = self::validate_sitemap_url( $url );

			if ( $status['valid'] ) {
				if ( ! in_array( $url, $active_sitemaps, true ) ) {
					$active_sitemaps[] = $url;
				}
			} elseif ( $status['checked'] && ! $status['valid'] ) {
				$broken_sitemaps[] = array(
					'url'    => $url,
					'error'  => $status['error'],
					'source' => $source,
				);
			}
		}

		// Also validate any extra sitemap declared in robots.txt
		foreach ( $robots_declaration as $robot_url ) {
			if ( ! in_array( $robot_url, $active_sitemaps, true ) ) {
				$status = self::validate_sitemap_url( $robot_url );
				if ( $status['valid'] ) {
					$active_sitemaps[] = $robot_url;
				} else {
					$broken_sitemaps[] = array(
						'url'    => $robot_url,
						'error'  => $status['error'],
						'source' => 'robots.txt',
					);
				}
			}
		}

		$primary_sitemap         = ! empty( $active_sitemaps ) ? $active_sitemaps[0] : null;
		$has_duplicate           = count( $active_sitemaps ) > 1;
		$robots_sitemap_declared = ! empty( $robots_declaration );

		// Issue 1: Zero active sitemaps found
		if ( empty( $active_sitemaps ) ) {
			$issues[] = array(
				'issue_type'     => 'missing_sitemap',
				'category'       => 'sitemaps',
				'severity'       => 'critical',
				'message'        => __( 'No active XML sitemap detected on this website.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Enable WordPress Core sitemaps or an active SEO plugin sitemap so search engines can discover all URLs.', 'ai-auto-fixer' ),
				'fix_available'  => 1,
				'fix_id'         => 'enable_core_sitemap',
			);
		}

		// Issue 2: Duplicate sitemaps detected
		if ( $has_duplicate ) {
			$issues[] = array(
				'issue_type'     => 'duplicate_sitemaps',
				'category'       => 'sitemaps',
				'severity'       => 'warning',
				'message'        => sprintf(
					/* translators: %s: Comma-separated sitemap URLs */
					__( 'Multiple conflicting XML sitemaps detected: %s. Multiple sitemaps can split crawl budget.', 'ai-auto-fixer' ),
					implode( ', ', $active_sitemaps )
				),
				'recommendation' => __( 'Disable conflicting sitemaps in either WordPress Core or redundant plugins to establish one primary sitemap index.', 'ai-auto-fixer' ),
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		// Issue 3: Broken sitemap declared in robots.txt or active
		if ( ! empty( $broken_sitemaps ) ) {
			foreach ( $broken_sitemaps as $broken ) {
				$issues[] = array(
					'issue_type'     => 'broken_sitemap',
					'category'       => 'sitemaps',
					'severity'       => 'critical',
					'message'        => sprintf(
						/* translators: 1: Sitemap URL, 2: Error message */
						__( 'Sitemap URL is broken (%1$s): %2$s', 'ai-auto-fixer' ),
						esc_url( $broken['url'] ),
						esc_html( $broken['error'] )
					),
					'recommendation' => __( 'Fix the sitemap server configuration or regenerate the XML sitemap.', 'ai-auto-fixer' ),
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		// Issue 4: Sitemap missing from robots.txt
		if ( ! $robots_sitemap_declared && ! empty( $primary_sitemap ) ) {
			$issues[] = array(
				'issue_type'     => 'sitemap_not_in_robots',
				'category'       => 'robots',
				'severity'       => 'warning',
				'message'        => __( 'Primary XML sitemap is not declared inside robots.txt.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Declare your primary XML sitemap in robots.txt so search and AI crawlers locate it immediately.', 'ai-auto-fixer' ),
				'fix_available'  => 1,
				'fix_id'         => 'add_sitemap_to_robots',
			);
		}

		return array(
			'primary_sitemap'         => $primary_sitemap,
			'active_sitemaps'         => $active_sitemaps,
			'broken_sitemaps'         => $broken_sitemaps,
			'duplicate_sitemaps'      => $has_duplicate,
			'robots_sitemap_declared' => $robots_sitemap_declared,
			'issues'                  => $issues,
		);
	}

	/**
	 * Retrieve candidate sitemaps tailored to the active environment.
	 *
	 * @return array<string, string>
	 */
	public static function get_sitemap_candidates_for_environment(): array {
		$candidates = array( 'wp_core' => self::CANDIDATE_SITEMAPS['wp_core'] );

		if ( class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' ) ) {
			$candidates['yoast'] = self::CANDIDATE_SITEMAPS['yoast'];
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			$candidates['rank_math'] = self::CANDIDATE_SITEMAPS['rank_math'];
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$candidates['aioseo'] = self::CANDIDATE_SITEMAPS['aioseo'];
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$candidates['seopress'] = self::CANDIDATE_SITEMAPS['seopress'];
		}

		return $candidates;
	}

	/**
	 * Inspect robots.txt content for Sitemap: declarations.
	 *
	 * @return string[] List of declared sitemap URLs.
	 */
	public static function find_sitemaps_in_robots(): array {
		$sitemaps = array();

		// Check virtual robots.txt
		$robots_url = home_url( '/robots.txt' );
		$response   = HttpClient::safe_get( $robots_url );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( preg_match_all( '/^\s*Sitemap:\s*(https?:\/\/[^\s]+)/im', $body, $matches ) ) {
				$sitemaps = array_map( 'trim', $matches[1] );
			}
		}

		return array_values( array_unique( $sitemaps ) );
	}

	/**
	 * Validate if a given sitemap URL responds with valid XML and 200 OK.
	 *
	 * @param string $url Sitemap URL.
	 * @return array{checked: bool, valid: bool, error: string}
	 */
	public static function validate_sitemap_url( string $url ): array {
		$response = HttpClient::safe_get( $url, array( 'timeout' => 4 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'checked' => true,
				'valid'   => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'checked' => true,
				'valid'   => false,
				'error'   => sprintf( 'HTTP %d response', $code ),
			);
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		if ( strpos( $body, '<?xml' ) === false && strpos( $body, '<urlset' ) === false && strpos( $body, '<sitemapindex' ) === false ) {
			return array(
				'checked' => true,
				'valid'   => false,
				'error'   => 'Response is not valid XML sitemap content',
			);
		}

		return array(
			'checked' => true,
			'valid'   => true,
			'error'   => '',
		);
	}
}
