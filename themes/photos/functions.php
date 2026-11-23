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
