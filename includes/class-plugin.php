<?php
/**
 * Main Plugin Orchestrator class.
 *
 * @package AiAutoFixer
 */

namespace AiAutoFixer;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Admin\AdminMenu;
use AiAutoFixer\Admin\AdminDashboard;
use AiAutoFixer\Services\SiteAuditScanner;
use AiAutoFixer\Services\SaasApiBridge;
use AiAutoFixer\Services\AutoFixService;
use AiAutoFixer\Api\RestController;

/**
 * Core Plugin singleton orchestrator.
 */
final class Plugin {

	/**
	 * Single instance of the plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin Menu handler.
	 *
	 * @var AdminMenu|null
	 */
	public ?AdminMenu $admin_menu = null;

	/**
	 * Admin Dashboard handler.
	 *
	 * @var AdminDashboard|null
	 */
	public ?AdminDashboard $admin_dashboard = null;

	/**
	 * Site Audit Scanner service.
	 *
	 * @var SiteAuditScanner|null
	 */
	public ?SiteAuditScanner $scanner = null;

	/**
	 * SaaS API Bridge service.
	 *
	 * @var SaasApiBridge|null
	 */
	public ?SaasApiBridge $api_bridge = null;

	/**
	 * Auto-Fix Service executor.
	 *
	 * @var AutoFixService|null
	 */
	public ?AutoFixService $auto_fix_service = null;

	/**
	 * REST API controller.
	 *
	 * @var RestController|null
	 */
	public ?RestController $rest_controller = null;

	/**
	 * Retrieve the main instance of the plugin.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize all core service components and bridges.
	 *
	 * @return void
	 */
	private function init_services(): void {
		$this->scanner          = new SiteAuditScanner();
		$this->api_bridge       = new SaasApiBridge();
		$this->auto_fix_service = new AutoFixService( $this->api_bridge );
		$this->rest_controller  = new RestController( $this->scanner, $this->api_bridge, $this->auto_fix_service );

		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$this->admin_dashboard = new AdminDashboard( $this->scanner, $this->api_bridge, $this->auto_fix_service );
			$this->admin_menu      = new AdminMenu( $this->admin_dashboard );
		}
	}

	/**
	 * Register core WordPress action and filter hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Internationalization.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// REST API routing.
		if ( null !== $this->rest_controller ) {
			add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		}

		// Asynchronous / WP-Cron Site Audit single-event runner.
		if ( null !== $this->scanner ) {
			add_action( 'ai_auto_fixer_run_site_audit', array( $this->scanner, 'execute_audit_job' ) );
		}

		// Allow Pro tier auto-fix features to inject filters/hooks when active.
		if ( null !== $this->auto_fix_service ) {
			$this->auto_fix_service->boot_active_fixes();
		}
	}

	/**
	 * Load plugin localization files.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ai-auto-fixer',
			false,
			dirname( AI_AUTO_FIXER_BASENAME ) . '/languages'
		);
	}

	/**
	 * Getter for Site Audit Scanner.
	 *
	 * @return SiteAuditScanner|null
	 */
	public function get_scanner(): ?SiteAuditScanner {
		return $this->scanner;
	}

	/**
	 * Getter for SaaS API Bridge.
	 *
	 * @return SaasApiBridge|null
	 */
	public function get_api_bridge(): ?SaasApiBridge {
		return $this->api_bridge;
	}

	/**
	 * Getter for Auto-Fix Service.
	 *
	 * @return AutoFixService|null
	 */
	public function get_auto_fix_service(): ?AutoFixService {
		return $this->auto_fix_service;
	}

	/**
	 * Getter for Admin Dashboard.
	 *
	 * @return AdminDashboard|null
	 */
	public function get_admin_dashboard(): ?AdminDashboard {
		return $this->admin_dashboard;
	}
}
