<?php
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Creates an AWS S3Client
 *
 * @param array 	$config 	Client configuration data
 *
 * @return object 	S3Client
 */
function s3sync_s3_client( $config, $entry = '' ) {
	S3Sync::autoload();
	$client = new S3Client( $config );
	return apply_filters( 's3sync_s3_client', $client, $config, $entry );
}

/**
 * Helper function for sending an entry's files to Amazon S3.
 *
 * @param  array 	$entry    	Entry data
 * @param  int 		$form_id  	Form ID
 * @param  int 		$field_id 	Field ID
 * @param  array 	$keys     	Keys (access token, secret token, bucket name, acl, and region)
 *
 * @return mixed 	Boolean (false if no files in entry, true if upload is successful), or WP_Error if problem updating entry
 */
function s3sync_send_entry_files_to_s3( $entry, $form_id, $field_id, $keys, $unlink = false ) {

	// Each form has its own upload path and URL
	$upload_path = GFFormsModel::get_upload_path( $form_id );
	$upload_url = GFFormsModel::get_upload_url( $form_id );
	$current_datetime = date( 'Y-m-d-H-i-s' );
    $folder_name = "{$current_datetime}/";

	// Loop through these if multi-file upload
	$files = 0 === strpos( $entry[$field_id], '{' ) || 0 === strpos( $entry[$field_id], '[' ) ? json_decode( $entry[$field_id], true ) : $entry[$field_id];

	// Bail if no files
	if ( empty( $files ) ) {
		return;
	}

	// Ensure an array to account for single and multi file uploads
	$files = (array) $files;

	$s3_urls = array();

	$client_config = array(
		'version' => 'latest',
		'region' => $keys['region'],
		'credentials' => array(
			'key' => $keys['access_key'],
			'secret' => $keys['secret_key'],
		),
	);

	// Overwrite the endpoint
	if ( ! empty( $keys['endpoint'] ) ) {
		$client_config['endpoint'] = $keys['endpoint'];
	}


	$client_config = apply_filters( 's3sync_send_entry_files_s3_client_config', $client_config, $entry );

	$s3 = s3sync_s3_client( $client_config, $entry );


	$files = apply_filters( 's3sync_entry_files', $files, $entry, $form_id );

	do_action( 's3sync_before_entry_upload_to_s3', $entry, $form_id, $files );

	foreach ( $files as $file ) {
		
		// Replace the file URL with the file path
		$file_path = str_replace( $upload_url, $upload_path, $file );

		// Grab the file name
		$file_parts = explode( '/', $file_path );
		$file_name = array_pop( $file_parts );


		$bucket_name = apply_filters( 's3sync_put_object_bucket_name', $keys['bucket_name'], $file, $file_name, $field_id, $form_id, $entry );

		/**
		 * File path relative to the bucket.
		 *
		 * @since 1.0.3
		 *
		 * @param string 	$path 			File path to return. Make sure the path ends with $file_name.
		 * @param string 	$file 			Local file URL when uploaded
		 * @param string 	$file_name 		Name of uploaded file
		 * @param int 		$field_id 		ID of the fileupload field
		 * @param int 		$form_id 		ID of the form
		 * @param array 	$entry 			Entry data
		 */

		// $object_path = apply_filters( 's3sync_put_object_file_path', "form-{$form_id}/{$entry['id']}/{$file_name}", $file, $file_name, $field_id, $form_id, $entry );
		$object_path = apply_filters( 's3sync_put_object_file_path',"form-{$entry['id']}/{$file_name}", $file, $file_name, $field_id, $form_id, $entry );

		$acl = apply_filters( 's3sync_put_object_acl', $keys['acl'], $file, $file_name, $field_id, $form_id, $entry );

		$api_status = [];
		// Send the file to S3 bucket
		// https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#putobject
		try {
		

			$args = apply_filters( 's3sync_putobject_args', array(
				'Bucket' 		=> $bucket_name,
				'Key'    		=> $object_path,
				'ContentLength' => filesize( $file_path ),
				'Body'   		=> fopen( $file_path, 'r' ),
				'ACL'    		=> $acl,
			), $file, $entry, $form_id );
			
			$result = $s3->putObject( $args );
		} catch (Throwable $e) {
			error_log( "There was an error uploading the file.\n{$e->getMessage()}" );
			unlink( $file_path );
		}


		if(!$api_status['status']){
			add_filter( 'gform_form_validation_errors', function ( $errors, $form ) {
				    $errors[] = array(
				        'field_label'    => 'the field label here',
				        'field_selector' => '#field_1_10',
				        'message'        => 'the error message here',
				    );
 
			    return $errors;
			}, 10, 2 );
		}
		if ( ! empty( $result ) && $api_status['status']) {

			// Store a reference to the file's S3 URL
			$reference_data = array(
				'file_url' => $result['ObjectURL'],
				'key' => $object_path,
				'region' => $keys['region'],
				'bucket' => $bucket_name,
				'acl' => $acl,
				'access_key' => $keys['access_key'],
				'secret_key' => $keys['secret_key'],
			);

			if ( ! empty( $keys['endpoint'] ) ) {
				$reference_data['endpoint'] = $keys['endpoint'];
			}
			

			$s3_urls[$field_id][] = $reference_data;

			if ( true === $unlink ) {
				unlink( $file_path );
			}
		}
	}

	$existing_urls = gform_get_meta( $entry['id'], 's3_urls' );
	$existing_urls = ! empty( $existing_urls ) ? $existing_urls : array();
	

	do_action( 's3sync_after_entry_upload_to_s3', $entry, $form_id, $files, $s3_urls );

	// Store the S3 URLs as entry meta
	return gform_update_meta( $entry['id'], 's3_urls', array_replace( $existing_urls, $s3_urls ) );
}

/**
 * Retrieves the correct Identity Pool ID.
 * 
 * @since 2.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string   Access key
 */
function s3sync_get_aws_identity_pool_id( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_key = ! empty( $settings['amazons3_identity_pool_id'] ) ? $settings['amazons3_identity_pool_id'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_key = ! empty( $form_meta['s3sync']['amazons3_identity_pool_id'] ) ? $form_meta['s3sync']['amazons3_identity_pool_id'] : '';

	if ( ! empty( $field->type ) && 's3sync_ajax_uploader' === $field->type && ! empty( $field->amazonS3IdentityPoolIdField ) ) {
		// Use field-level key
		$key = $field->amazonS3IdentityPoolIdField;
	} else if ( ! empty( $form_key ) ) {
		// Use form-level key
		$key = $form_key;
	} else if ( ! empty( $global_key ) ) {
		// Use global key
		$key = $global_key;
	}

	return $key;
}

/**
 * Retrieves the correct AWS Access Key.
 * 
 * @since 1.1.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string   Access key
 */
function s3sync_get_aws_access_key( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_key = ! empty( $settings['amazons3_access_key'] ) ? $settings['amazons3_access_key'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_key = ! empty( $form_meta['s3sync']['amazons3_access_key'] ) ? $form_meta['s3sync']['amazons3_access_key'] : '';

	if ( ! empty( $field->type ) && 'fileupload' === $field->type && ! empty( $field->amazonS3AccessKeyField ) && ! empty( $field->amazonS3SecretKeyField ) ) {
		// Use field-level key
		$key = $field->amazonS3AccessKeyField;
	} else if ( ! empty( $form_key ) ) {
		// Use form-level key
		$key = $form_key;
	} else if ( ! empty( $global_key ) ) {
		// Use global key
		$key = $global_key;
	}

	return $key;
}

/**
 * Retrieves the correct AWS Secret Key.
 * 
 * @since 1.1.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	Secret key
 */
function s3sync_get_aws_secret_key( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_key = ! empty( $settings['amazons3_secret_key'] ) ? $settings['amazons3_secret_key'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_key = ! empty( $form_meta['s3sync']['amazons3_secret_key'] ) ? $form_meta['s3sync']['amazons3_secret_key'] : '';

	if ( ! empty( $field->type ) && 'fileupload' === $field->type && ! empty( $field->amazonS3AccessKeyField ) && ! empty( $field->amazonS3SecretKeyField ) ) {
		// Use field-level key
		$key = $field->amazonS3SecretKeyField;
	} else if ( ! empty( $form_key ) ) {
		// Use form-level key
		$key = $form_key;
	} else if ( ! empty( $global_key ) ) {
		// Use global key
		$key = $global_key;
	}

	return $key;
}

/**
 * Retrieves the correct AWS Bucket name.
 * 
 * @since 1.1.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	Secret key
 */
function s3sync_get_aws_bucket_name( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_bucket = ! empty( $settings['amazons3_bucket_name'] ) ? $settings['amazons3_bucket_name'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_bucket = ! empty( $form_meta['s3sync']['amazons3_bucket_name'] ) ? $form_meta['s3sync']['amazons3_bucket_name'] : '';

	if ( ! empty( $field->amazonS3BucketNameField ) ) {
		// Use field-level key
		$key = $field->amazonS3BucketNameField;
	} else if ( ! empty( $form_bucket ) ) {
		// Use form-level key
		$key = $form_bucket;
	} else if ( ! empty( $global_bucket ) ) {
		// Use global key
		$key = $global_bucket;
	}

	return $key;
}

/**
 * Retrieves the correct AWS region.
 * 
 * @since 1.1.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	Secret key
 */
function s3sync_get_aws_region( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_region = ! empty( $settings['amazons3_region'] ) ? $settings['amazons3_region'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_region = ! empty( $form_meta['s3sync']['amazons3_region'] ) ? $form_meta['s3sync']['amazons3_region'] : '';

	if ( ! empty( $field->amazonS3RegionField ) ) {
		// Use field-level key
		$key = $field->amazonS3RegionField;
	} else if ( ! empty( $form_region ) ) {
		// Use form-level key
		$key = $form_region;
	} else if ( ! empty( $global_region ) ) {
		// Use global key
		$key = $global_region;
	}

	return $key;
}

/**
 * Retrieves the correct AWS ACL.
 * 
 * @since 1.5.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	ACL
 */
function s3sync_get_aws_acl( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_region = ! empty( $settings['amazons3_region'] ) ? $settings['amazons3_region'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_region = ! empty( $form_meta['s3sync']['amazons3_acl'] ) ? $form_meta['s3sync']['amazons3_acl'] : '';

	if ( ! empty( $field->amazonS3AclField ) ) {
		// Use field-level key
		$key = $field->amazonS3AclField;
	} else if ( ! empty( $form_acl ) ) {
		// Use form-level key
		$key = $form_acl;
	} else if ( ! empty( $global_acl ) ) {
		// Use global key
		$key = $global_acl;
	}

	return $key;
}

/**
 * Retrieves the correct AWS endpoint.
 * 
 * @since 1.2.2
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	Endpoint
 */
function s3sync_get_aws_endpoint( $form = array(), $field = false ) {

	$key = '';
	$settings = get_option( 'gravityformsaddon_s3sync_settings' );

	// Global key
	$global_endpoint = ! empty( $settings['amazons3_endpoint'] ) ? $settings['amazons3_endpoint'] : '';

	// Form-level key
	$form_meta = ! empty( $form['id'] ) ? RGFormsModel::get_form_meta( $form['id'] ) : array();
	$form_endpoint = ! empty( $form_meta['s3sync']['amazons3_endpoint'] ) ? $form_meta['s3sync']['amazons3_endpoint'] : '';

	if ( ! empty( $field->type ) && 'fileupload' === $field->type && ! empty( $field->amazonS3EndpointField ) ) {
		// Use field-level key
		$key = $field->amazonS3EndpointField;
	} else if ( ! empty( $form_endpoint ) ) {
		// Use form-level key
		$key = $form_endpoint;
	} else if ( ! empty( $global_endpoint ) ) {
		// Use global key
		$key = $global_endpoint;
	}

	return $key;
}

/**
 * Retrieves the full AWS config (keys, region, and bucket).
 * 
 * @since 1.5.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	Secret key
 */
function s3sync_get_aws_settings( $form = array(), $field = false ) {
	return array(
		'access_key' => s3sync_get_aws_access_key( $form, $field ),
		'secret_key' => s3sync_get_aws_secret_key( $form, $field ),
		'bucket_name' => s3sync_get_aws_bucket_name( $form, $field ),
		'region' => s3sync_get_aws_region( $form, $field ),
		'acl' => s3sync_get_aws_acl( $form, $field ),
		'endpoint' => s3sync_get_aws_endpoint( $form, $field ),
		'identity_pool_id' => s3sync_get_aws_identity_pool_id( $form, $field )
	);
}

/**
 * Deprecated since 1.5.0.
 * 
 * @since 1.1.0
 *
 * @param  array   	$form  		Form data
 * @param  object 	$field 		Field object
 *
 * @return string 	Secret key
 */
function s3sync_get_aws_keys( $form = array(), $field = false ) {
	return s3sync_get_aws_settings( $form, $field );
}

/**
 * Parses an S3 URL.
 *
 * @param  string 	$url 	S3 URL
 *
 * @return array
 */
function s3sync_get_url_parts( $url ) {

	$the_parts = explode( '/', str_replace( 'https://', '', $url ) );

	// The first part of the URL contains the bucket name and region
	$s3parts = explode( '.', $the_parts[0] );
	$bucket = $s3parts[0];
	$region = $s3parts[2];
	$file_name = array_pop( $the_parts );

	return array(
		'file_name' => $file_name,
		'bucket' => $bucket,
		'region' => $region
	);
}

/**
 * Determines if a file has a public ACL.
 * 
 * @since  1.5.0
 *
 * @param  array 	$file 	File data (saved in entry meta)
 *
 * @return boolean
 */
function s3sync_is_file_public( $file ) {
	return ! empty( $file['acl'] ) && false === strpos( $file['acl'], 'private' );
}

/**
 * Gets the S3 URLs of an entry
 *
 * @since 1.4.2
 *
 * @param  mixed 	$entry 		Entry or Entry ID
 * @param  boolean 	$entry_id 	Whether to sign the URL (needed to access a protected file)
 *
 * @return array
 */
function s3sync_get_entry_s3_urls( $entry, $presigned = false ) {

	$entry_id = is_array( $entry ) && ! empty( $entry['id'] ) ? (int) $entry['id'] : (int) $entry;

	if ( empty( $entry_id ) ) {
		return;
	}

	$s3_urls = gform_get_meta( $entry_id, 's3_urls' );

	if ( empty( $s3_urls ) ) {
		return;
	}

	$returned_urls = array();

	foreach ( $s3_urls as $field_id => $urls ) {
		foreach ( $urls as $index => $url ) {
			if ( is_array( $url ) && isset( $url['file_url'] ) ) {
				if ( $presigned ) {
					$client_config = array(
						'version' => 'latest',
						'region' => $url['region'],
						'credentials' => array(
							'key' => $url['access_key'],
							'secret' => $url['secret_key'],
						),
					);
					if ( ! empty( $url['endpoint'] ) ) {
						$client_config['endpoint'] = $url['endpoint'];
					}
					$s3 = s3sync_s3_client( $client_config, $entry );
					$cmd = $s3->getCommand( 'GetObject', [
						'Bucket' => $url['bucket'],
						'Key' => $url['key']
					]);
					$request = $s3->createPresignedRequest( $cmd, apply_filters( 's3sync_get_entry_s3_urls_presigned_link_time', '+20 minutes', $url, $entry_id, $field_id ) );
					$returned_urls[$field_id][$index]['signed'] = (string) $request->getUri();
				}
				$returned_urls[$field_id][$index]['unsigned'] = $url['file_url'];
			} else {
				$returned_urls[$field_id][$index]['unsigned'] = $url;
			}
		}
	}

	return $returned_urls;
}


function s3sync_get_s3_acls( $empty_option = false, $as_choices = false ) {
	$acls = array(
		'private' => __( 'Private', 's3sync' ),
		'public-read' => __( 'Public (Read)', 's3sync' ),
		'public-read-write' => __( 'Public (Read & Write)', 's3sync' )
	);

	$acls = apply_filters( 's3sync_acls_select_choices', $acls );

	if ( $empty_option ) {
		$empty = array( '' => '' );
		$acls = $empty + $acls;
	}

	if ( ! $as_choices ) {
		return $acls;
	}

	// Assemble the acls as choices for Gravity Forms select input
	$choices = array();
	foreach ( $acls as $key => $label ) {
		$choices[] = array(
			'label' => $label,
			'value' => $key
		);
	}

	return $choices;
}
function s3sync_get_s3_regions( $empty_option = false, $as_choices = false ) {
	$regions = array(
		'us-east-2' => __( 'US East (Ohio)', 's3sync' ),
		'us-east-1' => __( 'US East (N. Virginia)', 's3sync' ),
		'us-west-1' => __( 'US West (N. California)', 's3sync' ),
		'us-west-2' => __( 'US West (Oregon)', 's3sync' ),
		'af-south-1' => __( 'Africa (Cape Town)', 's3sync' ),
		'ap-east-1' => __( 'Asia Pacific (Hong Kong)', 's3sync' ),
		'ap-south-1' => __( 'Asia Pacific (Mumbai)', 's3sync' ),
		'ap-northeast-3' => __( 'Asia Pacific (Osaka-Local)', 's3sync' ),
		'ap-northeast-2' => __( 'Asia Pacific (Seoul)', 's3sync' ),
		'ap-southeast-1' => __( 'Asia Pacific (Singapore)', 's3sync' ),
		'ap-southeast-2' => __( 'Asia Pacific (Sydney)', 's3sync' ),
		'ap-northeast-1' => __( 'Asia Pacific (Tokyo)', 's3sync' ),
		'ca-central-1' => __( 'Canada (Central)', 's3sync' ),
		'cn-north-1' => __( 'China (Beijing)', 's3sync' ),
		'cn-northwest-1' => __( 'China (Ningxia)', 's3sync' ),
		'eu-central-1' => __( 'Europe (Frankfurt)', 's3sync' ),
		'eu-west-1' => __( 'Europe (Ireland)', 's3sync' ),
		'eu-west-2' => __( 'Europe (London)', 's3sync' ),
		'eu-south-1' => __( 'Europe (Milan)', 's3sync' ),
		'eu-west-3' => __( 'Europe (Paris)', 's3sync' ),
		'eu-north-1' => __( 'Europe (Stockholm)', 's3sync' ),
		'me-south-1' => __( 'Middle East (Bahrain)', 's3sync' ),
		'sa-east-1' => __( 'South America (São Paulo)', 's3sync' )
	);

	$regions = apply_filters( 's3sync_regions_select_choices', $regions );

	if ( $empty_option ) {
		$empty = array( '' => '' );
		$regions = $empty + $regions;
	}

	if ( ! $as_choices ) {
		return $regions;
	}

	// Assemble the regions as choices for Gravity Forms select input
	$choices = array();
	foreach ( $regions as $key => $label ) {
		$choices[] = array(
			'label' => $label,
			'value' => $key
		);
	}

	return $choices;
}


function s3sync_delete_file( $entry_id, $field_id, $file_name ) {
	$s3_urls = gform_get_meta( $entry_id, 's3_urls' );

	if ( ! empty( $s3_urls ) ) {
		S3Sync::autoload();
		foreach ( $s3_urls as $field_id => $urls ) {
			if ( ! empty( $urls ) ) {
				foreach ( $urls as $index => $url ) {
					if ( is_array( $url ) && ! empty( $url['file_url'] ) && $file_name === s3sync_get_url_parts( $url['file_url'] )['file_name'] ) {
						$s3 = new \Aws\S3\S3Client( array(
							'version' => 'latest',
							'region' => $url['region'],
							'credentials' => array(
								'key' => $url['access_key'],
								'secret' => $url['secret_key'],
							),
						) );
						$deleted = $s3->deleteObject([
						    'Bucket' => $url['bucket'],
						    'Key'    => $url['key']
						]);
						// Cleanup
						if ( $deleted ) {
							unset( $s3_urls[$field_id][$index] );
						}
						if ( empty( $s3_urls[$field_id] ) ) {
							unset( $s3_urls[$field_id] );
						}
					}
				}
			}
		}
		gform_update_meta( $entry_id, 's3_urls', $s3_urls );
	}

	// If all went well, we should not get here
	return false;
}


/**
 * Gets a file's S3 URL.
 *
 * @since 1.4.7
 *
 * @param  array 	$args  		Request data
 *
 * @return mixed 	URL if request is good, else false
 */
function s3sync_create_s3_request( $args ) {
	$client = $args['client'];	
	$url = false;
	$args = apply_filters( 's3sync_create_s3_request_args', $args );
	if ( true === $args['presigned'] ) {
		$cmd = $client->getCommand( 'GetObject', [
			'Bucket' => $args['url']['bucket'],
			'Key' => $args['url']['key']
		]);
		$request = $client->createPresignedRequest( $cmd, apply_filters( $args['filter'], $args['filter_value'], $args['filter_args'] ) );
		if ( ! empty( $request ) ) {
			$url = (string) $request->getUri();
		}
	} else {
		$url = $client->getObjectUrl( $args['url']['bucket'], $args['url']['key'] );
	}
	return $url;
}

/**
 * Determine whether a form has a "Direct to S3" uploader field.
 * 
 * @since 1.6.0
 *
 * @param  array 	$form 	Form data
 *
 * @return boolean
 */
function s3sync_form_has_uploader( $form ) {
	if ( is_array( $form['fields'] ) ) {
		foreach ( $form['fields'] as $field ) {
			if ( RGFormsModel::get_input_type( $field ) == 's3sync_ajax_uploader' ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Retrieves the maximum number of files allowed for upload.
 * 
 * @since 1.6.0
 *
 * @param  object 	$field 		Field object
 *
 * @return int
 */
function s3sync_get_max_files( $field = false ) {
	return ! empty( $field->amazonS3MaxFilesField ) ? (int) $field->amazonS3MaxFilesField : 9999999999;
}

/**
 * Retrieves a field's accepted files.
 * 
 * @since 1.6.0
 *
 * @param  object 	$field 		Field object
 *
 * @return string   Accepted files
 */
function s3sync_get_accepted_files( $field = false ) {
	return ! empty( $field->amazonS3AcceptedFilesField ) ? sanitize_text_field( $field->amazonS3AcceptedFilesField ) : '';
}

/**
 * Retrieves a field's upload action.
 * 
 * @since 1.6.0
 *
 * @param  object 	$field 		Field object
 *
 * @return string   Accepted files
 */
function s3sync_get_upload_action( $field = false ) {
	return ! empty( $field->amazonS3UploadActionField ) ? sanitize_text_field( $field->amazonS3UploadActionField ) : '';
}

