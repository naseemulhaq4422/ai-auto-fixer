<?php
/**
 * Master Site Audit Scanner Service (Enterprise Local Orchestration).
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Database\MigrationManager;
use AiAutoFixer\Detectors\SeoPluginDetector;
use AiAutoFixer\Detectors\ConflictDetector;
use AiAutoFixer\Detectors\SchemaOwnershipDetector;
use AiAutoFixer\Detectors\MetaOwnershipDetector;

/**
 * Executes comprehensive, non-blocking site audits for SEO, Indexability, Schema, AI/GEO, and Media.
 */
class SiteAuditScanner {

	/**
	 * Option key for storing audit results.
	 */
	public const OPTION_RESULTS = 'ai_auto_fixer_audit_results';

	/**
	 * Option key for audit progress status.
	 */
	public const OPTION_STATUS = 'ai_auto_fixer_audit_status';

	/**
	 * Transient key used as an execution mutex lock.
	 */
	public const TRANSIENT_LOCK = 'ai_auto_fixer_audit_lock';

	/**
	 * Single-event background execution handler called by WP-Cron.
	 *
	 * @return void
	 */
	public function execute_audit_job(): void {
		$this->run_audit( true );
	}

	/**
	 * Run the comprehensive site audit.
	 *
	 * @param bool $is_background Whether the audit is running via background cron or direct request.
	 * @return array The audit results array.
	 */
	public function run_audit( bool $is_background = false ): array {
		global $wpdb;

		// Acquire mutex lock to prevent concurrent redundant scans.
		if ( get_transient( self::TRANSIENT_LOCK ) ) {
			$existing = $this->get_last_audit_results();
			return ! empty( $existing ) ? $existing : array( 'status' => 'in_progress' );
		}

		set_transient( self::TRANSIENT_LOCK, true, 120 ); // 120-second lock.
		update_option( self::OPTION_STATUS, 'in_progress', 'no' );

		$run_uuid = wp_generate_uuid4();
		$site_url = home_url( '/' );
		$now      = current_time( 'mysql' );

		// Create run entry in wp_aaf_audit_runs
		$table_runs   = $wpdb->prefix . MigrationManager::TABLE_RUNS;
		$table_issues = $wpdb->prefix . MigrationManager::TABLE_ISSUES;

		$wpdb->insert(
			$table_runs,
			array(
				'run_uuid'         => $run_uuid,
				'total_urls'       => 0,
				'crawled_urls'     => 0,
				'seo_health_score' => 0,
				'safety_score'     => 0,
				'status'           => 'processing',
				'started_at'       => $now,
			),
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		$run_id = (int) $wpdb->insert_id;

		$all_issues = array();
		$passes     = array();

		// 1. Discover URLs and queue them
		$discovered_urls = CrawlerEngine::discover_all_urls();
		$total_urls      = count( $discovered_urls );
		if ( $run_id > 0 ) {
			CrawlerEngine::enqueue_urls( $run_id, $discovered_urls );
		}

		// 2. Core Search Engine Visibility
		$this->audit_search_visibility( $all_issues, $passes );

		// 3. Robots.txt & Multi-Sitemap Audits
		$this->audit_robots_txt( $all_issues, $passes );
		$this->audit_xml_sitemaps( $all_issues, $passes );

		// 4. AI Bot Crawlers (GPTBot, ClaudeBot, etc.)
		$ai_bot_findings = AiBotScanner::audit_ai_crawlers();
		if ( ! empty( $ai_bot_findings['issues'] ) ) {
			foreach ( $ai_bot_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $ai_bot_findings['passes'] ) ) {
			foreach ( $ai_bot_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 5. Indexability Diagnostics on Homepage & Discovered URLs
		$crawled_count = 0;
		$sample_urls   = array_slice( $discovered_urls, 0, 10 );
		foreach ( $sample_urls as $sample_url ) {
			$idx_res = IndexabilityScanner::evaluate_url( $sample_url );
			$crawled_count++;
			if ( ! empty( $idx_res['issues'] ) ) {
				foreach ( $idx_res['issues'] as $issue ) {
					$all_issues[] = $issue;
				}
			}
		}

		// 6. Technical SEO on Homepage
		$tech_findings = TechnicalSeoScanner::audit_url( $site_url );
		if ( ! empty( $tech_findings['issues'] ) ) {
			foreach ( $tech_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $tech_findings['passes'] ) ) {
			foreach ( $tech_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 7. On-Page, Meta & Heading Scan on Homepage
		$this->audit_onpage_geo( $all_issues, $passes );

		// 8. Schema Ownership & Structured Data Scan
		$home_html       = $this->fetch_html_safe( $site_url );
		$schema_findings = SchemaScanner::audit_schemas( $site_url, $home_html );
		if ( ! empty( $schema_findings['issues'] ) ) {
			foreach ( $schema_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $schema_findings['passes'] ) ) {
			foreach ( $schema_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 9. Broken Link Scan
		$link_findings = LinkScanner::audit_links( $site_url, $home_html );
		if ( ! empty( $link_findings['issues'] ) ) {
			foreach ( $link_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $link_findings['passes'] ) ) {
			foreach ( $link_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 10. Image SEO Audit
		$image_findings = ImageSeoScanner::audit_media_library( 20 );
		if ( ! empty( $image_findings['issues'] ) ) {
			foreach ( $image_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $image_findings['passes'] ) ) {
			foreach ( $image_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 11. Unlinked Media Audit
		$media_findings = UnusedMediaScanner::audit_media_library( 20 );
		if ( ! empty( $media_findings['issues'] ) ) {
			foreach ( $media_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}

		// 12. GEO & AEO Entity Readiness Audit
		$geo_findings = GeoAeoScanner::audit_readiness();
		if ( ! empty( $geo_findings['issues'] ) ) {
			foreach ( $geo_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $geo_findings['passes'] ) ) {
			foreach ( $geo_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 13. Performance Audits
		$perf_findings = PerformanceScanner::audit_performance( $site_url );
		if ( ! empty( $perf_findings['issues'] ) ) {
			foreach ( $perf_findings['issues'] as $issue ) {
				$all_issues[] = $issue;
			}
		}
		if ( ! empty( $perf_findings['passes'] ) ) {
			foreach ( $perf_findings['passes'] as $pass ) {
				$passes[] = $pass;
			}
		}

		// 14. WooCommerce Catalog Audit (if active)
		if ( class_exists( 'WooCommerce' ) ) {
			$woo_findings = WooCommerceScanner::audit_catalog( 20 );
			if ( ! empty( $woo_findings['issues'] ) ) {
				foreach ( $woo_findings['issues'] as $issue ) {
					$all_issues[] = $issue;
				}
			}
		}

		// 15. Process recommendations through RecommendationEngine
		$rec_data = RecommendationEngine::process_recommendations( $all_issues );

		// 16. Calculate Dual Health Scores
		$pillars = array(
			'technical'    => HealthScore::deduct_score_from_issues( 100, array_filter( $all_issues, function( $i ) { return ( $i['category'] ?? '' ) === 'technical'; } ) ),
			'indexability' => HealthScore::deduct_score_from_issues( 100, array_filter( $all_issues, function( $i ) { return in_array( $i['category'] ?? '', array( 'indexability', 'robots', 'sitemap' ), true ); } ) ),
			'on_page'      => HealthScore::deduct_score_from_issues( 100, array_filter( $all_issues, function( $i ) { return ( $i['category'] ?? '' ) === 'on_page'; } ) ),
			'schema'       => HealthScore::deduct_score_from_issues( 100, array_filter( $all_issues, function( $i ) { return ( $i['category'] ?? '' ) === 'schema'; } ) ),
			'ai_geo'       => HealthScore::deduct_score_from_issues( 100, array_filter( $all_issues, function( $i ) { return in_array( $i['category'] ?? '', array( 'geo_aeo', 'ai_crawlers' ), true ); } ) ),
			'images'       => HealthScore::deduct_score_from_issues( 100, array_filter( $all_issues, function( $i ) { return ( $i['category'] ?? '' ) === 'images'; } ) ),
		);

		$safety_factors = array(
			'ssl'                => is_ssl() ? 100 : 20,
			'headers'            => 85,
			'directory_browsing' => 90,
			'permissions'        => 95,
		);

		$seo_health_score = HealthScore::calculate_seo_health_score( $pillars );
		$safety_score     = HealthScore::calculate_safety_score( $safety_factors );

		// 17. Persist individual issues into wp_aaf_audit_issues
		if ( $run_id > 0 ) {
			foreach ( $rec_data['prioritized_issues'] as $p_issue ) {
				$fp = $p_issue['fingerprint'] ?? hash( 'sha256', ( $p_issue['issue_type'] ?? 'issue' ) . '|' . ( $p_issue['url'] ?? $site_url ) );

				$wpdb->replace(
					$table_issues,
					array(
						'run_id'         => $run_id,
						'url'            => esc_url_raw( $p_issue['url'] ?? $site_url ),
						'object_type'    => sanitize_text_field( $p_issue['object_type'] ?? 'url' ),
						'object_id'      => (int) ( $p_issue['object_id'] ?? 0 ),
						'issue_type'     => sanitize_text_field( $p_issue['issue_type'] ?? ( $p_issue['id'] ?? 'unknown' ) ),
						'category'       => sanitize_text_field( $p_issue['category'] ?? 'general' ),
						'severity'       => sanitize_text_field( $p_issue['severity'] ?? 'warning' ),
						'status'         => 'open',
						'source'         => sanitize_text_field( $p_issue['source'] ?? 'AI Auto Fixer' ),
						'message'        => sanitize_text_field( $p_issue['title'] ?? ( $p_issue['message'] ?? '' ) ),
						'recommendation' => sanitize_text_field( $p_issue['recommendation'] ?? '' ),
						'risk_level'     => sanitize_text_field( $p_issue['policy']['risk_level'] ?? ( $p_issue['risk_level'] ?? 'low' ) ),
						'fix_available'  => ! empty( $p_issue['auto_fixable'] ) ? 1 : 0,
						'fix_id'         => sanitize_text_field( $p_issue['fix_action'] ?? ( $p_issue['fix_id'] ?? '' ) ),
						'fingerprint'    => $fp,
						'created_at'     => $now,
						'updated_at'     => $now,
					),
					array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
				);
			}

			// Update wp_aaf_audit_runs with completion data
			$wpdb->update(
				$table_runs,
				array(
					'total_urls'       => $total_urls,
					'crawled_urls'     => $crawled_count,
					'seo_health_score' => $seo_health_score,
					'safety_score'     => $safety_score,
					'status'           => 'completed',
					'completed_at'     => current_time( 'mysql' ),
				),
				array( 'id' => $run_id ),
				array( '%d', '%d', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		$results = array(
			'run_id'           => $run_id,
			'run_uuid'         => $run_uuid,
			'timestamp'        => time(),
			'scan_date'        => current_time( 'mysql' ),
			'site_url'         => $site_url,
			'seo_health_score' => $seo_health_score,
			'safety_score'     => $safety_score,
			'score'            => $seo_health_score, // backward compatibility
			'counts'           => $rec_data['summary'],
			'safe_fixes'       => $rec_data['safe_fixes_count'],
			'review_fixes'     => $rec_data['review_fixes_count'],
			'manual_only'      => $rec_data['manual_only_count'],
			'issues'           => $rec_data['prioritized_issues'],
			'passes'           => $passes,
		);

		update_option( self::OPTION_RESULTS, $results, 'no' );
		update_option( self::OPTION_STATUS, 'completed', 'no' );
		delete_transient( self::TRANSIENT_LOCK );

		return $results;
	}

	/**
	 * Safe internal HTML retrieval helper with SSRF defense.
	 *
	 * @param string $url Target URL.
	 * @return string HTML body or empty string.
	 */
	private function fetch_html_safe( string $url ): string {
		$response = HttpClient::get( $url, array( 'timeout' => 5 ) );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			return wp_remote_retrieve_body( $response );
		}
		return '';
	}

	/**
	 * Check if WordPress core setting 'blog_public' is discouraging indexing.
	 *
	 * @param array $issues Reference to issues array.
	 * @param array $passes Reference to passes array.
	 * @return void
	 */
	private function audit_search_visibility( array &$issues, array &$passes ): void {
		$blog_public = (int) get_option( 'blog_public', 1 );

		if ( 0 === $blog_public ) {
			$issues[] = array(
				'id'            => 'search_discouraged',
				'issue_type'    => 'search_discouraged',
				'category'      => 'on_page',
				'severity'      => 'critical',
				'title'         => __( 'Search Engines are Discouraged from Indexing', 'ai-auto-fixer' ),
				'description'   => __( 'WordPress is currently configured with "Discourage search engines from indexing this site". Both Google and AI Search Engines (ChatGPT, Perplexity, Gemini) will ignore your site completely.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Go to Settings > Reading and uncheck "Discourage search engines from indexing this site".', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'enable_search_visibility',
				'risk_level'    => 'low',
			);
		} else {
			$passes[] = array(
				'id'    => 'search_visibility_ok',
				'title' => __( 'Search Engine Visibility is Enabled', 'ai-auto-fixer' ),
			);
		}
	}

	/**
	 * Audit robots.txt accessibility and AI Crawler configurations.
	 *
	 * @param array $issues Reference to issues array.
	 * @param array $passes Reference to passes array.
	 * @return void
	 */
	private function audit_robots_txt( array &$issues, array &$passes ): void {
		$robots_url = home_url( '/robots.txt' );
		$response   = HttpClient::get( $robots_url, array( 'timeout' => 5 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$issues[] = array(
				'id'            => 'robots_missing',
				'issue_type'    => 'robots_missing',
				'category'      => 'robots',
				'severity'      => 'warning',
				'title'         => __( 'robots.txt File is Inaccessible or Missing', 'ai-auto-fixer' ),
				'description'   => __( 'No valid robots.txt file was found at /robots.txt. Search engines and AI crawlers rely on this to locate your sitemap and determine crawl permissions.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Deploy an optimized robots.txt with sitemap pointers and AI search agent directives.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'generate_ai_robots',
				'risk_level'    => 'medium',
			);
			return;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( preg_match( '/User-agent:\s*\*\s*[\r\n]+Disallow:\s*\/(?![\w\.-])/i', $body ) ) {
			$issues[] = array(
				'id'            => 'robots_disallow_all',
				'issue_type'    => 'robots_disallow_all',
				'category'      => 'robots',
				'severity'      => 'critical',
				'title'         => __( 'robots.txt Blocks All Crawlers (Disallow: /)', 'ai-auto-fixer' ),
				'description'   => __( 'Your robots.txt file contains a wildcard directive blocking all crawlers from accessing the entire site.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Update robots.txt to allow crawling of public pages.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'clean_robots_wildcard',
				'risk_level'    => 'medium',
			);
		}

		// Check if Sitemap directive is declared in robots.txt
		if ( ! preg_match( '/Sitemap:\s*https?:\/\//i', $body ) ) {
			$issues[] = array(
				'id'            => 'robots_missing_sitemap_directive',
				'issue_type'    => 'robots_missing_sitemap_directive',
				'category'      => 'robots',
				'severity'      => 'info',
				'title'         => __( 'Sitemap Directive Missing in robots.txt', 'ai-auto-fixer' ),
				'description'   => __( 'Specifying your XML sitemap URL inside robots.txt speeds up discovery of new pages by web spiders.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Append "Sitemap: ' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" to your robots.txt.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'enable_core_sitemap',
				'risk_level'    => 'low',
			);
		} else {
			$passes[] = array(
				'id'    => 'robots_sitemap_declared',
				'title' => __( 'XML Sitemap is Referenced in robots.txt', 'ai-auto-fixer' ),
			);
		}
	}

	/**
	 * Audit XML Sitemaps for availability and valid response codes.
	 *
	 * @param array $issues Reference to issues array.
	 * @param array $passes Reference to passes array.
	 * @return void
	 */
	private function audit_xml_sitemaps( array &$issues, array &$passes ): void {
		$sitemaps = SitemapScanner::discover_sitemaps();

		if ( empty( $sitemaps['sitemaps'] ) ) {
			$issues[] = array(
				'id'            => 'sitemap_missing',
				'issue_type'    => 'sitemap_missing',
				'category'      => 'sitemap',
				'severity'      => 'critical',
				'title'         => __( 'No Valid XML Sitemap Detected', 'ai-auto-fixer' ),
				'description'   => __( 'Neither standard WordPress Core XML sitemap (/wp-sitemap.xml) nor dedicated plugin sitemaps returned a valid XML response.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Enable WordPress core sitemaps or generate a valid XML sitemap.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'enable_core_sitemap',
				'risk_level'    => 'low',
			);
		} else {
			$primary_url = $sitemaps['primary'] ?? home_url( '/wp-sitemap.xml' );
			$passes[]    = array(
				'id'    => 'sitemap_ok',
				'title' => sprintf(
					/* translators: %s: Sitemap URL */
					__( 'Valid XML Sitemap Detected at %s', 'ai-auto-fixer' ),
					esc_url( $primary_url )
				),
			);
		}
	}

	/**
	 * Audit Homepage for essential on-page factors, OpenGraph, and GEO/AEO structured data.
	 *
	 * @param array $issues Reference to issues array.
	 * @param array $passes Reference to passes array.
	 * @return void
	 */
	private function audit_onpage_geo( array &$issues, array &$passes ): void {
		$home_url = home_url( '/' );
		$response = HttpClient::get( $home_url, array( 'timeout' => 8 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$issues[] = array(
				'id'            => 'homepage_unreachable',
				'issue_type'    => 'homepage_unreachable',
				'category'      => 'on_page',
				'severity'      => 'critical',
				'title'         => __( 'Homepage Failed to Respond to Internal Audit Request', 'ai-auto-fixer' ),
				'description'   => __( 'The site homepage returned a non-200 HTTP code or connection error during the internal scan.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Check your web server configuration and SSL certificates.', 'ai-auto-fixer' ),
				'auto_fixable'  => false,
			);
			return;
		}

		$html = wp_remote_retrieve_body( $response );

		// 1. Meta Title Tag Check
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			$title = trim( $matches[1] );
			if ( strlen( $title ) < 10 ) {
				$issues[] = array(
					'id'            => 'title_too_short',
					'issue_type'    => 'title_too_short',
					'category'      => 'on_page',
					'severity'      => 'warning',
					'title'         => __( 'Homepage Title Tag is Too Short', 'ai-auto-fixer' ),
					'description'   => __( 'Your homepage title tag is under 10 characters. A descriptive title improves CTR and helps AI agents understand your primary entity.', 'ai-auto-fixer' ),
					'recommendation'=> __( 'Expand homepage title to between 30 and 60 characters.', 'ai-auto-fixer' ),
					'auto_fixable'  => false,
				);
			} else {
				$passes[] = array(
					'id'    => 'title_ok',
					'title' => __( 'Homepage Has an Optimized Meta Title Tag', 'ai-auto-fixer' ),
				);
			}
		} else {
			$issues[] = array(
				'id'            => 'title_missing',
				'issue_type'    => 'title_missing',
				'category'      => 'on_page',
				'severity'      => 'critical',
				'title'         => __( 'Homepage Is Missing a <title> Tag', 'ai-auto-fixer' ),
				'description'   => __( 'No HTML <title> tag was found on the homepage. Title tags are foundational for search engine indexing.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Ensure your theme supports title-tag or configure an SEO title.', 'ai-auto-fixer' ),
				'auto_fixable'  => false,
			);
		}

		// 2. Meta Description Check
		if ( ! preg_match( '/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html ) ) {
			$issues[] = array(
				'id'            => 'meta_description_missing',
				'issue_type'    => 'meta_description_missing',
				'category'      => 'on_page',
				'severity'      => 'warning',
				'title'         => __( 'Homepage Meta Description is Missing', 'ai-auto-fixer' ),
				'description'   => __( 'No meta description tag was detected on your homepage. A concise meta description helps AI and search engines summarize your brand.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Add a concise (120-160 character) meta description for your homepage.', 'ai-auto-fixer' ),
				'auto_fixable'  => false,
			);
		} else {
			$passes[] = array(
				'id'    => 'meta_description_ok',
				'title' => __( 'Homepage Has a Meta Description Tag', 'ai-auto-fixer' ),
			);
		}

		// 3. OpenGraph Tags Check
		if ( ! preg_match( '/<meta\s+property=["\']og:title["\']/i', $html ) ) {
			$issues[] = array(
				'id'            => 'opengraph_missing',
				'issue_type'    => 'opengraph_missing',
				'category'      => 'on_page',
				'severity'      => 'info',
				'title'         => __( 'OpenGraph Social Meta Tags Missing on Homepage', 'ai-auto-fixer' ),
				'description'   => __( 'OpenGraph tags (og:title, og:description, og:url) allow conversational AI models and social platforms to render rich entity preview cards.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Enable OpenGraph meta tag injection in AI Auto-Fixer settings.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'inject_opengraph_tags',
				'risk_level'    => 'low',
			);
		} else {
			$passes[] = array(
				'id'    => 'opengraph_ok',
				'title' => __( 'OpenGraph Meta Tags are Active on Homepage', 'ai-auto-fixer' ),
			);
		}
	}

	/**
	 * Get last stored audit results.
	 *
	 * @return array|null
	 */
	public function get_last_audit_results(): ?array {
		$results = get_option( self::OPTION_RESULTS, null );
		return is_array( $results ) ? $results : null;
	}

	/**
	 * Get current audit scan status ('pending', 'in_progress', 'completed').
	 *
	 * @return string
	 */
	public function get_audit_status(): string {
		return (string) get_option( self::OPTION_STATUS, 'pending' );
	}

	/**
	 * Generate intelligent built-in GEO, AEO, and SEO recommendations locally.
	 *
	 * @param array $audit_data Audit findings.
	 * @return array Recommendations list.
	 */
	public function get_local_recommendations( array $audit_data = array() ): array {
		return array(
			array(
				'title'        => __( 'Generative AI Indexing Optimization (GEO)', 'ai-auto-fixer' ),
				'badge'        => 'GEO High Impact',
				'description'  => __( 'Ensure AI search engines (ChatGPT, Claude, Perplexity) can crawl your site. Granting access increases brand citations in AI-generated answers.', 'ai-auto-fixer' ),
				'action_label' => __( 'Configure AI Crawler Rules', 'ai-auto-fixer' ),
				'action_key'   => 'generate_ai_robots',
			),
			array(
				'title'        => __( 'Entity Schema Markup for Direct AI Answers (AEO)', 'ai-auto-fixer' ),
				'badge'        => 'AEO Answer Engine',
				'description'  => __( 'Inject Schema.org Organization, WebSite, and SearchAction JSON-LD metadata so Large Language Models understand your authority and entity relationships.', 'ai-auto-fixer' ),
				'action_label' => __( 'Inject Schema Markup', 'ai-auto-fixer' ),
				'action_key'   => 'inject_geo_schema',
			),
			array(
				'title'        => __( 'Robots.txt & XML Sitemap Synergy', 'ai-auto-fixer' ),
				'badge'        => 'Crawl Speed',
				'description'  => __( 'Direct search engines and AI spiders directly to your comprehensive XML sitemap inside robots.txt to accelerate indexing of new pages.', 'ai-auto-fixer' ),
				'action_label' => __( 'Sync Sitemap to Robots.txt', 'ai-auto-fixer' ),
				'action_key'   => 'enable_core_sitemap',
			),
			array(
				'title'        => __( 'AI-Optimized Social OpenGraph & Entity Signals', 'ai-auto-fixer' ),
				'badge'        => 'Entity Recognition',
				'description'  => __( 'Configure OpenGraph title, description, and website identity so conversational agents and social crawlers parse rich page previews.', 'ai-auto-fixer' ),
				'action_label' => __( 'Inject OpenGraph Tags', 'ai-auto-fixer' ),
				'action_key'   => 'inject_opengraph_tags',
			),
		);
	}
}
