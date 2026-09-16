<?php
/**
 * On-Page Meta & Social Tag Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Detectors\MetaOwnershipDetector;

/**
 * Evaluates Title tags, Meta descriptions, OpenGraph, and Twitter Cards with ownership intelligence.
 */
class MetaScanner {

	/**
	 * Audit all on-page meta tags for a given rendered page.
	 *
	 * @param string $html Rendered HTML content.
	 * @param string $url URL being inspected.
	 * @return array List of issues found.
	 */
	public static function audit_meta( string $html, string $url = '' ): array {
		$issues = array();

		if ( empty( $html ) ) {
			return $issues;
		}

		// 1. Title Tag Audit
		if ( preg_match( '/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			$title = trim( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) );
			$len   = mb_strlen( $title );

			if ( empty( $title ) ) {
				$issues[] = array(
					'issue_type'     => 'empty_title',
					'category'       => 'on_page',
					'severity'       => 'critical',
					'message'        => __( 'Page title tag is empty.', 'ai-auto-fixer' ),
					'recommendation' => __( 'Add a concise, keyword-focused title between 30 and 60 characters.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			} elseif ( $len < 30 ) {
				$issues[] = array(
					'issue_type'     => 'short_title',
					'category'       => 'on_page',
					'severity'       => 'warning',
					'message'        => sprintf( __( 'Page title is short (%d characters). Aim for 30–60 characters.', 'ai-auto-fixer' ), $len ),
					'recommendation' => __( 'Expand title with relevant branding or primary search terms.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			} elseif ( $len > 65 ) {
				$issues[] = array(
					'issue_type'     => 'long_title',
					'category'       => 'on_page',
					'severity'       => 'warning',
					'message'        => sprintf( __( 'Page title is long (%d characters). It will likely be truncated in search snippets.', 'ai-auto-fixer' ), $len ),
					'recommendation' => __( 'Shorten title to keep important keywords within the 60-character visible limit.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		} else {
			$issues[] = array(
				'issue_type'     => 'missing_title',
				'category'       => 'on_page',
				'severity'       => 'critical',
				'message'        => __( 'No <title> tag found in HTML document head.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Add a primary title tag to ensure search engines can index and display the page.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		// 2. Meta Description Audit with Ownership & Duplicate Checks
		$desc_analysis = MetaOwnershipDetector::analyze_descriptions( $html );
		if ( 0 === $desc_analysis['count'] ) {
			$issues[] = array(
				'issue_type'     => 'missing_meta_description',
				'category'       => 'on_page',
				'severity'       => 'warning',
				'message'        => __( 'Page is missing a meta description tag.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Add an informative meta description between 120 and 160 characters.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 1,
				'fix_id'         => 'generate_ai_meta_description',
			);
		} elseif ( $desc_analysis['has_duplicate'] ) {
			$issues[] = array(
				'issue_type'     => 'duplicate_meta_description',
				'category'       => 'on_page',
				'severity'       => 'critical',
				'message'        => sprintf(
					/* translators: 1: Count, 2: Source */
					__( 'Multiple meta descriptions detected (%1$d) from %2$s.', 'ai-auto-fixer' ),
					$desc_analysis['count'],
					esc_html( $desc_analysis['source'] )
				),
				'recommendation' => __( 'Remove conflicting description tags to prevent search snippet confusion.', 'ai-auto-fixer' ),
				'risk_level'     => 'medium',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		// 3. OpenGraph Social & AI Entity Tags
		$has_og_title = (bool) preg_match( '/<meta\b[^>]*property=[\'"]og:title[\'"][^>]*>/i', $html );
		$has_og_desc  = (bool) preg_match( '/<meta\b[^>]*property=[\'"]og:description[\'"][^>]*>/i', $html );
		$has_og_image = (bool) preg_match( '/<meta\b[^>]*property=[\'"]og:image[\'"][^>]*>/i', $html );

		if ( ! $has_og_title || ! $has_og_desc || ! $has_og_image ) {
			$missing_og = array();
			if ( ! $has_og_title ) { $missing_og[] = 'og:title'; }
			if ( ! $has_og_desc ) { $missing_og[] = 'og:description'; }
			if ( ! $has_og_image ) { $missing_og[] = 'og:image'; }

			$issues[] = array(
				'issue_type'     => 'missing_opengraph',
				'category'       => 'on_page',
				'severity'       => 'warning',
				'message'        => sprintf(
					/* translators: %s: Comma-separated missing OG tags */
					__( 'Missing recommended OpenGraph social tags: %s.', 'ai-auto-fixer' ),
					implode( ', ', $missing_og )
				),
				'recommendation' => __( 'Configure complete OpenGraph tags so conversational AI agents and social platforms generate rich preview cards.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 1,
				'fix_id'         => 'inject_opengraph_tags',
			);
		}

		return $issues;
	}
}
