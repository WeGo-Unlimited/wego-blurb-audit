<?php
/*
Plugin Name: WeGo Blurb Audit
Description: Audit and manage blurbs in WordPress posts and pages
Version: 0.0.5
Requires at least: 6.5
Requires PHP: 7.4
Author: WeGo Unlimited
Plugin URI: https://github.com/WeGo-Unlimited/wego-blurb-audit/releases/latest
License: GPLv2 or later
Text Domain: wego-blurb-audit
Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Load Composer dependencies
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/class-wego-blurb-audit-list-table.php';

class WeGo_Blurb_Audit {
	const GITHUB_USERNAME = 'WeGo-Unlimited';
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
			add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );
		}
	}

	/**
	 * Register admin menu
	 */
	public static function admin_menu() {
		$hook = add_management_page(
			'Wego Blurb Audit',
			'Wego Blurb Audit',
			'manage_options',
			'wego-blurb-audit',
			[ __CLASS__, 'render_admin_page' ]
		);

		// Add screen options on page load
		add_action( "load-$hook", [ __CLASS__, 'add_screen_options' ] );
	}

	/**
	 * Add screen options for items per page
	 */
	public static function add_screen_options() {
		add_screen_option( 'per_page', [
			'label'   => __( 'Blurbs per page', 'wego-blurb-audit' ),
			'default' => 20,
			'option'  => 'blurb_audit_per_page',
		] );
	}

	/**
	 * Render the admin page
	 */
	public static function render_admin_page() {
		$filter = isset( $_GET['blurb_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['blurb_filter'] ) ) : 'with';
		$table = new WeGo_Blurb_Audit_List_Table( $filter );
		$table->prepare_items();
		?>
		<style>
			.blurb-truncated { max-height: 3em; overflow: hidden; }
			.blurb-toggle { color: #0073aa; cursor: pointer; font-size: 0.9em; }
			.blurb-toggle:hover { text-decoration: underline; }
			.blurb-edit-link { margin-right: 0.6rem; }
		</style>
		<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			document.querySelectorAll( '.blurb-toggle' ).forEach( function( toggle ) {
				toggle.addEventListener( 'click', function() {
					const content = this.previousElementSibling;
					const isExpanded = content.style.maxHeight === 'none';
					content.style.maxHeight = isExpanded ? '3em' : 'none';
					this.textContent = isExpanded ? '[more]' : '[less]';
				} );
			} );
		} );
		</script>
		<div class="wrap">
			<h1><?= esc_html__( 'Wego Blurb Audit', 'wego-blurb-audit' ); ?></h1>
			<form method="get" style="margin-bottom: 1em;">
				<input type="hidden" name="page" value="<?= esc_attr( $_GET['page'] ?? 'wego-blurb-audit' ); ?>" />
				<label for="blurb_filter"><strong><?= esc_html__( 'Show:', 'wego-blurb-audit' ); ?></strong></label>
				<select name="blurb_filter" id="blurb_filter" onchange="this.form.submit()">
					<option value="with" <?= selected( $filter, 'with', false ); ?>><?= esc_html__( 'Audit Existing Blurbs', 'wego-blurb-audit' ); ?></option>
					<option value="missing" <?= selected( $filter, 'missing', false ); ?>><?= esc_html__( 'Missing Blurbs', 'wego-blurb-audit' ); ?></option>
				</select>
			</form>
			<form method="get">
				<input type="hidden" name="page" value="<?= esc_attr( $_GET['page'] ?? 'wego-blurb-audit' ); ?>" />
				<input type="hidden" name="blurb_filter" value="<?= esc_attr( $filter ); ?>" />
				<?php $table->display(); ?>
			</form>
		</div>
		   <?php
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
