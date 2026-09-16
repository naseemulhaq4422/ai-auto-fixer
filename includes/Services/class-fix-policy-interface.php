<?php
/**
 * Fix Policy Interface Contract.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface contract for actionable fix policies.
 */
interface FixPolicyInterface {
	public function get_risk_level(): string;
	public function requires_confirmation(): bool;
	public function is_reversible(): bool;
	public function requires_verification(): bool;
	public function supports_rollback(): bool;
}
