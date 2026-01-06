<?php
/*
Plugin Name: WeGo Blurb Audit
Description: Audit and manage blurbs in WordPress posts and pages
Version: 0.0.2
Requires at least: 6.5
Requires PHP: 7.4
Author: WeGo Unlimited
Plugin URI: https://github.com/WeGo-Unlimited/wego-blurb-audit/releases/latest
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
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new WeGo_Blurb_Audit_List_Table();
		$table->prepare_items();
		?>
		<style>
			.blurb-truncated { max-height: 3em; overflow: hidden; }
			.blurb-toggle { color: #0073aa; cursor: pointer; font-size: 0.9em; }
			.blurb-toggle:hover { text-decoration: underline; }
		</style>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.blurb-toggle').forEach(function(toggle) {
				toggle.addEventListener('click', function() {
					const content = this.previousElementSibling;
					const isExpanded = content.style.maxHeight === 'none';
					content.style.maxHeight = isExpanded ? '3em' : 'none';
					this.textContent = isExpanded ? '[more]' : '[less]';
				});
			});
		});
		</script>
		<div class="wrap">
			<h1><?= esc_html__( 'Wego Blurb Audit', 'wego-blurb-audit' ); ?></h1>
			<form method="get">
				<?php wp_nonce_field( 'wego_blurb_audit', 'wego_blurb_audit_nonce' ); ?>
				<input type="hidden" name="page" value="<?= esc_attr( $_REQUEST['page'] ?? 'wego-blurb-audit' ); ?>" />
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

/**
 * WP_List_Table class for blurb audit
 * Must be defined at file scope after WP_List_Table is loaded
 */
add_action( 'admin_init', function() {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	if ( ! class_exists( 'WeGo_Blurb_Audit_List_Table' ) ) {
		class WeGo_Blurb_Audit_List_Table extends WP_List_Table {
			public function get_columns() {
				return [
					'url'         => __( 'Page URL', 'wego-blurb-audit' ),
					'seo_title'   => __( 'SEO Title', 'wego-blurb-audit' ),
					'seo_blurb'   => __( 'SEO Blurb', 'wego-blurb-audit' ),
					'link_text'   => __( 'Link Text', 'wego-blurb-audit' ),
					'link_target' => __( 'Link Target', 'wego-blurb-audit' ),
				];
			}

			/**
			 * Extract link information from HTML content
			 */
			private function extract_link_from_html( $html ) {
				if ( empty( $html ) ) {
					return [ 'text' => '', 'href' => '' ];
				}

				// Suppress libxml errors for malformed HTML
				libxml_use_internal_errors( true );
				$dom = new DOMDocument();
				$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

				$links = $dom->getElementsByTagName( 'a' );
				if ( $links->length > 0 ) {
					$first_link = $links->item( 0 );
					return [
						'text' => trim( $first_link->textContent ),
						'href' => $this->make_relative_url( $first_link->getAttribute( 'href' ) ),
					];
				}

				return [ 'text' => '', 'href' => '' ];
			}

			/**
			 * Convert URLs to relative paths for cleaner display
			 */
			private function make_relative_url( $url ) {
				if ( empty( $url ) ) {
					return '';
				}

				$parsed_url = parse_url( $url );

				// If no host, it's already relative
				if ( ! isset( $parsed_url['host'] ) ) {
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

			public function column_default( $item, $column_name ) {
				return $item[ $column_name ] ?? '';
			}

			public function prepare_items() {
				// Get posts per page from screen options (with default fallback)
				$per_page = get_user_option( 'blurb_audit_per_page' ) ?: 20;
				$current_page = $this->get_pagenum();

				// First get the total count
				$count_args = [
					'post_type'      => [ 'page', 'post' ],
					'posts_per_page' => -1,
					'meta_query'     => [
						[
							'key'     => 'seo_blurb',
							'compare' => 'EXISTS',
						],
					],
					'post_status'    => 'publish',
					'fields'         => 'ids', // Only get IDs for counting
				];
				$count_query = new WP_Query( $count_args );
				$total_items = $count_query->found_posts;

				// Now get the paginated results
				$args = [
					'post_type'      => [ 'page', 'post' ],
					'posts_per_page' => $per_page,
					'paged'          => $current_page,
					'meta_query'     => [
						[
							'key'     => 'seo_blurb',
							'compare' => 'EXISTS',
						],
					],
					'post_status'    => 'publish',
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				];
				$query = new WP_Query( $args );
				$this->items = [];

				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$seo_title = get_post_meta( get_the_ID(), 'seo_title', true );
						$seo_blurb = get_post_meta( get_the_ID(), 'seo_blurb', true );

						if ( $seo_blurb ) {
							$link_data = $this->extract_link_from_html( $seo_blurb );
							$this->items[] = [
								'url'         => $this->make_relative_url( get_permalink() ),
							'seo_title'   => $seo_title ?: '',
							'seo_blurb'   => $seo_blurb,
								'link_text'   => $link_data['text'] ?: 'No link found',
								'link_target' => $link_data['href'] ?: '',
							];
						}
					}
				}
				wp_reset_postdata();

				// Set pagination
				$this->set_pagination_args( [
					'total_items' => $total_items,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total_items / $per_page ),
				] );

				$this->_column_headers = [ $this->get_columns(), [], [] ];
			}

			public function column_url( $item ) {
				return sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( get_home_url() . $item['url'] ),
					esc_html( $item['url'] )
				);
			}

			public function column_seo_title( $item ) {
				return esc_html( $item['seo_title'] );
			}

			public function column_seo_blurb( $item ) {
				// Render blurb as HTML with allowed tags
				$allowed_html = [
					'a' => [
						'href'   => [],
						'title'  => [],
						'target' => [],
						'class'  => [],
					],
					'strong' => [],
					'em'     => [],
					'p'      => [],
					'br'     => [],
				];
				$clean_html = wp_kses( $item['seo_blurb'], $allowed_html );

				return sprintf(
					'<div class="blurb-truncated" style="max-height: 3em; overflow: hidden;">%s</div><span class="blurb-toggle">[more]</span>',
					$clean_html
				);
			}

			public function column_link_text( $item ) {
				return esc_html( $item['link_text'] );
			}

			public function column_link_target( $item ) {
				if ( empty( $item['link_target'] ) ) {
					return '';
				}

				// If it starts with /, it's relative, prepend home URL
				if ( strpos( $item['link_target'], '/' ) === 0 ) {
					$full_url = get_home_url() . $item['link_target'];
				} else {
					// External URL, use as-is
					$full_url = $item['link_target'];
				}

				return sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $full_url ),
					esc_html( $item['link_target'] )
				);
			}
		}
	}
});

add_action( 'plugins_loaded', [ 'WeGo_Blurb_Audit', 'init' ] );
