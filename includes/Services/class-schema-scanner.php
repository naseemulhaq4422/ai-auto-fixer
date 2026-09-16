<?php
/**
 * Schema.org & AEO Entity Structure Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Detectors\SchemaOwnershipDetector;

/**
 * Validates Schema.org JSON-LD structured data with strict ownership adherence to prevent duplicates.
 */
class SchemaScanner {

	/**
	 * Run Schema.org audit accepting either ($url, $html) or ($html, $page_type).
	 *
	 * @param string $param1 URL or HTML string.
	 * @param string $param2 HTML or page type string.
	 * @return array
	 */
	public static function audit_schemas( string $param1, string $param2 = '' ): array {
		if ( strpos( $param1, '<' ) !== false ) {
			return self::audit_schema( $param1, ! empty( $param2 ) ? $param2 : 'general' );
		}
		if ( ! empty( $param2 ) && strpos( $param2, '<' ) !== false ) {
			return self::audit_schema( $param2, 'general' );
		}
		return self::audit_schema( $param2, 'general' );
	}

	/**
	 * Run complete Schema.org audit on an HTML document.
	 *
	 * @param string $html Rendered HTML markup.
	 * @param string $page_type 'home', 'product', or 'general'.
	 * @return array{
	 *     entities_detected: array,
	 *     issues: array,
	 *     passes: array
	 * }
	 */
	public static function audit_schema( string $html, string $page_type = 'general' ): array {
		$issues = array();
		$passes = array();

		$json_blocks = SchemaOwnershipDetector::extract_json_ld_blocks( $html );

		// Target entities to evaluate
		$targets = array( 'Organization', 'WebSite', 'BreadcrumbList' );
		if ( 'product' === $page_type ) {
			$targets[] = 'Product';
		}

		$entities_detected = array();

		foreach ( $targets as $target ) {
			$eval = SchemaOwnershipDetector::evaluate_entity_ownership( $target, $json_blocks, $html );
			$entities_detected[ $target ] = $eval;

			switch ( $eval['state'] ) {
				case SchemaOwnershipDetector::STATE_OWNED:
					$passes[] = array(
						'title'       => sprintf( __( '%s Schema Active', 'ai-auto-fixer' ), $target ),
						'description' => $eval['recommendation'],
						'owners'      => $eval['owners'],
					);
					break;

				case SchemaOwnershipDetector::STATE_MULTIPLE_OWNERS:
					$issues[] = array(
						'issue_type'     => 'duplicate_schema_' . strtolower( $target ),
						'category'       => 'schema_aeo',
						'severity'       => 'warning',
						'message'        => $eval['recommendation'],
						'recommendation' => __( 'Consolidate structured data so only one primary plugin outputs this entity.', 'ai-auto-fixer' ),
						'risk_level'     => 'medium',
						'fix_available'  => 0,
						'fix_id'         => null,
					);
					break;

				case SchemaOwnershipDetector::STATE_PARTIAL_OWNER:
					$issues[] = array(
						'issue_type'     => 'incomplete_schema_' . strtolower( $target ),
						'category'       => 'schema_aeo',
						'severity'       => 'warning',
						'message'        => $eval['recommendation'],
						'recommendation' => sprintf(
							/* translators: 1: Owner, 2: Missing properties */
							__( 'Configure missing properties (%2$s) in %1$s settings.', 'ai-auto-fixer' ),
							esc_html( implode( ', ', $eval['owners'] ) ),
							esc_html( implode( ', ', $eval['missing_properties'] ) )
						),
						'risk_level'     => 'low',
						'fix_available'  => 0,
						'fix_id'         => null,
					);
					break;

				case SchemaOwnershipDetector::STATE_NO_OWNER:
					$issues[] = array(
						'issue_type'     => 'missing_schema_' . strtolower( $target ),
						'category'       => 'schema_aeo',
						'severity'       => in_array( $target, array( 'Organization', 'WebSite' ), true ) ? 'warning' : 'recommendation',
						'message'        => sprintf( __( 'Missing %s Schema.org JSON-LD entity graph.', 'ai-auto-fixer' ), $target ),
						'recommendation' => sprintf( __( 'Inject structured %s schema to enhance entity clarity for search and answer engines.', 'ai-auto-fixer' ), $target ),
						'risk_level'     => 'low',
						'fix_available'  => ( 'Organization' === $target || 'WebSite' === $target ) ? 1 : 0,
						'fix_id'         => 'inject_geo_schema',
					);
					break;
			}
		}

		return array(
			'entities_detected' => $entities_detected,
			'issues'            => $issues,
			'passes'            => $passes,
		);
	}
}
