<?php
/**
 * Admin Dashboard Controller (100% Free & Standalone).
 *
 * @package AiAutoFixer\Admin
 */

namespace AiAutoFixer\Admin;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Services\SiteAuditScanner;
use AiAutoFixer\Services\AutoFixService;

/**
 * Controller for the modern, 100% free Admin Dashboard and fix controls.
 */
class AdminDashboard {

	/**
	 * Site Audit Scanner instance.
	 *
	 * @var SiteAuditScanner
	 */
	private SiteAuditScanner $scanner;

	/**
	 * Auto-Fix Service instance.
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

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue modern CSS and JS assets only on AI Auto-Fixer admin screens.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue on AI Auto-Fixer dashboard and settings pages.
		if ( strpos( $hook, 'ai-auto-fixer' ) === false ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'ai-auto-fixer-admin',
			AI_AUTO_FIXER_URL . 'assets/css/admin-dashboard.css',
			array(),
			AI_AUTO_FIXER_VERSION
		);

		// Enqueue JS.
		wp_enqueue_script(
			'ai-auto-fixer-admin',
			AI_AUTO_FIXER_URL . 'assets/js/admin-dashboard.js',
			array( 'jquery' ),
			AI_AUTO_FIXER_VERSION,
			true
		);

		// Localize script data for REST API calls.
		wp_localize_script(
			'ai-auto-fixer-admin',
			'aiAutoFixerData',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'ai-auto-fixer/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isFree'      => true,
				'i18n'        => array(
					'scanning'       => __( 'Running site audit...', 'ai-auto-fixer' ),
					'scanComplete'   => __( 'Audit complete!', 'ai-auto-fixer' ),
					'applyingFix'    => __( 'Applying fix...', 'ai-auto-fixer' ),
					'fixSuccess'     => __( 'Fix applied successfully!', 'ai-auto-fixer' ),
					'fixingAll'      => __( 'Applying all fixes...', 'ai-auto-fixer' ),
					'fixAllSuccess'  => __( 'All issues fixed successfully!', 'ai-auto-fixer' ),
					'genericError'   => __( 'An unexpected error occurred. Please try again.', 'ai-auto-fixer' ),
					'confirmFix'     => __( 'Apply this automated fix now?', 'ai-auto-fixer' ),
					'confirmFixAll'  => __( 'Are you sure you want to apply all recommended automated fixes to this site?', 'ai-auto-fixer' ),
				),
			)
		);
	}

	/**
	 * Render the primary Audit Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-auto-fixer' ) );
		}

		// Retrieve latest audit results, fallback to on-demand run if null.
		$audit_results = $this->scanner->get_last_audit_results();
		if ( null === $audit_results ) {
			$audit_results = $this->scanner->run_audit( false );
		}

		$recommendations = $this->scanner->get_local_recommendations( (array) $audit_results );
		$fix_history     = $this->auto_fixer->get_fix_history();
		$audit_status    = $this->scanner->get_audit_status();
		$settings        = get_option( 'ai_auto_fixer_settings', array() );

		include AI_AUTO_FIXER_PATH . 'views/admin-dashboard-page.php';
	}

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-auto-fixer' ) );
		}

		$settings = get_option( 'ai_auto_fixer_settings', array() );

		// Handle manual form post save if submitted without JS.
		if ( isset( $_POST['ai_auto_fixer_save_settings'] ) ) {
			check_admin_referer( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' );

			$settings['enable_ai_robots']  = ! empty( $_POST['enable_ai_robots'] );
			$settings['enable_geo_schema'] = ! empty( $_POST['enable_geo_schema'] );
			$settings['enable_opengraph']  = ! empty( $_POST['enable_opengraph'] );

			update_option( 'ai_auto_fixer_settings', $settings, 'no' );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings successfully saved.', 'ai-auto-fixer' ) . '</p></div>';
		}

		$audit_results   = $this->scanner->get_last_audit_results();
		$recommendations = $this->scanner->get_local_recommendations( (array) $audit_results );
		$fix_history     = $this->auto_fixer->get_fix_history();
		$audit_status    = $this->scanner->get_audit_status();

		include AI_AUTO_FIXER_PATH . 'views/admin-dashboard-page.php';
	}
}
