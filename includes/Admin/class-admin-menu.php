<?php
/**
 * Admin Menu Handler.
 *
 * @package AiAutoFixer\Admin
 */

namespace AiAutoFixer\Admin;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin menu registration and custom iconography.
 */
class AdminMenu {

	/**
	 * Admin Dashboard controller instance.
	 *
	 * @var AdminDashboard
	 */
	private AdminDashboard $dashboard;

	/**
	 * Menu slug constant.
	 */
	public const MENU_SLUG = 'ai-auto-fixer';

	/**
	 * Constructor.
	 *
	 * @param AdminDashboard $dashboard Injected dashboard controller.
	 */
	public function __construct( AdminDashboard $dashboard ) {
		$this->dashboard = $dashboard;
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the AI Auto-Fixer top-level admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/><path d="m15 9-6 6"/></svg>'
		);

		// Top-level menu page.
		add_menu_page(
			__( 'AI Auto-Fixer: Site Audit & GEO Health', 'ai-auto-fixer' ),
			__( 'AI Auto-Fixer', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this->dashboard, 'render_dashboard_page' ),
			$icon_svg,
			75
		);

		// Submenu: Dashboard / Audit Overview.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Site Audit & Health', 'ai-auto-fixer' ),
			__( 'Audit Dashboard', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this->dashboard, 'render_dashboard_page' )
		);

		// Submenu: Settings & License Key.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'License & SaaS Settings', 'ai-auto-fixer' ),
			__( 'Settings & License', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			array( $this->dashboard, 'render_settings_page' )
		);
	}
}
