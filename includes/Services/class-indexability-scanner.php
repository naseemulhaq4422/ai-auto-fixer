<?php
/**
 * Comprehensive Indexability & Diagnostics Engine.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Detectors\SeoPluginDetector;
use AiAutoFixer\Detectors\MetaOwnershipDetector;

/**
 * Executes multi-stage technical indexability evaluation, delivers root-cause diagnostics,
 * and identifies sitemap conflicts and orphan URLs.
 */
class IndexabilityScanner {

	public const STATUS_INDEXABLE      = 'INDEXABLE';
	public const STATUS_NOINDEX        = 'NOINDEX';
	public const STATUS_ROBOTS_BLOCKED = 'ROBOTS_BLOCKED';
	public const STATUS_CANONICALIZED  = 'CANONICALIZED';
	public const STATUS_REDIRECTED     = 'REDIRECTED';
	public const STATUS_HTTP_ERROR     = 'HTTP_ERROR';
	public const STATUS_PRIVATE        = 'PRIVATE';
	public const STATUS_PROTECTED      = 'PROTECTED';
	public const STATUS_NEEDS_REVIEW   = 'NEEDS_REVIEW';

	/**
	 * Run the 8-stage indexability analysis on a specific URL.
	 *
	 * @param string      $url Target URL.
	 * @param int         $post_id Associated post ID (0 if archive/term).
	 * @param string|null $robots_txt_content Optional pre-fetched robots.txt content.
	 * @param array       $sitemap_urls Optional set of URLs present in active sitemaps.
	 * @return array{
	 *     url: string,
	 *     indexability_status: string,
	 *     google_index_status: string,
	 *     is_indexable: bool,
	 *     reason: string,
	 *     source: string,
	 *     http_code: int,
	 *     canonical: string|null,
	 *     in_sitemap: bool,
	 *     internal_inbound_links: int,
	 *     is_orphan: bool,
	 *     recommendation: string,
	 *     severity: string
	 * }
	 */
	public static function evaluate_url(
		string $url,
		int $post_id = 0,
		?string $robots_txt_content = null,
		array $sitemap_urls = array()
	): array {
		$clean_url = esc_url_raw( trim( $url ) );

		// 1. Post Status Check (if associated post ID exists)
		if ( $post_id > 0 ) {
			$post_status = get_post_status( $post_id );
			if ( 'private' === $post_status ) {
				return self::format_result(
					$clean_url,
					self::STATUS_PRIVATE,
					__( 'Post status is marked as Private. Only logged-in administrators can access it.', 'ai-auto-fixer' ),
					'WordPress Core Post Status',
					200,
					null,
					false,
					0,
					__( 'Change post visibility to Public if this content is intended for search indexing.', 'ai-auto-fixer' ),
					'warning'
				);
			}

			if ( post_password_required( $post_id ) ) {
				return self::format_result(
					$clean_url,
					self::STATUS_PROTECTED,
					__( 'Post is password protected. Search engines cannot index password-gated pages.', 'ai-auto-fixer' ),
					'WordPress Core Password Protection',
					200,
					null,
					false,
					0,
					__( 'Remove password protection if this page should be indexable.', 'ai-auto-fixer' ),
					'warning'
				);
			}
		}

		// 2. Global WordPress Search Visibility Check
		if ( '0' === (string) get_option( 'blog_public' ) ) {
			return self::format_result(
				$clean_url,
				self::STATUS_NOINDEX,
				__( 'Site-wide search engine indexing is disabled in WordPress Settings (blog_public = 0).', 'ai-auto-fixer' ),
				'WordPress Reading Settings',
				200,
				null,
				false,
				0,
				__( 'Enable "Search Engine Visibility" in WordPress Settings > Reading.', 'ai-auto-fixer' ),
				'critical'
			);
		}

		// 3. HTTP Request & Response Code
		$response = HttpClient::safe_get( $clean_url );
		if ( is_wp_error( $response ) ) {
			return self::format_result(
				$clean_url,
				self::STATUS_HTTP_ERROR,
				sprintf( __( 'Connection failure: %s', 'ai-auto-fixer' ), $response->get_error_message() ),
				'Web Server / Network',
				0,
				null,
				false,
				0,
				__( 'Investigate server availability or firewall blocking local requests.', 'ai-auto-fixer' ),
				'critical'
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$headers   = wp_remote_retrieve_headers( $response );
		$body      = wp_remote_retrieve_body( $response );

		// 4. HTTP Status Verification (Redirects / 404 / 5xx)
		if ( $http_code >= 300 && $http_code < 400 ) {
			$location = $headers['location'] ?? 'another URL';
			return self::format_result(
				$clean_url,
				self::STATUS_REDIRECTED,
				sprintf( __( 'Page returns HTTP %d redirect to %s.', 'ai-auto-fixer' ), $http_code, esc_url( (string) $location ) ),
				'Web Server / Redirect Rules',
				$http_code,
				null,
				false,
				0,
				__( 'Update internal links directly to the final destination URL.', 'ai-auto-fixer' ),
				'info'
			);
		}

		if ( $http_code >= 400 ) {
			return self::format_result(
				$clean_url,
				self::STATUS_HTTP_ERROR,
				sprintf( __( 'Page returns error status HTTP %d.', 'ai-auto-fixer' ), $http_code ),
				'Web Server',
				$http_code,
				null,
				false,
				0,
				__( 'Repair broken endpoint or restore deleted content.', 'ai-auto-fixer' ),
				'critical'
			);
		}

		// 5. X-Robots-Tag HTTP Header
		$x_robots = $headers['x-robots-tag'] ?? '';
		if ( is_string( $x_robots ) && false !== stripos( $x_robots, 'noindex' ) ) {
			return self::format_result(
				$clean_url,
				self::STATUS_NOINDEX,
				sprintf( __( 'HTTP header "X-Robots-Tag: %s" explicitly instructs search engines not to index.', 'ai-auto-fixer' ), esc_html( $x_robots ) ),
				'Server Header / Plugin Filter',
				$http_code,
				null,
				false,
				0,
				__( 'Remove the X-Robots-Tag header configuration if this page should be indexable.', 'ai-auto-fixer' ),
				'critical'
			);
		}

		// 6. Robots.txt Disallow Check
		if ( null === $robots_txt_content ) {
			$robots_res = HttpClient::safe_get( home_url( '/robots.txt' ) );
			$robots_txt_content = ( ! is_wp_error( $robots_res ) && 200 === wp_remote_retrieve_response_code( $robots_res ) )
				? wp_remote_retrieve_body( $robots_res )
				: '';
		}

		if ( ! empty( $robots_txt_content ) && self::is_blocked_by_robots( $clean_url, $robots_txt_content ) ) {
			return self::format_result(
				$clean_url,
				self::STATUS_ROBOTS_BLOCKED,
				__( 'Path is blocked by a "Disallow:" directive in robots.txt.', 'ai-auto-fixer' ),
				'robots.txt',
				$http_code,
				null,
				false,
				0,
				__( 'Review and adjust disallow rules in your robots.txt file.', 'ai-auto-fixer' ),
				'critical'
			);
		}

		// 7. HTML <meta name="robots"> Check
		if ( preg_match( '/<meta\b[^>]*name=[\'"]robots[\'"][^>]*content=[\'"](.*?)[\'"][^>]*>/i', $body, $matches ) ) {
			$meta_robots = strtolower( trim( $matches[1] ) );
			if ( false !== strpos( $meta_robots, 'noindex' ) || false !== strpos( $meta_robots, 'none' ) ) {
				$source = MetaOwnershipDetector::detect_meta_source( $body );
				return self::format_result(
					$clean_url,
					self::STATUS_NOINDEX,
					sprintf( __( 'Page HTML contains <meta name="robots" content="%s">.', 'ai-auto-fixer' ), esc_html( $meta_robots ) ),
					$source,
					$http_code,
					null,
					false,
					0,
					sprintf( __( 'Review the page indexing setting in %s.', 'ai-auto-fixer' ), esc_html( $source ) ),
					'critical'
				);
			}
		}

		// 8. Canonical Tag Analysis
		$canonical_analysis = MetaOwnershipDetector::analyze_canonicals( $body );
		$target_canonical   = ! empty( $canonical_analysis['urls'] ) ? $canonical_analysis['urls'][0] : null;

		if ( $target_canonical && untrailingslashit( $target_canonical ) !== untrailingslashit( $clean_url ) ) {
			return self::format_result(
				$clean_url,
				self::STATUS_CANONICALIZED,
				sprintf( __( 'Canonical tag points to another URL: %s. Search engines will treat this page as duplicate.', 'ai-auto-fixer' ), esc_url( $target_canonical ) ),
				$canonical_analysis['source'],
				$http_code,
				$target_canonical,
				false,
				0,
				__( 'Verify if this page should be self-canonical or if consolidation is intentional.', 'ai-auto-fixer' ),
				'warning'
			);
		}

		// 9. Sitemap Presence & Orphan Status
		$in_sitemap   = in_array( $clean_url, $sitemap_urls, true ) || in_array( trailingslashit( $clean_url ), $sitemap_urls, true );
		$inbound_links = self::count_internal_inbound_links( $clean_url, $post_id );
		$is_orphan    = ( 0 === $inbound_links && untrailingslashit( $clean_url ) !== untrailingslashit( home_url() ) );

		// Healthy Indexable Page
		return self::format_result(
			$clean_url,
			self::STATUS_INDEXABLE,
			$is_orphan
				? __( 'Page is technically indexable, but no internal links point here (Orphan URL).', 'ai-auto-fixer' )
				: __( 'Page satisfies all technical indexability requirements.', 'ai-auto-fixer' ),
			'WordPress / All Checks Passed',
			$http_code,
			$target_canonical ?: $clean_url,
			$in_sitemap,
			$inbound_links,
			$is_orphan
				? __( 'Add contextual internal links pointing to this page from relevant parent articles or menus.', 'ai-auto-fixer' )
				: __( 'No action required.', 'ai-auto-fixer' ),
			$is_orphan ? 'warning' : 'passed'
		);
	}

	/**
	 * Test if a given absolute URL is disallowed in robots.txt content for standard crawlers.
	 *
	 * @param string $url Target URL.
	 * @param string $robots_content Robots.txt string.
	 * @return bool True if disallowed.
	 */
	public static function is_blocked_by_robots( string $url, string $robots_content ): bool {
		$parsed = wp_parse_url( $url );
		$path   = ( $parsed['path'] ?? '/' ) . ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );

		$lines         = explode( "\n", $robots_content );
		$is_user_agent = false;

		foreach ( $lines as $line ) {
			$clean_line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( empty( $clean_line ) ) {
				continue;
			}

			if ( preg_match( '/^User-agent:\s*(.+)$/i', $clean_line, $m ) ) {
				$ua = trim( $m[1] );
				$is_user_agent = ( '*' === $ua );
				continue;
			}

			if ( $is_user_agent && preg_match( '/^Disallow:\s*(.+)$/i', $clean_line, $m ) ) {
				$pattern = trim( $m[1] );
				if ( '/' === $pattern ) {
					return true;
				}
				if ( ! empty( $pattern ) && 0 === strpos( $path, $pattern ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Count internal links pointing to this page within standard post content.
	 *
	 * @param string $url URL to search.
	 * @param int    $post_id Post ID being checked.
	 * @return int Number of referencing posts.
	 */
	public static function count_internal_inbound_links( string $url, int $post_id = 0 ): int {
		global $wpdb;

		$relative_path = wp_parse_url( $url, PHP_URL_PATH );
		if ( empty( $relative_path ) || '/' === $relative_path ) {
			return 1; // Homepage is never an orphan
		}

		$search_pattern = '%' . $wpdb->esc_like( $relative_path ) . '%';

		$sql = "SELECT COUNT(ID) FROM {$wpdb->posts} 
				WHERE post_status = 'publish' 
				AND post_type IN ('post', 'page') 
				AND post_content LIKE %s";

		if ( $post_id > 0 ) {
			$sql .= " AND ID != %d";
			$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $search_pattern, $post_id ) );
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $search_pattern ) );
		}

		return max( 0, $count );
	}

	/**
	 * Helper formatter to return consistent structured indexability objects.
	 */
	private static function format_result(
		string $url,
		string $status,
		string $reason,
		string $source,
		int $http_code,
		?string $canonical,
		bool $in_sitemap,
		int $inbound_links,
		string $recommendation,
		string $severity
	): array {
		return array(
			'url'                    => $url,
			'indexability_status'    => $status,
			'google_index_status'    => 'Unknown (Local technical analysis only; external Search Console data required for live index state)',
			'is_indexable'           => ( self::STATUS_INDEXABLE === $status ),
			'reason'                 => $reason,
			'source'                 => $source,
			'http_code'              => $http_code,
			'canonical'              => $canonical,
			'in_sitemap'             => $in_sitemap,
			'internal_inbound_links' => $inbound_links,
			'is_orphan'              => ( 0 === $inbound_links && self::STATUS_INDEXABLE === $status && untrailingslashit( $url ) !== untrailingslashit( home_url() ) ),
			'recommendation'         => $recommendation,
			'severity'               => $severity,
		);
	}
}
