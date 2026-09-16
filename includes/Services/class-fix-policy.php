<?php
/**
 * Machine-Readable Fix Policy Engine & Interface.
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
	public function get_risk_level(): string; // 'low' | 'medium' | 'high'
	public function requires_confirmation(): bool;
	public function is_reversible(): bool;
	public function requires_verification(): bool;
	public function supports_rollback(): bool;
}

/**
 * Concrete Fix Policy implementation enforcing explicit machine-readable constraints.
 */
class FixPolicy implements FixPolicyInterface {

	public const POLICY_SAFE   = 'safe_auto_fix';
	public const POLICY_REVIEW = 'review_required';
	public const POLICY_MANUAL = 'manual_only';

	public const RISK_LOW    = 'low';
	public const RISK_MEDIUM = 'medium';
	public const RISK_HIGH   = 'high';

	private string $action_type;
	private string $risk_level;
	private bool $requires_confirmation;
	private bool $is_reversible;
	private bool $requires_verification;
	private bool $supports_rollback;

	public function __construct(
		string $action_type = self::POLICY_SAFE,
		string $risk_level = self::RISK_LOW,
		bool $requires_confirmation = true,
		bool $is_reversible = true,
		bool $requires_verification = true,
		bool $supports_rollback = true
	) {
		$this->action_type           = $action_type;
		$this->risk_level            = $risk_level;
		$this->requires_confirmation = $requires_confirmation;
		$this->is_reversible         = $is_reversible;
		$this->requires_verification = $requires_verification;
		$this->supports_rollback     = $supports_rollback;
	}

	public function get_action_type(): string {
		return $this->action_type;
	}

	public function get_risk_level(): string {
		return $this->risk_level;
	}

	public function requires_confirmation(): bool {
		return $this->requires_confirmation;
	}

	public function is_reversible(): bool {
		return $this->is_reversible;
	}

	public function requires_verification(): bool {
		return $this->requires_verification;
	}

	public function supports_rollback(): bool {
		return $this->supports_rollback;
	}

	/**
	 * Factory helper to determine policy for an issue array.
	 *
	 * @param array $issue Issue definition.
	 * @return FixPolicy
	 */
	public static function from_issue( array $issue ): FixPolicy {
		$fix_action = $issue['fix_action'] ?? ( $issue['fix_id'] ?? '' );
		$auto_fix   = ! empty( $issue['auto_fixable'] );

		if ( ! $auto_fix || empty( $fix_action ) ) {
			return new self( self::POLICY_MANUAL, self::RISK_HIGH, true, false, false, false );
		}

		$safe_actions = array(
			'apply_image_alt',
			'enable_core_sitemap',
			'enable_search_visibility',
			'inject_geo_schema',
			'inject_opengraph_tags',
		);

		if ( in_array( $fix_action, $safe_actions, true ) ) {
			return new self( self::POLICY_SAFE, self::RISK_LOW, true, true, true, true );
		}

		return new self( self::POLICY_REVIEW, self::RISK_MEDIUM, true, true, true, true );
	}
}
