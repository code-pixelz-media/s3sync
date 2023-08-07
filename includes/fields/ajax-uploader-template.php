<div class="ginput_container ginput_container_<?php echo $this->type; ?>">
	<div class="syncs3-ajax-uploader" data-field-id="<?php echo $field_id; ?>">
		<div id="dropzone-uploader">
			<div class="dz-message">
				<div class="cloud-icon" style="width: 50px;">
					<svg fill="#c4c4c4" enable-background="new 0 0 139 139" viewBox="0 0 139 139" xmlns="http://www.w3.org/2000/svg"><path d="m112.431 58.393c-1.502-15.815-14.816-28.187-31.026-28.187-13.516 0-25.019 8.602-29.341 20.63-2.222-1.067-4.704-1.682-7.334-1.682-8.695 0-15.853 6.535-16.858 14.961-9.098 2.51-15.782 10.839-15.782 20.735 0 11.884 9.634 21.519 21.517 21.519h24.522v-18.223h-10.845c-1.771 0-3.364-1.121-4.029-2.832-.667-1.708-.267-3.669 1.002-4.954l23.948-24.163c1.687-1.703 4.366-1.703 6.052 0l23.681 23.896c.985.829 1.615 2.097 1.615 3.521 0 2.502-1.947 4.532-4.348 4.532-.008 0-.017 0-.024 0h-11.678v18.222h13.182c.815 0 1.616-.05 2.411-.14.866.09 1.746.14 2.638.14 13.908 0 25.178-11.277 25.178-25.18.001-10.081-5.926-18.772-14.481-22.795z"/></svg>
				</div>			
				<div class="upload-message"><?php _e( 'Drag and drop your files here (or click to upload)', 'syncs3' ); ?></div>
			</div>
			<?php if ( ! $is_form_editor ) : ?>
				<div id="syncs3-upload-preview-titles" class="preview-titles">
					<div><?php _e( 'Preview', 'syncs3' ); ?></div>
					<div><?php _e( 'File Name', 'syncs3' ); ?></div>
					<div><?php _e( 'Size', 'syncs3' ); ?></div>
					<div><?php _e( 'Actions', 'syncs3' ); ?></div>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( ! $is_form_editor ) : ?>
			<div id="syncs3-upload-preview" class="dz-preview dz-file-preview">
				<div class="dz-details">
					<div class="dz-preview-image"><img data-dz-thumbnail /></div>
					<div class="dz-filename"><span data-dz-name></span></div>
					<div class="dz-size" data-dz-size></div>
					<div class="dz-remove"><span data-dz-remove><?php _e( 'Remove', 'syncs3' ); ?></span></div>
				</div>
			</div>
			<div class="dz-error-message"><span data-dz-errormessage></span></div>
			<div class="syncs3-upload-progress" id="syncs3_upload_progress"></div>
		<?php endif; ?>
	</div>
	<?php echo $input; ?>
	<?php
		if ( ! empty( $entry_id ) ) { // edit entry
			$file_list_id   = 'gform_preview_' . $form_id . '_' . $id;
			$file_urls      = gform_get_meta( $entry_id, 's3_urls' );
			$preview = sprintf( "<div id='%s'></div>", $file_list_id );
			$preview .= sprintf( "<div id='preview_existing_files_%d'>", $id );

			foreach ( $file_urls as $field_id => $files ) {
				if ( $field_id != $id ) {
					continue;
				}
				foreach ( $files as $file_index => $file ) {
					$download_file_text  = esc_attr__( 'Download file', 'syncs3' );
					$delete_file_text    = esc_attr__( 'Delete file', 'syncs3' );
					$view_file_text      = esc_attr__( 'View file', 'syncs3' );
					$file_index          = intval( $file_index );
					$file_url            = home_url( "/syncs3/{$entry_id}/{$field_id}/{$file_index}" );
					$file_url            = esc_attr( $file_url );
					$display_file_url    = esc_attr( $file['key'] );
					$download_button_url = GFCommon::get_base_url() . '/images/download.png';
					$delete_button_url   = GFCommon::get_base_url() . '/images/delete.png';
					$preview .= "<div id='preview_file_{$file_index}' class='ginput_preview'>
									<a href='{$file_url}' target='_blank' aria-label='{$view_file_text}'>{$display_file_url}</a>
									<a href='{$file_url}' target='_blank' aria-label='{$download_file_text}' class='ginput_preview_control gform-icon gform-icon--circle-arrow-down'></a>
									<a href='javascript:void(0);' aria-label='{$delete_file_text}' onclick='DeleteFile({$entry_id},{$id},this);' onkeypress='DeleteFile({$entry_id},{$id},this);' class='ginput_preview_control gform-icon gform-icon--circle-delete'></a>
								</div>";
				}
			}

			$preview .= '</div>';

			echo $preview;
		}
	?>
</div>
<script>
	window.onload = function() {
		var preview = document.querySelector('#syncs3-upload-preview');
		var previewTitles = document.querySelector('#syncs3-upload-preview-titles');
		var progressTrack = document.querySelector('#syncs3_upload_progress');

		var form = document.querySelector("#gform_<?php echo $form_id; ?>");
		var input = document.querySelector("#syncs3_ajax_uploader_<?php echo $field_id; ?>");
		var processingNotice = document.querySelector('#syncs3_uploader_waiting_notice');

		AWS.config.update({
			region: '<?php echo $aws_keys['region']; ?>',
			credentials: new AWS.CognitoIdentityCredentials({
				IdentityPoolId: '<?php echo $aws_keys['identity_pool_id']; ?>'
			})
		});

		<?php
			$upload_params = apply_filters( 'syncs3_putobject_args', array(
				'Bucket' 		=> $aws_keys['bucket_name'],
				'ACL'    		=> apply_filters( 'syncs3_put_object_acl', $aws_keys['acl'], '', '', $field_id, $form_id, null ),
			), '', null, $form_id );
		?>

		const processFile = function(file) {
			return new Promise(function(resolve){
				var params = <?php echo json_encode( $upload_params ); ?>;
				params.Body = file;
				params.Key = <?php echo $path_js; ?>;
				var upload = new AWS.S3.ManagedUpload({
					queueSize: 4,
					params: params
				});
				var promise = upload.promise();

				upload.on( 'httpUploadProgress', function(event) {
					var total = event.loaded * 100 / event.total;
					progressTrack.style.width = total + '%';
					progressTrack.classList.add('uploading');
					if ( 100 == total ) {
						progressTrack.classList.add('complete');
					}
				} );

				promise.then(
					function(data) {
						var inputValue = input.value;
						var inputJSON = inputValue ? JSON.parse(input.value) : [];
						inputJSON.push({
							Location: data.Location,
							Bucket: data.Bucket,
							Key: data.Key
						});
						input.value = JSON.stringify(inputJSON);
						resolve(data);
					},
					function(err) {
						console.log(err);
					}
				);
			});
		}

		const processFiles = function() {
			return new Promise(async function(resolve) {
				for (var i = 0; i < dzUploader.files.length; i++) {
					await processFile(dzUploader.files[i]);
				}
				resolve('Files uploaded.');
			});
		}

		var dzUploader = new Dropzone( '#dropzone-uploader', {
			url: "<?php echo $_SERVER['REQUEST_URI'] ?>",
			autoProcessQueue: false,
			previewTemplate: preview.innerHTML,
			maxFiles: <?php echo syncs3_get_max_files( $this ); ?>,
			acceptedFiles: "<?php echo syncs3_get_accepted_files( $this ); ?>",
			init: function() {
				var alerted = false;
				this.on( 'addedfile', async function(file, progress, bytesSent) { 
					previewTitles.classList.add('showing');
					if ( this.files.length > this.options.maxFiles ) {
						this.removeFile(file);
						if ( false === alerted ) {
							alert( 'You cannot upload more than ' + this.options.maxFiles + ' files for this field.' );
							alerted = true;
						}
					}
					<?php if ( ! empty( $upload_action ) && 'file-selected' === $upload_action ) : ?>
						processingNotice.classList.remove('hidden');
						await processFile(file);
						processingNotice.classList.add('hidden');
					<?php endif; ?>
				});
				this.on( 'removedfile', function(file) { 
					if ( 0 === this.files.length ) {
						previewTitles.classList.remove('showing');
					}
				});
				
			}
		} );

		<?php if ( ! empty( $upload_action ) && 'form-submit' === $upload_action ) : ?>
			form.addEventListener('submit', async function(e) {
				if ( dzUploader.files.length > 0 ) {
					e.preventDefault();
					var submitButton = document.querySelector('#gform_submit_button_<?php echo $form_id; ?>');
					submitButton.setAttribute( 'disabled', 'disabled' );
					console.log('Processing files');
					processingNotice.classList.remove('hidden');
					await processFiles();
					processingNotice.classList.add('hidden');
					form.submit();
				}
			});
		<?php endif; ?>
	}
</script>
