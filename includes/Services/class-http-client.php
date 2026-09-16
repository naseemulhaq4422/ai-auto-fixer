<?php
/**
 * SSRF-Hardened HTTP Request Client.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Handles outgoing HTTP requests for crawling and validation with strict SSRF defense.
 */
class HttpClient {

	/**
	 * Default request timeout in seconds.
	 */
	public const DEFAULT_TIMEOUT = 5;

	/**
	 * Perform a safe GET request with strict SSRF and private IP blocking.
	 *
	 * @param string $url Target URL to fetch.
	 * @param array  $args Optional overrides for wp_safe_remote_get.
	 * @return array|WP_Error Response array on success, WP_Error on validation failure.
	 */
	public static function safe_get( string $url, array $args = array() ) {
		$validation = self::validate_url_security( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$defaults = array(
			'timeout'     => self::DEFAULT_TIMEOUT,
			'redirection' => 3,
			'user-agent'  => 'Mozilla/5.0 (compatible; AI-Auto-Fixer-Auditor/1.0; +' . home_url() . ')',
			'sslverify'   => apply_filters( 'https_local_ssl_verify', true ),
			'headers'     => array(
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			),
		);

		$parsed_args = wp_parse_args( $args, $defaults );

		return wp_safe_remote_get( esc_url_raw( $url ), $parsed_args );
	}

	/**
	 * Perform a lightweight HEAD request to inspect HTTP status and response headers.
	 *
	 * @param string $url Target URL.
	 * @param array  $args Additional request arguments.
	 * @return array|WP_Error Response or error.
	 */
	public static function safe_head( string $url, array $args = array() ) {
		$validation = self::validate_url_security( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$defaults = array(
			'timeout'     => self::DEFAULT_TIMEOUT,
			'redirection' => 3,
			'user-agent'  => 'AI-Auto-Fixer-Auditor/1.0',
			'sslverify'   => apply_filters( 'https_local_ssl_verify', true ),
		);

		return wp_safe_remote_head( esc_url_raw( $url ), wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Convenient alias for safe_get().
	 *
	 * @param string $url Target URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Response or error.
	 */
	public static function get( string $url, array $args = array() ) {
		return self::safe_get( $url, $args );
	}

	/**
	 * Convenient alias for safe_head().
	 *
	 * @param string $url Target URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Response or error.
	 */
	public static function head( string $url, array $args = array() ) {
		return self::safe_head( $url, $args );
	}

	/**
	 * Validate URL against protocol restrictions, private IP ranges, and local hostnames.
	 *
	 * @param string $url URL to inspect.
	 * @return bool|WP_Error True if clean, WP_Error if dangerous.
	 */
	public static function validate_url_security( string $url ) {
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid or empty URL provided.', 'ai-auto-fixer' ) );
		}

		$parsed = wp_parse_url( $url );
		$scheme = strtolower( $parsed['scheme'] ?? '' );

		// 1. Protocol lockdown: Only http and https permitted
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'ssrf_blocked_protocol', __( 'Blocked dangerous protocol. Only HTTP and HTTPS are permitted.', 'ai-auto-fixer' ) );
		}

		$host = strtolower( $parsed['host'] ?? '' );
		if ( empty( $host ) ) {
			return new WP_Error( 'ssrf_missing_host', __( 'Invalid URL: Hostname missing.', 'ai-auto-fixer' ) );
		}

		// Strict cloud metadata blocks (always forbidden, unconditionally)
		$cloud_metadata = array(
			'169.254.169.254',
			'metadata.google.internal',
			'instance-data',
		);
		if ( in_array( $host, $cloud_metadata, true ) ) {
			return new WP_Error( 'ssrf_blocked_host', __( 'Access to cloud metadata endpoints is prohibited.', 'ai-auto-fixer' ) );
		}

		// Self-host exemption: If auditing the site's own domain, permit loopback/internal crawl
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! empty( $home_host ) && $host === $home_host ) {
			return true;
		}

		// 2. Forbidden hosts for external requests (localhost, 127.0.0.1, 0.0.0.0)
		$forbidden_hosts = array(
			'localhost',
			'127.0.0.1',
			'0.0.0.0',
		);

		if ( in_array( $host, $forbidden_hosts, true ) ) {
			return new WP_Error( 'ssrf_blocked_host', __( 'Access to local or loopback endpoints is prohibited.', 'ai-auto-fixer' ) );
		}

		// 3. Resolve IP and block private/reserved subnets for external requests
		$ip = gethostbyname( $host );
		if ( $ip !== $host ) {
			if ( self::is_private_or_reserved_ip( $ip ) ) {
				return new WP_Error( 'ssrf_blocked_ip', sprintf(
					/* translators: %s: Blocked IP */
					__( 'Security Alert: Domain resolved to private/reserved IP address (%s). Request aborted.', 'ai-auto-fixer' ),
					esc_html( $ip )
				) );
			}
		}

		return true;
	}

	/**
	 * Check if an IPv4 address falls within private or link-local RFC ranges.
	 *
	 * @param string $ip IPv4 address string.
	 * @return bool True if private/reserved.
	 */
	public static function is_private_or_reserved_ip( string $ip ): bool {
		$long_ip = ip2long( $ip );
		if ( false === $long_ip ) {
			return true;
		}

		// Private & Loopback IP ranges:
		// 127.0.0.0/8 (Loopback)
		// 10.0.0.0/8 (Class A private)
		// 172.16.0.0/12 (Class B private)
		// 192.168.0.0/16 (Class C private)
		// 169.254.0.0/16 (Link-local / Cloud metadata)
		// 0.0.0.0/8 (Current network)
		$ranges = array(
			array( '0.0.0.0', '0.255.255.255' ),
			array( '10.0.0.0', '10.255.255.255' ),
			array( '127.0.0.0', '127.255.255.255' ),
			array( '169.254.0.0', '169.254.255.255' ),
			array( '172.16.0.0', '172.31.255.255' ),
			array( '192.168.0.0', '192.168.255.255' ),
			array( '224.0.0.0', '239.255.255.255' ), // Multicast
			array( '240.0.0.0', '255.255.255.255' ), // Reserved
		);

		foreach ( $ranges as $range ) {
			$min = ip2long( $range[0] );
			$max = ip2long( $range[1] );
			if ( $long_ip >= $min && $long_ip <= $max ) {
				return true;
			}
		}

		return false;
	}
}
