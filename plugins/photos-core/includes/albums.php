<?php
/**
 * Albums: collections of photos added with a Gallery block. The main grid shows an
 * album's cover; its photos only appear on the album page.
 *
 * @package photos-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the album post type (/albums/ archive, /albums/<name>/ pages).
 */
function photos_register_album_post_type() {
	register_post_type(
		'album',
		array(
			'labels'        => array(
				'name'          => __( 'Albums', 'photos-core' ),
				'singular_name' => __( 'Album', 'photos-core' ),
				'add_new_item'  => __( 'Add New Album', 'photos-core' ),
				'edit_item'     => __( 'Edit Album', 'photos-core' ),
				'new_item'      => __( 'New Album', 'photos-core' ),
				'view_item'     => __( 'View Album', 'photos-core' ),
				'search_items'  => __( 'Search Albums', 'photos-core' ),
				'not_found'     => __( 'No albums found.', 'photos-core' ),
				'all_items'     => __( 'All Albums', 'photos-core' ),
			),
			'public'        => true,
			'has_archive'   => 'albums',
			'rewrite'       => array(
				'slug'       => 'albums',
				'with_front' => false,
			),
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-format-gallery',
			'show_in_rest'  => true,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			// New albums start with a wide gallery, ready for photos to be dropped in.
			'template'      => array(
				array(
					'core/gallery',
					array(
						'align'  => 'wide',
						'linkTo' => 'none',
					),
				),
			),
		)
	);
}
add_action( 'init', 'photos_register_album_post_type' );

/**
 * Mix albums into the home page grid, alongside single photos.
 *
 * @param WP_Query $query Query.
 */
function photos_include_albums_on_home( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
		$query->set( 'post_type', array( 'post', 'album' ) );
	}
}
add_action( 'pre_get_posts', 'photos_include_albums_on_home' );

/**
 * Photos of an album: every Image block with an uploaded image, in order.
 *
 * @param WP_Post|int $album Album.
 * @return array[] List of { id, caption, alt }.
 */
function photos_get_album_images( $album ) {
	static $cache = array();

	$album = get_post( $album );
	if ( ! $album ) {
		return array();
	}
	$key = $album->ID . ':' . $album->post_modified_gmt;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$images = array();
	$walk   = static function ( $blocks ) use ( &$walk, &$images ) {
		foreach ( $blocks as $block ) {
			if ( 'core/image' === $block['blockName'] && ! empty( $block['attrs']['id'] ) ) {
				$html    = (string) $block['innerHTML'];
				$caption = preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#s', $html, $match )
					? trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $match[1] ), ENT_QUOTES, 'UTF-8' ) ) )
					: '';
				$tags    = new WP_HTML_Tag_Processor( $html );
				$alt     = $tags->next_tag( 'img' ) ? (string) $tags->get_attribute( 'alt' ) : '';

				$images[] = array(
					'id'      => (int) $block['attrs']['id'],
					'caption' => $caption,
					'alt'     => $alt,
				);
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( $album->post_content ) );

	$cache[ $key ] = $images;
	return $images;
}

/**
 * Albums without a featured image use their first photo as the cover.
 *
 * @param int|false        $thumbnail_id Featured image ID.
 * @param WP_Post|int|null $post         Post.
 * @return int|false
 */
function photos_album_cover_fallback( $thumbnail_id, $post ) {
	if ( $thumbnail_id || ! $post || 'album' !== get_post_type( $post ) ) {
		return $thumbnail_id;
	}
	$images = photos_get_album_images( $post );
	return $images ? $images[0]['id'] : $thumbnail_id;
}
add_filter( 'post_thumbnail_id', 'photos_album_cover_fallback', 10, 2 );

/**
 * Viewer data for every photo in an album. Each photo's URL is the album page plus
 * #photo-<id>, so a single photo can be linked to.
 *
 * @param WP_Post $album Album.
 * @return array[]
 */
function photos_get_album_viewer_photos( $album ) {
	$location = trim( (string) get_post_meta( $album->ID, 'photos_location', true ) );
	$title    = html_entity_decode( get_the_title( $album ), ENT_QUOTES, 'UTF-8' );
	$base_url = get_permalink( $album );

	$photos = array();
	foreach ( photos_get_album_images( $album ) as $image ) {
		$own_location = photos_get_image_location( $image['id'] );
		$photo        = photos_build_viewer_photo(
			$image['id'],
			array(
				'url'         => $base_url . '#photo-' . $image['id'],
				'imageId'     => $image['id'],
				'title'       => $title,
				'description' => $image['caption'],
				'items'       => photos_get_image_details_items( $image['id'], $own_location ? $own_location : $location ),
				'alt'         => $image['alt'],
			)
		);
		if ( $photo ) {
			$photos[] = $photo;
		}
	}
	return $photos;
}

/**
 * Month range of the photos, e.g. "May 2025", "May – June 2025", "December 2024 – January 2025".
 *
 * @param int $from Earliest timestamp.
 * @param int $to   Latest timestamp.
 * @return string
 */
function photos_format_month_range( $from, $to ) {
	$utc = new DateTimeZone( 'UTC' );
	/* translators: date format for a month and year, see https://www.php.net/manual/datetime.format.php */
	$month_year = __( 'F Y', 'photos-core' );
	$start      = wp_date( $month_year, $from, $utc );
	$end        = wp_date( $month_year, $to, $utc );

	if ( $start === $end ) {
		return $start;
	}
	if ( wp_date( 'Y', $from, $utc ) === wp_date( 'Y', $to, $utc ) ) {
		return wp_date( 'F', $from, $utc ) . ' – ' . $end;
	}
	return $start . ' – ' . $end;
}

/**
 * Short facts about an album: photo count, location and when the photos were taken.
 *
 * @param WP_Post|int $album Album.
 * @return string[]
 */
function photos_get_album_summary_parts( $album ) {
	$album  = get_post( $album );
	$images = $album ? photos_get_album_images( $album ) : array();
	$parts  = array();

	if ( $images ) {
		/* translators: %s: number of photos. */
		$parts[] = sprintf( _n( '%s photo', '%s photos', count( $images ), 'photos-core' ), number_format_i18n( count( $images ) ) );
	}

	$location = $album ? trim( (string) get_post_meta( $album->ID, 'photos_location', true ) ) : '';
	if ( $location ) {
		$parts[] = $location;
	}

	$times = array();
	foreach ( $images as $image ) {
		$taken = (int) ( photos_get_attachment_image_meta( $image['id'] )['created_timestamp'] ?? 0 );
		if ( $taken ) {
			$times[] = $taken;
		}
	}
	if ( $times ) {
		$parts[] = photos_format_month_range( min( $times ), max( $times ) );
	}

	return $parts;
}

/**
 * On album pages, make every photo a link to its full-size file (opens the viewer with
 * JavaScript, the image itself without) and give it an anchor for #photo-<id> links.
 *
 * @param string $block_content Rendered Image block.
 * @param array  $block         Parsed block.
 * @return string
 */
function photos_link_album_images( $block_content, $block ) {
	$image_id = (int) ( $block['attrs']['id'] ?? 0 );
	if ( ! $image_id || ! is_singular( 'album' ) ) {
		return $block_content;
	}

	$full = wp_get_attachment_image_url( $image_id, 'full' );
	if ( $full && false === strpos( $block_content, '<a ' ) ) {
		$block_content = preg_replace_callback(
			'/<img\b[^>]*>/',
			static function ( $match ) use ( $full ) {
				return '<a class="photos-album__link" href="' . esc_url( $full ) . '">' . $match[0] . '</a>';
			},
			$block_content,
			1
		);
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag( 'figure' ) ) {
		$tags->set_attribute( 'id', 'photo-' . $image_id );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/image', 'photos_link_album_images', 10, 2 );

/**
 * "Location" field for images in the Media Library (overrides the album's location).
 *
 * @param array   $fields Attachment form fields.
 * @param WP_Post $post   Attachment.
 * @return array
 */
function photos_attachment_location_field( $fields, $post ) {
	if ( ! wp_attachment_is_image( $post ) ) {
		return $fields;
	}
	$fields['photos_location'] = array(
		'label' => __( 'Location', 'photos-core' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, 'photos_location', true ),
		'helps' => __( 'Shown with the photo. In an album, replaces the album location for this photo.', 'photos-core' ),
	);
	return $fields;
}
add_filter( 'attachment_fields_to_edit', 'photos_attachment_location_field', 10, 2 );

/**
 * Save the Media Library "Location" field.
 *
 * @param array $post       Attachment data.
 * @param array $attachment Submitted fields.
 * @return array
 */
function photos_save_attachment_location( $post, $attachment ) {
	if ( isset( $attachment['photos_location'] ) ) {
		update_post_meta( $post['ID'], 'photos_location', sanitize_text_field( wp_unslash( $attachment['photos_location'] ) ) );
	}
	return $post;
}
add_filter( 'attachment_fields_to_save', 'photos_save_attachment_location', 10, 2 );
