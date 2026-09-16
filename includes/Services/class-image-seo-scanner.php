<?php
/**
 * Image SEO Audit & Smart Contextual ALT Engine.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audits image SEO quality (ALT, dimensions, broken files) and generates contextual ALT text with confidence ratings.
 */
class ImageSeoScanner {

	/**
	 * Common generic phrases that trigger weak/generic ALT warnings.
	 */
	public const GENERIC_ALT_PATTERNS = array(
		'image', 'photo', 'picture', 'graphic', 'icon', 'img', 'banner', 'screenshot', 'logo', 'placeholder',
	);

	/**
	 * Audit images contained in HTML content.
	 *
	 * @param string $html Rendered HTML markup.
	 * @param string $source_url Page URL.
	 * @return array List of image SEO issues found.
	 */
	public static function audit_html_images( string $html, string $source_url = '' ): array {
		$issues = array();

		if ( ! preg_match_all( '/<img\b([^>]*)>/is', $html, $matches ) ) {
			return $issues;
		}

		foreach ( $matches[1] as $img_tag_attrs ) {
			// Extract src
			if ( ! preg_match( '/\bsrc=[\'"]([^\'"]+)[\'"]/i', $img_tag_attrs, $src_m ) ) {
				continue;
			}
			$src = trim( $src_m[1] );

			// Extract alt
			$has_alt = (bool) preg_match( '/\balt=[\'"](.*?)[\'"]/is', $img_tag_attrs, $alt_m );
			$alt     = $has_alt ? trim( html_entity_decode( $alt_m[1], ENT_QUOTES, 'UTF-8' ) ) : null;

			// Extract width/height
			$has_width  = (bool) preg_match( '/\bwidth=[\'"][^\'"]+[\'"]/i', $img_tag_attrs );
			$has_height = (bool) preg_match( '/\bheight=[\'"][^\'"]+[\'"]/i', $img_tag_attrs );

			// 1. Missing or Empty ALT
			if ( ! $has_alt ) {
				$issues[] = array(
					'issue_type'     => 'missing_alt',
					'category'       => 'image_seo',
					'severity'       => 'warning',
					'message'        => sprintf( __( 'Image is missing an alt attribute: %s', 'ai-auto-fixer' ), esc_url( $src ) ),
					'recommendation' => __( 'Descriptive ALT text improves accessibility and helps search engines understand image context.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 1,
					'fix_id'         => 'apply_image_alt',
				);
			} elseif ( '' === $alt ) {
				// Empty ALT is acceptable only if intentionally decorative
				$issues[] = array(
					'issue_type'     => 'empty_alt',
					'category'       => 'image_seo',
					'severity'       => 'recommendation',
					'message'        => sprintf( __( 'Image has an empty alt="" attribute: %s', 'ai-auto-fixer' ), esc_url( $src ) ),
					'recommendation' => __( 'If decorative, empty ALT is valid. Otherwise, provide a succinct description of the image content.', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 1,
					'fix_id'         => 'apply_image_alt',
				);
			} else {
				// Evaluate Weak / Generic / Keyword Stuffed ALT
				$alt_quality = self::evaluate_alt_quality( $alt );
				if ( ! empty( $alt_quality['issue'] ) ) {
					$issues[] = array(
						'issue_type'     => $alt_quality['type'],
						'category'       => 'image_seo',
						'severity'       => 'warning',
						'message'        => sprintf( '%s (%s): "%s"', $alt_quality['message'], esc_url( $src ), esc_html( $alt ) ),
						'recommendation' => $alt_quality['recommendation'],
						'risk_level'     => 'low',
						'fix_available'  => 1,
						'fix_id'         => 'apply_image_alt',
					);
				}
			}

			// 2. Missing Dimensions
			if ( ! $has_width || ! $has_height ) {
				$issues[] = array(
					'issue_type'     => 'missing_image_dimensions',
					'category'       => 'performance',
					'severity'       => 'recommendation',
					'message'        => sprintf( __( 'Image lacks explicit width/height HTML attributes: %s', 'ai-auto-fixer' ), esc_url( $src ) ),
					'recommendation' => __( 'Add width and height attributes to eliminate Cumulative Layout Shift (CLS).', 'ai-auto-fixer' ),
					'risk_level'     => 'low',
					'fix_available'  => 0,
					'fix_id'         => null,
				);
			}
		}

		return $issues;
	}

	/**
	 * Evaluate the quality and semantic validity of an ALT text string.
	 *
	 * @param string $alt ALT attribute text.
	 * @return array{issue: bool, type: string, message: string, recommendation: string}
	 */
	public static function evaluate_alt_quality( string $alt ): array {
		$clean = strtolower( trim( $alt ) );

		// Check filename pattern (e.g. IMG_1234, photo_2024.jpg)
		if ( preg_match( '/^(img|dsc|dcm|photo|image|pic)[_\-\d]+/i', $clean ) || preg_match( '/\.(jpg|png|webp|gif|jpeg)$/i', $clean ) ) {
			return array(
				'issue'          => true,
				'type'           => 'filename_alt',
				'message'        => __( 'ALT text resembles a raw file name rather than a semantic description.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Replace file name with a human description of what appears in the image.', 'ai-auto-fixer' ),
			);
		}

		// Check generic single-word patterns
		if ( in_array( $clean, self::GENERIC_ALT_PATTERNS, true ) ) {
			return array(
				'issue'          => true,
				'type'           => 'generic_alt',
				'message'        => __( 'Generic non-descriptive ALT text detected.', 'ai-auto-fixer' ),
				'recommendation' => __( 'Use a descriptive phrase explaining the subject and context of the image.', 'ai-auto-fixer' ),
			);
		}

		// Check keyword stuffing (repeating word >= 3 times)
		$words = str_word_count( $clean, 1 );
		$word_freq = array_count_values( $words );
		foreach ( $word_freq as $word => $count ) {
			if ( $count >= 3 && strlen( $word ) > 3 ) {
				return array(
					'issue'          => true,
					'type'           => 'keyword_stuffed_alt',
					'message'        => sprintf( __( 'Unnatural repetition of keyword "%s" detected in ALT text.', 'ai-auto-fixer' ), esc_html( $word ) ),
					'recommendation' => __( 'Write natural sentences without repeating keywords for artificial ranking purposes.', 'ai-auto-fixer' ),
				);
			}
		}

		// Overly long ALT (> 125 chars)
		if ( mb_strlen( $alt ) > 125 ) {
			return array(
				'issue'          => true,
				'type'           => 'long_alt',
				'message'        => sprintf( __( 'ALT text is overly long (%d characters).', 'ai-auto-fixer' ), mb_strlen( $alt ) ),
				'recommendation' => __( 'Keep ALT text concise (typically under 100–125 characters) to benefit screen readers.', 'ai-auto-fixer' ),
			);
		}

		return array(
			'issue'          => false,
			'type'           => '',
			'message'        => '',
			'recommendation' => '',
		);
	}

	/**
	 * Generate contextual Smart ALT text with a calculated confidence score.
	 *
	 * @param int $attachment_id Media attachment ID.
	 * @param int $parent_post_id Parent post or product ID (optional).
	 * @return array{suggested_alt: string, confidence: int, source: string, requires_review: bool}
	 */
	public static function generate_smart_alt( int $attachment_id, int $parent_post_id = 0 ): array {
		$confidence = 0;
		$components = array();
		$sources    = array();

		// 1. Attachment Title
		$title = get_the_title( $attachment_id );
		if ( ! empty( $title ) && ! preg_match( '/^(img|dsc|photo|image|banner)[_\-\d]+/i', $title ) ) {
			$clean_title = sanitize_text_field( $title );
			$components[] = $clean_title;
			$sources[]    = 'attachment title';
			$confidence  += 25;
		}

		// 2. Attachment Caption
		$post = get_post( $attachment_id );
		if ( $post && ! empty( $post->post_excerpt ) ) {
			$caption = sanitize_text_field( $post->post_excerpt );
			$components[] = $caption;
			$sources[]    = 'caption';
			$confidence  += 25;
		}

		// 3. Parent Post / WooCommerce Product Context
		if ( 0 === $parent_post_id && $post && ! empty( $post->post_parent ) ) {
			$parent_post_id = $post->post_parent;
		}

		if ( $parent_post_id > 0 ) {
			$parent_title = get_the_title( $parent_post_id );
			$parent_type  = get_post_type( $parent_post_id );

			if ( ! empty( $parent_title ) ) {
				if ( 'product' === $parent_type ) {
					$components[] = $parent_title;
					$sources[]    = 'WooCommerce product name';
					$confidence  += 35;
				} else {
					$components[] = $parent_title;
					$sources[]    = 'parent article topic';
					$confidence  += 25;
				}
			}
		}

		// 4. Filename synthesis fallback
		$file_path = get_attached_file( $attachment_id );
		if ( empty( $components ) && $file_path ) {
			$filename = pathinfo( $file_path, PATHINFO_FILENAME );
			$clean_fn = trim( (string) preg_replace( '/[_\-]+/', ' ', $filename ) );

			if ( ! empty( $clean_fn ) && ! preg_match( '/^(img|dsc|dcm|photo)[_\-\d]+/i', $clean_fn ) ) {
				$components[] = ucwords( $clean_fn );
				$sources[]    = 'cleaned filename';
				$confidence  += 20;
			}
		}

		// Synthesize suggestion
		if ( ! empty( $components ) ) {
			$unique_parts = array_unique( $components );
			$suggested    = implode( ' - ', array_slice( $unique_parts, 0, 2 ) );
		} else {
			$suggested   = __( 'Descriptive photo illustration', 'ai-auto-fixer' );
			$sources[]   = 'generic fallback';
			$confidence  = 20;
		}

		$final_confidence = min( 100, max( 10, $confidence ) );
		$requires_review  = $final_confidence < 70;

		return array(
			'suggested_alt'   => $suggested,
			'confidence'      => $final_confidence,
			'source'          => implode( ' + ', $sources ),
			'requires_review' => $requires_review,
		);
	}

	/**
	 * Audit WordPress Media Library attachments for missing or low-quality ALT text.
	 *
	 * @param int $limit Maximum number of media items to audit.
	 * @return array{audited_count: int, issues: array, passes: array}
	 */
	public static function audit_media_library( int $limit = 50 ): array {
		$query_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		);

		$attachments = get_posts( $query_args );
		$issues      = array();
		$passes      = array();
		$audited     = 0;

		foreach ( $attachments as $attachment_id ) {
			$audited++;
			$attachment_id = (int) $attachment_id;
			$alt           = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$title         = get_the_title( $attachment_id );
			$url           = wp_get_attachment_url( $attachment_id );

			if ( empty( $alt ) ) {
				$suggestion = self::generate_smart_alt( $attachment_id );
				$issues[]   = array(
					'id'             => 'missing_alt_' . $attachment_id,
					'object_id'      => $attachment_id,
					'object_type'    => 'media',
					'issue_type'     => 'missing_alt',
					'category'       => 'images',
					'severity'       => 'warning',
					'title'          => sprintf( __( 'Missing ALT Text: %s', 'ai-auto-fixer' ), esc_html( $title ?: basename( (string) $url ) ) ),
					'message'        => sprintf( __( 'Image "%s" is missing an ALT attribute.', 'ai-auto-fixer' ), esc_html( $title ) ),
					'description'    => sprintf( __( 'Image "%s" is missing an ALT attribute.', 'ai-auto-fixer' ), esc_html( $title ) ),
					'recommendation' => sprintf( __( 'Suggested ALT: "%s" (%d%% confidence).', 'ai-auto-fixer' ), esc_html( $suggestion['suggested_alt'] ), $suggestion['confidence'] ),
					'suggested_alt'  => $suggestion['suggested_alt'],
					'confidence'     => $suggestion['confidence'],
					'risk_level'     => 'low',
					'auto_fixable'   => true,
					'fix_available'  => 1,
					'fix_id'         => 'apply_image_alt',
					'url'            => $url,
				);
			} else {
				$quality = self::evaluate_alt_quality( (string) $alt );
				if ( $quality['issue'] ) {
					$suggestion = self::generate_smart_alt( $attachment_id );
					$issues[]   = array(
						'id'             => 'weak_alt_' . $attachment_id,
						'object_id'      => $attachment_id,
						'object_type'    => 'media',
						'issue_type'     => $quality['type'],
						'category'       => 'images',
						'severity'       => 'recommendation',
						'title'          => sprintf( __( 'Weak ALT Text: %s', 'ai-auto-fixer' ), esc_html( $title ) ),
						'message'        => $quality['message'],
						'description'    => $quality['message'],
						'recommendation' => $quality['recommendation'],
						'suggested_alt'  => $suggestion['suggested_alt'],
						'confidence'     => $suggestion['confidence'],
						'risk_level'     => 'low',
						'auto_fixable'   => true,
						'fix_available'  => 1,
						'fix_id'         => 'apply_image_alt',
						'url'            => $url,
					);
				} else {
					$passes[] = array(
						'title'       => sprintf( __( 'Optimized ALT Text: %s', 'ai-auto-fixer' ), esc_html( $title ) ),
						'description' => esc_html( (string) $alt ),
					);
				}
			}
		}

		return array(
			'audited_count' => $audited,
			'issues'        => $issues,
			'passes'        => $passes,
		);
	}
}
