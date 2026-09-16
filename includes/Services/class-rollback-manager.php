<?php
/**
 * Transactional Snapshot & Rollback Engine.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Database\MigrationManager;

/**
 * Handles atomic pre-fix state snapshots, integrity checksum validation, and 1-click rollbacks.
 */
class RollbackManager {

	/**
	 * Capture and persist a pre-change snapshot before any database or filesystem modification.
	 *
	 * @param string $fix_id Action identifier.
	 * @param string $object_type 'option' | 'post_meta' | 'attachment' | 'robots_rule'.
	 * @param string $object_id Target key or post ID.
	 * @param mixed  $before_state State before fix.
	 * @param mixed  $after_state Planned state after fix.
	 * @return string UUID of the created snapshot record.
	 */
	public static function create_snapshot(
		string $fix_id,
		string $object_type,
		string $object_id,
		$before_state,
		$after_state
	): string {
		global $wpdb;

		$table_snapshots = $wpdb->prefix . MigrationManager::TABLE_SNAPSHOTS;
		$snapshot_uuid   = wp_generate_uuid4();

		$encoded_before = wp_json_encode( $before_state );
		$encoded_after  = wp_json_encode( $after_state );
		$checksum       = hash( 'sha256', (string) $encoded_before );

		$wpdb->insert(
			$table_snapshots,
			array(
				'snapshot_uuid' => $snapshot_uuid,
				'fix_id'        => sanitize_text_field( $fix_id ),
				'object_type'   => sanitize_text_field( $object_type ),
				'object_id'     => sanitize_text_field( $object_id ),
				'before_state'  => $encoded_before,
				'after_state'   => $encoded_after,
				'checksum'      => $checksum,
				'status'        => 'available',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $snapshot_uuid;
	}

	/**
	 * Revert an applied change back to its exact pre-fix state.
	 *
	 * @param string $snapshot_uuid Target snapshot identifier.
	 * @return array{success: bool, message: string}
	 */
	public static function execute_rollback( string $snapshot_uuid ): array {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized: manage_options capability required to perform rollbacks.', 'ai-auto-fixer' ),
			);
		}

		$table_snapshots = $wpdb->prefix . MigrationManager::TABLE_SNAPSHOTS;

		$record = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_snapshots} WHERE snapshot_uuid = %s",
			$snapshot_uuid
		) );

		if ( ! $record ) {
			return array(
				'success' => false,
				'message' => __( 'Snapshot record not found in database.', 'ai-auto-fixer' ),
			);
		}

		if ( 'reverted' === $record->status ) {
			return array(
				'success' => false,
				'message' => __( 'This snapshot has already been rolled back.', 'ai-auto-fixer' ),
			);
		}

		// Integrity validation: Checksum check
		$current_checksum = hash( 'sha256', (string) $record->before_state );
		if ( $current_checksum !== $record->checksum ) {
			return array(
				'success' => false,
				'message' => __( 'Rollback aborted: Snapshot checksum mismatch detected. Integrity compromised.', 'ai-auto-fixer' ),
			);
		}

		$before_data = json_decode( $record->before_state, true );
		$object_type = $record->object_type;
		$object_id   = $record->object_id;
		$reverted    = false;

		// Revert based on object type
		switch ( $object_type ) {
			case 'option':
				$reverted = update_option( $object_id, $before_data, 'no' );
				break;

			case 'post_meta':
				list( $post_id, $meta_key ) = explode( ':', $object_id );
				$reverted = (bool) update_post_meta( (int) $post_id, $meta_key, $before_data );
				break;

			case 'attachment':
				// Restore from trash
				$untrashed = wp_untrash_post( (int) $object_id );
				$reverted  = (bool) $untrashed;
				break;

			case 'robots_rule':
				$settings = get_option( 'ai_auto_fixer_settings', array() );
				$settings['enable_ai_robots'] = (bool) $before_data;
				$reverted = update_option( 'ai_auto_fixer_settings', $settings, 'no' );
				break;

			default:
				$reverted = false;
				break;
		}

		if ( $reverted ) {
			$wpdb->update(
				$table_snapshots,
				array( 'status' => 'reverted' ),
				array( 'snapshot_uuid' => $snapshot_uuid ),
				array( '%s' ),
				array( '%s' )
			);

			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: Action identifier */
					__( 'Rollback successful: Changes for action "%s" have been restored to pre-fix state.', 'ai-auto-fixer' ),
					esc_html( $record->fix_id )
				),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Rollback execution encountered an error while restoring state.', 'ai-auto-fixer' ),
		);
	}

	/**
	 * Retrieve all available snapshots for display in Fix History tab.
	 *
	 * @param int $limit Max items.
	 * @return array
	 */
	public static function get_snapshots( int $limit = 30 ): array {
		global $wpdb;

		$table = $wpdb->prefix . MigrationManager::TABLE_SNAPSHOTS;
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
			$limit
		) );

		return is_array( $rows ) ? $rows : array();
	}
}
