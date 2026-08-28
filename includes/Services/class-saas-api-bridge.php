<?php
/**
 * SaaS AI API Bridge Service.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles secure, fault-tolerant communication with the Next.js SaaS backend.
 */
class SaasApiBridge {

	/**
	 * Next.js SaaS API License Verification Endpoint URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://app.creativesdigitalagency.com/api/v1/license/verify';

	/**
	 * Next.js SaaS Base Purchase / Dashboard URL.
	 *
	 * @var string
	 */
	public const SAAS_PURCHASE_URL = 'https://app.creativesdigitalagency.com/';

	/**
	 * Transient cache key for verified license status.
	 *
	 * @var string
	 */
	public const LICENSE_CACHE_KEY = 'ai_auto_fixer_license_cache';

	/**
	 * HTTP Request timeout in seconds.
	 *
	 * @var int
	 */
	public const REQUEST_TIMEOUT = 12;

	/**
	 * Get the active SaaS API endpoint URL.
	 *
	 * Supports filter override or custom staging endpoint stored in options.
	 *
	 * @return string
	 */
	public function get_api_endpoint(): string {
		$custom = get_option( 'ai_auto_fixer_custom_endpoint', '' );
		if ( ! empty( $custom ) && is_string( $custom ) ) {
			$sanitized = esc_url_raw( trim( $custom ) );
			if ( filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
				return apply_filters( 'ai_auto_fixer_saas_endpoint', rtrim( $sanitized, '/' ) );
			}
		}

		return apply_filters( 'ai_auto_fixer_saas_endpoint', $this->api_url );
	}

	/**
	 * Retrieve the configured SaaS API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		$settings = get_option( 'ai_auto_fixer_settings', array() );
		return isset( $settings['api_key'] ) ? trim( (string) $settings['api_key'] ) : '';
	}

	/**
	 * Save a new API key and clear the validation cache.
	 *
	 * @param string $api_key Sanitized API key.
	 * @return bool
	 */
	public function save_api_key( string $api_key ): bool {
		$settings            = get_option( 'ai_auto_fixer_settings', array() );
		$settings['api_key'] = sanitize_text_field( trim( $api_key ) );

		delete_transient( self::LICENSE_CACHE_KEY );
		return update_option( 'ai_auto_fixer_settings', $settings, 'no' );
	}

	/**
	 * Verify license key with the Next.js SaaS backend.
	 *
	 * @param string $api_key Optional key to verify. If omitted, uses stored key.
	 * @param bool   $force_refresh Whether to bypass transient cache.
	 * @return array Standardized license details.
	 */
	public function verify_license( string $api_key = '', bool $force_refresh = false ): array {
		if ( empty( $api_key ) ) {
			$api_key = $this->get_api_key();
		}

		if ( empty( $api_key ) ) {
			return array(
				'valid'           => false,
				'is_active'       => false,
				'tier'            => 'free',
				'status'          => 'inactive',
				'plan_name'       => __( 'Free Tier', 'ai-auto-fixer' ),
				'fixes_remaining' => 0,
				'message'         => __( 'No SaaS API key configured. Audit results are available for free; please purchase a license key to unlock Auto-Fixes.', 'ai-auto-fixer' ),
			);
		}

		// Check transient cache to prevent hitting SaaS backend on every page load.
		if ( ! $force_refresh ) {
			$cached = get_transient( self::LICENSE_CACHE_KEY );
			if ( is_array( $cached ) && isset( $cached['is_active'] ) ) {
				return $cached;
			}
		}

		$endpoint = $this->get_api_endpoint();

		// Guard against malformed or empty URLs.
		if ( empty( $endpoint ) || ! filter_var( $endpoint, FILTER_VALIDATE_URL ) ) {
			$endpoint = $this->api_url;
		}

		$payload = array(
			'api_key'        => $api_key,
			'site_url'       => home_url(),
			'site_name'      => get_bloginfo( 'name' ),
			'wp_version'     => get_bloginfo( 'version' ),
			'plugin_version' => defined( 'AI_AUTO_FIXER_VERSION' ) ? AI_AUTO_FIXER_VERSION : '1.0.0',
			'timestamp'      => time(),
		);

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 2,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'X-Site-Origin' => home_url(),
				),
				'body'        => wp_json_encode( $payload ),
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		// Handle network or connection failures defensively.
		if ( is_wp_error( $response ) ) {
			return array(
				'valid'           => false,
				'is_active'       => false,
				'tier'            => 'free',
				'status'          => 'connection_error',
				'plan_name'       => __( 'Free Tier (Offline)', 'ai-auto-fixer' ),
				'fixes_remaining' => 0,
				'message'         => sprintf(
					/* translators: %s: Error message */
					__( 'Could not connect to AI Auto-Fixer Cloud: %s', 'ai-auto-fixer' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || ! is_array( $body ) ) {
			$error_msg = isset( $body['message'] ) ? sanitize_text_field( (string) $body['message'] ) : __( 'Invalid API key or license expired.', 'ai-auto-fixer' );
			$result    = array(
				'valid'           => false,
				'is_active'       => false,
				'tier'            => 'free',
				'status'          => 'invalid',
				'plan_name'       => __( 'Free Tier', 'ai-auto-fixer' ),
				'fixes_remaining' => 0,
				'message'         => $error_msg,
			);
			set_transient( self::LICENSE_CACHE_KEY, $result, 15 * MINUTE_IN_SECONDS );
			return $result;
		}

		// Valid license response from Next.js backend.
		$is_valid = ! empty( $body['valid'] ) || ! empty( $body['active'] ) || ! empty( $body['is_active'] );
		$tier     = isset( $body['tier'] ) ? sanitize_text_field( strtolower( (string) $body['tier'] ) ) : 'pro';

		$result = array(
			'valid'           => $is_valid,
			'is_active'       => $is_valid,
			'tier'            => $is_valid ? $tier : 'free',
			'status'          => $is_valid ? 'active' : 'invalid',
			'plan_name'       => isset( $body['plan_name'] ) ? sanitize_text_field( (string) $body['plan_name'] ) : ( $is_valid ? 'Pro Plan' : 'Free' ),
			'expires_at'      => isset( $body['expires_at'] ) ? sanitize_text_field( (string) $body['expires_at'] ) : null,
			'fixes_remaining' => isset( $body['fixes_remaining'] ) ? (int) $body['fixes_remaining'] : -1,
			'message'         => isset( $body['message'] ) ? sanitize_text_field( (string) $body['message'] ) : __( 'License verified successfully.', 'ai-auto-fixer' ),
		);

		// Cache valid status for 12 hours.
		set_transient( self::LICENSE_CACHE_KEY, $result, 12 * HOUR_IN_SECONDS );

		// Update persistent settings cache.
		$settings                   = get_option( 'ai_auto_fixer_settings', array() );
		$settings['license_tier']   = $result['tier'];
		$settings['license_status'] = $result['status'];
		update_option( 'ai_auto_fixer_settings', $settings, 'no' );

		return $result;
	}

	/**
	 * Send site audit data to Next.js SaaS backend to retrieve AI-powered GEO/AEO recommendations.
	 *
	 * @param array $audit_data The raw audit findings.
	 * @return array Recommendations response.
	 */
	public function fetch_ai_recommendations( array $audit_data ): array {
		$api_key  = $this->get_api_key();
		$endpoint = 'https://app.creativesdigitalagency.com/api/v1/recommendations/geo';

		$payload = array(
			'site_url'   => home_url(),
			'site_name'  => get_bloginfo( 'name' ),
			'audit_data' => $audit_data,
			'timestamp'  => time(),
		);

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
			'Accept'       => 'application/json',
		);

		if ( ! empty( $api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout'   => 12,
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		// Fallback recommendations if offline or server is responding slowly.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->get_fallback_recommendations( $audit_data );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['recommendations'] ) ) {
			return $this->get_fallback_recommendations( $audit_data );
		}

		return $body['recommendations'];
	}

	/**
	 * Retrieve a machine-executable fix payload from Next.js SaaS backend.
	 *
	 * @param string $fix_action The fix action key.
	 * @param array  $context Extra context for the fix.
	 * @return array Fix payload with executable rules or data.
	 */
	public function request_auto_fix_payload( string $fix_action, array $context = array() ): array {
		$license = $this->verify_license();

		if ( empty( $license['is_active'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Auto-Fix is a premium feature. Please connect a valid Pro SaaS API key.', 'ai-auto-fixer' ),
				'tier'    => 'free',
			);
		}

		$endpoint = 'https://app.creativesdigitalagency.com/api/v1/autofix/generate';
		$payload  = array(
			'site_url'   => home_url(),
			'site_name'  => get_bloginfo( 'name' ),
			'fix_action' => sanitize_text_field( $fix_action ),
			'context'    => $context,
			'timestamp'  => time(),
		);

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout'   => 12,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $this->get_api_key(),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'success'            => false,
				'message'            => __( 'Could not generate dynamic fix from SaaS backend. Falling back to local engine.', 'ai-auto-fixer' ),
				'use_local_fallback' => true,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array( 'success' => false, 'use_local_fallback' => true );
	}

	/**
	 * Provide intelligent fallback recommendations if SaaS backend is temporarily unreachable.
	 *
	 * @param array $audit_data The site audit findings.
	 * @return array Fallback recommendation cards.
	 */
	private function get_fallback_recommendations( array $audit_data ): array {
		return array(
			array(
				'title'        => __( 'Generative AI Indexing Optimization (GEO)', 'ai-auto-fixer' ),
				'badge'        => 'GEO High Impact',
				'description'  => __( 'Ensure AI search engines (ChatGPT, Claude, Perplexity) can crawl your site. Blocked AI crawlers drastically reduce brand visibility in AI-generated answers.', 'ai-auto-fixer' ),
				'action_label' => __( 'Configure AI Crawler Rules', 'ai-auto-fixer' ),
				'is_pro'       => true,
			),
			array(
				'title'        => __( 'Entity Schema Markup for Direct AI Answers (AEO)', 'ai-auto-fixer' ),
				'badge'        => 'AEO Answer Engine',
				'description'  => __( 'Inject Schema.org Organization, WebSite, and FAQ JSON-LD metadata so Large Language Models understand your authority and entity relationships.', 'ai-auto-fixer' ),
				'action_label' => __( 'Inject Schema Markup', 'ai-auto-fixer' ),
				'is_pro'       => true,
			),
			array(
				'title'        => __( 'Robots.txt & XML Sitemap Synergy', 'ai-auto-fixer' ),
				'badge'        => 'Crawl Speed',
				'description'  => __( 'Direct search engines and AI spiders directly to your comprehensive XML sitemap inside robots.txt to accelerate indexing of new posts.', 'ai-auto-fixer' ),
				'action_label' => __( 'Sync Sitemap to Robots.txt', 'ai-auto-fixer' ),
				'is_pro'       => true,
			),
		);
	}
}
