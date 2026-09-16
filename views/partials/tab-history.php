<?php
/**
 * Partial: Tab Fix History & 1-Click Rollback Manager.
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-history-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Transactional Fix Snapshots & 1-Click Rollback', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Every automated fix captures an atomic pre-state snapshot validated with SHA-256 integrity checksums. Revert any applied change in 1 click.', 'ai-auto-fixer' ); ?></p>
	</div>

	<div class="aaf-card" style="margin-top: 20px;">
		<table class="aaf-table aaf-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date & Time', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Fix Action', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Object Target', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Integrity Checksum', 'ai-auto-fixer' ); ?></th>
					<th><?php esc_html_e( 'Rollback Trigger', 'ai-auto-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody id="aaf-history-tbody">
				<?php if ( empty( $fix_history ) ) : ?>
					<tr>
						<td colspan="6" class="aaf-empty-cell">
							<span class="dashicons dashicons-clock"></span>
							<p><?php esc_html_e( 'No automated fixes have been executed yet.', 'ai-auto-fixer' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $fix_history as $snap ) : ?>
						<?php
						$is_reverted = 'reverted' === $snap->status;
						$checksum_short = substr( (string) $snap->checksum, 0, 12 ) . '...';
						?>
						<tr data-uuid="<?php echo esc_attr( $snap->snapshot_uuid ); ?>">
							<td><code><?php echo esc_html( $snap->created_at ); ?></code></td>
							<td><strong><?php echo esc_html( $snap->fix_id ); ?></strong></td>
							<td><code><?php echo esc_html( $snap->object_type . ':' . $snap->object_id ); ?></code></td>
							<td>
								<?php if ( $is_reverted ) : ?>
									<span class="aaf-badge aaf-badge-warning"><?php esc_html_e( 'Reverted', 'ai-auto-fixer' ); ?></span>
								<?php else : ?>
									<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Available', 'ai-auto-fixer' ); ?></span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $checksum_short ); ?></code></td>
							<td>
								<?php if ( ! $is_reverted ) : ?>
									<button type="button" class="aaf-btn aaf-btn-sm aaf-btn-secondary aaf-trigger-rollback-btn" data-uuid="<?php echo esc_attr( $snap->snapshot_uuid ); ?>">
										<span class="dashicons dashicons-undo"></span>
										<span class="aaf-btn-label"><?php esc_html_e( '1-Click Rollback', 'ai-auto-fixer' ); ?></span>
									</button>
								<?php else : ?>
									<span class="aaf-text-muted"><?php esc_html_e( 'Restored', 'ai-auto-fixer' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
