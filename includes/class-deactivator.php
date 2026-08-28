<?php
/**
 * Plugin Deactivation Handler.
 *
 * @package AiAutoFixer
 */

namespace AiAutoFixer;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation routines.
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * Cleans up scheduled single-event hooks and cron jobs without deleting user data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear any pending background audit cron events.
		$timestamp = wp_next_scheduled( 'ai_auto_fixer_run_site_audit' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_auto_fixer_run_site_audit' );
		}

		wp_clear_scheduled_hook( 'ai_auto_fixer_run_site_audit' );

		// Clear transients.
		delete_transient( 'ai_auto_fixer_license_cache' );
		delete_transient( 'ai_auto_fixer_audit_lock' );
	}
}
