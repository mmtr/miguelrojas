<?php
/**
 * Photo Viewer block: a full-screen dialog for the photos of the current page.
 *
 * On an album page it shows the album's photos; elsewhere, the photo posts of the main
 * query (the same posts the grid shows; albums keep linking to their own page).
 * Without JavaScript, links open each photo's page or image file instead.
 *
 * @package photos-core
 */

global $wp_query;

$photos_list  = array();
$photos_total = null;
if ( is_singular( 'album' ) ) {
	$photos_list = photos_get_album_viewer_photos( get_queried_object() );
} else {
	$photos_first = null;
	foreach ( (array) ( $wp_query->posts ?? array() ) as $photos_post ) {
		$photos_photo = 'post' === $photos_post->post_type ? photos_get_viewer_photo( $photos_post ) : null;
		if ( $photos_photo ) {
			$photos_list[] = $photos_photo;
			$photos_first  = $photos_first ? $photos_first : $photos_post;
		}
	}

	// On the home grid, number photos across the whole site so the count stays right as
	// more pages load ("27 / 30"); the photo page counts the same way.
	if ( is_home() ) {
		$photos_total = photos_count_photo_posts();
		if ( $photos_first ) {
			$photos_offset = photos_count_photo_posts( $photos_first->post_date );
			foreach ( $photos_list as $photos_i => $photos_photo ) {
				$photos_list[ $photos_i ]['index'] = $photos_offset + $photos_i + 1;
			}
		}
	}
}

// Nothing to show and nothing to load: skip. A grid page with only albums still needs the
// viewer when there are more pages, since its script also does the infinite scroll.
if ( ! $photos_list && ( is_singular() || (int) $wp_query->max_num_pages <= 1 ) ) {
	return;
}

wp_interactivity_state(
	'photos',
	array(
		'photos'       => $photos_list,
		'totalPhotos'  => $photos_total,
		'currentIndex' => -1,
		'siteName'     => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
	)
);

$photos_icon = static function ( $path ) {
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
};
?>
<dialog
	<?php echo get_block_wrapper_attributes( array( 'class' => 'photos-viewer' ) ); ?>
	aria-labelledby="photos-viewer-title"
	data-wp-interactive="photos"
	data-wp-init="callbacks.init"
	data-wp-watch="callbacks.syncDialog"
	data-wp-on--cancel="actions.onCancel"
	data-wp-on-document--click="actions.openFromLink"
	data-wp-on-document--keydown="actions.onKeydown"
	data-wp-on-window--popstate="actions.onPopstate"
>
	<button type="button" class="photos-viewer__close" data-wp-on--click="actions.close" aria-label="<?php esc_attr_e( 'Close', 'photos-core' ); ?>">
		<?php echo $photos_icon( 'M6 6l12 12M18 6L6 18' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</button>

	<div
		class="photos-viewer__stage"
		data-wp-on--click="actions.onStageClick"
		data-wp-on--touchstart="actions.onTouchStart"
		data-wp-on--touchend="actions.onTouchEnd"
	>
		<button type="button" class="photos-viewer__prev" data-wp-on--click="actions.prev" data-wp-bind--hidden="!state.hasPrev" aria-label="<?php esc_attr_e( 'Previous photo', 'photos-core' ); ?>">
			<?php echo $photos_icon( 'M15 5l-7 7 7 7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
		<img
			class="photos-viewer__image"
			alt=""
			decoding="async"
			sizes="(min-width: 900px) calc(100vw - 22rem), 100vw"
			data-wp-bind--src="state.current.src"
			data-wp-bind--srcset="state.current.srcset"
			data-wp-bind--width="state.current.width"
			data-wp-bind--height="state.current.height"
			data-wp-bind--alt="state.current.alt"
		>
		<button type="button" class="photos-viewer__next" data-wp-on--click="actions.next" data-wp-bind--hidden="!state.hasNext" aria-label="<?php esc_attr_e( 'Next photo', 'photos-core' ); ?>">
			<?php echo $photos_icon( 'M9 5l7 7-7 7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
	</div>

	<aside class="photos-viewer__panel">
		<p class="photos-viewer__position" data-wp-text="state.position"></p>
		<h2 class="photos-viewer__title" id="photos-viewer-title" data-wp-text="state.current.title"></h2>
		<p class="photos-viewer__description" data-wp-text="state.current.description" data-wp-bind--hidden="!state.current.description"></p>
		<dl class="photos-details">
			<template data-wp-each--item="state.current.items" data-wp-each-key="context.item.key">
				<div class="photos-details__item">
					<dt data-wp-text="context.item.label"></dt>
					<dd data-wp-text="context.item.value"></dd>
				</div>
			</template>
		</dl>
	</aside>
</dialog>
