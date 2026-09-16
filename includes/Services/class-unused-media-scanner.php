<?php
/**
 * Media Intelligence & 10-Source Dependency Scanner.
 *
 * @package AiAutoFixer\Services
 */

namespace AiAutoFixer\Services;

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deeply scans WordPress media library against 10 distinct content sources to safeguard against accidental deletion.
 */
class UnusedMediaScanner {

	public const STATUS_IN_USE          = 'IN_USE';
	public const STATUS_SAFE_TO_REVIEW  = 'SAFE_TO_REVIEW';
	public const STATUS_POSSIBLY_UNUSED = 'POSSIBLY_UNUSED';
	public const STATUS_UNKNOWN         = 'UNKNOWN';

	/**
	 * Run deep 10-source reference inspection for a specific media attachment.
	 *
	 * @param int $attachment_id Media attachment post ID.
	 * @return array{
	 *     attachment_id: int,
	 *     filename: string,
	 *     url: string,
	 *     status: string,
	 *     total_references: int,
	 *     references: array<string, int>,
	 *     can_trash: bool,
	 *     dependency_report: string
	 * }
	 */
	public static function inspect_media_dependencies( int $attachment_id ): array {
		global $wpdb;

		$url       = wp_get_attachment_url( $attachment_id );
		$file_path = get_attached_file( $attachment_id );
		$filename  = $file_path ? basename( $file_path ) : '';

		$references = array(
			'post_content'        => 0,
			'featured_images'     => 0,
			'woocommerce_gallery' => 0,
			'elementor_meta'      => 0,
			'gutenberg_blocks'    => 0,
			'nav_menus'           => 0,
			'active_widgets'      => 0,
			'custom_fields'       => 0,
			'site_logo_icon'      => 0,
			'css_uncertainty'     => 0,
		);

		if ( empty( $url ) ) {
			return array(
				'attachment_id'     => $attachment_id,
				'filename'          => $filename,
				'url'               => '',
				'status'            => self::STATUS_UNKNOWN,
				'total_references'  => 0,
				'references'        => $references,
				'can_trash'         => false,
				'dependency_report' => __( 'Attachment URL could not be resolved.', 'ai-auto-fixer' ),
			);
		}

		$search_filename = '%' . $wpdb->esc_like( $filename ) . '%';
		$attachment_id_str = (string) $attachment_id;

		// 1. Site Icon / Site Logo / Customizer Header
		$site_logo = get_theme_mod( 'custom_logo' );
		$site_icon = get_option( 'site_icon' );
		if ( (int) $site_logo === $attachment_id || (int) $site_icon === $attachment_id ) {
			$references['site_logo_icon'] = 1;
		}

		// 2. Featured Images (_thumbnail_id)
		$featured_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
			$attachment_id_str
		) );
		$references['featured_images'] = $featured_count;

		// 3. WooCommerce Gallery (_product_image_gallery)
		$woo_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND FIND_IN_SET(%s, meta_value)",
			$attachment_id_str
		) );
		$references['woocommerce_gallery'] = $woo_count;

		// 4. Elementor / Page Builder JSON Metadata (_elementor_data)
		$elementor_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND (meta_value LIKE %s OR meta_value LIKE %s)",
			'%"id":' . $attachment_id_str . ',%',
			$search_filename
		) );
		$references['elementor_meta'] = $elementor_count;

		// 5. Post Content (Standard WordPress body copy)
		$content_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('attachment', 'revision') AND (post_content LIKE %s OR post_content LIKE %s)",
			'%' . $wpdb->esc_like( 'wp-image-' . $attachment_id_str ) . '%',
			$search_filename
		) );
		$references['post_content'] = $content_count;

		// 6. Gutenberg Block ID References
		$gutenberg_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
			'%"id":' . $attachment_id_str . ',%'
		) );
		$references['gutenberg_blocks'] = $gutenberg_count;

		// 7. General Custom Fields & ACF Postmeta
		$acf_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE meta_key NOT IN ('_thumbnail_id', '_elementor_data', '_product_image_gallery') AND (meta_value = %s OR meta_value LIKE %s)",
			$attachment_id_str,
			$search_filename
		) );
		$references['custom_fields'] = $acf_count;

		// 8. Nav Menus
		$nav_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item' AND post_content LIKE %s",
			$search_filename
		) );
		$references['nav_menus'] = $nav_count;

		// 9. Active Widgets Options
		$widget_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE 'widget_%' AND option_value LIKE %s",
			$search_filename
		) );
		$references['active_widgets'] = $widget_count;

		// 10. Check CSS / Unknown uncertainty
		$css_uncertainty = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE (post_content LIKE %s OR post_content LIKE %s)",
			'%' . $wpdb->esc_like( 'background-image' ) . '%' . $search_filename . '%',
			'%' . $wpdb->esc_like( 'data-image' ) . '%' . $attachment_id_str . '%'
		) );
		$references['css_uncertainty'] = $css_uncertainty;

		$total_references = array_sum( $references );

		// Determine Classification State
		if ( $total_references > 0 && 0 === $css_uncertainty ) {
			$status    = self::STATUS_IN_USE;
			$can_trash = false;
			$report    = sprintf( __( 'Asset is actively in use (%d confirmed content references).', 'ai-auto-fixer' ), $total_references );
		} elseif ( $css_uncertainty > 0 ) {
			$status    = self::STATUS_UNKNOWN;
			$can_trash = false;
			$report    = __( 'Asset matched dynamic CSS background or JavaScript data attribute. Automatic deletion is blocked.', 'ai-auto-fixer' ),
		} else {
			// Check upload date
			$post = get_post( $attachment_id );
			$days_old = $post ? ( time() - strtotime( $post->post_date_gmt ) ) / DAY_IN_SECONDS : 0;

			if ( $days_old < 7 ) {
				$status    = self::STATUS_POSSIBLY_UNUSED;
				$can_trash = true;
				$report    = __( 'No detectable content references found, but image was uploaded recently (< 7 days). Review carefully before trashing.', 'ai-auto-fixer' );
			} else {
				$status    = self::STATUS_SAFE_TO_REVIEW;
				$can_trash = true;
				$report    = __( 'No detectable references found across all 10 standard database tables. Candidate for moving to Trash.', 'ai-auto-fixer' );
			}
		}

		return array(
			'attachment_id'     => $attachment_id,
			'filename'          => $filename,
			'url'               => $url,
			'status'            => $status,
			'total_references'  => $total_references,
			'references'        => $references,
			'can_trash'         => $can_trash,
			'dependency_report' => $report,
		);
	}

	/**
	 * Safely move an attachment to the WordPress Trash.
	 *
	 * Hard rule: Direct permanent deletion is prohibited.
	 *
	 * @param int $attachment_id Media attachment ID.
	 * @return array{success: bool, message: string}
	 */
	public static function move_to_trash( int $attachment_id ): array {
		if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied: Administrator role required to trash media.', 'ai-auto-fixer' ),
			);
		}

		// Pre-trash safety dependency check
		$inspection = self::inspect_media_dependencies( $attachment_id );
		if ( ! $inspection['can_trash'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Dependency report description */
					__( 'Action blocked by Media Protection Engine: %s', 'ai-auto-fixer' ),
					$inspection['dependency_report']
				),
			);
		}

		// Move strictly to Trash (false = trash, true = force delete)
		$trashed = wp_trash_post( $attachment_id );

		if ( $trashed ) {
			return array(
				'success' => true,
				'message' => __( 'Media successfully moved to WordPress Trash. It can be restored at any time.', 'ai-auto-fixer' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to move media to Trash. Verify file permissions.', 'ai-auto-fixer' ),
		);
	}
}
