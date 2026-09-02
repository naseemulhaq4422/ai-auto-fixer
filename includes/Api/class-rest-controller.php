<?php
/**
 * REST API Controller (100% Free & Standalone).
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

/**
 * Handles all REST API routes for asynchronous dashboard actions and 1-click auto-fixes.
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
	 * Register the REST API endpoints.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /wp-json/ai-auto-fixer/v1/scan - Run fresh site audit.
		register_rest_route(
			$this->namespace,
			'/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_run_scan' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// GET /wp-json/ai-auto-fixer/v1/results - Retrieve current audit results.
		register_rest_route(
			$this->namespace,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_results' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// POST /wp-json/ai-auto-fixer/v1/autofix - Execute individual auto-fix.
		register_rest_route(
			$this->namespace,
			'/autofix',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_execute_autofix' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'fix_action' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context'    => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		// POST /wp-json/ai-auto-fixer/v1/autofix/all - Execute all auto-fixes in 1 click.
		register_rest_route(
			$this->namespace,
			'/autofix/all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_execute_all_autofix' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// GET /wp-json/ai-auto-fixer/v1/recommendations - Fetch local AI GEO/AEO recommendations.
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
	 * Return latest audit findings.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_get_results( WP_REST_Request $request ): WP_REST_Response {
		$results = $this->scanner->get_last_audit_results();
		if ( null === $results ) {
			$results = $this->scanner->run_audit( false );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => $this->scanner->get_audit_status(),
				'results' => $results,
			)
		);
	}

	/**
	 * Execute an automated fix for a specific audited issue.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_execute_autofix( WP_REST_Request $request ): WP_REST_Response {
		$fix_action = sanitize_text_field( $request->get_param( 'fix_action' ) );
		$context    = (array) $request->get_param( 'context' );

		$result = $this->auto_fixer->execute_fix( $fix_action, $context );

		// If fix was successful, refresh audit results.
		if ( ! empty( $result['success'] ) ) {
			$updated_audit   = $this->scanner->run_audit( false );
			$result['audit'] = $updated_audit;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Execute all automated fixes in 1 click.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_execute_all_autofix( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->auto_fixer->execute_all_fixes();

		if ( ! empty( $result['success'] ) ) {
			$updated_audit   = $this->scanner->run_audit( false );
			$result['audit'] = $updated_audit;
		}

		return rest_ensure_response( $result );
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
