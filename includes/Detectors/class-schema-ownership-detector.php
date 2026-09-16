<?php
/**
 * Schema Ownership & Duplicate Detector.
 *
 * @package AiAutoFixer\Detectors
 */

namespace AiAutoFixer\Detectors;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accurately analyzes JSON-LD and Microdata entity blocks, maps ownership,
 * and guards against duplicate schema generation.
 */
class SchemaOwnershipDetector {

	public const STATE_OWNED           = 'OWNED';
	public const STATE_MULTIPLE_OWNERS = 'MULTIPLE_OWNERS';
	public const STATE_PARTIAL_OWNER   = 'PARTIAL_OWNER';
	public const STATE_UNKNOWN_OWNER   = 'UNKNOWN_OWNER';
	public const STATE_NO_OWNER        = 'NO_OWNER';
	public const STATE_CONFLICT        = 'CONFLICT';

	/**
	 * Extract and parse all JSON-LD blocks from an HTML document.
	 *
	 * @param string $html Rendered HTML string.
	 * @return array<int, array> Decoded JSON-LD structures.
	 */
	public static function extract_json_ld_blocks( string $html ): array {
		$blocks = array();

		if ( empty( $html ) ) {
			return $blocks;
		}

		if ( preg_match_all( '/<script\b[^>]*type=[\'"]application\/ld\+json[\'"][^>]*>(.*?)<\/script>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $json_str ) {
				$decoded = json_decode( trim( $json_str ), true );
				if ( is_array( $decoded ) ) {
					$blocks[] = $decoded;
				}
			}
		}

		return $blocks;
	}

	/**
	 * Determine the managing entity ownership state for a given schema entity type.
	 *
	 * @param string $target_entity Entity name e.g. 'Organization', 'WebSite', 'Product', 'BreadcrumbList'.
	 * @param array  $json_ld_blocks Parsed JSON-LD blocks from the page.
	 * @param string $raw_html Full HTML for signature matching.
	 * @return array{state: string, owners: string[], missing_properties: string[], recommendation: string}
	 */
	public static function evaluate_entity_ownership( string $target_entity, array $json_ld_blocks, string $raw_html = '' ): array {
		$detected_owners = array();
		$found_entities  = array();

		foreach ( $json_ld_blocks as $block ) {
			// Handle @graph collections (common in Yoast, Rank Math)
			$items = isset( $block['@graph'] ) && is_array( $block['@graph'] ) ? $block['@graph'] : array( $block );

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || empty( $item['@type'] ) ) {
					continue;
				}

				$types = is_array( $item['@type'] ) ? $item['@type'] : array( $item['@type'] );

				if ( in_array( $target_entity, $types, true ) ) {
					$found_entities[] = $item;
					$owner = self::identify_block_owner( $item, $block, $raw_html );
					if ( ! in_array( $owner, $detected_owners, true ) ) {
						$detected_owners[] = $owner;
					}
				}
			}
		}

		// 1. Zero entity blocks discovered -> NO_OWNER
		if ( empty( $found_entities ) ) {
			return array(
				'state'              => self::STATE_NO_OWNER,
				'owners'             => array(),
				'missing_properties' => array(),
				'recommendation'     => sprintf(
					/* translators: %s: Schema entity name */
					__( 'No existing %s schema detected. Safe generation is available.', 'ai-auto-fixer' ),
					esc_html( $target_entity )
				),
			);
		}

		// 2. Multiple distinct owners -> MULTIPLE_OWNERS / Possible duplicate
		if ( count( $detected_owners ) > 1 ) {
			return array(
				'state'              => self::STATE_MULTIPLE_OWNERS,
				'owners'             => $detected_owners,
				'missing_properties' => array(),
				'recommendation'     => sprintf(
					/* translators: 1: Schema entity name, 2: Comma-separated owners */
					__( 'Multiple implementations of %1$s schema detected (%2$s). Potential duplicate structured data. Review theme and plugin settings.', 'ai-auto-fixer' ),
					esc_html( $target_entity ),
					esc_html( implode( ', ', $detected_owners ) )
				),
			);
		}

		$primary_owner = reset( $detected_owners );
		$primary_entity = reset( $found_entities );

		// 3. Inspect missing required/recommended properties
		$missing = self::find_missing_properties( $target_entity, $primary_entity );

		if ( ! empty( $missing ) ) {
			return array(
				'state'              => self::STATE_PARTIAL_OWNER,
				'owners'             => $detected_owners,
				'missing_properties' => $missing,
				'recommendation'     => sprintf(
					/* translators: 1: Schema entity name, 2: Owner name, 3: Missing properties */
					__( '%1$s schema is managed by %2$s, but missing recommended properties: %3$s.', 'ai-auto-fixer' ),
					esc_html( $target_entity ),
					esc_html( $primary_owner ),
					esc_html( implode( ', ', $missing ) )
				),
			);
		}

		// 4. Healthy single owner
		return array(
			'state'              => self::STATE_OWNED,
			'owners'             => $detected_owners,
			'missing_properties' => array(),
			'recommendation'     => sprintf(
				/* translators: 1: Schema entity name, 2: Owner name */
				__( '%1$s schema is actively and completely managed by %2$s.', 'ai-auto-fixer' ),
				esc_html( $target_entity ),
				esc_html( $primary_owner )
			),
		);
	}

	/**
	 * Identify likely source or plugin owner of a schema JSON-LD block.
	 *
	 * @param array  $entity Target entity item.
	 * @param array  $parent_block Containing JSON-LD block.
	 * @param string $raw_html Full HTML for heuristic matching.
	 * @return string Detected owner name.
	 */
	private static function identify_block_owner( array $entity, array $parent_block, string $raw_html ): string {
		$id_str = isset( $entity['@id'] ) && is_string( $entity['@id'] ) ? $entity['@id'] : '';

		// Yoast signature check
		if ( strpos( $id_str, '#organization' ) !== false || strpos( $id_str, '#website' ) !== false || strpos( $raw_html, 'This site is optimized with the Yoast SEO plugin' ) !== false ) {
			return 'Yoast SEO';
		}

		// Rank Math signature check
		if ( strpos( $id_str, '#schema-' ) !== false || strpos( $raw_html, 'Rank Math WordPress SEO plugin' ) !== false ) {
			return 'Rank Math SEO';
		}

		// WooCommerce signature check
		if ( isset( $entity['@type'] ) && ( 'Product' === $entity['@type'] || in_array( 'Product', (array) $entity['@type'], true ) ) ) {
			if ( class_exists( 'WooCommerce' ) ) {
				return 'WooCommerce';
			}
		}

		// AI Auto-Fixer signature check
		if ( strpos( $raw_html, 'AI Auto-Fixer: GEO/AEO Entity Schema' ) !== false ) {
			return 'AI Auto-Fixer';
		}

		return 'Theme / Custom';
	}

	/**
	 * Find missing recommended schema properties for major schema types.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $data Entity attributes.
	 * @return string[] List of missing property names.
	 */
	private static function find_missing_properties( string $entity_type, array $data ): array {
		$missing = array();

		switch ( $entity_type ) {
			case 'Organization':
				$recommended = array( 'name', 'url', 'logo', 'contactPoint', 'sameAs' );
				break;

			case 'WebSite':
				$recommended = array( 'name', 'url', 'potentialAction' );
				break;

			case 'Product':
				$recommended = array( 'name', 'image', 'offers', 'sku' );
				break;

			default:
				$recommended = array( 'name' );
				break;
		}

		foreach ( $recommended as $prop ) {
			if ( empty( $data[ $prop ] ) ) {
				$missing[] = $prop;
			}
		}

		return $missing;
	}
}
