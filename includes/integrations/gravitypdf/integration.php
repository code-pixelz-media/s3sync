<?php

class SyncS3_Integration_GravityPDF {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the integration.
	 *
	 * @return void
	 */
	public function init() {
		// Make sure the dependency plugin is active
		if ( is_plugin_active( 'gravity-forms-pdf-extended/pdf.php' ) ) {
			// add_filter( 'gfpdf_registered_fields', array( $this, 'settings' ) );
			add_action( 'admin_footer', array( $this, 'footer_js' ) );
			add_filter( 'gfpdf_form_settings_advanced', array( $this, 'add_settings' ), 99 );
			add_action( 'gfpdf_post_save_pdf', array( $this, 'upload_pdf' ), 10, 5 );
			add_filter( 'gfpdf_get_pdf_url', array( $this, 'filter_pdf_url' ), 10, 6 );
		}
	}

	/**
	 * Javascript for showing/hiding settings when needed
	 *
	 * @return void
	 */
	function footer_js() {
		// Add the script only when needed
		if ( empty( $_GET['subview'] ) || 'pdf' !== $_GET['subview'] || empty( $_GET['page'] ) || 'gf_edit_forms' !== $_GET['page'] || empty( $_GET['pid'] ) ) {
			return;
		}
		?>
		<script>
			jQuery(document).ready(function($) {
				var enabledSetting = document.getElementById('gfpdf_settings[syncs3_gravitypdf_upload_pdf]');
				console.log(enabledSetting);
				$.each( $('.syncs3-gravitypdf-required'), function(index, el) {
					if ( ! enabledSetting.checked ) {
						$(el).hide();
					}
				} );
				enabledSetting.addEventListener('change', function(index, el) {
					$.each( $('.syncs3-gravitypdf-required'), function(index, el) {
						if ( ! enabledSetting.checked ) {
							$(el).hide();
						} else {
							$(el).show();
						}
					} );
				});
			});
		</script>
		<?php
	}

	/**
	 * Adds our settings to the "General" GravityPDF settings section.
	 *
	 * @param  array 	$settings 	GravityPDF General Settings
	 *
	 * @return void
	 */
	public function add_settings( $settings ) {
		$settings['syncs3_gravitypdf_upload_pdf'] = array(
			'id'         => 'syncs3_gravitypdf_upload_pdf',
			'name'       => esc_html__( 'Upload PDF to S3?', 'syncs3' ),
			'type'    	 => 'checkbox',
			'desc' 		 => esc_html__( 'Upload PDF to Amazon S3', 'syncs3' ),
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Upload PDF to Amazon S3', 'syncs3' ),
				esc_html__( 'When enabled, SyncS3 will push the PDF to your Amazon S3 bucket.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_unlink'] = array(
			'id'         => 'syncs3_gravitypdf_unlink',
			'class' 	 => 'syncs3-gravitypdf-required',
			'name'       => esc_html__( 'Delete Local PDF', 'syncs3' ),
			'type'    	 => 'checkbox',
			'desc' 		 => esc_html__( 'Delete local file after uploading to S3?', 'syncs3' ),
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Delete Local PDF', 'syncs3' ),
				esc_html__( 'When enabled, SyncS3 will delete the local copy of the PDF after it is uploaded to your S3 bucket.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_unlink'] = array(
			'id'         => 'syncs3_gravitypdf_unlink',
			'class' 	 => 'syncs3-gravitypdf-required',
			'name'       => esc_html__( 'Delete Local PDF', 'syncs3' ),
			'type'    	 => 'checkbox',
			'desc' 		 => esc_html__( 'Delete local file after uploading to S3?', 'syncs3' ),
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Delete Local PDF', 'syncs3' ),
				esc_html__( 'When enabled, SyncS3 will delete the local copy of the PDF after it is uploaded to your S3 bucket.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_access_key'] = array(
			'id'         => 'syncs3_gravitypdf_access_key',
			'class' 	 => 'syncs3-gravitypdf-required',
			'desc' 		 => esc_html__( 'Defaults to the form\'s SyncS3 settings if available, or the global SyncS3 settings.' ),
			'name'       => esc_html__( 'Access Key', 'syncs3' ),
			'type'    	 => 'text',
			'inputClass' => 'large',
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Access Key', 'syncs3' ),
				esc_html__( 'Your Amazon AWS Access Key. This allows you to send files to any Amazon S3 account.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_secret_key'] = array(
			'id'         => 'syncs3_gravitypdf_secret_key',
			'class' 	 => 'syncs3-gravitypdf-required',
			'desc' 		 => esc_html__( 'Defaults to the form\'s SyncS3 settings if available, or the global SyncS3 settings.' ),
			'name'       => esc_html__( 'Secret Key', 'syncs3' ),
			'type'    	 => 'text',
			'inputClass' => 'large',
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Secret Key', 'syncs3' ),
				esc_html__( 'Your Amazon AWS Secret Key. This allows you to send files to any Amazon S3 account.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_bucket_name'] = array(
			'id'         => 'syncs3_gravitypdf_bucket_name',
			'class' 	 => 'syncs3-gravitypdf-required',
			'desc' 		 => esc_html__( 'Defaults to the form\'s SyncS3 settings if available, or the global SyncS3 settings.' ),
			'name'       => esc_html__( 'Bucket Name', 'syncs3' ),
			'type'    	 => 'text',
			'inputClass' => 'large',
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Bucket Name', 'syncs3' ),
				esc_html__( 'Bucket to which the files should be added.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_region'] = array(
			'id'         => 'syncs3_gravitypdf_region',
			'class' 	 => 'syncs3-gravitypdf-required',
			'desc' 		 => esc_html__( 'Defaults to the form\'s SyncS3 settings if available, or the global SyncS3 settings.' ),
			'name'       => esc_html__( 'Region', 'syncs3' ),
			'type'    	 => 'select',
			'options' 	 => syncs3_get_s3_regions( true ),
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Region', 'syncs3' ),
				esc_html__( 'Region for the bucket.', 'syncs3' )
			),
		);
		$settings['syncs3_gravitypdf_endpoint'] = array(
			'id'         => 'syncs3_gravitypdf_endpoint',
			'class' 	 => 'syncs3-gravitypdf-required',
			'desc' 		 => esc_html__( 'Defaults to the form\'s SyncS3 settings if available, or the global SyncS3 settings.' ),
			'name'       => esc_html__( 'Endpoint', 'syncs3' ),
			'type'    	 => 'text',
			'inputClass' => 'large',
			'tooltip'    => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Endpoint', 'syncs3' ),
				esc_html__( 'WARNING: Do NOT add anything here unless you have a specific reason for it.', 'syncs3' )
			),
		);
		return $settings;
	}

	/**
	 * Upload the PDF to Amazon S3.
	 *
	 * @param  string 	$file_path 	Path to PDF
	 * @param  string 	$file_name 	File name
	 * @param  array 	$settings 	Settings
	 * @param  array 	$entry    	Entry data
	 * @param  form 	$form     	Form data
	 *
	 * @return void
	 */
	public function upload_pdf( $file_path, $file_name, $settings, $entry, $form ) {
		// global $gfpdf;
		// $gfpdf->options->get_form_pdfs( $form_id );

		// Bail if the form is not set to upload the PDF
		if ( empty( $settings['syncs3_gravitypdf_upload_pdf'] ) ) {
			return;
		}

		$keys = syncs3_get_aws_settings( $form );
		$region = ! empty( $settings['syncs3_gravitypdf_region'] ) ? $settings['syncs3_gravitypdf_region'] : $keys['region'];
		$access_key = ! empty( $settings['syncs3_gravitypdf_access_key'] ) ? $settings['syncs3_gravitypdf_access_key'] : $keys['access_key'];
		$secret_key = ! empty( $settings['syncs3_gravitypdf_secret_key'] ) ? $settings['syncs3_gravitypdf_secret_key'] : $keys['secret_key'];

		$client_config = array(
			'version' => 'latest',
			'region' => $region,
			'credentials' => array(
				'key' => $access_key,
				'secret' => $secret_key,
			),
		);

		// Overwrite the endpoint
		if ( ! empty( $settings['syncs3_gravitypdf_endpoint'] ) ) {
			$client_config['endpoint'] = $settings['syncs3_gravitypdf_endpoint'];
		} else if ( ! empty( $keys['endpoint'] ) ) {
			$client_config['endpoint'] = $keys['endpoint'];
		}

		/**
		 * Data to configure the S3Client before uploading PDF to S3.
		 *
		 * @since 1.4.0
		 *
		 * @param array 	$client_config 		Config data
		 * @param array 	$entry 				Entry data
		 * @param int 		$form 				Form data
		 */
		$client_config = apply_filters( 'syncs3_gravitypdf_s3_client_config', $client_config, $entry, $form );

		$s3 = syncs3_s3_client( $client_config, $entry );

		/**
		 * Bucket name.
		 *
		 * @since 1.4.0
		 *
		 * @param string 	$bucket 		Bucket name
		 * @param string 	$file_path 		Local URL to PDF
		 * @param string 	$file_name 		Name of PDF file
		 * @param array		$form			Form data
		 * @param array 	$entry 			Entry data
		 */
		$bucket_name = apply_filters( 'syncs3_gravitypdf_put_object_bucket_name', ! empty( $settings['syncs3_gravitypdf_bucket_name'] ) ? $settings['syncs3_gravitypdf_bucket_name'] : $keys['bucket_name'], $file_path, $file_name, $form, $entry );

		/**
		 * File path relative to the bucket.
		 *
		 * @since 1.4.0
		 *
		 * @param string 	$path 			File path to return. Make sure the path ends with $file_name.
		 * @param string 	$file_path 		Local file path when uploaded
		 * @param string 	$file_name 		Name of uploaded file
		 * @param array		$form 			Form data
		 * @param array 	$entry 			Entry data
		 */
		$object_path = apply_filters( 'syncs3_gravitypdf_put_object_file_path', "form-{$form['id']}/{$entry['id']}/{$file_name}", $file_path, $file_name, $form, $entry );

		// Send the file to S3 bucket
		try {
			$result = $s3->putObject([
				'Bucket' => $bucket_name,
				'Key'    => $object_path,
				'Body'   => fopen( $file_path, 'r' ),
				// See https://docs.aws.amazon.com/AmazonS3/latest/dev/acl-overview.html#canned-acl for possible ACL choices
				'ACL'    => apply_filters( 'syncs3_gravitypdf_put_object_acl', 'private', $file_path, $file_name, $form, $entry ),
			]);
		} catch (Aws\S3\Exception\S3Exception $e) {
			error_log( "There was an error uploading the PDF.\n{$e->getMessage()}" );
		}

		if ( ! empty( $result ) ) {

			// Store a reference to the PDF's S3 URL
			$reference_data = array(
				'file_url' => $result['ObjectURL'],
				'key' => $object_path,
				'region' => $region,
				'bucket' => $bucket_name,
				'access_key' => $access_key,
				'secret_key' => $secret_key,
			);

			if ( ! empty( $settings['syncs3_gravitypdf_endpoint'] ) ) {
				$reference_data['endpoint'] = $settings['syncs3_gravitypdf_endpoint'];
			} else if ( ! empty( $keys['endpoint'] ) ) {
				$reference_data['endpoint'] = $keys['endpoint'];
			}

			$reference_data = apply_filters( 'syncs3_gravitypdf_pdf_reference_data', $reference_data, $entry, $form );

			// Store the reference data to the PDF in S3
			gform_update_meta( $entry['id'], 'syncs3_gravitypdf_pdf_s3_data', $reference_data );

			// Delete the local PDF if enabled
			if ( ! empty( $settings['syncs3_gravitypdf_unlink'] ) ) {
				unlink( $file_path );
			}
		}
	}

	/**
	 * Replace the local PDF URLs with S3 URLs.
	 *
	 * @param  string 	$url      	URL
	 * @param  int 		$pid      	The PDF Form Settings ID
	 * @param  int 		$entry_id   The Gravity Form entry ID
	 * @param  boolean 	$download 	Whether the PDF should be downloaded or not
	 * @param  boolean 	$print    	Whether we should mark the PDF to be printed
	 * @param  boolean 	$esc      	Whether to escape the URL or not
	 *
	 * @return string
	 */
	public function filter_pdf_url( $url, $pid, $entry_id, $download, $print, $esc ) {
		
		$entry_meta = gform_get_meta( $entry_id, 'syncs3_gravitypdf_pdf_s3_data' );
		
		if ( ! empty( $entry_meta ) ) {

			$entry = GFAPI::get_entry( $entry_id );
			$form = GFAPI::get_form( $entry['form_id'] );

			// Use the stored reference data to obtain a presigned URL
			$client_config = array(
				'version' => 'latest',
				'region' => $entry_meta['region'],
				'credentials' => array(
					'key' => $entry_meta['access_key'],
					'secret' => $entry_meta['secret_key'],
				),
			);
			if ( ! empty( $entry_meta['endpoint'] ) ) {
				$client_config['endpoint'] = $entry_meta['endpoint'];
			}
			$url = syncs3_create_s3_request( array(
				'client' => syncs3_s3_client( $client_config, $entry ),
				'presigned' => true,
				'url' => array(
					'bucket' => $entry_meta['bucket'],
					'key' => $entry_meta['key']
				),
				'filter' => 'syncs3_presigned_link_time',
				'filter_value' => '+20 minutes',
				'filter_args' => array(
					'entry' => $entry,
					'form' => $form
				)
			) );
		}

		return $url;
	}
}

new SyncS3_Integration_GravityPDF;