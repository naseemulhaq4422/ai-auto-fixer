<?php
/**
 * Plugin Activation Handler.
 *
 * @package AiAutoFixer
 */

namespace AiAutoFixer;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation routines.
 */
class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Sets default options, initializes states, and schedules a silent background audit.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Initialize default plugin options if not set.
		$default_settings = array(
			'api_key'             => '',
			'license_tier'        => 'free',
			'license_status'      => 'inactive',
			'auto_scan_frequency' => 'weekly',
			'enable_geo_schema'   => false,
			'enable_ai_robots'    => false,
			'installed_version'   => AI_AUTO_FIXER_VERSION,
			'installed_at'        => time(),
		);

		$existing_settings = get_option( 'ai_auto_fixer_settings' );
		if ( false === $existing_settings ) {
			add_option( 'ai_auto_fixer_settings', $default_settings, '', 'no' );
		} else {
			// Merge any new keys without overriding existing user config.
			$updated = wp_parse_args( $existing_settings, $default_settings );
			update_option( 'ai_auto_fixer_settings', $updated, 'no' );
		}

		// Set initial audit status.
		update_option( 'ai_auto_fixer_audit_status', 'pending', 'no' );

		// Schedule a silent background site audit (single event) 5 seconds from now.
		// This ensures zero blocking during activation or page load.
		if ( ! wp_next_scheduled( 'ai_auto_fixer_run_site_audit' ) ) {
			wp_schedule_single_event( time() + 5, 'ai_auto_fixer_run_site_audit' );
		}
	}
}
