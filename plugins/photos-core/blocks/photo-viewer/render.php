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

$photos_list   = array();
$photos_total  = null;
$photos_social = null; // A photo post with likes and comments, if the viewer shows any.
if ( is_singular( 'album' ) ) {
	$photos_list = photos_get_album_viewer_photos( get_queried_object() );
} else {
	$photos_first = null;
	foreach ( (array) ( $wp_query->posts ?? array() ) as $photos_post ) {
		$photos_photo = 'post' === $photos_post->post_type ? photos_get_viewer_photo( $photos_post ) : null;
		if ( $photos_photo ) {
			$photos_list[] = $photos_photo;
			$photos_first  = $photos_first ? $photos_first : $photos_post;
			if ( ! $photos_social && $photos_photo['social'] ) {
				$photos_social = $photos_post;
			}
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

// The viewer drops each photo's likes and comments in from its page; their scripts have
// to be on this page already.
if ( $photos_social ) {
	photos_enqueue_social_assets( $photos_social );
}

$photos_icon = static function ( $path ) {
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
};
?>
<dialog
	<?php echo get_block_wrapper_attributes( array( 'class' => 'photos-viewer' ) ); ?>
	aria-labelledby="photos-viewer-title"
	data-wp-interactive="photos"
	data-wp-init="callbacks.init"
	data-wp-class--is-loading="state.loading"
	data-wp-class--is-portrait="state.isPortrait"
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
		class="photos-viewer__screen"
		data-wp-on--touchstart="actions.onTouchStart"
		data-wp-on--touchend="actions.onTouchEnd"
	>
		<div class="photos-viewer__figure">
			<header class="photos-viewer__head">
				<h2 class="photos-viewer__title" id="photos-viewer-title" data-wp-text="state.current.title"></h2>
				<ul class="photos-exif photos-viewer__meta">
					<template data-wp-each--item="state.metaItems" data-wp-each-key="context.item.key">
						<li class="photos-exif__item" data-wp-bind--data-key="context.item.key"><span class="photos-viewer__visually-hidden" data-wp-text="context.item.label"></span><span data-wp-text="context.item.value"></span></li>
					</template>
				</ul>
			</header>

			<div class="photos-viewer__photo">
				<button type="button" class="photos-viewer__prev" data-wp-on--click="actions.prev" data-wp-bind--hidden="!state.hasPrev" aria-label="<?php esc_attr_e( 'Previous photo', 'photos-core' ); ?>">
					<?php echo $photos_icon( 'M15 5l-7 7 7 7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
				<img
					class="photos-viewer__image"
					alt=""
					decoding="async"
					sizes="(min-width: 900px) calc(100vw - 9rem), 100vw"
					data-wp-bind--src="state.current.src"
					data-wp-bind--srcset="state.current.srcset"
					data-wp-bind--width="state.current.width"
					data-wp-bind--height="state.current.height"
					data-wp-bind--alt="state.current.alt"
				>
				<span class="photos-viewer__spinner" role="status" data-wp-bind--hidden="!state.loading">
					<span class="photos-viewer__visually-hidden"><?php esc_html_e( 'Loading photo', 'photos-core' ); ?></span>
				</span>
				<button type="button" class="photos-viewer__next" data-wp-on--click="actions.next" data-wp-bind--hidden="!state.hasNext" aria-label="<?php esc_attr_e( 'Next photo', 'photos-core' ); ?>">
					<?php echo $photos_icon( 'M9 5l7 7-7 7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>

			<footer class="photos-viewer__bar">
				<ul class="photos-exif">
					<template data-wp-each--item="state.exif" data-wp-each-key="context.item.key">
						<li class="photos-exif__item" data-wp-bind--data-key="context.item.key"><span class="photos-viewer__visually-hidden" data-wp-text="context.item.label"></span><span data-wp-text="context.item.value"></span></li>
					</template>
				</ul>
				<?php if ( $photos_social ) : ?>
					<div class="photos-viewer__like" data-wp-bind--hidden="!state.current.social">
						<?php // Jetpack's Like button takes its text and link colors from these. ?>
						<span class="sd-text-color" hidden></span><a class="sd-link-color" hidden></a>
						<div class="photos-viewer__like-content is-loading"></div>
					</div>
				<?php endif; ?>
			</footer>
		</div>
	</div>

	<section class="photos-viewer__more" data-wp-bind--hidden="!state.hasMore">
		<p class="photos-viewer__description" data-wp-text="state.current.description" data-wp-bind--hidden="!state.current.description"></p>
		<?php if ( $photos_social ) : ?>
			<h2 class="photos-viewer__comments-title" data-wp-text="state.current.comments" data-wp-bind--hidden="!state.current.social"></h2>
			<div class="photos-viewer__comments" data-wp-bind--hidden="!state.current.social">
				<div class="photos-viewer__comments-inner is-loading" aria-busy="true">
					<div class="photos-viewer__social-placeholder" aria-hidden="true">
						<span class="photos-viewer__skeleton photos-viewer__skeleton--title"></span>
						<span class="photos-viewer__skeleton photos-viewer__skeleton--box"></span>
					</div>
					<div class="photos-viewer__comments-content"></div>
				</div>
			</div>
		<?php endif; ?>
	</section>
</dialog>
