<?php
/**
 * Admin Dashboard Controller.
 *
 * @package AiAutoFixer\Admin
 */

namespace AiAutoFixer\Admin;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiAutoFixer\Services\SiteAuditScanner;
use AiAutoFixer\Services\SaasApiBridge;
use AiAutoFixer\Services\AutoFixService;

/**
 * Controller for the modern SaaS Admin Dashboard and settings views.
 */
class AdminDashboard {

	/**
	 * Site Audit Scanner instance.
	 *
	 * @var SiteAuditScanner
	 */
	private SiteAuditScanner $scanner;

	/**
	 * SaaS API Bridge instance.
	 *
	 * @var SaasApiBridge
	 */
	private SaasApiBridge $api_bridge;

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
	 * @param SaasApiBridge    $api_bridge Injected API Bridge.
	 * @param AutoFixService   $auto_fixer Injected Auto-Fixer.
	 */
	public function __construct( SiteAuditScanner $scanner, SaasApiBridge $api_bridge, AutoFixService $auto_fixer ) {
		$this->scanner    = $scanner;
		$this->api_bridge = $api_bridge;
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

		$license = $this->api_bridge->verify_license();

		// Localize script data for REST API calls.
		wp_localize_script(
			'ai-auto-fixer-admin',
			'aiAutoFixerData',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'ai-auto-fixer/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isPro'       => ! empty( $license['is_active'] ) && 'free' !== $license['tier'],
				'licenseTier' => $license['tier'] ?? 'free',
				'upgradeUrl'  => esc_url( 'https://app.creativesdigitalagency.com/' ),
				'i18n'        => array(
					'scanning'       => __( 'Running live site audit...', 'ai-auto-fixer' ),
					'scanComplete'   => __( 'Audit complete!', 'ai-auto-fixer' ),
					'applyingFix'    => __( 'Applying auto-fix securely...', 'ai-auto-fixer' ),
					'fixSuccess'     => __( 'Fix applied successfully!', 'ai-auto-fixer' ),
					'verifyingKey'   => __( 'Verifying SaaS API key...', 'ai-auto-fixer' ),
					'keySaved'       => __( 'License successfully verified and activated!', 'ai-auto-fixer' ),
					'upgradePrompt'  => __( 'Upgrade to Auto-Fix', 'ai-auto-fixer' ),
					'genericError'   => __( 'An unexpected error occurred. Please try again.', 'ai-auto-fixer' ),
					'confirmFix'     => __( 'Apply this automated fix now?', 'ai-auto-fixer' ),
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

		$license         = $this->api_bridge->verify_license();
		$recommendations = $this->api_bridge->fetch_ai_recommendations( $audit_results );
		$fix_history     = $this->auto_fixer->get_fix_history();
		$audit_status    = $this->scanner->get_audit_status();

		include AI_AUTO_FIXER_PATH . 'views/admin-dashboard-page.php';
	}

	/**
	 * Render the Settings & License page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-auto-fixer' ) );
		}

		$settings = get_option( 'ai_auto_fixer_settings', array() );
		$license  = $this->api_bridge->verify_license( '', true );

		// Handle manual form post save if submitted without JS.
		if ( isset( $_POST['ai_auto_fixer_save_settings'] ) ) {
			check_admin_referer( 'ai_auto_fixer_settings_action', 'ai_auto_fixer_settings_nonce' );

			$new_api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
			$this->api_bridge->save_api_key( $new_api_key );
			$license = $this->api_bridge->verify_license( $new_api_key, true );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings and API Key updated.', 'ai-auto-fixer' ) . '</p></div>';
		}

		$audit_results   = $this->scanner->get_last_audit_results();
		$recommendations = $this->api_bridge->fetch_ai_recommendations( (array) $audit_results );
		$fix_history     = $this->auto_fixer->get_fix_history();
		$audit_status    = $this->scanner->get_audit_status();

		include AI_AUTO_FIXER_PATH . 'views/admin-dashboard-page.php';
	}
}
