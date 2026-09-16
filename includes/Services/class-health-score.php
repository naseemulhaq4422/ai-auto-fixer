<?php
/**
 * Dual Health Score Engine (SEO Health & Safety Scores).
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes mathematically weighted, separate scores for SEO Health and Website Safety.
 */
class HealthScore {

	/**
	 * Weights for SEO Health Score (Total: 100%).
	 */
	public const WEIGHT_TECHNICAL_SEO = 0.25;
	public const WEIGHT_INDEXABILITY   = 0.25;
	public const WEIGHT_ON_PAGE        = 0.15;
	public const WEIGHT_SCHEMA_AEO     = 0.15;
	public const WEIGHT_AI_GEO         = 0.10;
	public const WEIGHT_IMAGE_SEO      = 0.10;

	/**
	 * Weights for Website Safety Score (Total: 100%).
	 */
	public const WEIGHT_SAFETY_SSL         = 0.30;
	public const WEIGHT_SAFETY_HEADERS     = 0.25;
	public const WEIGHT_SAFETY_BROWSING    = 0.25;
	public const WEIGHT_SAFETY_PERMISSIONS = 0.20;

	/**
	 * Calculate composite SEO Health Score based on individual pillar performance (0-100 each).
	 *
	 * @param array{
	 *     technical?: int|float,
	 *     indexability?: int|float,
	 *     on_page?: int|float,
	 *     schema?: int|float,
	 *     ai_geo?: int|float,
	 *     images?: int|float
	 * } $pillars Pillar scores from 0 to 100.
	 * @return int Weighted score clamped between 0 and 100.
	 */
	public static function calculate_seo_health_score( array $pillars ): int {
		$tech   = self::clamp( (float) ( $pillars['technical'] ?? 100 ) );
		$index  = self::clamp( (float) ( $pillars['indexability'] ?? 100 ) );
		$onpage = self::clamp( (float) ( $pillars['on_page'] ?? 100 ) );
		$schema = self::clamp( (float) ( $pillars['schema'] ?? 100 ) );
		$aigeo  = self::clamp( (float) ( $pillars['ai_geo'] ?? 100 ) );
		$images = self::clamp( (float) ( $pillars['images'] ?? 100 ) );

		$total = ( $tech * self::WEIGHT_TECHNICAL_SEO ) +
				 ( $index * self::WEIGHT_INDEXABILITY ) +
				 ( $onpage * self::WEIGHT_ON_PAGE ) +
				 ( $schema * self::WEIGHT_SCHEMA_AEO ) +
				 ( $aigeo * self::WEIGHT_AI_GEO ) +
				 ( $images * self::WEIGHT_IMAGE_SEO );

		return (int) round( self::clamp( $total ) );
	}

	/**
	 * Calculate composite Website Safety Score.
	 *
	 * @param array{
	 *     ssl?: int|float,
	 *     headers?: int|float,
	 *     directory_browsing?: int|float,
	 *     permissions?: int|float
	 * } $factors Safety factors (0 to 100).
	 * @return int Weighted score clamped between 0 and 100.
	 */
	public static function calculate_safety_score( array $factors ): int {
		$ssl     = self::clamp( (float) ( $factors['ssl'] ?? 100 ) );
		$headers = self::clamp( (float) ( $factors['headers'] ?? 100 ) );
		$browse  = self::clamp( (float) ( $factors['directory_browsing'] ?? 100 ) );
		$perms   = self::clamp( (float) ( $factors['permissions'] ?? 100 ) );

		$total = ( $ssl * self::WEIGHT_SAFETY_SSL ) +
				 ( $headers * self::WEIGHT_SAFETY_HEADERS ) +
				 ( $browse * self::WEIGHT_SAFETY_BROWSING ) +
				 ( $perms * self::WEIGHT_SAFETY_PERMISSIONS );

		return (int) round( self::clamp( $total ) );
	}

	/**
	 * Deduct score dynamically based on issue severities.
	 *
	 * @param int   $base_score Initial score (typically 100).
	 * @param array $issues List of issues with 'severity' key ('critical', 'warning', 'recommendation', 'info').
	 * @return int Deducted score clamped to 10-100.
	 */
	public static function compute_deductions( int $base_score, array $issues ): int {
		$score = (float) $base_score;

		foreach ( $issues as $issue ) {
			$severity = strtolower( (string) ( $issue['severity'] ?? 'info' ) );
			switch ( $severity ) {
				case 'critical':
					$score -= 10;
					break;
				case 'warning':
					$score -= 4;
					break;
				case 'recommendation':
				case 'info':
					$score -= 1.5;
					break;
			}
		}

		return (int) round( max( 10, min( 100, $score ) ) );
	}

	/**
	 * Direct alias for compute_deductions().
	 *
	 * @param int   $base_score Initial score.
	 * @param array $issues List of issues.
	 * @return int Deducted score.
	 */
	public static function deduct_score_from_issues( int $base_score, array $issues ): int {
		return self::compute_deductions( $base_score, $issues );
	}

	/**
	 * Convert numeric score to user-friendly qualitative rating.
	 *
	 * @param int $score Value between 0 and 100.
	 * @return string Rating label.
	 */
	public static function get_rating_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'Excellent', 'ai-auto-fixer' );
		}
		if ( $score >= 75 ) {
			return __( 'Good', 'ai-auto-fixer' );
		}
		if ( $score >= 50 ) {
			return __( 'Needs Improvement', 'ai-auto-fixer' );
		}
		return __( 'Critical Attention Required', 'ai-auto-fixer' );
	}

	/**
	 * Clamp float value between 0.0 and 100.0.
	 *
	 * @param float $val Input value.
	 * @return float Clamped value.
	 */
	private static function clamp( float $val ): float {
		return max( 0.0, min( 100.0, $val ) );
	}
}
