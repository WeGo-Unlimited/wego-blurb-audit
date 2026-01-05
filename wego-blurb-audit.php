<?php
/*
Plugin Name: WeGo Blurb Audit
Description: Audit and manage blurbs in WordPress posts and pages
Version: 1.0.0
Requires at least: 6.5
Requires PHP: 7.4
Author: WeGo Unlimited
Plugin URI: https://github.com/pglewis/wego-blurb-audit/releases/latest
License: GPLv2 or later
Text Domain: wego-blurb-audit
Domain Path: /languages/
*/

/**
 * Load Composer dependencies
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

class WeGo_Blurb_Audit {
	const GITHUB_USERNAME = 'pglewis';
	const GITHUB_REPO = 'wego-blurb-audit';

	public static $plugin_url;
	public static $plugin_dir;
	public static $plugin_basename;
	public static $plugin_version;

	/**
	 * Plugin bootstrap
	 */
	public static function init() {
		$plugin_data = get_plugin_data( __FILE__ );

		self::$plugin_url = trailingslashit( plugin_dir_url( __FILE__ ) );
		self::$plugin_dir = trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$plugin_basename = trailingslashit( dirname( plugin_basename( __FILE__ ) ) );
		self::$plugin_version = $plugin_data['Version'];

		load_plugin_textdomain( 'wego-blurb-audit', false, self::$plugin_basename . 'languages/' );

		if ( is_admin() ) {
			// Initialize GitHub auto-updates
			if ( class_exists( 'WeGo_Plugin_Updater' ) ) {
				new WeGo_Plugin_Updater( __FILE__, self::GITHUB_USERNAME, self::GITHUB_REPO );
			} else {
				add_action( 'admin_notices', [ __CLASS__, 'updater_missing_notice' ] );
			}
		}
	}

	/**
	 * Display admin notice when updater class is missing
	 */
	public static function updater_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?= esc_html__( 'WeGo Blurb Audit: Auto-update functionality unavailable. Please run composer install.', 'wego-blurb-audit' ); ?></p>
		</div>
		<?php
	}
}

add_action( 'plugins_loaded', [ 'WeGo_Blurb_Audit', 'init' ] );
