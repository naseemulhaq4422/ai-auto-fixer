<?php
/**
 * Content Quality & Thin Content Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inspects body copy length, word counts, and identifies thin content signals.
 */
class ContentScanner {

	/**
	 * Minimum recommended word count for standard indexable articles.
	 */
	public const MIN_RECOMMENDED_WORDS = 250;

	/**
	 * Audit content quality of a post or HTML body.
	 *
	 * @param string $content Raw content or rendered text.
	 * @param string $post_type Type of post being checked.
	 * @return array List of content issues found.
	 */
	public static function audit_content( string $content, string $post_type = 'post' ): array {
		$issues = array();

		// Strip scripts, styles, HTML tags
		$clean_text = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $content );
		$clean_text = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', (string) $clean_text );
		$plain_text = trim( strip_tags( (string) $clean_text ) );

		$word_count = ! empty( $plain_text ) ? str_word_count( $plain_text ) : 0;

		// Only enforce word counts on standard posts and long-form pages
		if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
			if ( 0 === $word_count ) {
				$issues[] = array(
					'issue_type'     => 'empty_content',
					'category'       => 'content',
					'severity'       => 'critical',
					'message'        => __( 'Page contains zero readable text content.', 'ai-auto-fixer' ),
					'recommendation' => __( 'Provide informative body content before making this page publicly indexable.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			} elseif ( $word_count < self::MIN_RECOMMENDED_WORDS ) {
				$issues[] = array(
					'issue_type'     => 'thin_content',
					'category'       => 'content',
					'severity'       => 'warning',
					'message'        => sprintf(
						/* translators: 1: Current words, 2: Minimum recommended */
						__( 'Thin content detected: %1$d words (recommended minimum: %2$d words).', 'ai-auto-fixer' ),
						$word_count,
						self::MIN_RECOMMENDED_WORDS
					),
					'recommendation' => __( 'Expand article with substantive depth, context, and answers to satisfy user query intent.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		return $issues;
	}
}
