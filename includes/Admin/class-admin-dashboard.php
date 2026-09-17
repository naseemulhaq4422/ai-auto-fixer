<?php
/**
 * Admin Dashboard Controller (Multi-Tab & Modular Views).
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
use AiAutoFixer\Services\RollbackManager;
use AiAutoFixer\Detectors\SeoPluginDetector;
use AiAutoFixer\Detectors\ConflictDetector;
use AiAutoFixer\Detectors\SchemaOwnershipDetector;
use AiAutoFixer\Detectors\MetaOwnershipDetector;

/**
 * Controller for the modern, multi-tab Admin Dashboard and interactive remediation controls.
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
	 * Enqueue CSS and JS assets on AI Auto-Fixer admin screens.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'ai-auto-fixer' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'ai-auto-fixer-admin',
			AI_AUTO_FIXER_URL . 'assets/css/admin-dashboard.css',
			array(),
			AI_AUTO_FIXER_VERSION
		);

		wp_enqueue_script(
			'ai-auto-fixer-admin',
			AI_AUTO_FIXER_URL . 'assets/js/admin-dashboard.js',
			array( 'jquery' ),
			AI_AUTO_FIXER_VERSION,
			true
		);

		wp_localize_script(
			'ai-auto-fixer-admin',
			'aiAutoFixerData',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'ai-auto-fixer/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isFree'      => true,
				'i18n'        => array(
					'scanning'         => __( 'Running site audit & deep crawl...', 'ai-auto-fixer' ),
					'scanComplete'     => __( 'Audit complete!', 'ai-auto-fixer' ),
					'applyingFix'      => __( 'Applying fix...', 'ai-auto-fixer' ),
					'fixSuccess'       => __( 'Fix applied successfully!', 'ai-auto-fixer' ),
					'fixingAll'        => __( 'Applying all safe fixes...', 'ai-auto-fixer' ),
					'fixAllSuccess'    => __( 'All safe issues fixed successfully!', 'ai-auto-fixer' ),
					'rollingBack'      => __( 'Reverting changes...', 'ai-auto-fixer' ),
					'rollbackSuccess'  => __( 'Rollback completed successfully!', 'ai-auto-fixer' ),
					'trashingMedia'    => __( 'Moving to trash...', 'ai-auto-fixer' ),
					'genericError'     => __( 'An unexpected error occurred. Please try again.', 'ai-auto-fixer' ),
					'confirmFix'       => __( 'Apply this automated fix now?', 'ai-auto-fixer' ),
					'confirmFixAll'    => __( 'Apply all verified low-risk fixes across the website? A pre-fix snapshot will be automatically saved for 1-click rollback.', 'ai-auto-fixer' ),
					'confirmRollback'  => __( 'Are you sure you want to revert this change back to its exact pre-fix state?', 'ai-auto-fixer' ),
					'confirmTrash'     => __( 'Move this media item to WordPress Trash? (It will NOT be permanently deleted and can be restored at any time).', 'ai-auto-fixer' ),
				),
			)
		);
	}

	/**
	 * Primary Dashboard / Overview tab.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		$this->render_view( 'overview' );
	}

	/**
	 * Indexation tab.
	 *
	 * @return void
	 */
	public function render_indexation_page(): void {
		$this->render_view( 'indexation' );
	}

	/**
	 * Image SEO tab.
	 *
	 * @return void
	 */
	public function render_images_page(): void {
		$this->render_view( 'images' );
	}

	/**
	 * Media Cleanup tab.
	 *
	 * @return void
	 */
	public function render_media_page(): void {
		$this->render_view( 'media' );
	}

	/**
	 * Technical SEO tab.
	 *
	 * @return void
	 */
	public function render_technical_page(): void {
		$this->render_view( 'technical' );
	}

	/**
	 * Schema & AI/GEO tab.
	 *
	 * @return void
	 */
	public function render_schema_page(): void {
		$this->render_view( 'schema' );
	}

	/**
	 * Fix History tab.
	 *
	 * @return void
	 */
	public function render_history_page(): void {
		$this->render_view( 'history' );
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-auto-fixer' ) );
		}

		$settings = get_option( 'ai_auto_fixer_settings', array() );

		// Handle manual settings post save
		if ( isset( $_POST['ai_auto_fixer_save_settings'] ) ) {
			check_admin_referer( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' );

			$settings['enable_ai_robots']  = ! empty( $_POST['enable_ai_robots'] );
			$settings['enable_geo_schema'] = ! empty( $_POST['enable_geo_schema'] );
			$settings['enable_opengraph']  = ! empty( $_POST['enable_opengraph'] );

			update_option( 'ai_auto_fixer_settings', $settings, 'no' );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings successfully saved.', 'ai-auto-fixer' ) . '</p></div>';
		}

		$this->render_view( 'settings' );
	}

	/**
	 * Master view renderer.
	 *
	 * @param string $active_tab Active tab slug.
	 * @return void
	 */
	private function render_view( string $active_tab ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-auto-fixer' ) );
		}

		$audit_results = $this->scanner->get_last_audit_results();
		if ( null === $audit_results ) {
			$audit_results = array(
				'seo_health_score' => 0,
				'safety_score'     => 95,
				'total_issues'     => 0,
				'crawled_urls'     => 0,
				'issues'           => array(),
				'passes'           => array(),
				'counts'           => array(
					'critical'       => 0,
					'warning'        => 0,
					'info'           => 0,
					'recommendation' => 0,
					'passed'         => 0,
				),
				'scan_date'        => __( 'Ready for initial audit', 'ai-auto-fixer' ),
				'safe_fixes'       => 0,
				'status'           => 'ready',
			);
		}

		// Handle settings post save
		$settings_saved = false;
		if ( isset( $_POST['ai_auto_fixer_save_settings'] ) ) {
			check_admin_referer( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' );

			$current_settings = get_option( 'ai_auto_fixer_settings', array() );
			$current_settings['enable_ai_robots']  = ! empty( $_POST['enable_ai_robots'] );
			$current_settings['enable_geo_schema'] = ! empty( $_POST['enable_geo_schema'] );
			$current_settings['enable_opengraph']  = ! empty( $_POST['enable_opengraph'] );

			update_option( 'ai_auto_fixer_settings', $current_settings, 'no' );
			$settings_saved = true;
			$active_tab     = 'settings';
		}

		$recommendations = $this->scanner->get_local_recommendations( (array) $audit_results );
		$fix_history     = RollbackManager::get_snapshots( 50 );
		$audit_status    = $this->scanner->get_audit_status();
		$settings        = get_option( 'ai_auto_fixer_settings', array() );

		// Environment detectors
		$detected_seo_plugins = SeoPluginDetector::detect();
		$detected_conflicts   = ConflictDetector::detect_conflicts();

		$current_tab = isset( $_POST['ai_auto_fixer_save_settings'] )
			? 'settings'
			: ( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : $active_tab );

		include AI_AUTO_FIXER_PATH . 'views/admin-dashboard-page.php';
	}
}
