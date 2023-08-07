<?php
/**
 *	SyncS3 Admin Notices
 *
 *	@package SyncS3 for Gravity Forms
 */

namespace SyncS3;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminNotices {

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_em_dismiss_admin_notice', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Add the notices
	 *
	 * @return void
	 */
	public function admin_notices() {
		$this->direct_to_s3_notice();
	}

	/**
	 * Dismiss a notice
	 *
	 * @return void
	 */
	public function dismiss_notice() {

		// Security check
		if ( empty( $_POST['notice'] ) || empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'em_dismiss_admin_notice' ) ) {
			die();
		}

		$notice = sanitize_text_field( $_POST['notice'] );
		$dismissed_notices = (array) get_option( 'em_dismissed_notices' );
		// $dismissed_notices = ! empty( $dismissed_notices ) ? $dismissed_notices : array();
		if ( ! in_array( $notice, $dismissed_notices ) ) {
			$dismissed_notices[] = $notice;
		}
		update_option( 'em_dismissed_notices', $dismissed_notices );
		wp_send_json_success();
	}

	/**
	 * Whether a notice was already dismissed
	 *
	 * @param  string  $notice 	Notice ID
	 *
	 * @return boolean
	 */
	public function is_notice_dismissed( $notice ) {
		$dismissed_notices = (array) get_option( 'em_dismissed_notices' );
		return in_array( $notice, $dismissed_notices );
	}

	/**
	 * Notice for "Direct to S3" feature
	 *
	 * @return void
	 */
	public function direct_to_s3_notice() {
		$notice = 'syncs3_notice_direct_to_s3';
		if ( $this->is_notice_dismissed( $notice ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible em-admin-notice">
			<h4>SyncS3 for Gravity Forms now supports "Direct to S3" uploads!</h4>
			<p>Bypass all your server's limitations, such as upload size and timeouts. "Direct to S3" sends file uploads directly to your S3 bucket without ever hitting your server.</p>
			<p><a href="https://elegantmodules.com/direct-to-s3-uploads-with-syncs3/" target="_blank" class="button button-primary">Learn how to get started.</a></p>
			<?php $this->dismiss_script( $notice ); ?>
		</div>
		<?php
	}

	public function dismiss_script( $notice ) {
		?>
		<script>
			jQuery(document).ready(function($) {
				$('body').on('click', '.em-admin-notice button.notice-dismiss', function(event) {
					var $this = $(this);
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'em_dismiss_admin_notice',
							nonce: "<?php echo wp_create_nonce( 'em_dismiss_admin_notice' ); ?>",
							notice: "<?php echo $notice; ?>"
						},
					})
					.done(function() {
						console.log("Notice dismissed");
					});
				});
			});
		</script>
		<?php
	}
}

new AdminNotices;