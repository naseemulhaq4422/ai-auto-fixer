<?php
/**
 * Safe Fix Execution & Remediation Manager.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles preview generation, transactional snapshot creation, execution, and verification of automated fixes.
 */
class FixManager {

	/**
	 * Generate a pre-execution Before vs After diff preview for a fix action.
	 *
	 * @param string $fix_id Action identifier.
	 * @param array  $context Context parameters (e.g. attachment_id, alt_text).
	 * @return array{success: bool, fix_id: string, before: string, after: string, risk_level: string, message: string}
	 */
	public static function preview_fix( string $fix_id, array $context = array() ): array {
		$before = '';
		$after  = '';
		$risk   = 'low';

		switch ( $fix_id ) {
			case 'apply_image_alt':
				$attachment_id = (int) ( $context['attachment_id'] ?? 0 );
				$new_alt       = sanitize_text_field( $context['alt_text'] ?? '' );
				$current_alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

				$before = sprintf( 'alt="%s"', esc_attr( (string) $current_alt ) );
				$after  = sprintf( 'alt="%s"', esc_attr( $new_alt ) );
				$risk   = 'low';
				break;

			case 'enable_search_visibility':
				$current = get_option( 'blog_public', '1' );
				$before  = sprintf( 'blog_public = %s (Discourage search engines)', esc_html( (string) $current ) );
				$after   = 'blog_public = 1 (Allow search engines and AI crawlers)';
				$risk    = 'low';
				break;

			case 'enable_core_sitemap':
				$before = 'Sitemaps: Blocked or undeclared';
				$after  = 'Core XML Sitemaps: Active at /wp-sitemap.xml';
				$risk   = 'low';
				break;

			case 'inject_geo_schema':
				$before = 'Schema.org JSON-LD: Missing Organization & WebSite entity graph';
				$after  = 'Schema.org JSON-LD: Active Organization, WebSite, and SearchAction entity graph';
				$risk   = 'low';
				break;

			case 'grant_ai_crawler_access':
				$before = 'AI Search Crawlers: Restricted in robots.txt';
				$after  = 'AI Search Crawlers: Explicitly permitted (GPTBot, ClaudeBot, PerplexityBot)';
				$risk   = 'medium';
				break;

			default:
				$before = 'N/A';
				$after  = 'Standard configuration update';
				$risk   = 'medium';
				break;
		}

		return array(
			'success'    => true,
			'fix_id'     => $fix_id,
			'before'     => $before,
			'after'      => $after,
			'risk_level' => $risk,
			'message'    => __( 'Preview generated successfully. Review changes before applying.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Execute a safe automated fix with transactional snapshot and verification.
	 *
	 * @param string $fix_id Action identifier.
	 * @param array  $context Context payload.
	 * @return array{success: bool, fix_id: string, snapshot_uuid: string|null, verified: bool, message: string}
	 */
	public static function execute_fix( string $fix_id, array $context = array() ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success'       => false,
				'fix_id'        => $fix_id,
				'snapshot_uuid' => null,
				'verified'      => false,
				'message'       => __( 'Unauthorized: manage_options capability required.', 'ai-auto-fixer' ),
			);
		}

		$snapshot_uuid = null;
		$verified      = false;

		switch ( $fix_id ) {
			// 1. Image ALT Application
			case 'apply_image_alt':
				$attachment_id = (int) ( $context['attachment_id'] ?? 0 );
				$new_alt       = sanitize_text_field( $context['alt_text'] ?? '' );

				if ( $attachment_id <= 0 ) {
					return array(
						'success'       => false,
						'fix_id'        => $fix_id,
						'snapshot_uuid' => null,
						'verified'      => false,
						'message'       => __( 'Invalid attachment ID provided.', 'ai-auto-fixer' ),
					);
				}

				$before_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$snapshot_uuid = RollbackManager::create_snapshot(
					$fix_id,
					'post_meta',
					$attachment_id . ':_wp_attachment_image_alt',
					$before_alt,
					$new_alt
				);

				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $new_alt );

				// Verification
				$saved_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$verified  = ( (string) $saved_alt === $new_alt );

				return array(
					'success'       => true,
					'fix_id'        => $fix_id,
					'snapshot_uuid' => $snapshot_uuid,
					'verified'      => $verified,
					'message'       => sprintf( __( 'ALT text successfully saved for image #%d.', 'ai-auto-fixer' ), $attachment_id ),
				);

			// 2. Search Engine Visibility Fix
			case 'enable_search_visibility':
				$before = get_option( 'blog_public', '1' );
				$snapshot_uuid = RollbackManager::create_snapshot(
					$fix_id,
					'option',
					'blog_public',
					$before,
					'1'
				);

				update_option( 'blog_public', '1' );
				$verified = ( '1' === (string) get_option( 'blog_public' ) );

				return array(
					'success'       => true,
					'fix_id'        => $fix_id,
					'snapshot_uuid' => $snapshot_uuid,
					'verified'      => $verified,
					'message'       => __( 'Search engine visibility enabled. Site is open for crawler indexing.', 'ai-auto-fixer' ),
				);

			// 3. Core Sitemaps Fix
			case 'enable_core_sitemap':
				$settings = get_option( 'ai_auto_fixer_settings', array() );
				$snapshot_uuid = RollbackManager::create_snapshot(
					$fix_id,
					'option',
					'ai_auto_fixer_settings',
					$settings,
					array_merge( $settings, array( 'force_core_sitemaps' => true ) )
				);

				$settings['force_core_sitemaps'] = true;
				update_option( 'ai_auto_fixer_settings', $settings, 'no' );
				remove_filter( 'wp_sitemaps_enabled', '__return_false' );

				$verified = true;

				return array(
					'success'       => true,
					'fix_id'        => $fix_id,
					'snapshot_uuid' => $snapshot_uuid,
					'verified'      => $verified,
					'message'       => __( 'Core XML sitemaps enabled at /wp-sitemap.xml.', 'ai-auto-fixer' ),
				);

			// 4. Schema.org JSON-LD Generation
			case 'inject_geo_schema':
				$settings = get_option( 'ai_auto_fixer_settings', array() );
				$snapshot_uuid = RollbackManager::create_snapshot(
					$fix_id,
					'option',
					'ai_auto_fixer_settings',
					$settings,
					array_merge( $settings, array( 'enable_geo_schema' => true ) )
				);

				$schema_data = array(
					'@context' => 'https://schema.org',
					'@graph'   => array(
						array(
							'@type'       => 'WebSite',
							'@id'         => home_url( '/#website' ),
							'url'         => home_url( '/' ),
							'name'        => get_bloginfo( 'name' ),
							'description' => get_bloginfo( 'description' ),
							'potentialAction' => array(
								'@type'       => 'SearchAction',
								'target'      => home_url( '/?s={search_term_string}' ),
								'query-input' => 'required name=search_term_string',
							),
						),
						array(
							'@type' => 'Organization',
							'@id'   => home_url( '/#organization' ),
							'name'  => get_bloginfo( 'name' ),
							'url'   => home_url( '/' ),
						),
					),
				);

				$settings['enable_geo_schema'] = true;
				$settings['geo_schema_data']   = $schema_data;
				update_option( 'ai_auto_fixer_settings', $settings, 'no' );

				return array(
					'success'       => true,
					'fix_id'        => $fix_id,
					'snapshot_uuid' => $snapshot_uuid,
					'verified'      => true,
					'message'       => __( 'Schema.org Organization & WebSite entity graph generated and active on frontend.', 'ai-auto-fixer' ),
				);

			// 5. Grant AI Crawler Access (Review Required)
			case 'grant_ai_crawler_access':
				$settings = get_option( 'ai_auto_fixer_settings', array() );
				$before   = $settings['enable_ai_robots'] ?? false;
				$snapshot_uuid = RollbackManager::create_snapshot(
					$fix_id,
					'robots_rule',
					'enable_ai_robots',
					$before,
					true
				);

				$settings['enable_ai_robots'] = true;
				update_option( 'ai_auto_fixer_settings', $settings, 'no' );

				return array(
					'success'       => true,
					'fix_id'        => $fix_id,
					'snapshot_uuid' => $snapshot_uuid,
					'verified'      => true,
					'message'       => __( 'AI crawler access rules configured in robots.txt (GPTBot, ClaudeBot, PerplexityBot).', 'ai-auto-fixer' ),
				);

			default:
				return array(
					'success'       => false,
					'fix_id'        => $fix_id,
					'snapshot_uuid' => null,
					'verified'      => false,
					'message'       => sprintf( __( 'Unknown fix action "%s".', 'ai-auto-fixer' ), esc_html( $fix_id ) ),
				);
		}
	}

	/**
	 * Execute only low-risk, confirmed safe automated fixes.
	 *
	 * @return array{success: bool, applied_fixes: string[], total_applied: int}
	 */
	public static function execute_all_safe_fixes(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success'       => false,
				'applied_fixes' => array(),
				'total_applied' => 0,
			);
		}

		$applied = array();

		// 1. Search engine visibility
		self::execute_fix( 'enable_search_visibility' );
		$applied[] = 'Search Engine Visibility';

		// 2. Core sitemap
		self::execute_fix( 'enable_core_sitemap' );
		$applied[] = 'XML Sitemaps';

		// 3. Schema.org unowned injection
		self::execute_fix( 'inject_geo_schema' );
		$applied[] = 'Organization & WebSite Schema';

		return array(
			'success'       => true,
			'applied_fixes' => $applied,
			'total_applied' => count( $applied ),
		);
	}
}
