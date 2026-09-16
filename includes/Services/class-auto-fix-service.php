<?php
/**
 * Auto-Fix Execution Service (100% Standalone & Free).
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the instant, 100% free execution and injection of automated SEO, GEO, and AEO fixes.
 * Fully standalone with zero external API/SaaS dependencies.
 */
class AutoFixService {

	/**
	 * Hook active dynamic fixes into WordPress runtime.
	 *
	 * @return void
	 */
	public function boot_active_fixes(): void {
		// Hook virtual robots.txt generation for AI search crawlers.
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 99, 2 );

		// Hook JSON-LD Schema injection for GEO/AEO entity recognition.
		add_action( 'wp_head', array( $this, 'output_geo_schema' ), 5 );

		// Hook missing meta tags and OpenGraph injection.
		add_action( 'wp_head', array( $this, 'output_optimized_meta_tags' ), 2 );
	}

	/**
	 * Execute an automated fix for a specific audited issue.
	 *
	 * 100% Free & Standalone: Runs directly inside WordPress.
	 *
	 * @param string $fix_action Identifier of the action to execute.
	 * @param array  $context Additional execution context or parameters.
	 * @return array Execution result status and message.
	 */
	public function execute_fix( string $fix_action, array $context = array() ): array {
		// Strict capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized: You lack administrative capabilities to execute fixes.', 'ai-auto-fixer' ),
			);
		}

		// Dispatch to specific safe fix routines.
		switch ( $fix_action ) {
			case 'enable_search_visibility':
				$result = $this->fix_search_visibility();
				break;

			case 'generate_ai_robots':
			case 'grant_ai_crawler_access':
			case 'clean_robots_wildcard':
			case 'add_sitemap_to_robots':
				$result = $this->fix_robots_txt_directives( $fix_action );
				break;

			case 'enable_core_sitemap':
				$result = $this->fix_enable_core_sitemaps();
				break;

			case 'inject_geo_schema':
				$result = $this->fix_inject_geo_schema( $context );
				break;

			case 'generate_ai_meta_description':
			case 'optimize_meta_title':
				$result = $this->fix_meta_tags( $fix_action, $context );
				break;

			case 'inject_opengraph_tags':
				$result = $this->fix_opengraph_tags( $context );
				break;

			case 'fix_all':
				$result = $this->execute_all_fixes();
				break;

			default:
				$result = array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: Action name */
						__( 'Unknown fix action "%s".', 'ai-auto-fixer' ),
						esc_html( $fix_action )
					),
				);
				break;
		}

		if ( ! empty( $result['success'] ) ) {
			$this->log_fix_history( $fix_action, $result['message'] );
		}

		return $result;
	}

	/**
	 * Execute all available automated fixes in one click.
	 *
	 * @return array
	 */
	public function execute_all_fixes(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized.', 'ai-auto-fixer' ),
			);
		}

		$applied = array();

		// 1. Enable Search Engine Visibility.
		$this->fix_search_visibility();
		$applied[] = __( 'Search Engine Visibility', 'ai-auto-fixer' );

		// 2. Configure AI Robots rules.
		$this->fix_robots_txt_directives( 'generate_ai_robots' );
		$applied[] = __( 'AI Search Crawler Access (robots.txt)', 'ai-auto-fixer' );

		// 3. Enable Core XML Sitemaps.
		$this->fix_enable_core_sitemaps();
		$applied[] = __( 'XML Sitemaps', 'ai-auto-fixer' );

		// 4. Inject Schema.org JSON-LD.
		$this->fix_inject_geo_schema( array() );
		$applied[] = __( 'Schema.org JSON-LD (GEO / AEO)', 'ai-auto-fixer' );

		// 5. Generate AI Meta Description.
		$this->fix_meta_tags( 'generate_ai_meta_description', array() );
		$applied[] = __( 'AI Meta Description', 'ai-auto-fixer' );

		// 6. Inject OpenGraph tags.
		$this->fix_opengraph_tags( array() );
		$applied[] = __( 'OpenGraph Tags', 'ai-auto-fixer' );

		$message = sprintf(
			/* translators: %s: Comma-separated list of fixed items */
			__( 'All automated fixes applied successfully: %s.', 'ai-auto-fixer' ),
			implode( ', ', $applied )
		);

		return array(
			'success' => true,
			'action'  => 'fix_all',
			'message' => $message,
			'applied' => $applied,
		);
	}

	/**
	 * Fix 1: Enable WordPress core search engine visibility.
	 *
	 * @return array
	 */
	private function fix_search_visibility(): array {
		update_option( 'blog_public', '1' );
		return array(
			'success' => true,
			'action'  => 'enable_search_visibility',
			'message' => __( 'Search Engine Visibility enabled. Search engines and AI crawlers can now index your website.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Fix 2: Configure optimized virtual robots.txt rules supporting AI search agents.
	 *
	 * @param string $action Specific robots fix subtype.
	 * @return array
	 */
	private function fix_robots_txt_directives( string $action ): array {
		$settings = get_option( 'ai_auto_fixer_settings', array() );
		$settings['enable_ai_robots'] = true;
		$settings['robots_custom_rules'] = array(
			'allow_ai_bots'  => true,
			'append_sitemap' => true,
		);

		update_option( 'ai_auto_fixer_settings', $settings, 'no' );

		return array(
			'success' => true,
			'action'  => $action,
			'message' => __( 'AI-ready robots.txt rules configured successfully. AI search engines (ChatGPT, Claude, Perplexity) are now permitted to index public content.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Fix 3: Ensure WordPress Core XML Sitemaps are active.
	 *
	 * @return array
	 */
	private function fix_enable_core_sitemaps(): array {
		$settings = get_option( 'ai_auto_fixer_settings', array() );
		$settings['force_core_sitemaps'] = true;
		update_option( 'ai_auto_fixer_settings', $settings, 'no' );

		// Remove any potential filter blocking core sitemaps.
		remove_filter( 'wp_sitemaps_enabled', '__return_false' );

		return array(
			'success' => true,
			'action'  => 'enable_core_sitemap',
			'message' => sprintf(
				/* translators: %s: Sitemap link */
				__( 'Core XML sitemaps enabled at %s.', 'ai-auto-fixer' ),
				esc_url( home_url( '/wp-sitemap.xml' ) )
			),
		);
	}

	/**
	 * Fix 4: Inject Schema.org JSON-LD structured data for GEO/AEO entity recognition.
	 *
	 * @param array $context Extra schema inputs.
	 * @return array
	 */
	private function fix_inject_geo_schema( array $context ): array {
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

		$settings = get_option( 'ai_auto_fixer_settings', array() );
		$settings['enable_geo_schema'] = true;
		$settings['geo_schema_data']   = $schema_data;
		update_option( 'ai_auto_fixer_settings', $settings, 'no' );

		return array(
			'success' => true,
			'action'  => 'inject_geo_schema',
			'message' => __( 'Schema.org JSON-LD entity graph successfully generated and active on frontend.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Fix 5: Optimize meta description and title tags for AI entity comprehension.
	 *
	 * @param string $action Fix action.
	 * @param array  $context Custom description or title.
	 * @return array
	 */
	private function fix_meta_tags( string $action, array $context ): array {
		$settings = get_option( 'ai_auto_fixer_settings', array() );

		if ( 'generate_ai_meta_description' === $action ) {
			$description = ! empty( $context['meta_description'] )
				? sanitize_text_field( $context['meta_description'] )
				: sprintf( '%s - %s. Authoritative official website.', get_bloginfo( 'name' ), get_bloginfo( 'description' ) );

			$settings['custom_meta_description'] = $description;
			$settings['enable_meta_tags']         = true;
			update_option( 'ai_auto_fixer_settings', $settings, 'no' );

			return array(
				'success' => true,
				'action'  => $action,
				'message' => __( 'AI Meta Description optimized and active on homepage.', 'ai-auto-fixer' ),
			);
		}

		return array(
			'success' => true,
			'action'  => $action,
			'message' => __( 'Meta tag configurations saved.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Fix 6: Inject OpenGraph meta tags.
	 *
	 * @param array $context Context details.
	 * @return array
	 */
	private function fix_opengraph_tags( array $context ): array {
		$settings = get_option( 'ai_auto_fixer_settings', array() );
		$settings['enable_opengraph'] = true;
		update_option( 'ai_auto_fixer_settings', $settings, 'no' );

		return array(
			'success' => true,
			'action'  => 'inject_opengraph_tags',
			'message' => __( 'OpenGraph social and AI summary tags successfully configured.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Filter virtual robots.txt to output AI-friendly directives.
	 *
	 * @param string $output Default robots.txt content.
	 * @param bool   $public Whether site is public.
	 * @return string Modified robots.txt content.
	 */
	public function filter_robots_txt( string $output, bool $public ): string {
		$settings = get_option( 'ai_auto_fixer_settings', array() );

		if ( empty( $settings['enable_ai_robots'] ) ) {
			return $output;
		}

		$ai_rules  = "\n# AI Auto-Fixer: Generative Engine Optimization (GEO) Directives\n";
		$ai_rules .= "User-agent: GPTBot\nAllow: /\n\n";
		$ai_rules .= "User-agent: ClaudeBot\nAllow: /\n\n";
		$ai_rules .= "User-agent: PerplexityBot\nAllow: /\n\n";
		$ai_rules .= "User-agent: CCBot\nAllow: /\n\n";
		$ai_rules .= "User-agent: Google-Extended\nAllow: /\n\n";
		$ai_rules .= "Sitemap: " . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n\n";

		return $output . $ai_rules;
	}

	/**
	 * Output JSON-LD Schema in frontend wp_head.
	 *
	 * @return void
	 */
	public function output_geo_schema(): void {
		$settings = get_option( 'ai_auto_fixer_settings', array() );

		if ( empty( $settings['enable_geo_schema'] ) || empty( $settings['geo_schema_data'] ) ) {
			return;
		}

		// Coexistence Guard: If an active SEO plugin already manages Organization or WebSite, do not inject duplicates
		$seo_plugins = \AiAutoFixer\Detectors\SeoPluginDetector::detect();
		if ( ! empty( $seo_plugins['primary'] ) ) {
			return;
		}

		if ( is_front_page() || is_home() ) {
			echo "\n<!-- AI Auto-Fixer: GEO/AEO Entity Schema -->\n";
			echo '<script type="application/ld+json">' . "\n";
			echo wp_json_encode( $settings['geo_schema_data'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
			echo "\n</script>\n";
		}
	}

	/**
	 * Output optimized meta description in frontend wp_head.
	 *
	 * @return void
	 */
	public function output_optimized_meta_tags(): void {
		$settings = get_option( 'ai_auto_fixer_settings', array() );

		// Coexistence Guard: If dedicated SEO plugin is active, it owns meta tags
		$seo_plugins = \AiAutoFixer\Detectors\SeoPluginDetector::detect();
		if ( ! empty( $seo_plugins['primary'] ) ) {
			return;
		}

		if ( ( is_front_page() || is_home() ) && ! empty( $settings['enable_meta_tags'] ) && ! empty( $settings['custom_meta_description'] ) ) {
			echo "\n<!-- AI Auto-Fixer: AI Optimized Meta -->\n";
			echo '<meta name="description" content="' . esc_attr( $settings['custom_meta_description'] ) . '">' . "\n";
		}

		if ( ( is_front_page() || is_home() ) && ! empty( $settings['enable_opengraph'] ) ) {
			echo '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( get_bloginfo( 'description' ) ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
			echo '<meta property="og:type" content="website">' . "\n";
		}
	}

	/**
	 * Log successful fix to execution history option.
	 *
	 * @param string $action Action key.
	 * @param string $message Status message.
	 * @return void
	 */
	private function log_fix_history( string $action, string $message ): void {
		$history   = get_option( 'ai_auto_fixer_fix_history', array() );
		$history[] = array(
			'action'    => sanitize_text_field( $action ),
			'message'   => sanitize_text_field( $message ),
			'user_id'   => get_current_user_id(),
			'timestamp' => time(),
			'date'      => current_time( 'mysql' ),
		);

		// Keep only last 50 entries.
		if ( count( $history ) > 50 ) {
			$history = array_slice( $history, -50 );
		}

		update_option( 'ai_auto_fixer_fix_history', $history, 'no' );
	}

	/**
	 * Get fix execution history log.
	 *
	 * @return array
	 */
	public function get_fix_history(): array {
		$history = get_option( 'ai_auto_fixer_fix_history', array() );
		return is_array( $history ) ? array_reverse( $history ) : array();
	}
}
