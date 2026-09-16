<?php
/**
 * SEO Plugin Detector.
 *
 * @package AiAutoFixer\Detectors
 */

namespace AiAutoFixer\Detectors;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects active third-party SEO plugins to ensure 100% compatibility and zero conflicts.
 */
class SeoPluginDetector {

	/**
	 * Detect all active SEO plugins in the WordPress environment.
	 *
	 * @return array<string, array{name: string, active: bool, version: string|null, slug: string}>
	 */
	public static function detect_active_seo_plugins(): array {
		$detected = array();

		// 1. Yoast SEO
		$is_yoast = defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
		if ( $is_yoast ) {
			$detected['yoast'] = array(
				'name'    => 'Yoast SEO',
				'active'  => true,
				'version' => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : null,
				'slug'    => 'wordpress-seo',
			);
		}

		// 2. Rank Math SEO
		$is_rank_math = class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
		if ( $is_rank_math ) {
			$detected['rank_math'] = array(
				'name'    => 'Rank Math SEO',
				'active'  => true,
				'version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
				'slug'    => 'seo-by-rank-math',
			);
		}

		// 3. All In One SEO (AIOSEO)
		$is_aioseo = defined( 'AIOSEO_VERSION' );
		if ( $is_aioseo ) {
			$detected['aioseo'] = array(
				'name'    => 'All in One SEO',
				'active'  => true,
				'version' => AIOSEO_VERSION,
				'slug'    => 'all-in-one-seo-pack',
			);
		}

		// 4. SEOPress & SEOPress PRO
		$is_seopress = defined( 'SEOPRESS_VERSION' );
		if ( $is_seopress ) {
			$detected['seopress'] = array(
				'name'    => defined( 'SEOPRESS_PRO_VERSION' ) ? 'SEOPress PRO' : 'SEOPress',
				'active'  => true,
				'version' => SEOPRESS_VERSION,
				'slug'    => 'wp-seopress',
			);
		}

		// 5. Slim SEO
		$is_slim = defined( 'SLIM_SEO_VER' );
		if ( $is_slim ) {
			$detected['slim_seo'] = array(
				'name'    => 'Slim SEO',
				'active'  => true,
				'version' => SLIM_SEO_VER,
				'slug'    => 'slim-seo',
			);
		}

		// 6. Squirrly SEO
		$is_squirrly = defined( 'SQ_VERSION' );
		if ( $is_squirrly ) {
			$detected['squirrly'] = array(
				'name'    => 'Squirrly SEO',
				'active'  => true,
				'version' => SQ_VERSION,
				'slug'    => 'squirrly-seo',
			);
		}

		return $detected;
	}

	/**
	 * Check if any major third-party SEO plugin is currently active.
	 *
	 * @return bool
	 */
	public static function has_active_seo_plugin(): bool {
		return ! empty( self::detect_active_seo_plugins() );
	}

	/**
	 * Get the primary dominant active SEO plugin name, or null.
	 *
	 * @return string|null
	 */
	public static function get_primary_seo_plugin_name(): ?string {
		$plugins = self::detect_active_seo_plugins();
		if ( empty( $plugins ) ) {
			return null;
		}

		$first = reset( $plugins );
		return $first['name'] ?? null;
	}

	/**
	 * Direct alias for detect_active_seo_plugins().
	 *
	 * @return array<string, array{name: string, active: bool, version: string|null, slug: string}>
	 */
	public static function detect(): array {
		return self::detect_active_seo_plugins();
	}
}
