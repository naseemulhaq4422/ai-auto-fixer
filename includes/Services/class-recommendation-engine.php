<?php
/**
 * Prioritized Recommendation Engine & Policy Synthesizer.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates all scan findings, prioritizes by business impact, and attaches machine-readable fix policies.
 */
class RecommendationEngine {

	/**
	 * Consolidate, deduplicate, and prioritize issues into an actionable directive roadmap.
	 *
	 * @param array $all_issues Array of issues from all scanners.
	 * @return array{
	 *     summary: array{critical: int, warning: int, recommendation: int, passed: int},
	 *     safe_fixes_count: int,
	 *     review_fixes_count: int,
	 *     manual_only_count: int,
	 *     prioritized_issues: array
	 * }
	 */
	public static function process_recommendations( array $all_issues ): array {
		$prioritized = array();
		$seen        = array();

		$summary = array(
			'critical'       => 0,
			'warning'        => 0,
			'recommendation' => 0,
			'passed'         => 0,
		);

		$safe_count   = 0;
		$review_count = 0;
		$manual_count = 0;

		foreach ( $all_issues as $issue ) {
			$type = $issue['issue_type'] ?? 'unknown';
			$url  = $issue['url'] ?? home_url( '/' );

			// Fingerprint deduplication
			$fp = hash( 'sha256', $type . '|' . $url );
			if ( in_array( $fp, $seen, true ) ) {
				continue;
			}
			$seen[] = $fp;

			$sev = strtolower( (string) ( $issue['severity'] ?? 'recommendation' ) );
			if ( isset( $summary[ $sev ] ) ) {
				$summary[ $sev ]++;
			}

			// Synthesize machine-readable FixPolicy
			$policy = self::determine_fix_policy( $issue );

			if ( 'safe_auto_fix' === $policy['action_type'] ) {
				$safe_count++;
			} elseif ( 'review_required' === $policy['action_type'] ) {
				$review_count++;
			} else {
				$manual_count++;
			}

			$prioritized[] = array_merge( $issue, array(
				'fingerprint' => $fp,
				'policy'      => $policy,
			) );
		}

		// Sort by severity: Critical (1) -> Warning (2) -> Recommendation (3) -> Passed (4)
		usort( $prioritized, function( $a, $b ) {
			$weight = array( 'critical' => 1, 'warning' => 2, 'recommendation' => 3, 'passed' => 4 );
			$w_a    = $weight[ strtolower( $a['severity'] ?? 'recommendation' ) ] ?? 5;
			$w_b    = $weight[ strtolower( $b['severity'] ?? 'recommendation' ) ] ?? 5;
			return $w_a <=> $w_b;
		} );

		return array(
			'summary'            => $summary,
			'safe_fixes_count'   => $safe_count,
			'review_fixes_count' => $review_count,
			'manual_only_count'  => $manual_count,
			'prioritized_issues' => $prioritized,
		);
	}

	/**
	 * Determine the execution safety policy for an issue.
	 *
	 * @param array $issue Issue data.
	 * @return array{
	 *     action_type: string,
	 *     risk_level: string,
	 *     requires_confirmation: bool,
	 *     is_reversible: bool,
	 *     supports_rollback: bool
	 * }
	 */
	public static function determine_fix_policy( array $issue ): array {
		$fix_id = $issue['fix_id'] ?? null;

		// Safe automated fixes (low risk, high confidence)
		$safe_fix_ids = array(
			'apply_image_alt',
			'enable_core_sitemap',
			'enable_search_visibility',
			'inject_geo_schema',
			'generate_ai_meta_description',
			'inject_opengraph_tags',
		);

		// Review required fixes (medium/high impact on crawler rules or database records)
		$review_fix_ids = array(
			'grant_ai_crawler_access',
			'add_sitemap_to_robots',
			'move_media_to_trash',
		);

		if ( ! empty( $fix_id ) && in_array( $fix_id, $safe_fix_ids, true ) ) {
			return array(
				'action_type'           => 'safe_auto_fix',
				'risk_level'            => 'low',
				'requires_confirmation' => false,
				'is_reversible'         => true,
				'supports_rollback'     => true,
			);
		}

		if ( ! empty( $fix_id ) && in_array( $fix_id, $review_fix_ids, true ) ) {
			return array(
				'action_type'           => 'review_required',
				'risk_level'            => 'medium',
				'requires_confirmation' => true,
				'is_reversible'         => true,
				'supports_rollback'     => true,
			);
		}

		return array(
			'action_type'           => 'manual_only',
			'risk_level'            => $issue['risk_level'] ?? 'medium',
			'requires_confirmation' => false,
			'is_reversible'         => false,
			'supports_rollback'     => false,
		);
	}
}
