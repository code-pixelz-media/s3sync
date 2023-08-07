<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class S3Sync_AJAX_Uploader extends GF_Field {

	/**
	 * @var string $type The field type.
	 */
	public $type = 's3sync_ajax_uploader';

	/**
	 * Return the field title, for use in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'S3 Uploader', 's3sync' );
	}

	/**
	 * Assign the field button to the Advanced Fields group.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'standard_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * The settings which should be available on the field in the form editor.
	 *
	 * @return array
	 */
	function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'rules_setting',
			'placeholder_setting',
			'input_class_setting',
			'css_class_setting',
			// 'size_setting',
			'admin_label_setting',
			// 'default_value_setting',
			// 'visibility_setting',
			'conditional_logic_field_setting',
			'amazons3_bucket_name_setting',
			'amazons3_region_setting',
			'amazons3_acl_setting',
			'amazons3_identity_pool_id_setting',
			'amazons3_identity_pool_id_setting_desc'
		);
	}

	/**
	 * Enable this field for use with conditional logic.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * The scripts to be included in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {

		// set the default field label for the simple type field
		$script = sprintf( "function SetDefaultValues_simple(field) {field.label = '%s';}", $this->get_form_editor_field_title() ) . PHP_EOL;

		// initialize the fields custom settings
		$script .= "jQuery(document).bind('gform_load_field_settings', function (event, field, form) {" .
		           "var inputClass = field.inputClass == undefined ? '' : field.inputClass;" .
		           "jQuery('#input_class_setting').val(inputClass);" .
		           "});" . PHP_EOL;

		// saving the simple setting
		$script .= "function SetInputClassSetting(value) {SetFieldProperty('inputClass', value);}" . PHP_EOL;

		return $script;
	}

	/**
	 * Saves the entry value in a custom format.
	 *
	 * @param  string|array 	$value      	The input value to be saved.
	 * @param  array 			$form       	Form data.
	 * @param  striing 			$input_name 	Input name attribute.
	 * @param  int 				$lead_id    	The ID of the entry currently being processed.
	 * @param  array 			$lead       	The GF_Entry currently being processed.
	 *
	 * @return string|array
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		$files_json = ! empty( $_POST[$input_name] ) ? json_decode( stripslashes( $_POST[$input_name] ), true ) : '';

		if ( empty( $files_json ) || ! is_array( $files_json ) ) {
			return '';
		}

		$s3_urls = array();

		$settings = s3sync_get_aws_settings( $form, $this );
		foreach ( $files_json as $file ) {
			$reference_data = array(
				'file_url' => $file['Location'],
				'key' => $file['Key'],
				'region' => $settings['region'],
				'bucket' => $file['Bucket'],
				'acl' => $settings['acl'],
				'access_key' => $settings['access_key'],
				'secret_key' => $settings['secret_key'],
			);
			$s3_urls[$this->id][] = $reference_data;
		}
		
		$existing_urls = gform_get_meta( $lead_id, 's3_urls' );
		$existing_urls = ! empty( $existing_urls ) ? $existing_urls : array();

		// Store the S3 URLs as entry meta
		gform_update_meta( $lead_id, 's3_urls', array_replace( $existing_urls, $s3_urls ) );

		// We store entries as meta, so just store an empty string for the value
		return '';
	}

	/**
	 * Define the fields inner markup.
	 *
	 * @param array $form The Form Object currently being processed.
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$id              = absint( $this->id );
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$entry_id = ! empty( $entry['id'] ) ? (int) $entry['id'] : null;

		// Prepare the value of the input ID attribute.
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$value = esc_attr( $value );

		// Get the value of the inputClass property for the current field.
		$inputClass = $this->inputClass;

		// Prepare the input classes.
		$size         = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class        = $size . $class_suffix . ' ' . $inputClass;

		// Prepare the other input attributes.
		// $tabindex              = $this->get_tabindex();
		// $logic_event           = ! $is_form_editor && ! $is_entry_detail ? $this->get_conditional_logic_event( 'keyup' ) : '';
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
		$disabled_text         = $is_form_editor ? 'disabled="disabled"' : '';

		// Prepare the input tag for this field.
		$input = "<input name='input_{$id}' id='s3sync_ajax_uploader_{$field_id}' type='hidden' value='{$value}' class='{$class}' {$required_attribute} {$invalid_attribute} {$disabled_text}/>";

		$aws_keys = s3sync_get_aws_settings( $form, $this );
		$upload_action = s3sync_get_upload_action( $this );
		$path_js = apply_filters( 's3sync_ajax_uploader_object_path_js', 'file.name', $this, $form, $entry );
		ob_start();
		include 'ajax-uploader-template.php';
		return ob_get_clean();
	}
}

GF_Fields::register( new S3Sync_AJAX_Uploader() );
