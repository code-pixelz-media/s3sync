<?php
GFForms::include_addon_framework();

class S3SyncAddon extends GFAddOn {

	protected $_version = S3SYNC_VERSION;
	protected $_min_gravityforms_version = '2.0';
	protected $_slug = 's3sync';
	protected $_path = 's3sync/s3sync.php';
	protected $_full_path = __FILE__;
	protected $_title = 'S3Sync';
	protected $_short_title = 'S3Sync';
	protected $_capabilities_settings_page = 'gravityforms_s3sync';
	protected $_capabilities_form_settings = 'gravityforms_s3sync';
	protected $_capabilities_uninstall = 'gravityforms_s3sync_uninstall';
	protected $_capabilities = array( 'gravityforms_s3sync', 'gravityforms_s3sync_uninstall' );

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return S3SyncAddon
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new S3SyncAddon();
		}

		return self::$_instance;
	}

	/**
	 * Include the field early so it is available when entry exports are being performed.
	 */
	public function pre_init() {
		parent::pre_init();
		if ( $this->is_gravityforms_supported() && class_exists( 'GF_Field' ) ) {
			require_once( 'fields/ajax-uploader-field.php' );
		}
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();
		add_action( 'gform_enqueue_scripts', 'S3SyncAddon::frontend_scripts', 10, 2 );
		add_action( 'gform_entry_created', 'S3SyncAddon::process_entry', 999, 2 );
		add_action( 'gform_field_advanced_settings', array( $this, 'upload_field_settings' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'editor_script' ) );
		add_filter( 'gform_tooltips', array( $this, 'add_tooltips' ) );
		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'meta_box' ), 10, 3 );
		add_action( 'gform_custom_merge_tags', array( $this, 'add_merge_tags' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'replace_s3urls_merge_tag' ), 10, 3 );
		add_filter( 'gform_secure_file_download_url', array( $this, 'replace_gf_file_url' ), 10, 2 );
		add_action( 'wp_ajax_nopriv_rg_delete_file', array( $this, 'remove_files_from_s3' ), 99 );
		add_action( 'wp_ajax_rg_delete_file', array( $this, 'remove_files_from_s3' ), 5 );
		add_filter( 'gform_submit_button', array( $this, 'prepend_uploading_notification' ), 99, 2 );

		// add_filter( 'gform_export_fields', array( $this, 'csv_export_column' ) );
		add_filter( 'gform_export_field_value', array( $this, 'csv_export_values' ), 10, 4 );
	}

	/**
	 * On forms with a "Direct to S3" uploader, we upload the files when the user clicks to submit the form.
	 * Therefore, we need to show a notice to let the user know that some processing is going on.
	 *
	 * @param  string 	$button 	Button output
	 * @param  array 	$form   	Form data
	 *
	 * @return string
	 */
	public function prepend_uploading_notification( $button, $form ) {
		if ( s3sync_form_has_uploader( $form ) ) {
			$button = sprintf( '<div class="s3sync-uploader-waiting-notice hidden" id="s3sync_uploader_waiting_notice"><div class="s3sync-loading-text">%s</div> <div class="s3sync-loading-indicator"></div></div>', __( 'Please wait while we upload your files.', 's3sync' ) ) . $button;
		}
		return $button;
	}

	public static function frontend_scripts( $form, $ajax ) {
		if ( s3sync_form_has_uploader( $form ) ) {
			$aws_version = '2.869.0';
			$dz_version = '5.8.1';
			wp_enqueue_script( 'aws', "//sdk.amazonaws.com/js/aws-sdk-{$aws_version}.min.js", array(), $aws_version, true );
			wp_enqueue_script( 'dropzone-js', "//cdnjs.cloudflare.com/ajax/libs/dropzone/{$dz_version}/min/dropzone.min.js", array(), $dz_version, true );
			wp_enqueue_style( 'dropzone-css', "//cdnjs.cloudflare.com/ajax/libs/dropzone/{$dz_version}/dropzone.min.css", array(), $dz_version );
			wp_enqueue_style( 'uploader-css', S3SYNC_PLUGIN_DIR_URL . 'assets/css/uploader.css', array(), S3SYNC_VERSION );
		}
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.3
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return '<svg style="max-width:1rem;" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 55 57" xmlns="http://www.w3.org/2000/svg"><g fill-rule="nonzero" transform="translate(-22.641 -21.806)"><path d="m22.812 35.714c-.107-.234-.17-.475-.17-.715z"/><path d="m77.188 35.714.17-.715c0 .24-.058.479-.17.715z"/><path d="m77.358 32.671v2.328l-.17.715v-2.326z"/><path d="m22.812 33.388v2.326l-.17-.715v-2.328z"/><g><path d="m77.358 28.229v2.327c0 .084-.01.167-.023.251v-2.324c.014-.084.023-.17.023-.254"/><path d="m77.335 28.483v2.324c-.014.1-.041.197-.076.295v-2.328c.035-.095.062-.193.076-.291"/><path d="m77.259 28.774v2.328c-.045.133-.111.267-.193.399v-2.325c.081-.13.148-.265.193-.402"/><path d="m77.065 29.177v2.325c-1.943 3.112-13.32 5.483-27.07 5.483-15.105 0-27.354-2.868-27.354-6.429v-2.327c0 3.562 12.248 6.431 27.354 6.431 13.75 0 25.127-2.372 27.07-5.483"/></g><path d="m49.995 21.806c15.113 0 27.363 2.877 27.363 6.424 0 3.562-12.25 6.431-27.363 6.431-15.105 0-27.354-2.868-27.354-6.431.001-3.547 12.249-6.424 27.354-6.424z"/><path d="m77.188 33.388v2.326l-8.947 38.195v-2.322z"/><path d="m31.761 71.587v2.322l-8.949-38.195v-2.326z"/><g><path d="m68.241 71.587v2.322c0 .06-.006.115-.018.172v-2.329c.012-.054.018-.11.018-.165"/><path d="m68.224 71.752v2.329c-.006.063-.027.129-.047.193v-2.325c.019-.067.041-.13.047-.197"/><path d="m68.177 71.949v2.325c-.033.09-.078.179-.131.268v-2.329c.053-.085.098-.174.131-.264"/><path d="m68.046 72.213v2.329c-1.295 2.072-8.879 3.652-18.051 3.652-10.07 0-18.234-1.911-18.234-4.285v-2.322c0 2.372 8.164 4.28 18.234 4.28 9.172 0 16.756-1.581 18.051-3.654"/></g><path d="m49.995 39.095c14.088 0 25.684-2.49 27.193-5.707l-8.947 38.199c0 2.372-8.166 4.28-18.246 4.28-10.07 0-18.234-1.908-18.234-4.28l-8.949-38.199c1.511 3.216 13.105 5.707 27.183 5.707z"/><path d="m77.42 32.668c.014.036.021.073.036.109l-.014-.063c-.006-.016-.016-.031-.022-.046z"/><path d="m77.391 32.579c-.022-.066-.044-.135-.059-.201.014.067.037.135.059.201z" fill="#146eb4"/></g></svg>';
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'S3Sync Settings', 's3sync' ),
				'icon' => 'dashicons-media-document',
				'fields' => array(
					array(
						'name' => 'amazons3_access_key',
						'tooltip' => esc_html__( 'Your Amazon AWS Access Key.', 's3sync' ),
						'label' => esc_html__( 'Access Key', 's3sync' ),
						'type' => 'text',
						'class' => 'large'
					),
					array(
						'name' => 'amazons3_secret_key',
						'tooltip' => esc_html__( 'Your Amazon AWS Secret Key.', 's3sync' ),
						'label' => esc_html__( 'Secret Key', 's3sync' ),
						'type' => 'text',
						'class' => 'large'
					),
					array(
						'name' => 'amazons3_bucket_name',
						'tooltip' => esc_html__( 'Default bucket name. This can be overridden on a form and field level.', 's3sync' ),
						'label' => esc_html__( 'Default Bucket', 's3sync' ),
						'type' => 'text',
						'class' => 'medium'
					),
					array(
						'name' => 'amazons3_region',
						'label' => esc_html__( 'Region', 's3sync' ),
						'type' => 'select_custom',
						'choices' => s3sync_get_s3_regions( true, true )
					),
					array(
						'name' => 'amazons3_acl',
						'label' => esc_html__( 'ACL', 's3sync' ),
						'type' => 'select_custom',
						'choices' => s3sync_get_s3_acls( true, true ),
						'tooltip' => esc_html__( 'Amazon S3 supports a set of predefined grants, known as <a href="https://docs.aws.amazon.com/AmazonS3/latest/userguide/acl_overview.html#canned-acl" target="_blank">canned ACLs</a>. Each canned ACL has a predefined set of grantees and permissions. This can be overridden on a form and field level.', 's3sync' ),
					),
					array(
						'name' => 'amazons3_endpoint',
						'tooltip' => esc_html__( 'WARNING: Do NOT add anything here unless you have a specific reason for it. This overwrites the default Amazon AWS endpoint.', 's3sync' ),
						'label' => esc_html__( 'Endpoint', 's3sync' ),
						'type' => 'text',
						'class' => 'medium',
					),
					array(
						'name' => 'amazons3_identity_pool_id',
						'tooltip' => esc_html__( 'For use with the "Direct to S3" uploader.', 's3sync' ),
						'label' => esc_html__( 'Identity Pool ID', 's3sync' ),
						'type' => 'text',
						'class' => 'large'
					),
					array(
						'name' => 's3sync_license_key',
						'tooltip' => esc_html__( 'Your S3Sync License Key.', 's3sync' ),
						'label' => esc_html__( 'License Key', 's3sync' ),
						'type' => 'text',
						'class' => 'large'
					),
				)
			)
		);
	}

	/**
	 * Configures the settings which should be rendered on the Form Settings > Simple Add-On tab.
	 *
	 * @param array 	$form 	Form
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) {
		return array(
			array(
				'title'  => esc_html__( 'S3Sync Settings', 's3sync' ),
				'fields' => array(
					array(
						'label' => esc_html__( 'Access Key', 's3sync' ),
						'type' => 'text',
						'name' => 'amazons3_access_key',
						'tooltip' => esc_html__( 'Your Amazon AWS Access Key. This will override the global setting, allowing you to send files to a different Amazon S3 account. If left empty, the global setting will be used. This can also be overridden from the field level.', 's3sync' ),
						'class' => 'large'
					),
					array(
						'label' => esc_html__( 'Secret Key', 's3sync' ),
						'type' => 'text',
						'name' => 'amazons3_secret_key',
						'tooltip' => esc_html__( 'Your Amazon AWS Secret Key. This will override the global setting, allowing you to send files to a different Amazon S3 account. If left empty, the global setting will be used. This can also be overridden from the field level.', 's3sync' ),
						'class' => 'large'
					),
					array(
						'label' => esc_html__( 'Bucket Name', 's3sync' ),
						'type' => 'text',
						'name' => 'amazons3_bucket_name',
						'tooltip' => esc_html__( 'Bucket to which the files should be added. If left empty, the global setting will be used. This can also be overridden from the field level.', 's3sync' ),
						'class' => 'medium'
					),
					array(
						'name'    => 'amazons3_region',
						'label'   => esc_html__( 'Region', 's3sync' ),
						'type'    => 'select_custom',
						'choices' => s3sync_get_s3_regions( true, true )
					),
					array(
						'name'    => 'amazons3_acl',
						'label'   => esc_html__( 'ACL', 's3sync' ),
						'type'    => 'select_custom',
						'choices' => s3sync_get_s3_acls( true, true ),
						'tooltip' => esc_html__( 'Amazon S3 supports a set of predefined grants, known as <a href="https://docs.aws.amazon.com/AmazonS3/latest/userguide/acl_overview.html#canned-acl" target="_blank">canned ACLs</a>. Each canned ACL has a predefined set of grantees and permissions. If left empty, the global setting will be used.', 's3sync' ),
					),
					array(
						'name' => 'amazons3_endpoint',
						'tooltip' => esc_html__( 'WARNING: Do NOT add anything here unless you have a specific reason for it. This overwrites the default Amazon AWS endpoint.', 's3sync' ),
						'label' => esc_html__( 'Endpoint', 's3sync' ),
						'type' => 'text',
						'class' => 'medium',
					),
					array(
						'label' => esc_html__( 'Identity Pool ID', 's3sync' ),
						'type' => 'text',
						'name' => 'amazons3_identity_pool_id',
						'tooltip' => esc_html__( 'For use with the "Direct to S3" uploader. This will override the global setting. If left empty, the global setting will be used. This can also be overridden from the field level.', 's3sync' ),
						'class' => 'large'
					),
				),
			),
		);
	}

	/**
	 * Adds custom settings to the field's Advanced tab
	 *
	 * @param  int 	$position 	Position
	 * @param  int 	$form_id  	Form ID
	 *
	 * @return void
	 */
	public function upload_field_settings( $position, $form_id ) {
		
		// Put settings at the very end
		if ( $position == -1 ) {
			?>
			<style>
				.s3sync-field-settings {
					/*display: none;*/
				}
				.ginput_container_fileupload ~ .ui-tabs .s3sync-field-settings,
				.ginput_container_s3sync_ajax_uploader ~ .ui-tabs .s3sync-field-settings {
					display: block;
					background-color: #f5f5f5; 
					padding: 20px; 
					margin-top: 20px;
				}
			</style>
			<div class="s3sync-field-settings">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Amazon S3 Upload Settings', 's3sync' ); ?></h3>
				<li class="amazons3_identity_pool_id_setting_desc field_setting">
					<div class="notice inline notice-info">
						<p><?php _e( 'The "Direct to S3" uploader uses special S3 settings and configurations to upload files directly from the browser to your S3 bucket. To ensure a proper setup, first follow these steps:', 's3sync' ); ?></p>
						<p><?php _e( '1. Make sure you have created your bucket. Note your bucket\'s region, as you will need it in the next step.', 's3sync' ); ?></p>
						<p><?php _e( '2. In the <a href="https://console.aws.amazon.com/cognito/" target="_blank">Amazon Cognito console</a>, create an Amazon Cognito identity pool using Federated Identities with access enabled for unauthenticated users in the same Region as your S3 bucket. You need to include the identity pool ID in the code to obtain credentials for the browser script.', 's3sync' ); ?></p>
						<p><?php _e( '3. In the <a href="https://console.aws.amazon.com/iam/" target="_blank">IAM console</a>, find the IAM role created by Amazon Cognito for <strong>unauthenticated users</strong>. Add the following policy to grant <strong>read and write permissions</strong> to your S3 bucket (replace <code>BUCKET_NAME</code> with your bucket\'s slug). ', 's3sync' ); ?></p>
						<pre>
{
   "Version": "2012-10-17",
   "Statement": [
	  {
		 "Effect": "Allow",
		 "Action": [
			"s3:DeleteObject",
			"s3:GetObject",
			"s3:ListBucket",
			"s3:PutObject",
			"s3:PutObjectAcl"
		 ],
		 "Resource": [            
			"arn:aws:s3:::BUCKET_NAME",
			"arn:aws:s3:::BUCKET_NAME/*"
		 ]
	  }
   ]
}
						</pre>
						<p><?php _e( '4. In your bucket\'s Permissions settings, add the following CORS policy to allow uploads from the browser: ', 's3sync' ); ?></p>
						<pre>
[
	{
		"AllowedHeaders": [
			"*"
		],
		"AllowedMethods": [
			"HEAD",
			"GET",
			"PUT",
			"POST",
			"DELETE"
		],
		"AllowedOrigins": [
			"*"
		],
		"ExposeHeaders": [
			"ETag"
		]
	}
]
						</pre>
					</div>
				</li>
				<li class="amazons3_enable_setting field_setting">
					<input type="checkbox" id="field_amazons3_enable" onclick="SetFieldProperty('enableS3Field', this.checked);" />
					<label for="field_amazons3_enable" style="display:inline;">
						<?php esc_html_e( 'Enable Uploads to S3', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_enable' ) ?>
					</label>
				</li>
				<li class="amazons3_unlink_setting field_setting">
					<input type="checkbox" id="field_amazons3_unlink" onclick="SetFieldProperty('amazonS3UnlinkField', this.checked);" />
					<label for="field_amazons3_unlink" style="display:inline;">
						<?php esc_html_e( 'Remove File After Uploading to S3', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_unlink' ) ?>
					</label>
				</li>
				<li class="amazons3_access_key_setting field_setting">
					<label for="field_amazons3_access_key" class="section_label">
						<?php esc_html_e( 'Access Key', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_access_key' ) ?>
					</label>
					<input type="text" value="" id="field_amazons3_access_key" size="35" onchange="SetFieldProperty('amazonS3AccessKeyField', this.value);" />
				</li>
				<li class="amazons3_secret_key_setting field_setting">
					<label for="field_amazons3_secret_key" class="section_label">
						<?php esc_html_e( 'Secret Key', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_secret_key' ) ?>
					</label>
					<input type="text" value="" id="field_amazons3_secret_key" size="35" onchange="SetFieldProperty('amazonS3SecretKeyField', this.value);" />
				</li>
				<li class="amazons3_bucket_name_setting field_setting">
					<label for="field_amazons3_bucket_name" class="section_label">
						<?php esc_html_e( 'Bucket Name', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_bucket_name' ) ?>
					</label>
					<input type="text" value="" id="field_amazons3_bucket_name" size="35" onchange="SetFieldProperty('amazonS3BucketNameField', this.value);" />
				</li>
				<li class="amazons3_region_setting field_setting">
					<label for="field_amazons3_region" class="section_label">
						<?php esc_html_e( 'Region', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_region' ) ?>
					</label>
					<!-- <input type="text" value="" id="field_amazons3_region" size="35" onchange="SetFieldProperty('amazonS3RegionField', this.value);" /> -->
					<select id="field_amazons3_region" onchange="SetFieldProperty('amazonS3RegionField', this.value);">
						<?php foreach ( s3sync_get_s3_regions( true ) as $key => $label ) : ?>
							<option value="<?php esc_attr_e( $key ); ?>"><?php esc_html_e( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</li>
				<li class="amazons3_acl_setting field_setting">
					<label for="field_amazons3_acl" class="section_label">
						<?php esc_html_e( 'ACL', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_acl' ) ?>
					</label>
					<!-- <input type="text" value="" id="field_amazons3_acl" size="35" onchange="SetFieldProperty('amazonS3AclField', this.value);" /> -->
					<select id="field_amazons3_acl" onchange="SetFieldProperty('amazonS3AclField', this.value);">
						<?php foreach ( s3sync_get_s3_acls( true ) as $key => $label ) : ?>
							<option value="<?php esc_attr_e( $key ); ?>"><?php esc_html_e( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</li>
				<li class="amazons3_endpoint_setting field_setting">
					<label for="field_amazons3_endpoint" class="section_label">
						<?php esc_html_e( 'Endpoint', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_endpoint' ) ?>
					</label>
					<input type="url" value="" id="field_amazons3_endpoint" size="35" onchange="SetFieldProperty('amazonS3EndpointField', this.value);" />
				</li>
				<li class="amazons3_identity_pool_id_setting field_setting">
					<label for="field_amazons3_identity_pool_id" class="section_label">
						<?php esc_html_e( 'Identity Pool ID', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_identity_pool_id' ) ?>
					</label>
					<input type="text" value="" id="field_amazons3_identity_pool_id" size="35" onchange="SetFieldProperty('amazonS3IdentityPoolIdField', this.value);" />
				</li>
				<li class="amazons3_max_files_setting field_setting">
					<label for="field_amazons3_max_files" class="section_label">
						<?php esc_html_e( 'Maximum Number of Files', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_max_files' ) ?>
					</label>
					<input type="number" value="" id="field_amazons3_max_files" size="35" onchange="SetFieldProperty('amazonS3MaxFilesField', this.value);" />
				</li>
				<li class="amazons3_accepted_files_setting field_setting">
					<label for="field_amazons3_accepted_files" class="section_label">
						<?php esc_html_e( 'Accepted Files', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_accepted_files' ) ?>
					</label>
					<input type="text" value="" id="field_amazons3_accepted_files" size="35" onchange="SetFieldProperty('amazonS3AcceptedFilesField', this.value);" />
				</li>
				<li class="amazons3_upload_action_setting field_setting">
					<label for="field_amazons3_upload_action" class="section_label">
						<?php esc_html_e( 'Upload Action', 's3sync' ); ?>
						<?php gform_tooltip( 'form_field_amazons3_upload_action' ) ?>
					</label>
					<select id="field_amazons3_upload_action" onchange="SetFieldProperty('amazonS3UploadActionField', this.value);">
						<option value="file-selected"><?php _e( 'File Select', 's3sync' ); ?></option>
						<option value="form-submit"><?php _e( 'Form Submit', 's3sync' ); ?></option>
					</select>
				</li>
			</div>
			<?php
		}
	}

	/**
	 * Script that runs in the form editor.
	 * This is responsible for binding the fields to Gravity Forms's save process.
	 *
	 * @return void
	 */
	public function editor_script(){
		?>
		<script>
			// Add setting to fileupload field type
			fieldSettings.fileupload += ", .amazons3_enable_setting, .amazons3_access_key_setting, .amazons3_secret_key_setting, .amazons3_bucket_name_setting, .amazons3_region_setting, .amazons3_acl_setting, .amazons3_endpoint_setting, .amazons3_unlink_setting";
			fieldSettings.s3sync_ajax_uploader += ".amazons3_bucket_name_setting, .amazons3_region_setting, .amazons3_acl_setting, .amazons3_identity_pool_id_setting, .amazons3_identity_pool_id_setting_desc, .amazons3_max_files_setting, .amazons3_accepted_files_setting, .amazons3_upload_action_setting";
	
			// binding to the load field settings event to initialize the checkbox
			jQuery(document).on("gform_load_field_settings", function(event, field, form){
				jQuery("#field_amazons3_enable").attr("checked", field["enableS3Field"] == true);
				jQuery("#field_amazons3_unlink").attr("checked", field["amazonS3UnlinkField"] == true);
				jQuery("#field_amazons3_access_key").val(field["amazonS3AccessKeyField"]);
				jQuery("#field_amazons3_secret_key").val(field["amazonS3SecretKeyField"]);
				jQuery("#field_amazons3_bucket_name").val(field["amazonS3BucketNameField"]);
				jQuery("#field_amazons3_region").val(field["amazonS3RegionField"]);
				jQuery("#field_amazons3_acl").val(field["amazonS3AclField"]);
				jQuery("#field_amazons3_endpoint").val(field["amazonS3EndpointField"]);
				jQuery("#field_amazons3_identity_pool_id").val(field["amazonS3IdentityPoolIdField"]);
				jQuery("#field_amazons3_max_files").val(field["amazonS3MaxFilesField"]);
				jQuery("#field_amazons3_accepted_files").val(field["amazonS3AcceptedFilesField"]);
				jQuery("#field_amazons3_upload_action").val(field["amazonS3UploadActionField"]);
			});
		</script>
		<?php
	}

	/**
	 * Render custom tooltips.
	 *
	 * @param array 	$tooltips 	Tooltips
	 * 
	 * @return array
	 */
	public function add_tooltips( $tooltips ) {
		$tooltips['form_field_amazons3_enable'] = __( "<h6>Enable</h6>This will enable sending file uploads to Amazon S3.", 's3sync' );
		$tooltips['form_field_amazons3_unlink'] = __( "<h6>Delete File</h6>This will delete the file locally (from your server) after it's uploaded to S3.", 's3sync' );
		$tooltips['form_field_amazons3_access_key'] = __( "<h6>Access Key</h6>Your Amazon AWS Access Key. This will override the global or form setting, allowing you to send files to a different Amazon S3 account.", 's3sync' );
		$tooltips['form_field_amazons3_secret_key'] = __( "<h6>Secret Key</h6>Your Amazon AWS Secret Key. This will override the global or form setting, allowing you to send files to a different Amazon S3 account.", 's3sync' );
		$tooltips['form_field_amazons3_bucket_name'] = __( "<h6>Bucket Name</h6>Bucket to which the files should be added. If left empty, the global setting will be used.", 's3sync' );
		$tooltips['form_field_amazons3_region'] = __( "<h6>Region</h6>Region for the bucket. If left empty, the global setting will be used.", 's3sync' );
		$tooltips['form_field_amazons3_acl'] = __( "<h6>ACL</h6>Amazon S3 supports a set of predefined grants, known as <a href=\"https://docs.aws.amazon.com/AmazonS3/latest/userguide/acl_overview.html#canned-acl\" target=\"_blank\">canned ACLs</a>. Each canned ACL has a predefined set of grantees and permissions. If left empty, the global setting will be used.", 's3sync' );
		$tooltips['form_field_amazons3_endpoint'] = __( "<h6>Endpoint</h6>WARNING: Do NOT add anything here unless you have a specific reason for it. This overwrites the default Amazon AWS endpoint.", 's3sync' );
		$tooltips['form_field_amazons3_identity_pool_id'] = __( "<h6>Identity Pool ID</h6>This will look something like: us-east-2:86dcc0b2-60bb-4001-a512-b643451a5b3e", 's3sync' );
		$tooltips['form_field_amazons3_max_files'] = __( "<h6>Maximum Number of Files</h6>Limit the number of files that can be uploaded.", 's3sync' );
		$tooltips['form_field_amazons3_accepted_files'] = __( "<h6>Accepted Files</h6>A comma separated list of mime types or file extensions.", 's3sync' );
		$tooltips['form_field_amazons3_upload_action'] = __( "<h6>Upload Action</h6>When the files should be uploaded. <strong>File Select</strong> - When the user selects their files. <strong>Form Submit</strong> - When the user submits the form.", 's3sync' );
		return $tooltips;
	}

	/**
	 * Send files to Amazon S3 when a form is submitted.
	 *
	 * @param array 	$entry 	The entry currently being processed.
	 * @param array 	$form 	The form currently being processed.
	 *
	 * @return void
	 */
	public static function process_entry( $entry, $form ) {

		// Allow for short-circuiting the process
		if ( false === apply_filters( 's3sync_should_process_entry', true, $entry, $form ) ) {
			return;
		}

		$form_meta = RGFormsModel::get_form_meta( $form['id'] );
		$fields = $form_meta['fields'];

		// Check all file upload fields
		foreach ( $fields as $field ) {

			// Only act on file upload fields enabled for S3 uploads
			if ( 'fileupload' !== $field->type || ! $field->enableS3Field ) {
				continue;
			}

			// Allow for skipping fields (or entire entries)
			if ( false === apply_filters( 's3sync_process_entry_should_process_field', true, $field, $entry, $form ) ) {
				continue;
			}

			$uploaded = s3sync_send_entry_files_to_s3( $entry, $form['id'], $field->id, s3sync_get_aws_settings( $form, $field ), $field->amazonS3UnlinkField );
		}
	}

	/**
	 * Adds a meta box to the Entry
	 *
	 * @param  array 	$meta_boxes 	Meta boxes
	 * @param  array 	$entry      	Entry
	 * @param  array 	$form       	Form
	 *
	 * @return array
	 */
	public function meta_box( $meta_boxes, $entry, $form ) {
		
		$meta_boxes['s3_urls'] = array(
			'title'    => esc_html__( 'S3 URLs', 's3sync' ),
			'callback' => array( $this, 'render_mb' ),
			'context'  => 'normal',
			'priority' => 'high',
			'callback_args' => array(
				'entry' => $entry,
				'form' => $form
			)
		);

		return $meta_boxes;
	}

	/**
	 * Renders the S3 URLs meta box
	 *
	 * @param  array 	$args 	Args
	 *
	 * @return void
	 */
	public function render_mb( $args ) {

		// Get S3 URLs
		$s3_urls = gform_get_meta( $args['entry']['id'], 's3_urls' );

		?>
		<?php if ( ! empty( $s3_urls ) ) : ?>
			<table cellspacing="0" class="widefat fixed entry-detail-view">
				<thead>
					<tr>
						<th colspan="2">S3 URLs</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $s3_urls as $field_id => $urls ) : ?>
						<?php if ( ! empty( $urls ) ) : ?>
							<?php $field = GFAPI::get_field( $args['form'], $field_id ); ?>
							<tr>
								<td colspan="2" class="entry-view-field-name"><strong><?php echo $field['label']; ?></td>
							</tr>
							<tr>
								<td colspan="2">
									<ul>
										<?php foreach ( $urls as $file_key => $url ) : ?>
											<?php
												$link = home_url( "/s3sync/{$args['entry']['id']}/{$field_id}/{$file_key}" );
											?>
											<li>
												<a href="<?php echo esc_url( $link ); ?>" target="_blank"><?php echo esc_url( $link ); ?></a>
											</li>
										<?php endforeach; ?>
									</ul>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<?php esc_html_e( 'This entry does not have any files that were uploaded to S3.', 's3sync' ); ?>
		<?php endif;
	}

	/**
	 * Add our custom merge tag.
	 *
	 * @param array 	$merge_tags 	Array of existing custom merge tags
	 * @param int 		$form_id    	Form ID
	 * @param array 	$fields     	Form fields
	 * @param int 		$element_id 	ID of input field
	 */
	public function add_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		$merge_tags[] = array( 
			'label' => 'S3 URLs', 
			'tag' => '{s3urls}' 
		);
		return $merge_tags;
	}

	/**
	 * Parse the merge tags.
	 *
	 * @param  string 	$text       Merge tag text
	 * @param  array 	$form       Form data
	 * @param  array 	$entry      Entry data
	 *
	 * @return string
	 */
	public function replace_s3urls_merge_tag( $text, $form, $entry ) {

		preg_match_all( '/({s3urls\s?.*?})/', $text, $merge_tag_matches );

		// Bail if notification does not contain our merge tag
		if ( empty( $merge_tag_matches[1] ) ) {
			return $text;
		}

		// Blank it
		$list = '';

		// Get S3 URLs
		$s3_url_fields = gform_get_meta( $entry['id'], 's3_urls' );

		foreach ( $merge_tag_matches[1] as $match ) {
			
			if ( ! empty( $s3_url_fields ) ) { // Entry has S3 uploads, so replace the merge tag with a list

				$field_id_matches = array();
				preg_match( '/(?:field_id)(?:=|:)(?:"|\')?(\d+)(?:"|\')?/', $match, $field_id_matches );
				ob_start();
				?>
				<ul>
					<?php foreach ( $s3_url_fields as $field_id => $urls ) : ?>
						<?php 
							if ( ! empty( $field_id_matches[1] ) && $field_id_matches[1] != $field_id ) {
								continue;
							} 
						?>
						<?php foreach ( $urls as $file_key => $url ) : ?>
							<?php $redirect_url = home_url( "/s3sync/{$entry['id']}/{$field_id}/{$file_key}" ); ?>
							<li><a href="<?php echo esc_url( $redirect_url ); ?>"><?php esc_attr_e( $redirect_url ); ?></a></li>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</ul>
				<?php
				$list = ob_get_clean();
				$text = str_replace( $match, $list, $text );

			} else { // Entry doesn't have S3 uploads, so just blank the merge tag

				$text = str_replace( $match, '', $text );
			}
		}

		return $text;
	}

	/**
	 * This will replace the default file URL generated by Gravity Forms
	 *
	 * @param  string 	$url   	File URL
	 * @param  object 	$field 	Field
	 *
	 * @return string
	 */
	public function replace_gf_file_url( $url, $field ) {

		// Entry available via GravityView
		if ( ! empty( get_query_var( 'entry' ) ) && ! empty( get_query_var( 'gravityview' ) ) ) {
			$entry_id = (int) get_query_var( 'entry' );
		} else {
			// lid available in the admin edit entry page
			$entry_id = ! empty( $_GET['lid'] ) ? (int) $_GET['lid'] : false;
		}

		// Bail early if the file is stored locally, or if not an S3 field
		if ( 's3sync_ajax_uploader' !== $field->type && empty( $field->amazonS3UnlinkField ) ) {
			return $url;
		}

		if ( $entry_id ) {
			
			$file_urls = s3sync_get_entry_s3_urls( $entry_id, true );
			$field_file_urls = $file_urls[$field->id];

			if ( is_array( $field_file_urls ) ) {
				foreach ( $field_file_urls as $field_url ) {
					$s3_url_path = explode( '/', parse_url( $field_url['signed'] )['path'] );
					$field_url_parts = parse_url( $url );
					$s3_filename = array_pop( $s3_url_path );

					if ( false !== strpos( $field_url_parts['query'], $s3_filename ) ) {
						$url = $field_url['signed'];
						break;
					}
				}
			}
		}

		return $url;
	}

	/**
	 * Remove files from S3 when a file is deleted.
	 *
	 * @return void
	 */
	public function remove_files_from_s3() {
		check_ajax_referer( 'rg_delete_file', 'rg_delete_file' );
		$lead_id = intval( $_POST['lead_id'] );
		$field_id = intval( $_POST['field_id'] );
		$file_index = intval( $_POST['file_index'] );
		$entry = RGFormsModel::get_lead( $lead_id );
		$field = RGFormsModel::get_field( $entry['form_id'], $field_id );

		if ( ! empty( $field->multipleFiles ) ) {
			$file_urls = json_decode( $entry[$field_id], true );
			$file_url  = $file_urls[$file_index];
			$file_name = s3sync_get_url_parts( $file_url )['file_name'];
		} else {
			$s3_urls = gform_get_meta( $lead_id, 's3_urls' );
			$file_url = $s3_urls[$field_id][$file_index];
			$file_name = $file_url['key'];
		}

		s3sync_delete_file( $lead_id, $field_id, $file_name );
	}

	/**
	 * Add a column to CSV exports
	 *
	 * @param  array 	$form 	Form Data
	 *
	 * @return array
	 */
	public function csv_export_column( $form ) {
		array_push( 
			$form['fields'], 
			array( 
				'id' => 's3sync_urls', 
				'label' => __( 'S3 URLs', 's3sync' ) 
			) 
		);
		return $form;
	}

	/**
	 * Gives our custom column a value.
	 *
	 * @param  string 	$value    	Value of the field being exported.
	 * @param  int 		$form_id  	ID of the form
	 * @param  string 	$field_id 	Column ID
	 * @param  array 	$entry    	Entry data
	 *
	 * @return string
	 */
	public function csv_export_values( $value, $form_id, $field_id, $entry ) {
		$field = GFAPI::get_field( $form_id, $field_id );
		if ( $field && ( 'fileupload' === $field->get_input_type() || 's3sync_ajax_uploader' === $field->get_input_type() ) ) {
			$s3_urls = gform_get_meta( $entry['id'], 's3_urls' );
			if ( ! empty( $s3_urls ) ) {
				$csv_urls = array();
				foreach ( $s3_urls as $url_field_id => $urls ) {
					if ( $field_id == $url_field_id ) {
						foreach ( $urls as $file_key => $url ) {
							$csv_urls[] = home_url( "/s3sync/{$entry['id']}/{$field_id}/{$file_key}" );
						}
					}
				}
			}
			$value = implode( "\n", $csv_urls );
		}
		return $value;
	}
}