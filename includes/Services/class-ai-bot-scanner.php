<?php
/**
 * Configurable AI Search & LLM Crawler Registry and Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages an extensible registry of AI search agents and LLM scrapers, auditing their robots.txt permissions.
 */
class AiBotScanner {

	/**
	 * Option key storing dynamic bot registry.
	 */
	public const OPTION_REGISTRY = 'ai_auto_fixer_ai_bots';

	/**
	 * Default AI bot definitions.
	 */
	public const DEFAULT_BOTS = array(
		'gptbot' => array(
			'name'         => 'GPTBot',
			'user_agent'   => 'GPTBot',
			'organization' => 'OpenAI',
			'purpose'      => 'AI Training & Citation',
			'category'     => 'ai_training',
			'default_rule' => 'allow',
		),
		'oai_searchbot' => array(
			'name'         => 'OAI-SearchBot',
			'user_agent'   => 'OAI-SearchBot',
			'organization' => 'OpenAI',
			'purpose'      => 'ChatGPT Search Engine',
			'category'     => 'ai_search',
			'default_rule' => 'allow',
		),
		'claudebot' => array(
			'name'         => 'ClaudeBot',
			'user_agent'   => 'ClaudeBot',
			'organization' => 'Anthropic',
			'purpose'      => 'Claude AI Model Training',
			'category'     => 'ai_training',
			'default_rule' => 'allow',
		),
		'perplexitybot' => array(
			'name'         => 'PerplexityBot',
			'user_agent'   => 'PerplexityBot',
			'organization' => 'Perplexity AI',
			'purpose'      => 'Conversational Search Indexing',
			'category'     => 'ai_search',
			'default_rule' => 'allow',
		),
		'ccbot' => array(
			'name'         => 'CCBot',
			'user_agent'   => 'CCBot',
			'organization' => 'Common Crawl',
			'purpose'      => 'Open Web Crawl Dataset',
			'category'     => 'common_crawl',
			'default_rule' => 'allow',
		),
		'google_extended' => array(
			'name'         => 'Google-Extended',
			'user_agent'   => 'Google-Extended',
			'organization' => 'Google',
			'purpose'      => 'Gemini & Vertex AI Training',
			'category'     => 'ai_training',
			'default_rule' => 'allow',
		),
	);

	/**
	 * Retrieve active AI Bot registry (merges saved option with defaults).
	 *
	 * @return array
	 */
	public static function get_registry(): array {
		$saved = get_option( self::OPTION_REGISTRY, array() );
		return is_array( $saved ) && ! empty( $saved )
			? wp_parse_args( $saved, self::DEFAULT_BOTS )
			: self::DEFAULT_BOTS;
	}

	/**
	 * Direct alias for audit_ai_bots().
	 *
	 * @param string|null $robots_txt Optional pre-fetched robots.txt content.
	 * @return array
	 */
	public static function audit_ai_crawlers( ?string $robots_txt = null ): array {
		return self::audit_ai_bots( $robots_txt );
	}

	/**
	 * Audit all registered AI search crawlers against active robots.txt directives.
	 *
	 * @param string|null $robots_txt Optional pre-fetched robots.txt content.
	 * @return array{
	 *     bots: array,
	 *     allowed_count: int,
	 *     blocked_count: int,
	 *     issues: array
	 * }
	 */
	public static function audit_ai_bots( ?string $robots_txt = null ): array {
		if ( null === $robots_txt ) {
			$res = HttpClient::safe_get( home_url( '/robots.txt' ) );
			$robots_txt = ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) )
				? wp_remote_retrieve_body( $res )
				: '';
		}

		$registry = self::get_registry();
		$bot_eval = array();
		$issues   = array();
		$allowed  = 0;
		$blocked  = 0;

		foreach ( $registry as $key => $bot ) {
			$ua     = $bot['user_agent'];
			$status = self::parse_bot_status( $ua, $robots_txt );

			$bot_eval[ $key ] = array_merge( $bot, array(
				'status'      => $status['status'], // 'allowed', 'blocked', 'default'
				'rule'        => $status['rule'],
				'is_blocked'  => ( 'blocked' === $status['status'] ),
			) );

			if ( 'blocked' === $status['status'] ) {
				$blocked++;
				$issues[] = array(
					'issue_type'     => 'ai_bot_blocked_' . $key,
					'category'       => 'ai_geo',
					'severity'       => 'warning',
					'message'        => sprintf(
						/* translators: 1: Bot name, 2: Purpose */
						__( 'AI Search Crawler "%1$s" (%2$s) is blocked in robots.txt.', 'ai-auto-fixer' ),
						esc_html( $bot['name'] ),
						esc_html( $bot['purpose'] )
					),
					'recommendation' => __( 'Review before allowing: If you wish to receive citations in AI-generated answers, permit this bot in robots.txt.', 'ai-auto-fixer' ),
					'risk_level'     => 'medium',
					'fix_available'  => 1,
					'fix_id'         => 'grant_ai_crawler_access',
				);
			} else {
				$allowed++;
			}
		}

		return array(
			'bots'          => $bot_eval,
			'allowed_count' => $allowed,
			'blocked_count' => $blocked,
			'issues'        => $issues,
		);
	}

	/**
	 * Parse robots.txt string for specific user agent directives.
	 *
	 * @param string $user_agent Crawler user agent name.
	 * @param string $robots_content Full robots.txt content.
	 * @return array{status: string, rule: string}
	 */
	public static function parse_bot_status( string $user_agent, string $robots_content ): array {
		if ( empty( $robots_content ) ) {
			return array( 'status' => 'default', 'rule' => 'No robots.txt found (default allow)' );
		}

		$lines   = explode( "\n", $robots_content );
		$in_block = false;

		foreach ( $lines as $line ) {
			$clean = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( empty( $clean ) ) {
				continue;
			}

			if ( preg_match( '/^User-agent:\s*(.+)$/i', $clean, $m ) ) {
				$ua = trim( $m[1] );
				$in_block = ( 0 === strcasecmp( $ua, $user_agent ) );
				continue;
			}

			if ( $in_block ) {
				if ( preg_match( '/^Disallow:\s*(\/.*)?$/i', $clean, $m ) ) {
					$dis = trim( $m[1] ?? '' );
					if ( '/' === $dis || '' === $dis ) {
						return array( 'status' => 'blocked', 'rule' => $clean );
					}
				} elseif ( preg_match( '/^Allow:\s*(\/.*)?$/i', $clean, $m ) ) {
					return array( 'status' => 'allowed', 'rule' => $clean );
				}
			}
		}

		// Fallback check against wildcard User-agent: *
		if ( IndexabilityScanner::is_blocked_by_robots( home_url( '/' ), $robots_content ) ) {
			return array( 'status' => 'blocked', 'rule' => 'Disallow: / (under User-agent: *)' );
		}

		return array( 'status' => 'default', 'rule' => 'Inherits default allow' );
	}
}
