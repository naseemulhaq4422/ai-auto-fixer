<?php
/**
 * Environmental & Conflict Detector.
 *
 * @package AiAutoFixer\Detectors
 */

namespace AiAutoFixer\Detectors;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects page builders, e-commerce suites, custom fields, and caching systems
 * to safeguard against unintended layout or data conflicts.
 */
class ConflictDetector {

	/**
	 * Build comprehensive environmental ecosystem status.
	 *
	 * @return array<string, array{name: string, active: bool, type: string, version: string|null}>
	 */
	public static function get_ecosystem_status(): array {
		$ecosystem = array();

		// 1. Page Builders
		$is_elementor = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) || defined( 'ELEMENTOR_VERSION' );
		$ecosystem['elementor'] = array(
			'name'    => 'Elementor',
			'active'  => (bool) $is_elementor,
			'type'    => 'page_builder',
			'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
		);

		$is_divi = defined( 'ET_BUILDER_PLUGIN_VERSION' ) || function_exists( 'et_setup_theme' );
		$ecosystem['divi'] = array(
			'name'    => 'Divi Builder',
			'active'  => (bool) $is_divi,
			'type'    => 'page_builder',
			'version' => defined( 'ET_BUILDER_PLUGIN_VERSION' ) ? ET_BUILDER_PLUGIN_VERSION : null,
		);

		// 2. E-Commerce
		$is_woo = class_exists( 'WooCommerce' );
		$ecosystem['woocommerce'] = array(
			'name'    => 'WooCommerce',
			'active'  => (bool) $is_woo,
			'type'    => 'ecommerce',
			'version' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
		);

		// 3. Custom Fields / ACF
		$is_acf = class_exists( 'ACF' ) || function_exists( 'get_field' );
		$ecosystem['acf'] = array(
			'name'    => 'Advanced Custom Fields (ACF)',
			'active'  => (bool) $is_acf,
			'type'    => 'custom_fields',
			'version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : null,
		);

		// 4. Caching & Performance
		$is_wp_rocket = defined( 'WP_ROCKET_VERSION' );
		$ecosystem['wp_rocket'] = array(
			'name'    => 'WP Rocket',
			'active'  => (bool) $is_wp_rocket,
			'type'    => 'cache',
			'version' => defined( 'WP_ROCKET_VERSION' ) ? WP_ROCKET_VERSION : null,
		);

		$is_litespeed = defined( 'LSCWP_V' );
		$ecosystem['litespeed'] = array(
			'name'    => 'LiteSpeed Cache',
			'active'  => (bool) $is_litespeed,
			'type'    => 'cache',
			'version' => defined( 'LSCWP_V' ) ? LSCWP_V : null,
		);

		return $ecosystem;
	}

	/**
	 * Check if an active page builder is installed on the site.
	 *
	 * @return bool
	 */
	public static function has_page_builder(): bool {
		$status = self::get_ecosystem_status();
		return ! empty( $status['elementor']['active'] ) || ! empty( $status['divi']['active'] );
	}

	/**
	 * Check if WooCommerce is actively running.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Direct alias for get_ecosystem_status().
	 *
	 * @return array<string, array{name: string, active: bool, type: string, version: string|null}>
	 */
	public static function detect_conflicts(): array {
		return self::get_ecosystem_status();
	}
}
