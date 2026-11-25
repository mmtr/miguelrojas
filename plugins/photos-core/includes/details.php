<?php
/**
 * Photo details: camera data read from the image file, plus a location field per post.
 *
 * @package photos-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Store camera make, lens and 35mm-equivalent focal length on upload (WordPress drops them),
 * and prefer the moment the photo was taken over when it was digitized.
 *
 * @param array        $meta       Image meta WordPress extracted.
 * @param string       $file       Image path.
 * @param int          $image_type Image type constant.
 * @param array        $iptc       IPTC data.
 * @param array|false  $exif       Raw EXIF data.
 * @return array
 */
function photos_read_extra_image_meta( $meta, $file, $image_type, $iptc, $exif ) {
	if ( ! is_array( $exif ) ) {
		return $meta;
	}

	$text = static function ( $value ) {
		return is_string( $value ) ? trim( wp_check_invalid_utf8( wp_strip_all_tags( $value ), true ) ) : '';
	};

	$meta['make'] = $text( $exif['Make'] ?? '' );
	// PHP doesn't name the LensModel tag (0xA434), so it shows up as an undefined tag.
	$meta['lens']              = $text( $exif['UndefinedTag:0xA434'] ?? $exif['LensModel'] ?? '' );
	$meta['focal_length_35mm'] = (int) ( $exif['FocalLengthIn35mmFilm'] ?? 0 );

	if ( ! empty( $exif['DateTimeOriginal'] ) && is_string( $exif['DateTimeOriginal'] ) ) {
		$taken = wp_exif_date2ts( $exif['DateTimeOriginal'] );
		if ( $taken ) {
			$meta['created_timestamp'] = $taken;
		}
	}

	return $meta;
}
add_filter( 'wp_read_image_metadata', 'photos_read_extra_image_meta', 10, 5 );

/**
 * Register the location field for photo posts, albums and individual images.
 */
function photos_register_meta() {
	foreach ( array( 'post', 'album', 'attachment' ) as $post_type ) {
		register_post_meta(
			$post_type,
			'photos_location',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'photos_register_meta' );

/**
 * Format a number with at most one decimal, dropping ".0".
 *
 * @param float $value Number.
 * @return string
 */
function photos_format_number( $value ) {
	$value = round( (float) $value, 1 );
	return number_format_i18n( $value, floor( $value ) === $value ? 0 : 1 );
}

/**
 * Camera name from EXIF make and model, without repeating the make ("Canon Canon EOS R6").
 *
 * @param string $make  Camera make.
 * @param string $model Camera model.
 * @return string
 */
function photos_format_camera( $make, $model ) {
	$make  = trim( (string) $make );
	$model = trim( (string) $model );
	if ( '' === $make || '' === $model || 0 === stripos( $model, $make ) ) {
		return $model ? $model : $make;
	}
	return $make . ' ' . $model;
}

/**
 * Shutter speed as photographers write it: 1/250 s, 2 s.
 *
 * @param float|string $seconds Exposure time in seconds.
 * @return string
 */
function photos_format_shutter( $seconds ) {
	$seconds = (float) $seconds;
	if ( $seconds <= 0 ) {
		return '';
	}
	$value = $seconds < 1 ? '1/' . round( 1 / $seconds ) : photos_format_number( $seconds );
	/* translators: %s: exposure time, e.g. 1/250 or 2. */
	return sprintf( __( '%s s', 'photos-core' ), $value );
}

/**
 * Focal length, with the 35mm equivalent when it differs (crop sensors, phones).
 *
 * @param float|string $focal   Focal length in mm.
 * @param int          $focal35 35mm-equivalent focal length in mm.
 * @return string
 */
function photos_format_focal_length( $focal, $focal35 ) {
	$focal = (float) $focal;
	if ( $focal <= 0 ) {
		return '';
	}
	/* translators: %s: focal length number. */
	$value = sprintf( __( '%s mm', 'photos-core' ), photos_format_number( $focal ) );
	if ( $focal35 > 0 && abs( $focal35 - $focal ) >= 1 ) {
		/* translators: 1: focal length, 2: 35mm-equivalent focal length number. */
		$value = sprintf( __( '%1$s (%2$s mm equiv.)', 'photos-core' ), $value, number_format_i18n( $focal35 ) );
	}
	return $value;
}

/**
 * Image meta (camera data) stored for an image.
 *
 * @param int $image_id Attachment ID.
 * @return array
 */
function photos_get_attachment_image_meta( $image_id ) {
	$metadata = $image_id ? wp_get_attachment_metadata( $image_id ) : array();
	return is_array( $metadata ) && ! empty( $metadata['image_meta'] ) ? $metadata['image_meta'] : array();
}

/**
 * Location set on the image itself (Media Library), if any.
 *
 * @param int $image_id Attachment ID.
 * @return string
 */
function photos_get_image_location( $image_id ) {
	return $image_id ? trim( (string) get_post_meta( $image_id, 'photos_location', true ) ) : '';
}

/**
 * Details shown with an image, in display order, skipping anything unknown.
 *
 * @param int    $image_id Attachment ID.
 * @param string $location Location to show.
 * @return array[] List of { key, label, value }.
 */
function photos_get_image_details_items( $image_id, $location ) {
	$meta  = photos_get_attachment_image_meta( $image_id );
	$taken = (int) ( $meta['created_timestamp'] ?? 0 );
	$iso   = (int) ( $meta['iso'] ?? 0 );

	$values = array(
		'location' => array( __( 'Location', 'photos-core' ), (string) $location ),
		// EXIF times have no time zone, so format them as-is instead of shifting to the site's zone.
		'taken'    => array( __( 'Taken', 'photos-core' ), $taken ? wp_date( get_option( 'date_format' ), $taken, new DateTimeZone( 'UTC' ) ) : '' ),
		'camera'   => array( __( 'Camera', 'photos-core' ), photos_format_camera( $meta['make'] ?? '', $meta['camera'] ?? '' ) ),
		'lens'     => array( __( 'Lens', 'photos-core' ), (string) ( $meta['lens'] ?? '' ) ),
		'focal'    => array( __( 'Focal length', 'photos-core' ), photos_format_focal_length( $meta['focal_length'] ?? 0, (int) ( $meta['focal_length_35mm'] ?? 0 ) ) ),
		'aperture' => array( __( 'Aperture', 'photos-core' ), (float) ( $meta['aperture'] ?? 0 ) > 0 ? 'ƒ/' . photos_format_number( $meta['aperture'] ) : '' ),
		'shutter'  => array( __( 'Shutter speed', 'photos-core' ), photos_format_shutter( $meta['shutter_speed'] ?? 0 ) ),
		'iso'      => array( __( 'ISO', 'photos-core' ), $iso > 0 ? (string) $iso : '' ), // No thousands separator: "ISO 3200".
	);

	$items = array();
	foreach ( $values as $key => list( $label, $value ) ) {
		if ( '' !== trim( $value ) ) {
			$items[] = array(
				'key'   => $key,
				'label' => $label,
				'value' => $value,
			);
		}
	}
	return $items;
}

/**
 * Details of a photo post: its featured image, with the post's location
 * (or the image's own location when the post has none).
 *
 * @param int $post_id Post ID.
 * @return array[]
 */
function photos_get_details_items( $post_id ) {
	$image_id = (int) get_post_thumbnail_id( $post_id );
	$location = trim( (string) get_post_meta( $post_id, 'photos_location', true ) );
	return photos_get_image_details_items( $image_id, $location ? $location : photos_get_image_location( $image_id ) );
}

/**
 * Number of published photo posts (posts with a featured image), optionally only those
 * newer than a date. Photos are numbered across the whole site, newest first, so the
 * viewer and the photo page agree ("27 / 30") however many grid pages are loaded.
 *
 * @param string|null $newer_than Post date (Y-m-d H:i:s) to count photos after.
 * @return int
 */
function photos_count_photo_posts( $newer_than = null ) {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => '_thumbnail_id',
				'compare' => 'EXISTS',
			),
		),
	);
	if ( $newer_than ) {
		$args['date_query'] = array(
			array(
				'after'     => $newer_than,
				'inclusive' => false,
			),
		);
	}
	return (int) ( new WP_Query( $args ) )->found_posts;
}

/**
 * Where a photo post sits in the home grid (newest first, mixing photo posts and albums):
 * the URL of its grid page, so closing a photo page returns there, and its number among
 * all photos ("27 / 30"), as the viewer shows it.
 *
 * @param WP_Post $post Post.
 * @return array { url, index (1-based, or null), total }
 */
function photos_get_grid_position( $post ) {
	$per_page = max( 1, (int) get_option( 'posts_per_page' ) );
	$newer    = new WP_Query(
		array(
			'post_type'      => array( 'post', 'album' ),
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => $post->post_date,
					'inclusive' => false,
				),
			),
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	$page     = intdiv( $newer->post_count, $per_page ) + 1;
	$is_photo = 'post' === $post->post_type && has_post_thumbnail( $post );

	return array(
		'url'   => $page > 1 ? home_url( "/page/$page/" ) : home_url( '/' ),
		'index' => $is_photo ? photos_count_photo_posts( $post->post_date ) + 1 : null,
		'total' => photos_count_photo_posts(),
	);
}

/**
 * Post content as plain text, for places that can't render HTML (the viewer).
 *
 * @param WP_Post $post Post.
 * @return string
 */
function photos_get_description( $post ) {
	$text = wp_strip_all_tags( excerpt_remove_blocks( strip_shortcodes( $post->post_content ) ) );
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * Everything the viewer needs to show one image, or null if the image is missing.
 *
 * @param int   $image_id Attachment ID.
 * @param array $fields   url (history URL), imageId, title, description, items, alt.
 * @return array|null
 */
function photos_build_viewer_photo( $image_id, $fields ) {
	$full = $image_id ? wp_get_attachment_image_src( $image_id, 'full' ) : false;
	if ( ! $full ) {
		return null;
	}

	$fields = array_merge(
		array(
			'url'           => null,
			'imageId'       => null,
			'title'         => '',
			'description'   => '',
			'items'         => array(),
			'alt'           => '',
			'social'        => false,
		),
		$fields
	);

	$alt = trim( (string) $fields['alt'] );
	if ( '' === $alt ) {
		$alt = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
	}
	$srcset = wp_get_attachment_image_srcset( $image_id, 'full' );

	return array_merge(
		$fields,
		array(
			'src'    => $full[0],
			'width'  => (int) $full[1],
			'height' => (int) $full[2],
			'srcset' => $srcset ? $srcset : null,
			'alt'    => '' !== $alt ? $alt : $fields['title'],
		)
	);
}

/**
 * Viewer data for a photo post, or null if it has no featured image.
 *
 * @param WP_Post $post Post.
 * @return array|null
 */
function photos_get_viewer_photo( $post ) {
	return photos_build_viewer_photo(
		(int) get_post_thumbnail_id( $post ),
		array(
			'url'         => get_permalink( $post ),
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'description' => photos_get_description( $post ),
			'items'       => photos_get_details_items( $post->ID ),
			'social'      => photos_has_social( $post ), // The viewer loads them from the photo's page.
		)
	);
}
