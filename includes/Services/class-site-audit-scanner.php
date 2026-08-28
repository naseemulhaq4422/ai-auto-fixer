<?php
/**
 * Site Audit Scanner Service.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes comprehensive, non-blocking site audits for robots.txt, XML sitemaps, and on-page GEO/AEO factors.
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
	 * Run the site audit.
	 *
	 * @param bool $is_background Whether the audit is running via background cron or direct request.
	 * @return array The audit results array.
	 */
	public function run_audit( bool $is_background = false ): array {
		// Acquire mutex lock to prevent concurrent redundant scans.
		if ( get_transient( self::TRANSIENT_LOCK ) ) {
			$existing = $this->get_last_audit_results();
			return ! empty( $existing ) ? $existing : array( 'status' => 'in_progress' );
		}

		set_transient( self::TRANSIENT_LOCK, true, 60 ); // 60-second lock.
		update_option( self::OPTION_STATUS, 'in_progress', 'no' );

		$issues  = array();
		$passes  = array();
		$site_url = home_url();

		// 1. Audit Search Engine Visibility setting in WordPress.
		$this->audit_search_visibility( $issues, $passes );

		// 2. Audit robots.txt and AI Crawler rules (GEO/AEO).
		$this->audit_robots_txt( $issues, $passes );

		// 3. Audit XML Sitemaps.
		$this->audit_xml_sitemaps( $issues, $passes );

		// 4. Audit On-Page Essentials & GEO/AEO metadata on Homepage.
		$this->audit_onpage_geo( $issues, $passes );

		// Calculate health score (0 - 100).
		$score = $this->calculate_health_score( $issues );

		$results = array(
			'timestamp'   => time(),
			'scan_date'   => current_time( 'mysql' ),
			'site_url'    => $site_url,
			'score'       => $score,
			'counts'      => array(
				'critical' => count(
					array_filter(
						$issues,
						function ( $i ) {
							return 'critical' === $i['severity'];
						}
					)
				),
				'warning'  => count(
					array_filter(
						$issues,
						function ( $i ) {
							return 'warning' === $i['severity'];
						}
					)
				),
				'info'     => count(
					array_filter(
						$issues,
						function ( $i ) {
							return 'info' === $i['severity'];
						}
					)
				),
				'passed'   => count( $passes ),
			),
			'issues'      => $issues,
			'passes'      => $passes,
		);

		update_option( self::OPTION_RESULTS, $results, 'no' );
		update_option( self::OPTION_STATUS, 'completed', 'no' );
		delete_transient( self::TRANSIENT_LOCK );

		return $results;
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
				'category'      => 'on_page',
				'severity'      => 'critical',
				'title'         => __( 'Search Engines are Discouraged from Indexing', 'ai-auto-fixer' ),
				'description'   => __( 'WordPress is currently configured with "Discourage search engines from indexing this site". Both Google and AI Search Engines (ChatGPT, Perplexity, Gemini) will ignore your site completely.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Go to Settings > Reading and uncheck "Discourage search engines from indexing this site".', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'enable_search_visibility',
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

		$response = wp_safe_remote_get(
			$robots_url,
			array(
				'timeout'    => 5,
				'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
				'user-agent' => 'AI-Auto-Fixer-Auditor/' . AI_AUTO_FIXER_VERSION,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$issues[] = array(
				'id'            => 'robots_missing',
				'category'      => 'robots',
				'severity'      => 'warning',
				'title'         => __( 'robots.txt File is Inaccessible or Missing', 'ai-auto-fixer' ),
				'description'   => __( 'No valid robots.txt file was found at /robots.txt. Search engines and AI crawlers rely on this to locate your sitemap and determine crawl permissions.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Deploy an optimized robots.txt with sitemap pointers and AI search agent directives.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'generate_ai_robots',
			);
			return;
		}

		$body = wp_remote_retrieve_body( $response );

		// Check if site is blocking everything with "Disallow: /"
		if ( preg_match( '/User-agent:\s*\*\s*[\r\n]+Disallow:\s*\/(?![\w\.-])/i', $body ) ) {
			$issues[] = array(
				'id'            => 'robots_disallow_all',
				'category'      => 'robots',
				'severity'      => 'critical',
				'title'         => __( 'robots.txt Blocks All Crawlers (Disallow: /)', 'ai-auto-fixer' ),
				'description'   => __( 'Your robots.txt file contains a wildcard directive blocking all crawlers from accessing the entire site.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Update robots.txt to allow crawling of public pages.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'clean_robots_wildcard',
			);
		}

		// Check if AI crawlers (GPTBot, ClaudeBot, PerplexityBot) are blocked
		$ai_bots = array( 'GPTBot', 'CCBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended' );
		$blocked_ai_bots = array();

		foreach ( $ai_bots as $bot ) {
			if ( preg_match( '/User-agent:\s*' . preg_quote( $bot, '/' ) . '\s*[\r\n]+Disallow:\s*\//i', $body ) ) {
				$blocked_ai_bots[] = $bot;
			}
		}

		if ( ! empty( $blocked_ai_bots ) ) {
			$issues[] = array(
				'id'            => 'ai_crawlers_blocked',
				'category'      => 'geo_aeo',
				'severity'      => 'warning',
				'title'         => sprintf(
					/* translators: %s: Comma-separated list of blocked AI bots */
					__( 'AI Search Crawlers Blocked in robots.txt: %s', 'ai-auto-fixer' ),
					implode( ', ', $blocked_ai_bots )
				),
				'description'   => __( 'Your robots.txt explicitly blocks major Generative AI search engines. This prevents your site from being cited as an authoritative source in ChatGPT, Claude, and Perplexity answers.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Allow reputable AI crawlers access to your public content to maximize Generative Engine Optimization (GEO).', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'grant_ai_crawler_access',
			);
		} else {
			$passes[] = array(
				'id'    => 'ai_crawlers_allowed',
				'title' => __( 'AI Search Engines are Permitted in robots.txt', 'ai-auto-fixer' ),
			);
		}

		// Check if Sitemap directive is declared in robots.txt
		if ( ! preg_match( '/Sitemap:\s*https?:\/\//i', $body ) ) {
			$issues[] = array(
				'id'            => 'robots_missing_sitemap_directive',
				'category'      => 'robots',
				'severity'      => 'info',
				'title'         => __( 'Sitemap Directive Missing in robots.txt', 'ai-auto-fixer' ),
				'description'   => __( 'Specifying your XML sitemap URL inside robots.txt speeds up discovery of new pages by web spiders.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Append "Sitemap: ' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" to your robots.txt.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'add_sitemap_to_robots',
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
		$candidates = array(
			home_url( '/wp-sitemap.xml' ),
			home_url( '/sitemap.xml' ),
			home_url( '/sitemap_index.xml' ),
		);

		$found_sitemap = false;
		$working_url   = '';

		foreach ( $candidates as $url ) {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'    => 5,
					'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
					'user-agent' => 'AI-Auto-Fixer-Auditor/' . AI_AUTO_FIXER_VERSION,
				)
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
				if ( stripos( $body, '<urlset' ) !== false || stripos( $body, '<sitemapindex' ) !== false || stripos( $body, 'xml' ) !== false ) {
					$found_sitemap = true;
					$working_url   = $url;
					break;
				}
			}
		}

		if ( ! $found_sitemap ) {
			$issues[] = array(
				'id'            => 'sitemap_missing',
				'category'      => 'sitemap',
				'severity'      => 'critical',
				'title'         => __( 'No Valid XML Sitemap Detected', 'ai-auto-fixer' ),
				'description'   => __( 'Neither standard WordPress Core XML sitemap (/wp-sitemap.xml) nor dedicated plugin sitemaps returned a valid XML response.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Enable WordPress core sitemaps or generate a valid XML sitemap.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'enable_core_sitemap',
			);
		} else {
			$passes[] = array(
				'id'    => 'sitemap_ok',
				'title' => sprintf(
					/* translators: %s: Sitemap URL */
					__( 'Valid XML Sitemap Detected at %s', 'ai-auto-fixer' ),
					esc_url( $working_url )
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

		$response = wp_safe_remote_get(
			$home_url,
			array(
				'timeout'    => 8,
				'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
				'user-agent' => 'AI-Auto-Fixer-Auditor/' . AI_AUTO_FIXER_VERSION,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$issues[] = array(
				'id'            => 'homepage_unreachable',
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
					'category'      => 'on_page',
					'severity'      => 'warning',
					'title'         => __( 'Homepage Title Tag is Too Short', 'ai-auto-fixer' ),
					'description'   => __( 'Your homepage title tag is under 10 characters. A descriptive title improves CTR and helps AI agents understand your primary entity.', 'ai-auto-fixer' ),
					'recommendation'=> __( 'Expand homepage title to between 30 and 60 characters.', 'ai-auto-fixer' ),
					'auto_fixable'  => true,
					'fix_action'    => 'optimize_meta_title',
				);
			} else {
				$passes[] = array(
					'id'    => 'title_ok',
					'title' => __( 'Homepage Meta Title Tag is Present and Well-Formed', 'ai-auto-fixer' ),
				);
			}
		} else {
			$issues[] = array(
				'id'            => 'title_missing',
				'category'      => 'on_page',
				'severity'      => 'critical',
				'title'         => __( 'Homepage Missing <title> Tag', 'ai-auto-fixer' ),
				'description'   => __( 'No <title> tag was found in the homepage HTML document.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Add a proper site title in Settings > General or your theme header.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'optimize_meta_title',
			);
		}

		// 2. Meta Description Tag Check
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/is', $html, $matches ) ||
		     preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/is', $html, $matches ) ) {
			$desc = trim( $matches[1] );
			if ( strlen( $desc ) < 50 ) {
				$issues[] = array(
					'id'            => 'meta_desc_short',
					'category'      => 'on_page',
					'severity'      => 'warning',
					'title'         => __( 'Homepage Meta Description is Missing or Too Brief', 'ai-auto-fixer' ),
					'description'   => __( 'Your meta description has fewer than 50 characters, leaving search and AI summary engines with insufficient context.', 'ai-auto-fixer' ),
					'recommendation'=> __( 'Craft a concise 120-160 character summary of your brand and services.', 'ai-auto-fixer' ),
					'auto_fixable'  => true,
					'fix_action'    => 'generate_ai_meta_description',
				);
			} else {
				$passes[] = array(
					'id'    => 'meta_desc_ok',
					'title' => __( 'Homepage Meta Description is Present', 'ai-auto-fixer' ),
				);
			}
		} else {
			$issues[] = array(
				'id'            => 'meta_desc_missing',
				'category'      => 'on_page',
				'severity'      => 'warning',
				'title'         => __( 'Homepage Missing Meta Description Tag', 'ai-auto-fixer' ),
				'description'   => __( 'No meta description was detected on your homepage.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Add an AI-optimized meta description tag.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'generate_ai_meta_description',
			);
		}

		// 3. Schema.org JSON-LD Structured Data Check (Critical for GEO/AEO)
		if ( preg_match( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html ) ) {
			$passes[] = array(
				'id'    => 'schema_ld_json_ok',
				'title' => __( 'Schema.org JSON-LD Structured Data Detected', 'ai-auto-fixer' ),
			);
		} else {
			$issues[] = array(
				'id'            => 'schema_missing',
				'category'      => 'geo_aeo',
				'severity'      => 'warning',
				'title'         => __( 'Missing Schema.org JSON-LD Structured Data', 'ai-auto-fixer' ),
				'description'   => __( 'AI models like Google Gemini, ChatGPT, and Perplexity heavily rely on JSON-LD (Organization, WebSite, and FAQ Schema) to extract direct factual answers and cite entities accurately.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Inject structured JSON-LD entity markup for WebSite and Organization.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'inject_geo_schema',
			);
		}

		// 4. OpenGraph Social & AI Tags
		if ( ! preg_match( '/<meta[^>]+property=["\']og:title["\']/is', $html ) ||
		     ! preg_match( '/<meta[^>]+property=["\']og:image["\']/is', $html ) ) {
			$issues[] = array(
				'id'            => 'og_tags_incomplete',
				'category'      => 'on_page',
				'severity'      => 'info',
				'title'         => __( 'OpenGraph Social Meta Tags Incomplete', 'ai-auto-fixer' ),
				'description'   => __( 'OpenGraph tags (og:title, og:image) ensure your content formats properly when shared across social channels and conversational AI assistants.', 'ai-auto-fixer' ),
				'recommendation'=> __( 'Configure OpenGraph title, description, and preview image tags.', 'ai-auto-fixer' ),
				'auto_fixable'  => true,
				'fix_action'    => 'inject_opengraph_tags',
			);
		} else {
			$passes[] = array(
				'id'    => 'og_tags_ok',
				'title' => __( 'OpenGraph Tags are Configured', 'ai-auto-fixer' ),
			);
		}
	}

	/**
	 * Compute overall site health & GEO readiness score (0 - 100).
	 *
	 * @param array $issues List of identified issues.
	 * @return int Health score between 0 and 100.
	 */
	private function calculate_health_score( array $issues ): int {
		$score = 100;

		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ) {
				case 'critical':
					$score -= 25;
					break;
				case 'warning':
					$score -= 10;
					break;
				case 'info':
					$score -= 4;
					break;
			}
		}

		return (int) max( 10, min( 100, $score ) );
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
}
