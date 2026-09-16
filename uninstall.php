<?php
/**
 * Fired when the plugin is uninstalled (100% Free & Standalone).
 *
 * @package AiAutoFixer
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete persistent plugin options.
delete_option( 'ai_auto_fixer_settings' );
delete_option( 'ai_auto_fixer_audit_results' );
delete_option( 'ai_auto_fixer_audit_status' );
delete_option( 'ai_auto_fixer_fix_history' );
delete_option( 'ai_auto_fixer_db_version' );

// Delete transients.
delete_transient( 'ai_auto_fixer_audit_lock' );
delete_transient( 'ai_auto_fixer_crawl_lock' );

// Drop custom plugin tables upon complete uninstallation
$tables = array(
	$wpdb->prefix . 'aaf_audit_runs',
	$wpdb->prefix . 'aaf_audit_issues',
	$wpdb->prefix . 'aaf_scan_queue',
	$wpdb->prefix . 'aaf_fix_snapshots',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
