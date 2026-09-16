<?php
/**
 * Meta Tag Ownership & Conflict Detector.
 *
 * @package AiAutoFixer\Detectors
 */

namespace AiAutoFixer\Detectors;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accurately analyzes rendered meta elements, identifies their generating origin,
 * and flags conflicting or duplicated canonical, title, and description tags.
 */
class MetaOwnershipDetector {

	public const ORIGIN_YOAST       = 'Yoast SEO';
	public const ORIGIN_RANK_MATH   = 'Rank Math SEO';
	public const ORIGIN_AIOSEO      = 'All in One SEO';
	public const ORIGIN_SEOPRESS    = 'SEOPress';
	public const ORIGIN_AI_AUTO_FIX = 'AI Auto-Fixer';
	public const ORIGIN_THEME_CORE  = 'Theme / WordPress Core';

	/**
	 * Detect whether multiple contradictory canonical tags exist in the document.
	 *
	 * @param string $html Document HTML markup.
	 * @return bool True if conflicting canonicals found.
	 */
	public static function detect_conflicting_canonicals( string $html ): bool {
		$analysis = self::analyze_canonicals( $html );
		return $analysis['has_conflict'];
	}

	/**
	 * Detect comprehensive meta ownership and conflicts.
	 *
	 * @param string $html Rendered HTML markup.
	 * @return array
	 */
	public static function detect_meta_ownership( string $html = '' ): array {
		return array(
			'canonicals'   => self::analyze_canonicals( $html ),
			'descriptions' => self::analyze_descriptions( $html ),
			'primary_owner'=> self::detect_meta_source( $html ),
		);
	}

	/**
	 * Analyze rendered HTML head for canonical tags and detect conflicts.
	 *
	 * @param string $html Full or head HTML markup.
	 * @return array{count: int, urls: string[], has_conflict: bool, source: string, message: string}
	 */
	public static function analyze_canonicals( string $html ): array {
		$canonicals = array();

		if ( preg_match_all( '/<link\b[^>]*rel=[\'"]canonical[\'"][^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $matches ) ) {
			$canonicals = array_map( 'trim', $matches[1] );
		}

		$count        = count( $canonicals );
		$has_conflict = $count > 1;
		$source       = self::detect_meta_source( $html );

		if ( 0 === $count ) {
			$message = __( 'Missing canonical tag. Search engines may struggle to select the primary indexing URL.', 'ai-auto-fixer' );
		} elseif ( $has_conflict ) {
			$message = sprintf(
				/* translators: %d: Number of canonical tags detected */
				__( 'Conflict: Multiple canonical tags detected (%d). Conflicting canonicals confuse search crawlers and dilute ranking authority.', 'ai-auto-fixer' ),
				$count
			);
		} else {
			$message = __( 'Valid single canonical tag detected.', 'ai-auto-fixer' );
		}

		return array(
			'count'        => $count,
			'urls'         => $canonicals,
			'has_conflict' => $has_conflict,
			'source'       => $source,
			'message'      => $message,
		);
	}

	/**
	 * Analyze meta descriptions in the rendered document.
	 *
	 * @param string $html Rendered HTML markup.
	 * @return array{count: int, descriptions: string[], has_duplicate: bool, source: string, message: string}
	 */
	public static function analyze_descriptions( string $html ): array {
		$descriptions = array();

		if ( preg_match_all( '/<meta\b[^>]*name=[\'"]description[\'"][^>]*content=[\'"](.*?)[\'"][^>]*>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $desc ) {
				$clean = trim( html_entity_decode( $desc, ENT_QUOTES, 'UTF-8' ) );
				if ( ! empty( $clean ) ) {
					$descriptions[] = $clean;
				}
			}
		}

		$count         = count( $descriptions );
		$has_duplicate = $count > 1;
		$source        = self::detect_meta_source( $html );

		if ( 0 === $count ) {
			$message = __( 'Missing meta description. Search engines will generate automated snippets.', 'ai-auto-fixer' );
		} elseif ( $has_duplicate ) {
			$message = sprintf(
				/* translators: %d: Duplicate count */
				__( 'Conflict: Multiple meta descriptions detected (%d). Only one description tag should be output.', 'ai-auto-fixer' ),
				$count
			);
		} else {
			$len = mb_strlen( $descriptions[0] );
			if ( $len < 70 ) {
				$message = sprintf( __( 'Meta description is too short (%d characters). Aim for 120–160 characters.', 'ai-auto-fixer' ), $len );
			} elseif ( $len > 160 ) {
				$message = sprintf( __( 'Meta description is too long (%d characters). Snippet may be truncated in search results.', 'ai-auto-fixer' ), $len );
			} else {
				$message = __( 'Optimal meta description length detected.', 'ai-auto-fixer' );
			}
		}

		return array(
			'count'         => $count,
			'descriptions'  => $descriptions,
			'has_duplicate' => $has_duplicate,
			'source'        => $source,
			'message'       => $message,
		);
	}

	/**
	 * Detect origin/owner of meta tags based on HTML comments or known signatures.
	 *
	 * @param string $html HTML document snippet.
	 * @return string Source name.
	 */
	public static function detect_meta_source( string $html ): string {
		if ( strpos( $html, 'This site is optimized with the Yoast SEO plugin' ) !== false ) {
			return self::ORIGIN_YOAST;
		}
		if ( strpos( $html, 'Rank Math WordPress SEO plugin' ) !== false ) {
			return self::ORIGIN_RANK_MATH;
		}
		if ( strpos( $html, 'All in One SEO Pack' ) !== false || strpos( $html, 'All in One SEO Pro' ) !== false ) {
			return self::ORIGIN_AIOSEO;
		}
		if ( strpos( $html, 'SEOPress' ) !== false ) {
			return self::ORIGIN_SEOPRESS;
		}
		if ( strpos( $html, 'AI Auto-Fixer' ) !== false ) {
			return self::ORIGIN_AI_AUTO_FIX;
		}

		return self::ORIGIN_THEME_CORE;
	}
}
