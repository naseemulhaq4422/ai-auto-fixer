<?php
/**
 * Autoloader for AI Auto-Fixer classes.
 *
 * @package AiAutoFixer
 */

namespace AiAutoFixer;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class responsible for loading plugin classes based on namespace.
 */
class Autoloader {

	/**
	 * Class prefix namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'AiAutoFixer\\';

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Loads the class file corresponding to the requested class name.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		// Check if class uses the plugin's namespace prefix.
		$len = strlen( self::NAMESPACE_PREFIX );
		if ( strncmp( self::NAMESPACE_PREFIX, $class, $len ) !== 0 ) {
			return;
		}

		// Get relative class name.
		$relative_class = substr( $class, $len );

		// Split by namespace separators.
		$parts = explode( '\\', $relative_class );

		// The last element is the class name itself.
		$class_name = array_pop( $parts );

		// Convert class name to WordPress file naming format: class-kebab-case.php
		$kebab = strtolower( (string) preg_replace( '/([a-z\d])([A-Z])/', '$1-$2', $class_name ) );
		$kebab = (string) preg_replace( '/-+/', '-', $kebab );
		$formatted_class_name = 'class-' . $kebab . '.php';

		// Build the path with any sub-namespaces/sub-directories.
		$sub_path = ! empty( $parts ) ? implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR : '';

		$file_path = AI_AUTO_FIXER_PATH . 'includes' . DIRECTORY_SEPARATOR . $sub_path . $formatted_class_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return;
		}

		// Alternative 1: woocommerce without hyphen (e.g. class-woocommerce-scanner.php)
		$alt_name = str_replace( 'woo-commerce', 'woocommerce', $formatted_class_name );
		$alt_file = AI_AUTO_FIXER_PATH . 'includes' . DIRECTORY_SEPARATOR . $sub_path . $alt_name;
		if ( file_exists( $alt_file ) ) {
			require_once $alt_file;
			return;
		}

		// Alternative 2: FixPolicyInterface -> class-fix-policy.php
		if ( strpos( $formatted_class_name, 'fix-policy-interface' ) !== false ) {
			$fp_file = AI_AUTO_FIXER_PATH . 'includes' . DIRECTORY_SEPARATOR . $sub_path . 'class-fix-policy.php';
			if ( file_exists( $fp_file ) ) {
				require_once $fp_file;
				return;
			}
		}

		// Alternative 3: Standard PSR-4
		$psr4_file = AI_AUTO_FIXER_PATH . 'includes' . DIRECTORY_SEPARATOR . $sub_path . $class_name . '.php';
		if ( file_exists( $psr4_file ) ) {
			require_once $psr4_file;
			return;
		}
	}
}
