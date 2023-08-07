<?php
/**
 *	S3Sync URL Rewrites
 *
 *	@package S3Sync for Gravity Forms
 */

namespace S3Sync;
use WP_CLI, WP_CLI_Command, GFAPI, S3SyncAddon;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewrites {

	public function __construct() {
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'init', array( $this, 'add_rewrites' ) );
		add_action( 'template_redirect', array( $this, 'perform_redirect' ) );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 's3sync_rewrite';
		$vars[] = 's3sync_entry_id';
		$vars[] = 's3sync_field_id';
		$vars[] = 's3sync_file_id';
		return $vars;
	}

	public function add_rewrites() {
		$base = apply_filters( 's3sync_rewrite_base', 's3sync' );
		add_rewrite_rule(
			"^{$base}/([0-9]+)/([0-9]+)/([0-9]+)[/]?$", // entry/field/file
			'index.php?s3sync_rewrite=s3_url&s3sync_entry_id=$matches[1]&s3sync_field_id=$matches[2]&s3sync_file_id=$matches[3]',
			'top'
		);
	}

	public function perform_redirect() {
		$s3sync_rewrite = get_query_var( 's3sync_rewrite' );
		$url = '';

		if ( ! empty( $s3sync_rewrite ) ) {
			$entry_id = (int) get_query_var( 's3sync_entry_id' );
			$field_id = (int) get_query_var( 's3sync_field_id' );
			$file_id = (int) get_query_var( 's3sync_file_id' );
			if ( apply_filters( 's3sync_can_access_s3_url', true, $entry_id, $field_id, $file_id ) ) {
				switch ( $s3sync_rewrite ) {
					case 's3_url':
						$entry_meta = gform_get_meta( $entry_id, 's3_urls' );
						$urls = s3sync_get_entry_s3_urls( $entry_id, true );
						$file_config = ! empty( $entry_meta[$field_id][$file_id] ) ? $entry_meta[$field_id][$file_id] : '';
						$file_urls = ! empty( $urls[$field_id][$file_id] ) ? $urls[$field_id][$file_id] : '';
						$url = s3sync_is_file_public( $file_config ) ? $file_urls['unsigned'] : $file_urls['signed'];
						break;
					
					default:
						break;
				}
			}
		}

		if ( ! empty( $url ) ) {
			do_action( 's3sync_s3url_rewrite_before_redirect', $entry_id, $field_id, $file_id );
			wp_redirect( $url );
			exit;
		}
	}
}

new Rewrites;