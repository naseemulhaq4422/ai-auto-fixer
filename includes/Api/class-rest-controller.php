<?php
/**
 * Hardened REST API Controller (SSRF Protected, Paginated & Secure).
 *
 * @package AiAutoFixer\Api
 */

namespace AiAutoFixer\Api;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AiAutoFixer\Services\SiteAuditScanner;
use AiAutoFixer\Services\AutoFixService;
use AiAutoFixer\Services\FixManager;
use AiAutoFixer\Services\RollbackManager;
use AiAutoFixer\Services\CrawlerEngine;
use AiAutoFixer\Services\IndexabilityScanner;
use AiAutoFixer\Services\ImageSeoScanner;
use AiAutoFixer\Services\UnusedMediaScanner;
use AiAutoFixer\Services\LinkScanner;
use AiAutoFixer\Detectors\SeoPluginDetector;
use AiAutoFixer\Detectors\ConflictDetector;
use AiAutoFixer\Detectors\SchemaOwnershipDetector;
use AiAutoFixer\Detectors\MetaOwnershipDetector;
use AiAutoFixer\Database\MigrationManager;

/**
 * Handles all REST API routes for asynchronous dashboard actions, diagnostics, previews, and rollbacks.
 */
class RestController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ai-auto-fixer/v1';

	/**
	 * Site Audit Scanner.
	 *
	 * @var SiteAuditScanner
	 */
	private SiteAuditScanner $scanner;

	/**
	 * Auto-Fix Service.
	 *
	 * @var AutoFixService
	 */
	private AutoFixService $auto_fixer;

	/**
	 * Constructor.
	 *
	 * @param SiteAuditScanner $scanner Injected Scanner.
	 * @param AutoFixService   $auto_fixer Injected Auto-Fixer.
	 */
	public function __construct( SiteAuditScanner $scanner, AutoFixService $auto_fixer ) {
		$this->scanner    = $scanner;
		$this->auto_fixer = $auto_fixer;
	}

	/**
	 * Register all REST API endpoints.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// 1. POST /scan - Run fresh site audit
		register_rest_route(
			$this->namespace,
			'/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_run_scan' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// 2. GET /scan/progress - Check scan and queue progress
		register_rest_route(
			$this->namespace,
			'/scan/progress',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_scan_progress' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// 3. GET /results - Retrieve paginated audit issues
		register_rest_route(
			$this->namespace,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_results' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					'category' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'severity' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// 4. GET /indexation - Paginated indexability diagnostics
		register_rest_route(
			$this->namespace,
			'/indexation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_indexation' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					'status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// 5. GET /images - Paginated Image SEO checks
		register_rest_route(
			$this->namespace,
			'/images',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_images' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					'filter'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all' ),
				),
			)
		);

		// 6. POST /images/alt-suggest - Smart ALT text recommendation with confidence score
		register_rest_route(
			$this->namespace,
			'/images/alt-suggest',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_alt_suggest' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'attachment_id' => array( 'required' => true, 'type' => 'integer' ),
					'post_id'       => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);

		// 7. POST /images/alt-fix - Apply ALT text or mark decorative
		register_rest_route(
			$this->namespace,
			'/images/alt-fix',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_alt_fix' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'attachment_id' => array( 'required' => true, 'type' => 'integer' ),
					'alt_text'      => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
					'is_decorative' => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);

		// 8. GET /media/unlinked - Paginated unlinked media with 10-source dependency reports
		register_rest_route(
			$this->namespace,
			'/media/unlinked',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_unlinked_media' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20 ),
					'status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'all' ),
				),
			)
		);

		// 9. POST /media/trash - Move unlinked media strictly to WordPress Trash
		register_rest_route(
			$this->namespace,
			'/media/trash',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_trash_media' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'attachment_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		// 10. GET /broken-links - Paginated broken links
		register_rest_route(
			$this->namespace,
			'/broken-links',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_broken_links' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20 ),
				),
			)
		);

		// 11. GET /compatibility - Environment compatibility matrix & schema owners
		register_rest_route(
			$this->namespace,
			'/compatibility',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_compatibility' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// 12. POST /autofix - Execute individual fix with snapshot and verification
		register_rest_route(
			$this->namespace,
			'/autofix',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_execute_autofix' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'fix_action' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'context'    => array( 'type' => 'object', 'default' => array() ),
				),
			)
		);

		// 13. POST /autofix/preview - Generate Before vs After diff preview
		register_rest_route(
			$this->namespace,
			'/autofix/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_preview_autofix' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'fix_action' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'context'    => array( 'type' => 'object', 'default' => array() ),
				),
			)
		);

		// 14. POST /autofix/all-safe - Execute all low-risk safe fixes in bulk
		register_rest_route(
			$this->namespace,
			'/autofix/all-safe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_execute_all_safe_fixes' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// Legacy alias: POST /autofix/all
		register_rest_route(
			$this->namespace,
			'/autofix/all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_execute_all_safe_fixes' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// 15. POST /verify - Trigger post-fix verification
		register_rest_route(
			$this->namespace,
			'/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_verify_fix' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'fix_id'  => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'context' => array( 'type' => 'object', 'default' => array() ),
				),
			)
		);

		// 16. POST /rollback - 1-Click Rollback to pre-fix state
		register_rest_route(
			$this->namespace,
			'/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_execute_rollback' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'snapshot_uuid' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		// 17. GET /history - Paginated snapshot rollback log
		register_rest_route(
			$this->namespace,
			'/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_history' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'per_page' => array( 'type' => 'integer', 'default' => 30 ),
				),
			)
		);

		// 18. GET /recommendations - Fetch local AI GEO/AEO blueprint
		register_rest_route(
			$this->namespace,
			'/recommendations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_recommendations' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Strict admin permissions check for all management endpoints.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function permissions_check( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have administrative permission to perform this action.', 'ai-auto-fixer' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Trigger a fresh site audit.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_run_scan( WP_REST_Request $request ): WP_REST_Response {
		$results = $this->scanner->run_audit( false );
		return rest_ensure_response(
			array(
				'success' => true,
				'results' => $results,
			)
		);
	}

	/**
	 * Return scan progress metrics.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_scan_progress( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table_runs = $wpdb->prefix . MigrationManager::TABLE_RUNS;
		$latest_run = $wpdb->get_row( "SELECT * FROM {$table_runs} ORDER BY id DESC LIMIT 1" );

		if ( ! $latest_run ) {
			return rest_ensure_response( array(
				'success'  => true,
				'status'   => 'idle',
				'progress' => array( 'total' => 0, 'completed' => 0, 'percent' => 0 ),
			) );
		}

		$progress = CrawlerEngine::get_queue_progress( (int) $latest_run->id );

		return rest_ensure_response( array(
			'success'  => true,
			'run_id'   => (int) $latest_run->id,
			'run_uuid' => $latest_run->run_uuid,
			'status'   => $latest_run->status,
			'progress' => $progress,
		) );
	}

	/**
	 * Return paginated audit findings with total count headers.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_results( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$category = sanitize_text_field( $request->get_param( 'category' ) ?? '' );
		$severity = sanitize_text_field( $request->get_param( 'severity' ) ?? '' );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$offset   = ( $page - 1 ) * $per_page;

		$table_issues = $wpdb->prefix . MigrationManager::TABLE_ISSUES;
		$where_clauses = array( '1=1' );
		$params        = array();

		if ( ! empty( $category ) && 'all' !== $category ) {
			$where_clauses[] = 'category = %s';
			$params[]        = $category;
		}
		if ( ! empty( $severity ) && 'all' !== $severity ) {
			$where_clauses[] = 'severity = %s';
			$params[]        = $severity;
		}
		if ( ! empty( $status ) && 'all' !== $status ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count query
		$count_sql = "SELECT COUNT(id) FROM {$table_issues} WHERE {$where_sql}";
		$total     = ! empty( $params )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: (int) $wpdb->get_var( $count_sql );

		// Query page items
		$query_sql = "SELECT * FROM {$table_issues} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$query_params   = array_merge( $params, array( $per_page, $offset ) );
		$items          = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_params ), ARRAY_A );

		// Fallback to option if database table is empty
		if ( 0 === $total ) {
			$last_results = $this->scanner->get_last_audit_results();
			if ( ! empty( $last_results['issues'] ) ) {
				$raw_issues = $last_results['issues'];
				if ( ! empty( $severity ) && 'all' !== $severity ) {
					$raw_issues = array_filter( $raw_issues, function( $i ) use ( $severity ) {
						return ( $i['severity'] ?? '' ) === $severity;
					} );
				}
				$total = count( $raw_issues );
				$items = array_slice( array_values( $raw_issues ), $offset, $per_page );
			}
		}

		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

		$response = new WP_REST_Response( array(
			'success'     => true,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'issues'      => $items ?: array(),
		), 200 );

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Return paginated indexability diagnostics.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_indexation( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? 'all' );
		$offset   = ( $page - 1 ) * $per_page;

		$discovered = CrawlerEngine::discover_all_urls();
		$evaluated  = array();
		$summary    = array(
			'total'         => count( $discovered ),
			'indexable'     => 0,
			'noindex'       => 0,
			'blocked'       => 0,
			'canonicalized' => 0,
			'redirected'    => 0,
			'orphan'        => 0,
		);

		foreach ( $discovered as $url ) {
			$diag = IndexabilityScanner::evaluate_url( $url );
			$st   = strtolower( $diag['status'] );

			if ( isset( $summary[ $st ] ) ) {
				$summary[ $st ]++;
			} else {
				$summary['indexable']++;
			}

			if ( 'all' === $status || $st === $status ) {
				$evaluated[] = array(
					'url'         => $url,
					'status'      => $diag['status'],
					'is_indexable'=> $diag['is_indexable'],
					'reasons'     => $diag['reasons'],
					'source'      => $diag['source'],
				);
			}
		}

		$total       = count( $evaluated );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$paged_items = array_slice( $evaluated, $offset, $per_page );

		$response = new WP_REST_Response( array(
			'success'     => true,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'summary'     => $summary,
			'items'       => $paged_items,
		), 200 );

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Return paginated image SEO findings.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_images( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$filter   = sanitize_text_field( $request->get_param( 'filter' ) ?? 'all' );
		$offset   = ( $page - 1 ) * $per_page;

		$audit = ImageSeoScanner::audit_media_library( 100 );
		$items = $audit['issues'];

		if ( 'all' !== $filter ) {
			$items = array_filter( $items, function( $i ) use ( $filter ) {
				return ( $i['issue_type'] ?? '' ) === $filter;
			} );
		}

		$total       = count( $items );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$paged_items = array_slice( array_values( $items ), $offset, $per_page );

		$response = new WP_REST_Response( array(
			'success'     => true,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'summary'     => $audit['counts'],
			'items'       => $paged_items,
		), 200 );

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Generate contextual Smart ALT recommendation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_alt_suggest( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$post_id       = (int) $request->get_param( 'post_id' );

		if ( $attachment_id <= 0 ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid attachment ID', 'ai-auto-fixer' ) ), 400 );
		}

		$suggestion = ImageSeoScanner::generate_smart_alt( $attachment_id, $post_id );

		return rest_ensure_response( array(
			'success'           => true,
			'attachment_id'     => $attachment_id,
			'suggested_alt'     => $suggestion['suggested_alt'],
			'confidence'        => $suggestion['confidence'],
			'sources'           => $suggestion['sources'],
			'is_low_confidence' => $suggestion['is_low_confidence'],
		) );
	}

	/**
	 * Apply image ALT text or mark decorative.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_alt_fix( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$alt_text      = sanitize_text_field( (string) $request->get_param( 'alt_text' ) );
		$is_decorative = (bool) $request->get_param( 'is_decorative' );

		if ( $attachment_id <= 0 ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid attachment ID', 'ai-auto-fixer' ) ), 400 );
		}

		if ( $is_decorative ) {
			$alt_text = '';
		}

		$res = FixManager::execute_fix( 'apply_image_alt', array(
			'attachment_id' => $attachment_id,
			'alt_text'      => $alt_text,
		) );

		return rest_ensure_response( $res );
	}

	/**
	 * Return paginated unlinked media with 10-source dependency reports.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_unlinked_media( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? 'all' );
		$offset   = ( $page - 1 ) * $per_page;

		$audit = UnusedMediaScanner::audit_media_library( 100 );
		$items = $audit['items'];

		if ( 'all' !== $status ) {
			$items = array_filter( $items, function( $i ) use ( $status ) {
				return strtolower( $i['status'] ?? '' ) === strtolower( $status );
			} );
		}

		$total       = count( $items );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$paged_items = array_slice( array_values( $items ), $offset, $per_page );

		$response = new WP_REST_Response( array(
			'success'     => true,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'summary'     => $audit['counts'],
			'items'       => $paged_items,
		), 200 );

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Move unlinked media item to Trash with strict UNKNOWN state protection.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_trash_media( WP_REST_Request $request ) {
		$attachment_id = (int) $request->get_param( 'attachment_id' );

		if ( $attachment_id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid attachment ID.', 'ai-auto-fixer' ), array( 'status' => 400 ) );
		}

		$inspection = UnusedMediaScanner::inspect_media_dependencies( $attachment_id );
		if ( ! $inspection['can_trash'] || 'UNKNOWN' === $inspection['status'] ) {
			return new WP_Error(
				'media_protection_blocked',
				sprintf(
					/* translators: %s: Dependency report */
					__( 'Action blocked by Media Protection Engine: %s', 'ai-auto-fixer' ),
					$inspection['dependency_report']
				),
				array( 'status' => 400 )
			);
		}

		// Create pre-trash snapshot for 1-click rollback
		$snapshot_uuid = RollbackManager::create_snapshot(
			'trash_media',
			'attachment',
			(string) $attachment_id,
			'publish',
			'trash'
		);

		$trash_result = UnusedMediaScanner::move_to_trash( $attachment_id );
		$trash_result['snapshot_uuid'] = $snapshot_uuid;

		return rest_ensure_response( $trash_result );
	}

	/**
	 * Return paginated broken links.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_broken_links( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$site_url = home_url( '/' );
		$response = HttpClient::get( $site_url, array( 'timeout' => 5 ) );
		$html     = ! is_wp_error( $response ) ? wp_remote_retrieve_body( $response ) : '';

		$audit = LinkScanner::audit_links( $site_url, $html );
		$items = $audit['issues'];

		$total       = count( $items );
		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$paged_items = array_slice( $items, $offset, $per_page );

		$res = new WP_REST_Response( array(
			'success'     => true,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'items'       => $paged_items,
		), 200 );

		$res->header( 'X-WP-Total', (string) $total );
		$res->header( 'X-WP-TotalPages', (string) $total_pages );

		return $res;
	}

	/**
	 * Return environment compatibility matrix & schema owners.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_compatibility( WP_REST_Request $request ): WP_REST_Response {
		$site_url  = home_url( '/' );
		$response  = HttpClient::get( $site_url, array( 'timeout' => 5 ) );
		$html      = ! is_wp_error( $response ) ? wp_remote_retrieve_body( $response ) : '';

		$seo_plugins   = SeoPluginDetector::detect();
		$conflicts     = ConflictDetector::detect_conflicts();
		$schema_owners = SchemaOwnershipDetector::detect_ownership( $html );
		$meta_owners   = MetaOwnershipDetector::detect_meta_ownership( $html );

		return rest_ensure_response( array(
			'success'       => true,
			'compatibility' => array(
				'seo_plugins'   => $seo_plugins,
				'conflicts'     => $conflicts,
				'schema_owners' => $schema_owners,
				'meta_owners'   => $meta_owners,
			),
		) );
	}

	/**
	 * Execute an automated fix for a specific issue.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_execute_autofix( WP_REST_Request $request ): WP_REST_Response {
		$fix_action = sanitize_text_field( (string) $request->get_param( 'fix_action' ) );
		$context    = (array) $request->get_param( 'context' );

		$result = FixManager::execute_fix( $fix_action, $context );

		if ( ! empty( $result['success'] ) ) {
			$updated_audit   = $this->scanner->run_audit( false );
			$result['audit'] = $updated_audit;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Generate Before vs After diff preview for a fix action.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_preview_autofix( WP_REST_Request $request ): WP_REST_Response {
		$fix_action = sanitize_text_field( (string) $request->get_param( 'fix_action' ) );
		$context    = (array) $request->get_param( 'context' );

		$preview = FixManager::preview_fix( $fix_action, $context );

		return rest_ensure_response( $preview );
	}

	/**
	 * Execute all low-risk safe fixes in bulk.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_execute_all_safe_fixes( WP_REST_Request $request ): WP_REST_Response {
		$result = FixManager::execute_all_safe_fixes();

		if ( ! empty( $result['success'] ) ) {
			$updated_audit   = $this->scanner->run_audit( false );
			$result['audit'] = $updated_audit;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Post-fix verification endpoint.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_verify_fix( WP_REST_Request $request ): WP_REST_Response {
		$fix_id  = sanitize_text_field( (string) $request->get_param( 'fix_id' ) );
		$context = (array) $request->get_param( 'context' );

		$verified = FixManager::verify_fix( $fix_id, $context );

		return rest_ensure_response( array(
			'success'  => true,
			'fix_id'   => $fix_id,
			'verified' => $verified,
			'message'  => $verified
				? __( 'Fix verified successfully on frontend.', 'ai-auto-fixer' )
				: __( 'Fix verification pending or condition not yet satisfied.', 'ai-auto-fixer' ),
		) );
	}

	/**
	 * Revert an applied change back to its exact pre-fix state.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_execute_rollback( WP_REST_Request $request ): WP_REST_Response {
		$snapshot_uuid = sanitize_text_field( (string) $request->get_param( 'snapshot_uuid' ) );

		if ( empty( $snapshot_uuid ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Snapshot UUID is required.', 'ai-auto-fixer' ),
			), 400 );
		}

		$result = RollbackManager::execute_rollback( $snapshot_uuid );

		if ( ! empty( $result['success'] ) ) {
			$updated_audit   = $this->scanner->run_audit( false );
			$result['audit'] = $updated_audit;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Fetch paginated fix history and snapshots.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_history( WP_REST_Request $request ): WP_REST_Response {
		$limit     = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$snapshots = RollbackManager::get_snapshots( $limit );

		return rest_ensure_response( array(
			'success'   => true,
			'snapshots' => $snapshots,
		) );
	}

	/**
	 * Fetch intelligent built-in AI recommendations.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_recommendations( WP_REST_Request $request ): WP_REST_Response {
		$audit_results   = $this->scanner->get_last_audit_results();
		$recommendations = $this->scanner->get_local_recommendations( (array) $audit_results );

		return rest_ensure_response(
			array(
				'success'         => true,
				'recommendations' => $recommendations,
			)
		);
	}
}
