<?php
/**
 * Photos theme setup.
 *
 * @package photos
 */

add_action(
	'wp_enqueue_scripts',
	function () {
		// File modification time as version so CSS edits show up without cache issues.
		wp_enqueue_style( 'photos', get_stylesheet_uri(), array(), (string) filemtime( get_stylesheet_directory() . '/style.css' ) );

		// Grid pages: thumbnails fade in as they load.
		if ( is_home() || is_archive() || is_singular( 'album' ) ) {
			wp_enqueue_script(
				'photos-tiles',
				get_theme_file_uri( 'assets/tiles.js' ),
				array(),
				(string) filemtime( get_theme_file_path( 'assets/tiles.js' ) ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}
);

/*
 * Thumbnail sizes for the photo grids (home and album pages), so browsers download an
 * image about as wide as the tile, not the 1024 px WordPress assumes. Tiles are at least
 * 17.5rem with a 0.875rem gap (style.css) inside the wide layout (1440 px at most, minus
 * the page padding): 4 columns from 1258 px, 3 from 945 px, 2 from 624 px, else 1.
 */
const PHOTOS_TILE_SIZES = '(min-width: 1536px) 350px, (min-width: 1258px) calc((100vw - 138px) / 4), (min-width: 945px) calc((92vw - 28px) / 3), (min-width: 624px) calc((92vw - 14px) / 2), calc(100vw - 48px)';

/**
 * Set the tile sizes on every image in a grid's HTML (keeping "auto", which browsers
 * that support it use for lazy-loaded images).
 *
 * @param string $html Block HTML.
 * @return string
 */
function photos_set_tile_sizes( $html ) {
	// Gallery images get their srcset later (the_content), which keeps a sizes set here.
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'img' ) ) {
		$auto = str_starts_with( (string) $tags->get_attribute( 'sizes' ), 'auto' );
		$tags->set_attribute( 'sizes', ( $auto ? 'auto, ' : '' ) . PHOTOS_TILE_SIZES );
	}
	return $tags->get_updated_html();
}

add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		return str_contains( $block['attrs']['className'] ?? '', 'photos-grid' ) ? photos_set_tile_sizes( $html ) : $html;
	},
	10,
	2
);

add_filter(
	'render_block_core/gallery',
	function ( $html ) {
		return is_singular( 'album' ) ? photos_set_tile_sizes( $html ) : $html;
	}
);

add_action(
	'after_setup_theme',
	function () {
		add_editor_style( 'style.css' );
	}
);

add_action(
	'init',
	function () {
		register_block_type( __DIR__ . '/blocks/logo' );
	}
);

// Monogram as the browser tab icon, until a Site Icon is set in WordPress.
add_action(
	'wp_head',
	function () {
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( get_theme_file_uri( 'assets/favicon.svg' ) ) );
		}
	}
);
