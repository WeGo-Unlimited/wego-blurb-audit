<?php
/**
 * WP_List_Table class for blurb audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WeGo_Blurb_Audit_List_Table extends WP_List_Table {
	private $blurb_filter;

	public function __construct( $blurb_filter = 'with', $args = [] ) {
		parent::__construct( $args );
		$this->blurb_filter = $blurb_filter;
	}

	public function get_columns() {
		if ( 'missing' === $this->blurb_filter ) {
			return [
				'url' => __( 'Page', 'wego-blurb-audit' ),
			];
		}

		return [
			'url'         => __( 'Page URL', 'wego-blurb-audit' ),
			'seo_title'   => __( 'SEO Title', 'wego-blurb-audit' ),
			'seo_blurb'   => __( 'SEO Blurb', 'wego-blurb-audit' ),
			'link_text'   => __( 'Link Text', 'wego-blurb-audit' ),
			'link_target' => __( 'Link Target', 'wego-blurb-audit' ),
		];
	}

	public function get_sortable_columns() {
		if ( 'missing' === $this->blurb_filter ) {
			return [];
		}

		return [
			'url'         => [ 'url', false ],
			'seo_title'   => [ 'seo_title', false ],
			'seo_blurb'   => [ 'seo_blurb', false ],
			'link_text'   => [ 'link_text', false ],
			'link_target' => [ 'link_target', false ],
		];
	}

	public function column_default( $item, $column_name ) {
		return $item[ $column_name ] ?? '';
	}

	public function prepare_items() {
		// Get posts per page from screen options (with default fallback)
		$per_page = get_user_option( 'blurb_audit_per_page' ) ?: 20;
		$current_page = $this->get_pagenum();

		// Get sort parameters
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';
		$order = strtolower( $order ) === 'desc' ? 'desc' : 'asc';

		// Fetch all posts (no pagination in query)
		$args = WeGo_Blurb_Audit::get_audit_query_args( $this->blurb_filter );
		$query = new WP_Query( $args );
		$all_items = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$seo_title = get_post_meta( get_the_ID(), 'seo_title', true );
				$seo_blurb = get_post_meta( get_the_ID(), 'seo_blurb', true );

				if ( 'missing' === $this->blurb_filter ) {
					// Only show if seo_blurb is truly empty (not just missing meta key)
					if ( empty( $seo_blurb ) ) {
						$all_items[] = [
							'post_id'     => get_the_ID(),
							'url'         => WeGo_Blurb_Audit::make_relative_url( get_permalink() ),
							'title'       => get_the_title(),
							'seo_title'   => $seo_title ?: '',
							'seo_blurb'   => '',
							'link_text'   => '',
							'link_target' => '',
						];
					}
				} else {
					if ( $seo_blurb ) {
						$link_data = WeGo_Blurb_Audit::extract_link_from_html( $seo_blurb );
						$all_items[] = [
							'post_id'     => get_the_ID(),
							'url'         => WeGo_Blurb_Audit::make_relative_url( get_permalink() ),
							'title'       => get_the_title(),
							'seo_title'   => $seo_title ?: '',
							'seo_blurb'   => $seo_blurb,
							'link_text'   => $link_data['text'] ?: 'No link found',
							'link_target' => WeGo_Blurb_Audit::make_relative_url( $link_data['href'] ),
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

		// Get total count
		$total_items = count( $all_items );

		// Slice for pagination
		$offset = ( $current_page - 1 ) * $per_page;
		$this->items = array_slice( $all_items, $offset, $per_page );

		// Set pagination
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	public function column_url( $item ) {
		// For missing-blurbs view we show edit link first, then the page title/permalink
		if ( 'missing' === $this->blurb_filter ) {
			$edit_link = get_edit_post_link( $item['post_id'] );
			$permalink = get_permalink( $item['post_id'] );
			$title = $item['title'] ?: $item['url'];

			$edit_html = $edit_link ? sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html__( '[edit]', 'wego-blurb-audit' ) ) : '';
			$title_html = $permalink ? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $permalink ), esc_html( $title ) ) : esc_html( $title );

			return trim( '<span class="blurb-edit-link">' . $edit_html . '</span>' . $title_html );
		}

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