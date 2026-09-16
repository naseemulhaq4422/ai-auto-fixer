<?php
/**
 * Asynchronous Batch Crawler Engine.
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
 * Discovers site URLs and orchestrates state-machine batch processing to prevent timeouts.
 */
class CrawlerEngine {

	public const BATCH_SIZE = 50;
	public const LOCK_TRANSIENT = 'ai_auto_fixer_crawl_lock';

	/**
	 * Discover all public URLs across the WordPress installation.
	 *
	 * @return string[] Unique list of discovered absolute URLs.
	 */
	public static function discover_all_urls(): array {
		$urls = array();

		// 1. Homepage & Front page
		$urls[] = home_url( '/' );

		// 2. Public Post Types (Pages, Posts, Products, CPTs)
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] ); // Exclude attachments from URL discovery

		$posts = get_posts( array(
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		foreach ( $posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( $permalink && ! in_array( $permalink, $urls, true ) ) {
				$urls[] = $permalink;
			}
		}

		// 3. Public Taxonomies (Categories, Tags, Product Categories)
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		unset( $taxonomies['post_format'] );

		$terms = get_terms( array(
			'taxonomy'   => array_values( $taxonomies ),
			'hide_empty' => true,
			'number'     => 500,
		) );

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) && ! in_array( $link, $urls, true ) ) {
					$urls[] = $link;
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Populate the scan queue table with discovered URLs for a run.
	 *
	 * @param int      $run_id The database run ID.
	 * @param string[] $urls List of URLs to queue.
	 * @return int Number of URLs successfully enqueued.
	 */
	public static function enqueue_urls( int $run_id, array $urls ): int {
		global $wpdb;

		$table_queue = $wpdb->prefix . MigrationManager::TABLE_QUEUE;
		$enqueued    = 0;

		foreach ( $urls as $url ) {
			$clean_url = esc_url_raw( trim( $url ) );
			$hash      = hash( 'sha256', $clean_url );

			// Check for existing queue entry in this run
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table_queue} WHERE run_id = %d AND url_hash = %s",
				$run_id,
				$hash
			) );

			if ( ! $exists ) {
				$wpdb->insert(
					$table_queue,
					array(
						'run_id'     => $run_id,
						'url'        => $clean_url,
						'url_hash'   => $hash,
						'status'     => 'queued',
						'attempts'   => 0,
						'started_at' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s', '%d', '%s' )
				);
				$enqueued++;
			}
		}

		return $enqueued;
	}

	/**
	 * Fetch the next batch of queued URLs to process.
	 *
	 * @param int $run_id Target run ID.
	 * @param int $batch_size Maximum items to retrieve.
	 * @return array List of queue objects.
	 */
	public static function fetch_next_batch( int $run_id, int $batch_size = self::BATCH_SIZE ): array {
		global $wpdb;

		$table_queue = $wpdb->prefix . MigrationManager::TABLE_QUEUE;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_queue} WHERE run_id = %d AND status = 'queued' ORDER BY id ASC LIMIT %d",
			$run_id,
			$batch_size
		) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Update status of an individual queue item.
	 *
	 * @param int    $queue_id ID in queue table.
	 * @param string $status New status ('processing', 'completed', 'failed', 'retry', 'skipped').
	 * @param string $error Optional error description.
	 * @return bool
	 */
	public static function update_queue_status( int $queue_id, string $status, string $error = '' ): bool {
		global $wpdb;

		$table_queue = $wpdb->prefix . MigrationManager::TABLE_QUEUE;

		$data = array(
			'status' => sanitize_text_field( $status ),
		);

		if ( 'processing' === $status ) {
			$data['started_at'] = current_time( 'mysql' );
		} elseif ( in_array( $status, array( 'completed', 'failed', 'skipped' ), true ) ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		if ( ! empty( $error ) ) {
			$data['last_error'] = sanitize_text_field( $error );
		}

		$updated = $wpdb->update(
			$table_queue,
			$data,
			array( 'id' => $queue_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get progress metrics for an ongoing or completed audit run.
	 *
	 * @param int $run_id Audit run ID.
	 * @return array{total: int, completed: int, queued: int, failed: int, percent: int}
	 */
	public static function get_queue_progress( int $run_id ): array {
		global $wpdb;

		$table_queue = $wpdb->prefix . MigrationManager::TABLE_QUEUE;

		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_queue} WHERE run_id = %d", $run_id ) );
		$completed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_queue} WHERE run_id = %d AND status = 'completed'", $run_id ) );
		$queued    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_queue} WHERE run_id = %d AND status = 'queued'", $run_id ) );
		$failed    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_queue} WHERE run_id = %d AND status = 'failed'", $run_id ) );

		$percent = $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0;

		return array(
			'total'     => $total,
			'completed' => $completed,
			'queued'    => $queued,
			'failed'    => $failed,
			'percent'   => min( 100, max( 0, $percent ) ),
		);
	}
}
