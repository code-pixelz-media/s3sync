<?php
namespace SyncS3;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'Plugin_Updater.php';

class Updater {

	private $plugin_data;
	private $updater_url;
	private $updater_id;

	public function __construct() {

		$this->updater_url = 'https://elegantmodules.com';
		$this->updater_id = 446;

		add_action( 'admin_init', array( $this, 'new_updater' ), 0 );
		add_action( 'gform_settings_syncs3', array( $this, 'display_status' ), 99 );
		add_action( 'wp_ajax_syncs3_get_license_status', array( $this, 'ajax_get_license_status' ) );
		add_action( 'wp_ajax_syncs3_activate_license', array( $this, 'activate' ) );
	}

	/**
	 * The license key setting is saved after the settings are painted, so use AJAX to get the current license status.
	 *
	 * @return void
	 */
	public function ajax_get_license_status() {

		// Security check
		if ( empty( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'syncs3_get_license_status' ) ) {
			die();
		}

		$status = get_option( 'syncs3_license_status' );

		ob_start();

		if ( 'valid' === $status ) : ?>
			<div style="color: #067506;">
				<p><?php esc_html_e( 'Your SyncS3 license is active.', 'syncs3' ); ?></p>
			</div>
		<?php else : ?>
			<div style="color: #d63638;">
				<p><?php esc_html_e( 'Your SyncS3 license is not active.', 'syncs3' ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		$output = ob_get_clean();

		wp_send_json_success( array(
			'status_html' => str_replace( array( "\n", "\t" ), '', $output )
		) );
	}

	/**
	 * Activate a license when requested.
	 *
	 * @return void
	 */
	public function activate() {

		if ( empty( $_POST['nonce'] ) ) {
			die();
		}

		$response = $this->activate_license();
		$status = get_option( 'syncs3_license_status' );

		ob_start();

		if ( 'valid' === $status ) : ?>
			<div style="color: #067506;">
				<?php esc_html_e( 'Your SyncS3 license is active.', 'syncs3' ); ?>
			</div>
		<?php else : ?>
			<div style="color: #d63638;">
				<?php esc_html_e( 'Your SyncS3 license could not be activated. Please refresh the page and try again in a moment. If the problem persist, please contact Elegant Modules support.', 'syncs3' ); ?>
			</div>
		<?php endif; ?>

		<?php
		$output = ob_get_clean();

		wp_send_json_success( array(
			'response' => $response,
			'status_html' => str_replace( array( "\n", "\t" ), '', $output )
		) );
	}

	/**
	 * Displays the license status on the SyncS3 plugin settings page.
	 *
	 * @return void
	 */
	public function display_status() {

		$addon_options = get_option( 'gravityformsaddon_syncs3_settings' );
		$license_status = get_option( 'syncs3_license_status' );

		if ( empty( $addon_options['syncs3_license_key'] ) ) {
			delete_option( 'syncs3_license_status' );
			$license_status = '';
		}

		ob_start();
		?>
		<style>
			#gform_setting_syncs3_license_key input[type="text"] {
				border: 1px solid <?php echo ! empty( $addon_options['syncs3_license_key'] ) && 'valid' !== $license_status ? '#d63638' : '#9092b2'; ?>;
			}
		</style>
		<div id="gaddon-setting-row-syncs3_license_status" style="display: none;">
			<div id="syncs3_license_status"></div>
			<div id="syncs3_license_actions">
				<?php if ( ! empty( $addon_options['syncs3_license_key'] ) && 'valid' !== $license_status ) : ?>
					<p>
						<button id="syncs3_activate_license" class="primary button large"><?php esc_html_e( 'Activate License', 'syncs3' ); ?></button>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php $row = str_replace( array( "\n", "\t" ), '', ob_get_clean() ); ?>
		<script>
			jQuery(document).ready(function($) {
				$('#gform_setting_syncs3_license_key').append('<?php echo $row; ?>');

				var licenseStatus = $('#syncs3_license_status');

				$.ajax({
					url: ajaxurl,
					type: 'GET',
					data: {
						action: 'syncs3_get_license_status',
						nonce: "<?php echo wp_create_nonce( 'syncs3_get_license_status' ); ?>"
					},
				})
				.done(function(data) {
					licenseStatus.html(data.data.status_html);
					$('#gaddon-setting-row-syncs3_license_status').show();
				});

				$('#syncs3_activate_license').click(function(e){
					e.preventDefault();
					var $this = $(this);

					$this.prop('disabled', 'disabled');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'syncs3_activate_license',
							nonce: "<?php echo wp_create_nonce( 'syncs3_activate_license' ); ?>"
						},
					})
					.done(function(data) {
						console.log(data);
						licenseStatus.html(data.data.status_html);
						$this.remove();
					});					
				});
			});
		</script>
		<?php
	}

	/**
	 * Instantiate the updater class
	 *
	 * @return void
	 */
	public function new_updater() {

		$this->plugin_data = get_plugin_data( SYNCS3_PLUGIN_FILE, false, false );
		$addon_options = get_option( 'gravityformsaddon_syncs3_settings' );
		$license_key = ! empty( $addon_options['syncs3_license_key'] ) ? sanitize_text_field( $addon_options['syncs3_license_key'] ) : '';
		$this->license_key = trim( $license_key );

		new Plugin_Updater( 
			$this->updater_url,
			SYNCS3_PLUGIN_FILE,
			array(
				'version' => $this->plugin_data['Version'],
				'license' => $this->license_key,
				'item_id' => $this->updater_id,
				'author'  => $this->plugin_data['Author'],
				'beta'    => false,
			)
		);
	}

	/**
	 * Sends a request to activate a license
	 *
	 * @return void
	 */
	public function activate_license() {

		$args = array(
			'edd_action' => 'activate_license',
			'license'    => $this->license_key,
			'item_name'  => urlencode( 'SyncS3 for Gravity Forms' ),
			'url'        => home_url()
		);

		$response = wp_remote_post( $this->updater_url, array( 
			'timeout' => 15, 
			'sslverify' => false, 
			'body' => $args 
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			// Bad response

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred activating your license. Please try again.', 'syncs3' );
			}

		} else {

			// Good response

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {

				switch( $license_data->error ) {

					case 'expired' :

						$message = sprintf(
							__( 'Your license key expired on %s.', 'syncs3' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'disabled' :
					case 'revoked' :

						$message = __( 'Your license key has been disabled.', 'syncs3' );
						break;

					case 'missing' :

						$message = __( 'Invalid license.', 'syncs3' );
						break;

					case 'invalid' :
					case 'site_inactive' :

						$message = __( 'Your license is not active for this URL.', 'syncs3' );
						break;

					case 'item_name_mismatch' :

						$message = sprintf( __( 'This appears to be an invalid license key.', 'syncs3' ) );
						break;

					case 'no_activations_left':

						$message = __( 'Your license key has reached its activation limit.', 'syncs3' );
						break;

					default :

						$message = __( 'An error occurred, please try again.', 'syncs3' );
						break;
				}

			}

		}

		// $license_data->license will be either "valid" or "invalid"
		update_option( "syncs3_license_status", $license_data->license );

		return $message;
	}
}

new Updater;
