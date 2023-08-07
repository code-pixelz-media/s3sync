<?php

class SyncS3_Integration_GravityView {

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
		if ( defined( 'GV_PLUGIN_VERSION' ) && version_compare( GV_PLUGIN_VERSION, '2.8.2', '>=' ) ) {
			add_filter( 'gravityview/fields/fileupload/file_path', array( $this, 'modify_file_upload_path' ), 99, 4 );
			add_action( 'gravityview/edit_entry/after_update', array( $this, 'gravityview_after_update' ), 99, 2 );
		}
	}

	/**
	 * Modify the path to the file to use the S3 file.
	 *
	 * @param  string 	$path     	File path
	 * @param  array 	$settings 	Gravity View settings
	 * @param  object 	$context  	Everything about the field, including form, entry, field, etc.
	 *
	 * @return string
	 */
	public function modify_file_upload_path( $path, $settings, $context, $index ) {
		
		$entry = $context->entry;
		$entry_id = $entry->ID;
		$field = $context->field->field;
		$s3_url_fields = gform_get_meta( $entry_id, 's3_urls' );
		$s3_field = $s3_url_fields[$field->id][$index];

		// Upload to S3 enabled
		if ( $field->enableS3Field && ! empty( $s3_field ) ) {

			$entry = GFAPI::get_entry( $entry_id );
			$form = GFAPI::get_form( $entry['form_id'] );

			if ( is_array( $s3_field ) && isset( $s3_field['file_url'] ) ) {
				$client_config = array(
					'version' => 'latest',
					'region' => $s3_field['region'],
					'credentials' => array(
						'key' => $s3_field['access_key'],
						'secret' => $s3_field['secret_key'],
					),
				);
				if ( ! empty( $s3_field['endpoint'] ) ) {
					$client_config['endpoint'] = $s3_field['endpoint'];
				}
				$path = syncs3_create_s3_request( array(
					'client' => syncs3_s3_client( $client_config, $entry ),
					'presigned' => true,
					'url' => array(
						'bucket' => $s3_field['bucket'],
						'key' => $s3_field['key']
					),
					'filter' => 'syncs3_gravityview_presigned_link_time',
					'filter_value' => '+20 minutes',
					'filter_args' => array(
						'entry' => $entry,
						'form' => $form,
						'field' => $s3_field,
						'field_id' => $field->id
					)
				) );
			} else {
				$path = $url;
			}
		}

		return $path;
	}

	/**
	 * Entries need to be updated when using GravityView
	 *
	 * @param  array 	$form     	Form data
	 * @param  int 		$entry_id 	Entry ID
	 *
	 * @return void
	 */
	public function gravityview_after_update( $form, $entry_id ) {

		$form_meta = RGFormsModel::get_form_meta( $form['id'] );
		$fields = $form_meta['fields'];
		$entry = GFAPI::get_entry( $entry_id );

		// Check all file upload fields
		foreach ( $fields as $field ) {

			// Only act on file upload fields enabled for S3 uploads
			if ( 'fileupload' !== $field->type || ! $field->enableS3Field ) {
				continue;
			}

			$uploaded = syncs3_send_entry_files_to_s3( $entry, $form['id'], $field->id, syncs3_get_aws_settings( $form, $field ), $field->amazonS3UnlinkField );
		}
	}
}

new SyncS3_Integration_GravityView;