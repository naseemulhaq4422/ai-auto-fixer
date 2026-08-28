<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package AiAutoFixer
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete persistent plugin options.
delete_option( 'ai_auto_fixer_settings' );
delete_option( 'ai_auto_fixer_audit_results' );
delete_option( 'ai_auto_fixer_audit_status' );
delete_option( 'ai_auto_fixer_fix_history' );
delete_option( 'ai_auto_fixer_custom_endpoint' );

// Delete transients.
delete_transient( 'ai_auto_fixer_license_cache' );
delete_transient( 'ai_auto_fixer_audit_lock' );
