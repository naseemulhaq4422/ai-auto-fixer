<?php
/**
 * Plugin Name:       AI Auto-Fixer
 * Plugin URI:        https://app.creativesdigitalagency.com/
 * Description:       Intelligent SEO, GEO (Generative Engine Optimization), and AEO site auditor with automated 1-click AI fixes powered by a SaaS backend.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Creatives Digital Agency
 * Author URI:        https://app.creativesdigitalagency.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-auto-fixer
 * Domain Path:       /languages
 *
 * @package           AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin core constants.
 */
define( 'AI_AUTO_FIXER_VERSION', '1.0.0' );
define( 'AI_AUTO_FIXER_FILE', __FILE__ );
define( 'AI_AUTO_FIXER_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_AUTO_FIXER_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_AUTO_FIXER_BASENAME', plugin_basename( __FILE__ ) );
define( 'AI_AUTO_FIXER_MIN_PHP', '7.4' );
define( 'AI_AUTO_FIXER_MIN_WP', '5.8' );

/**
 * Check PHP and WordPress version compatibility before booting.
 *
 * @return bool True if environment meets requirements, false otherwise.
 */
function ai_auto_fixer_check_compatibility(): bool {
	global $wp_version;

	$php_compatible = version_compare( PHP_VERSION, AI_AUTO_FIXER_MIN_PHP, '>=' );
	$wp_compatible  = version_compare( $wp_version, AI_AUTO_FIXER_MIN_WP, '>=' );

	if ( ! $php_compatible || ! $wp_compatible ) {
		add_action(
			'admin_notices',
			function () use ( $php_compatible, $wp_compatible ) {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'AI Auto-Fixer could not be initialized:', 'ai-auto-fixer' ); ?></strong>
						<?php
						if ( ! $php_compatible ) {
							printf(
								/* translators: 1: Required PHP version, 2: Current PHP version */
								esc_html__( ' Requires PHP %1$s or higher (running %2$s).', 'ai-auto-fixer' ),
								esc_html( AI_AUTO_FIXER_MIN_PHP ),
								esc_html( PHP_VERSION )
							);
						}
						if ( ! $wp_compatible ) {
							global $wp_version;
							printf(
								/* translators: 1: Required WP version, 2: Current WP version */
								esc_html__( ' Requires WordPress %1$s or higher (running %2$s).', 'ai-auto-fixer' ),
								esc_html( AI_AUTO_FIXER_MIN_WP ),
								esc_html( $wp_version )
							);
						}
						?>
					</p>
				</div>
				<?php
			}
		);
		return false;
	}

	return true;
}

if ( ! ai_auto_fixer_check_compatibility() ) {
	return;
}

// Load and register the class autoloader.
require_once AI_AUTO_FIXER_PATH . 'includes/class-autoloader.php';
\AiAutoFixer\Autoloader::register();

/**
 * Activation hook callback.
 */
register_activation_hook(
	__FILE__,
	function () {
		\AiAutoFixer\Activator::activate();
	}
);

/**
 * Deactivation hook callback.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		\AiAutoFixer\Deactivator::deactivate();
	}
);

/**
 * Initialize and bootstrap the plugin instance on 'plugins_loaded'.
 */
function ai_auto_fixer_init() {
	return \AiAutoFixer\Plugin::instance();
}
add_action( 'plugins_loaded', 'ai_auto_fixer_init' );
