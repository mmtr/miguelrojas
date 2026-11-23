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
