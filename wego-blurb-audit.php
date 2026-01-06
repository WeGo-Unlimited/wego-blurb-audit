<?php
/*
Plugin Name: WeGo Blurb Audit
Description: Audit and manage blurbs in WordPress posts and pages
Version: 1.0.1
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
	const EXPORT_ACTION = 'export_blurb_audit_xlsx';

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
			add_filter( 'set-screen-option', [ __CLASS__, 'set_screen_option' ], 10, 3 );
			add_action( 'admin_action_' . self::EXPORT_ACTION, [ __CLASS__, 'export_xlsx' ] );
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
	 * Handle screen option saving
	 */
	public static function set_screen_option( $status, $option, $value ) {
		if ( 'blurb_audit_per_page' === $option ) {
			return $value;
		}
		return $status;
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
			for ( const toggle of document.querySelectorAll( '.blurb-toggle' ) ) {
				toggle.addEventListener( 'click', function() {
					const content = this.previousElementSibling;
					const isExpanded = content.style.maxHeight === 'none';
					content.style.maxHeight = isExpanded ? '3em' : 'none';
					this.textContent = isExpanded ? '[more]' : '[less]';
				} );
			}
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
			<?php self::render_export_button( $filter ); ?>
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

	/**
	 * Render export button
	 */
	public static function render_export_button( $filter ) {
		$url = admin_url( 'admin.php' );
		$url = add_query_arg( [
			'action' => self::EXPORT_ACTION,
			'blurb_filter' => $filter,
		], $url );

		// Preserve current sort order if set
		if ( ! empty( $_GET['orderby'] ) ) {
			$url = add_query_arg( 'orderby', sanitize_text_field( wp_unslash( $_GET['orderby'] ) ), $url );
		}

		if ( ! empty( $_GET['order'] ) ) {
			$url = add_query_arg( 'order', sanitize_text_field( wp_unslash( $_GET['order'] ) ), $url );
		}

		$url = wp_nonce_url( $url, self::EXPORT_ACTION );

		echo '<div style="margin: 10px 0;">';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary">';
		echo esc_html__( 'Export to XLSX', 'wego-blurb-audit' );
		echo '</a>';
		echo '</div>';
	}

	/**
	 * Export blurbs to XLSX
	 */
	public static function export_xlsx() {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::EXPORT_ACTION ) ) {
			wp_die( __( 'Security check failed', 'wego-blurb-audit' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to export data', 'wego-blurb-audit' ) );
		}

		// Verify PhpSpreadsheet is available
		if ( ! class_exists( 'PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
			wp_die( __( 'PhpSpreadsheet library is not installed. Please run composer install.', 'wego-blurb-audit' ) );
		}

		// Get filter parameter
		$filter = isset( $_GET['blurb_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['blurb_filter'] ) ) : 'with';

		// Get sort parameters
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
		$order = strtolower( $order ) === 'desc' ? 'desc' : 'asc';

		// Fetch all posts
		$args = self::get_audit_query_args( $filter );

		$query = new WP_Query( $args );
		$all_items = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$seo_title = get_post_meta( get_the_ID(), 'seo_title', true );
				$seo_blurb = get_post_meta( get_the_ID(), 'seo_blurb', true );

				if ( 'missing' === $filter ) {
					if ( empty( $seo_blurb ) ) {
						$all_items[] = [
							'url'         => get_permalink(),
							'title'       => get_the_title(),
							'seo_title'   => $seo_title ?: '',
							'seo_blurb'   => '',
							'link_text'   => '',
							'link_target' => '',
						];
					}
				} else {
					if ( $seo_blurb ) {
						$link_data = self::extract_link_from_html( $seo_blurb );
						$all_items[] = [
							'url'         => get_permalink(),
							'title'       => get_the_title(),
							'seo_title'   => $seo_title ?: '',
							'seo_blurb'   => $seo_blurb,
							'link_text'   => $link_data['text'] ?: 'No link found',
							'link_target' => self::make_absolute_url( $link_data['href'] ),
						];
					}
				}
			}
		}
		wp_reset_postdata();

		// Sort if orderby is specified
		if ( ! empty( $orderby ) && in_array( $orderby, [ 'url', 'seo_title', 'seo_blurb', 'link_text', 'link_target' ], true ) ) {
			usort( $all_items, function( $a, $b ) use ( $orderby, $order ) {
				$val_a = $a[ $orderby ];
				$val_b = $b[ $orderby ];

				// Strip HTML tags and trim whitespace for accurate sorting
				$val_a = trim( wp_strip_all_tags( $val_a ) );
				$val_b = trim( wp_strip_all_tags( $val_b ) );

				// Case-insensitive string comparison
				$result = strcasecmp( $val_a, $val_b );

				return $order === 'desc' ? -$result : $result;
			} );
		}

		// Create spreadsheet
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		   // Set headers based on filter
		   if ( 'missing' === $filter ) {
			   $sheet->setCellValue( 'A1', __( 'Page URL', 'wego-blurb-audit' ) );
			   $sheet->setCellValue( 'B1', __( 'Page Title', 'wego-blurb-audit' ) );
			   // Set sane column widths
			   $sheet->getColumnDimension('A')->setWidth(40);
			   $sheet->getColumnDimension('B')->setWidth(30);
		   } else {
			   $sheet->setCellValue( 'A1', __( 'Page URL', 'wego-blurb-audit' ) );
			   $sheet->setCellValue( 'B1', __( 'SEO Title', 'wego-blurb-audit' ) );
			   $sheet->setCellValue( 'C1', __( 'SEO Blurb', 'wego-blurb-audit' ) );
			   $sheet->setCellValue( 'D1', __( 'Link Text', 'wego-blurb-audit' ) );
			   $sheet->setCellValue( 'E1', __( 'Link Target', 'wego-blurb-audit' ) );
			   // Set sane column widths
			   $sheet->getColumnDimension('A')->setWidth(40);
			   $sheet->getColumnDimension('B')->setWidth(30);
			   $sheet->getColumnDimension('C')->setWidth(50);
			   $sheet->getColumnDimension('D')->setWidth(20);
			   $sheet->getColumnDimension('E')->setWidth(40);
		   }

		// Write data rows
		$row = 2;
		foreach ( $all_items as $item ) {
			if ( 'missing' === $filter ) {
				$sheet->getCell( 'A' . $row )->setValue( $item['url'] );
				$sheet->getCell( 'A' . $row )->getHyperlink()->setUrl( $item['url'] );
				$sheet->setCellValue( 'B' . $row, $item['title'] );
			} else {
				// Strip HTML tags from blurb
				$clean_blurb = wp_strip_all_tags( $item['seo_blurb'] );

				$sheet->getCell( 'A' . $row )->setValue( $item['url'] );
				$sheet->getCell( 'A' . $row )->getHyperlink()->setUrl( $item['url'] );
				$sheet->setCellValue( 'B' . $row, $item['seo_title'] );
				$sheet->setCellValue( 'C' . $row, $clean_blurb );
				$sheet->setCellValue( 'D' . $row, $item['link_text'] );

				if ( ! empty( $item['link_target'] ) ) {
					$sheet->getCell( 'E' . $row )->setValue( $item['link_target'] );
					$sheet->getCell( 'E' . $row )->getHyperlink()->setUrl( $item['link_target'] );
				}
			}
			$row++;
		}

		// Set headers for XLSX download
		$filename_filter = $filter === 'with' ? 'existing' : $filter;
		$filename = 'blurb-audit-' . $filename_filter . '-' . wp_date( 'Y-m-d' ) . '.xlsx';
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Write to output
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
		$writer->save( 'php://output' );
		exit;
	}

	/**
	 * Extract link information from HTML content
	 */
	public static function extract_link_from_html( $html ) {
		if ( empty( $html ) ) {
			return [ 'text' => '', 'href' => '' ];
		}

		// Suppress libxml errors for malformed HTML
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		// Wrap the HTML fragment in a complete document structure
		$wrapped_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
		$dom->loadHTML( $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$links = $dom->getElementsByTagName( 'a' );
		if ( $links->length > 0 ) {
			$first_link = $links->item( 0 );
			return [
				'text' => trim( $first_link->textContent ),
				'href' => $first_link->getAttribute( 'href' ),
			];
		}

		// Reset libxml errors
		libxml_clear_errors();
		return [ 'text' => '', 'href' => '' ];
	}

	/**
	 * Convert URLs to relative paths for cleaner display
	 * Only relativizes internal site URLs, external URLs pass through unchanged
	 */
	public static function make_relative_url( $url ) {
		static $home_host = null;

		if ( empty( $url ) ) {
			return '';
		}

		$parsed_url = parse_url( $url );

		// If no host, it's already relative
		if ( ! isset( $parsed_url['host'] ) ) {
			return $url;
		}

		// Check if this URL matches the current site's domain
		if ( null === $home_host ) {
			$home_url = parse_url( get_home_url() );
			$home_host = $home_url['host'] ?? '';
		}

		$is_internal = $home_host && $parsed_url['host'] === $home_host;

		// Only relativize internal URLs, pass through external ones
		if ( ! $is_internal ) {
			return $url;
		}

		// Extract just the path portion
		$path = $parsed_url['path'] ?? '/';
		if ( isset( $parsed_url['query'] ) ) {
			$path .= '?' . $parsed_url['query'];
		}
		if ( isset( $parsed_url['fragment'] ) ) {
			$path .= '#' . $parsed_url['fragment'];
		}

		return $path;
	}

	/**
	 * Convert URLs to absolute paths for export
	 * Handles both relative and absolute URLs
	 */
	public static function make_absolute_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$parsed_url = parse_url( $url );

		// If it has a scheme, it's already absolute
		if ( isset( $parsed_url['scheme'] ) ) {
			return $url;
		}

		// Relative URL, prepend home URL
		return trailingslashit( get_home_url() ) . ltrim( $url, '/' );
	}

	/**
	 * Get query args for audit
	 */
	public static function get_audit_query_args( $filter ) {
		// Set up meta_query based on filter
		if ( 'missing' === $filter ) {
			$meta_query = [
				'relation' => 'OR',
				[
					'key'     => 'seo_blurb',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'seo_blurb',
					'value'   => '',
					'compare' => '=',
				],
			];
		} else {
			$meta_query = [
				[
					'key'     => 'seo_blurb',
					'value'   => '',
					'compare' => '!=',
				],
			];
		}

		return [
			'post_type'      => [ 'page', 'post' ],
			'posts_per_page' => -1,
			'meta_query'     => $meta_query,
			'post_status'    => 'publish',
			'orderby'        => [
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			],
		];
	}
}

add_action( 'plugins_loaded', [ 'WeGo_Blurb_Audit', 'init' ] );
