<?php
/**
 * Measurable GEO (Generative Engine) & AEO (Answer Engine) Readiness Scanner.
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
 * Quantifiably measures a website's readiness for AI conversational models, entity extraction, and direct answers.
 */
class GeoAeoScanner {

	/**
	 * Run deep 4-dimensional GEO/AEO readiness audit.
	 *
	 * @param string $html Rendered HTML markup.
	 * @param string $url Page URL.
	 * @return array{
	 *     total_score: int,
	 *     pillars: array{entity_clarity: int, author_transparency: int, answer_readiness: int, citation_readiness: int},
	 *     issues: array
	 * }
	 */
	public static function audit_geo_aeo( string $html, string $url = '' ): array {
		$issues = array();

		// 1. Entity Clarity (Max 25 pts)
		$entity_score = 0;
		$json_blocks  = SchemaOwnershipDetector::extract_json_ld_blocks( $html );

		$has_org     = false;
		$has_sameas  = false;
		$has_contact = false;

		foreach ( $json_blocks as $block ) {
			$items = isset( $block['@graph'] ) ? $block['@graph'] : array( $block );
			foreach ( $items as $item ) {
				$type = $item['@type'] ?? '';
				if ( 'Organization' === $type || in_array( 'Organization', (array) $type, true ) ) {
					$has_org = true;
					if ( ! empty( $item['sameAs'] ) ) { $has_sameas = true; }
					if ( ! empty( $item['contactPoint'] ) || ! empty( $item['telephone'] ) || ! empty( $item['email'] ) ) { $has_contact = true; }
				}
			}
		}

		if ( $has_org ) { $entity_score += 10; }
		if ( $has_sameas ) { $entity_score += 8; }
		if ( $has_contact ) { $entity_score += 7; }

		if ( ! $has_org ) {
			$issues[] = array(
				'issue_type'     => 'geo_missing_entity_organization',
				'category'       => 'ai_geo',
				'severity'       => 'warning',
				'message'        => __( 'No formal Organization entity declared in structured data.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Declare Organization schema with legal name, official website, and sameAs entity profiles to anchor AI knowledge graphs.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 1,
				'fix_id'         => 'inject_geo_schema',
			);
		}

		// 2. Author Transparency (Max 25 pts)
		$author_score = 0;
		$has_author   = (bool) preg_match( '/\b(author|byline|posted-by)\b/i', $html ) || strpos( $html, '"author"' ) !== false;
		$has_date     = (bool) preg_match( '/\b(dateModified|datePublished|updated|published)\b/i', $html );

		if ( $has_author ) { $author_score += 13; }
		if ( $has_date ) { $author_score += 12; }

		if ( ! $has_author ) {
			$issues[] = array(
				'issue_type'     => 'aeo_missing_author_transparency',
				'category'       => 'ai_geo',
				'severity'       => 'recommendation',
				'message'        => __( 'Lack of declared author attribution on this content.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Include clear author bylines and credentials to satisfy Experience, Expertise, Authoritativeness, and Trustworthiness (E-E-A-T) signals.', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		// 3. Answer Readiness (Max 25 pts)
		$answer_score = 0;
		$has_question_heading = (bool) preg_match( '/<h[2-4]\b[^>]*>(What|How|Why|Where|When|Who|Can|Is)\b.*?\?<\/h[2-4]>/i', $html );
		$has_lists            = (bool) preg_match( '/<(ol|ul)\b[^>]*>/i', $html );
		$has_faq_schema       = strpos( $html, 'FAQPage' ) !== false;

		if ( $has_question_heading ) { $answer_score += 10; }
		if ( $has_lists ) { $answer_score += 8; }
		if ( $has_faq_schema ) { $answer_score += 7; }

		if ( ! $has_question_heading ) {
			$issues[] = array(
				'issue_type'     => 'aeo_no_direct_answer_headings',
				'category'       => 'ai_geo',
				'severity'       => 'recommendation',
				'message'        => __( 'Content does not utilize conversational question-based headings (e.g. "What is...", "How to...").', 'ai-auto-fixer' ),
				'recommendation' => __( 'Structure subheadings around real user questions, followed by concise direct answer paragraphs (40-60 words).', 'ai-auto-fixer' ),
				'risk_level'     => 'low',
				'fix_available'  => 0,
				'fix_id'         => null,
			);
		}

		// 4. Citation & Reference Readiness (Max 25 pts)
		$citation_score = 0;
		$has_tables     = (bool) preg_match( '/<table\b[^>]*>/i', $html );
		$has_ext_links  = (bool) preg_match( '/<a\b[^>]*href=[\'"]https?:\/\/(?!' . preg_quote( wp_parse_url( home_url(), PHP_URL_HOST ), '/' ) . ')[^\'"]+[\'"][^>]*>/i', $html );

		if ( $has_ext_links ) { $citation_score += 15; }
		if ( $has_tables ) { $citation_score += 10; }

		$total = $entity_score + $author_score + $answer_score + $citation_score;

		return array(
			'total_score' => min( 100, max( 0, $total ) ),
			'pillars'     => array(
				'entity_clarity'      => $entity_score,
				'author_transparency' => $author_score,
				'answer_readiness'    => $answer_score,
				'citation_readiness'  => $citation_score,
			),
			'issues'      => $issues,
		);
	}
}
