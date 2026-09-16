<?php
/**
 * Partial: Tab Overview (Findings, Auto-Fixes & Recommendations).
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-tab-overview-wrap">
	<!-- Audit Results Table -->
	<?php include AI_AUTO_FIXER_PATH . 'views/partials/audit-results-table.php'; ?>

	<div style="margin-top: 32px;">
		<!-- Recommendations Card -->
		<?php include AI_AUTO_FIXER_PATH . 'views/partials/recommendations-card.php'; ?>
	</div>
</div>
