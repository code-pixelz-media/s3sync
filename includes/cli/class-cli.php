<?php

namespace S3Sync;
use WP_CLI, WP_CLI_Command, GFAPI, S3SyncAddon;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Example WPCLI extension class
	 *
	 * S3SyncCLI Class
	 *
	 * @since   1.0
	 */
	class CLI extends WP_CLI_Command {

		/**
		 * Moves file uploads to Amazon S3
		 *
		 * ## OPTIONS
		 * --form_id=<id>
		 * : Form ID
		 *
		 * ## EXAMPLES
		 *
		 * wp s3sync process_entries --form_id=5
		 */
		public function process_entries( $args, $assoc_args ) {

			// Required parameters
			$form_id = isset( $assoc_args ) && array_key_exists( 'form_id', $assoc_args ) ? absint( $assoc_args['form_id'] ) : false;

			// Bail if required parameters are missing
			if ( false === $form_id ) {
				WP_CLI::error( __( 'The Form ID must be provided.', 's3sync' ) );
			}

			$entries_count = GFAPI::count_entries( $form_id );
			$entries = GFAPI::get_entries( $form_id, array(), null, array( 'offset' => 0, 'page_size' => 0 ) );

			WP_CLI::line( __( 'Starting Uploads to S3', 's3sync' ) );
			$addon = new S3SyncAddon;

			$progress = \WP_CLI\Utils\make_progress_bar( __( 'Uploads Progress', 's3sync' ), $entries_count );

			foreach ( $entries as $entry ) {
				$addon->process_entry( $entry, GFAPI::get_form( $form_id ) );
				$progress->tick();
			}

			$progress->finish();

			WP_CLI::line( __( 'Uploads Complete', 's3sync' ) );
		}
	}

	WP_CLI::add_command( 's3sync', __NAMESPACE__ . '\\CLI' );
};