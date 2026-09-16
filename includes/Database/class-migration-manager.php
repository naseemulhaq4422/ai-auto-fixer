<?php
/**
 * Database Migration & Schema Manager.
 *
 * @package AiAutoFixer\Database
 */

namespace AiAutoFixer\Database;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database versioning, table installations, upgrades, and schema queries.
 */
class MigrationManager {

	/**
	 * Current database schema version.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Option key storing installed DB schema version.
	 */
	public const DB_VERSION_OPTION = 'ai_auto_fixer_db_version';

	/**
	 * Table name constants.
	 */
	public const TABLE_RUNS      = 'aaf_audit_runs';
	public const TABLE_ISSUES    = 'aaf_audit_issues';
	public const TABLE_QUEUE     = 'aaf_scan_queue';
	public const TABLE_SNAPSHOTS = 'aaf_fix_snapshots';

	/**
	 * Run database migrations if version mismatch is detected.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			self::install_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, 'no' );
		}
	}

	/**
	 * Install or upgrade custom tables using WordPress dbDelta.
	 *
	 * @return void
	 */
	public static function install_tables(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();

		$table_runs      = $wpdb->prefix . self::TABLE_RUNS;
		$table_issues    = $wpdb->prefix . self::TABLE_ISSUES;
		$table_queue     = $wpdb->prefix . self::TABLE_QUEUE;
		$table_snapshots = $wpdb->prefix . self::TABLE_SNAPSHOTS;

		// 1. Audit Runs Table
		$sql_runs = "CREATE TABLE {$table_runs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			run_uuid VARCHAR(64) NOT NULL,
			total_urls INT NOT NULL DEFAULT 0,
			crawled_urls INT NOT NULL DEFAULT 0,
			seo_health_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			safety_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_uuid (run_uuid)
		) {$charset_collate};";

		// 2. Audit Issues Table
		$sql_issues = "CREATE TABLE {$table_issues} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT(20) UNSIGNED NOT NULL,
			url TEXT NOT NULL,
			object_type VARCHAR(32) NOT NULL DEFAULT 'url',
			object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			issue_type VARCHAR(64) NOT NULL,
			category VARCHAR(32) NOT NULL,
			severity VARCHAR(16) NOT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'open',
			source VARCHAR(64) NOT NULL,
			message TEXT NOT NULL,
			recommendation TEXT NOT NULL,
			risk_level VARCHAR(16) NOT NULL DEFAULT 'low',
			fix_available TINYINT(1) NOT NULL DEFAULT 0,
			fix_id VARCHAR(64) NULL,
			fingerprint CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY issue_type (issue_type),
			KEY severity (severity),
			KEY fingerprint (fingerprint)
		) {$charset_collate};";

		// 3. Scan Queue Table
		$sql_queue = "CREATE TABLE {$table_queue} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT(20) UNSIGNED NOT NULL,
			url TEXT NOT NULL,
			url_hash CHAR(64) NOT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'queued',
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			last_error TEXT NULL,
			started_at DATETIME NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY status (status),
			KEY url_hash (url_hash)
		) {$charset_collate};";

		// 4. Fix Snapshots (Rollback) Table
		$sql_snapshots = "CREATE TABLE {$table_snapshots} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			snapshot_uuid VARCHAR(64) NOT NULL,
			fix_id VARCHAR(64) NOT NULL,
			object_type VARCHAR(32) NOT NULL,
			object_id VARCHAR(128) NOT NULL,
			before_state LONGTEXT NOT NULL,
			after_state LONGTEXT NOT NULL,
			checksum CHAR(64) NOT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'available',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY snapshot_uuid (snapshot_uuid),
			KEY fix_id (fix_id)
		) {$charset_collate};";

		dbDelta( $sql_runs );
		dbDelta( $sql_issues );
		dbDelta( $sql_queue );
		dbDelta( $sql_snapshots );
	}

	/**
	 * Compute unique SHA-256 fingerprint for deduplicating issue records.
	 *
	 * @param string $url Target URL.
	 * @param string $issue_type Type identifier.
	 * @param int|string $object_id Associated post/attachment ID.
	 * @return string SHA-256 hash.
	 */
	public static function generate_fingerprint( string $url, string $issue_type, $object_id = 0 ): string {
		return hash( 'sha256', trim( $url ) . '|' . trim( $issue_type ) . '|' . (string) $object_id );
	}
}
