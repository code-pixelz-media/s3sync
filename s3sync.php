<?php
/**
 * Plugin Name: S3 Sync Gravity Forms Addon
 * Plugin URI: codepixelzmedia.com.np
 * Description: Push and sync Gravity Forms file uploads to your Amazon S3 buckets.
 * Version: 1.0.0
 * Author: Codepixelzmedia
 * Author URI: Codepixelzmedia
 * Text Domain: s3sync
 * Domain Path: /languages/
 *
 * License: GPL-2.0+
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'S3Sync' ) ) :

class S3Sync {

	private static $instance;

	public static function instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof S3Sync ) ) {
			
			self::$instance = new S3Sync;

			self::$instance->constants();
			self::$instance->includes();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Constants
	 */
	public function constants() {

		// Plugin version
		if ( ! defined( 'S3SYNC_VERSION' ) ) {
			define( 'S3SYNC_VERSION', '1.7.0' );
		}

		// Plugin file
		if ( ! defined( 'S3SYNC_PLUGIN_FILE' ) ) {
			define( 'S3SYNC_PLUGIN_FILE', __FILE__ );
		}

		// Plugin basename
		if ( ! defined( 'S3SYNC_PLUGIN_BASENAME' ) ) {
			define( 'S3SYNC_PLUGIN_BASENAME', plugin_basename( S3SYNC_PLUGIN_FILE ) );
		}

		// Plugin directory path
		if ( ! defined( 'S3SYNC_PLUGIN_DIR_PATH' ) ) {
			define( 'S3SYNC_PLUGIN_DIR_PATH', trailingslashit( plugin_dir_path( S3SYNC_PLUGIN_FILE )  ) );
		}

		// Plugin directory URL
		if ( ! defined( 'S3SYNC_PLUGIN_DIR_URL' ) ) {
			define( 'S3SYNC_PLUGIN_DIR_URL', trailingslashit( plugin_dir_url( S3SYNC_PLUGIN_FILE )  ) );
		}

		// Templates directory
		if ( ! defined( 'S3SYNC_PLUGIN_TEMPLATES_DIR_PATH' ) ) {
			define ( 'S3SYNC_PLUGIN_TEMPLATES_DIR_PATH', S3SYNC_PLUGIN_DIR_PATH . 'templates/' );
		}
	}

	/**
	 * Load the AWS library
	 *
	 * @return void
	 */
	public static function autoload() {
		// Check for conflicts
		if ( ! class_exists( 'Aws\AwsClient' ) ) {
			require_once 'lib/aws/vendor/autoload.php';
		}
	}

	/**
	 * Include files
	 */
	public function includes() {
		include_once 'includes/helpers.php';
	

	}

	/**
	 * Action/filter hooks
	 */
	public function hooks() {
		register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
		add_action( 'init', array( $this, 'run_activation_tasks' ) );
		add_action( 'plugins_loaded', array( $this, 'loaded' ) );
		add_action( 'gform_loaded', array( $this, 'register_addon' ), 5 );
	}

	/**
	 * Runs when the plugin is activated.
	 *
	 * @return void
	 */
	public function plugin_activation() {
		if ( false == get_option( 's3sync_run_activation', false ) ) {
			update_option( 's3sync_run_activation', true );
		}
	}

	/**
	 * Runs the activation processes.
	 *
	 * @return void
	 */
	public function run_activation_tasks() {
		if ( true == get_option( 's3sync_run_activation', false ) ) {
			flush_rewrite_rules();
			delete_option( 's3sync_run_activation' );
		}
	}

	/**
	 * Runs when the plugin is deactivated.
	 *
	 * @return void
	 */
	public function plugin_deactivation() {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Load plugin text domain
	 */
	public function loaded() {

		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 's3sync' );
		
		unload_textdomain( 's3sync' );
		
		load_textdomain( 's3sync', WP_LANG_DIR . '/s3sync/s3sync-' . $locale . '.mo' );
		load_plugin_textdomain( 's3sync', false, dirname( S3SYNC_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Registers the GFAddon
	 *
	 * @return void
	 */
	public function register_addon() {

		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once 'includes/class-s3sync-addon.php';
		GFAddOn::register( 'S3SyncAddon' );
	}
}

endif;

/**
 * Main function for retrieving the plugin instance.
 * 
 * @return object 	S3Sync instance
 */
function s3sync() {
	return S3Sync::instance();
}

add_action( 'plugins_loaded', 's3sync_bootstrap', 1 );
/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function s3sync_bootstrap() {
	s3sync();
}

