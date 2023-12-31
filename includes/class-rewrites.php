<?php
/**
 *	S3Sync URL Rewrites
 *
 *	@package S3Sync for Gravity Forms
 */

namespace S3Sync;
use WP_CLI, WP_CLI_Command, GFAPI, SyncS3Addon;

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
        $vars[] = 'syncs3_rewrite';
        $vars[] = 'syncs3_entry_id';
        $vars[] = 'syncs3_field_id';
        $vars[] = 'syncs3_key';  // Adding the key variable
        return $vars;
    }

    public function add_rewrites() {
        $base = apply_filters( 'syncs3_rewrite_base', 's3sync' );
        add_rewrite_rule(
            "^{$base}/([0-9]+)/([0-9]+)/([0-9]+)[/]?$",
            'index.php?syncs3_rewrite=s3_url&syncs3_entry_id=$matches[1]&syncs3_field_id=$matches[2]&syncs3_key=$matches[3]',  // Updated rule with syncs3_key
            'top'
        );
    }

    public function perform_redirect() {
        $syncs3_rewrite = get_query_var( 'syncs3_rewrite' );
        $url = '';

        if ( ! empty( $syncs3_rewrite ) ) {
            $entry_id = (int) get_query_var( 'syncs3_entry_id' );
            $field_id = (int) get_query_var( 'syncs3_field_id' );
            $key = (int) get_query_var( 'syncs3_key' );  // Getting the key variable
            if ( apply_filters( 'syncs3_can_access_s3_url', true, $entry_id, $field_id, $key ) ) {
                switch ( $syncs3_rewrite ) {
                    case 's3_url':
                        $entry_meta = gform_get_meta( $entry_id, 's3_urls' );
                        $urls = s3sync_get_entry_s3_urls( $entry_id, true );
                        $file_config = ! empty( $entry_meta[$field_id][$key] ) ? $entry_meta[$field_id][$key] : '';
                        $file_urls = ! empty( $urls[$field_id][$key] ) ? $urls[$field_id][$key] : '';
                        $url = s3sync_is_file_public( $file_config ) ? $file_urls['unsigned'] : $file_urls['signed'];
                        break;
                    
                    default:
                        break;
                }
            }
        }

        if ( ! empty( $url ) ) {
            do_action( 'syncs3_s3url_rewrite_before_redirect', $entry_id, $field_id, $key );  // Updated action
            wp_redirect( $url );
            exit;
        }
    }
}

new Rewrites;
