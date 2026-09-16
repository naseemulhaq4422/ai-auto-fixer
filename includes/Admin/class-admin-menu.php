<?php
/**
 * Admin Menu Handler (Multi-Submenu Navigation).
 *
 * @package AiAutoFixer\Admin
 */

namespace AiAutoFixer\Admin;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin menu registration with rich submenus and custom iconography.
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

		// Top-level menu page
		add_menu_page(
			__( 'AI Auto-Fixer: Enterprise SEO & Health', 'ai-auto-fixer' ),
			__( 'AI Auto-Fixer', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this->dashboard, 'render_dashboard_page' ),
			$icon_svg,
			75
		);

		// Submenu 1: Overview Dashboard
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Audit Dashboard & Score', 'ai-auto-fixer' ),
			__( 'Audit Dashboard', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this->dashboard, 'render_dashboard_page' )
		);

		// Submenu 2: Indexation Health
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Indexation Health & Crawl Diagnostics', 'ai-auto-fixer' ),
			__( 'Indexation Health', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-indexation',
			array( $this->dashboard, 'render_indexation_page' )
		);

		// Submenu 3: Image SEO & Smart ALT
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Image SEO & Smart ALT', 'ai-auto-fixer' ),
			__( 'Image SEO', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-images',
			array( $this->dashboard, 'render_images_page' )
		);

		// Submenu 4: Unlinked Media Cleanup
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Safe Media Cleanup', 'ai-auto-fixer' ),
			__( 'Media Cleanup', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-media',
			array( $this->dashboard, 'render_media_page' )
		);

		// Submenu 5: Technical SEO & Links
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Technical SEO & Broken Links', 'ai-auto-fixer' ),
			__( 'Technical SEO', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-technical',
			array( $this->dashboard, 'render_technical_page' )
		);

		// Submenu 6: Schema & AI/GEO
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Schema & AI Search (GEO/AEO)', 'ai-auto-fixer' ),
			__( 'Schema & AI / GEO', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-schema',
			array( $this->dashboard, 'render_schema_page' )
		);

		// Submenu 7: Fix History & 1-Click Rollback
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Fix History & Rollbacks', 'ai-auto-fixer' ),
			__( 'Fix History', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-history',
			array( $this->dashboard, 'render_history_page' )
		);

		// Submenu 8: Settings & Compatibility Matrix
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings & Compatibility Matrix', 'ai-auto-fixer' ),
			__( 'Settings', 'ai-auto-fixer' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			array( $this->dashboard, 'render_settings_page' )
		);
	}
}
