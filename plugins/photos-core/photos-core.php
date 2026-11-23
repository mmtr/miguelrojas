<?php
/**
 * Plugin Name: Photos Core
 * Description: Photo details (camera settings, date taken, location) and the full-screen photo viewer.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: photos-core
 *
 * @package photos-core
 */

defined( 'ABSPATH' ) || exit;

define( 'PHOTOS_CORE_FILE', __FILE__ );

require __DIR__ . '/includes/details.php';

add_action(
	'init',
	function () {
		// Shared by the details block and the viewer, so both lists look the same.
		wp_register_style(
			'photos-details',
			plugins_url( 'assets/details.css', __FILE__ ),
			array(),
			(string) filemtime( __DIR__ . '/assets/details.css' )
		);

		register_block_type( __DIR__ . '/blocks/photo-details' );
		register_block_type( __DIR__ . '/blocks/photo-viewer' );
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script(
			'photos-photo-panel',
			plugins_url( 'editor/photo-panel.js', __FILE__ ),
			array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-core-data', 'wp-element', 'wp-i18n' ),
			(string) filemtime( __DIR__ . '/editor/photo-panel.js' ),
			true
		);
	}
);
