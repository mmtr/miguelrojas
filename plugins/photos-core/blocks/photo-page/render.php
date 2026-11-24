<?php
/**
 * Photo Page block: a photo post laid out like the viewer (dark background, photo on the
 * left, details on the right), so reloading or opening a shared link looks the same as
 * clicking the photo in the grid. Arrows link to the newer and older photo, in grid order;
 * × goes back to the grid page the photo is on. Keyboard and swipe work as in the viewer.
 *
 * @package photos-core
 *
 * @var WP_Block $block Block instance.
 */

$photos_post = get_post( $block->context['postId'] ?? get_the_ID() );
if ( ! $photos_post ) {
	return;
}

$photos_image_id = (int) get_post_thumbnail_id( $photos_post );
$photos_position = photos_get_grid_position( $photos_post );
$photos_desc     = photos_get_description( $photos_post );
$photos_items    = photos_get_details_items( $photos_post->ID );

// Grid order is newest first: the left arrow goes to the newer photo, the right to the older.
$photos_newer = 'post' === $photos_post->post_type ? get_adjacent_post( false, '', false ) : null;
$photos_older = 'post' === $photos_post->post_type ? get_adjacent_post( false, '', true ) : null;

$photos_icon = static function ( $path ) {
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
};

// The neighbour's image, so the script can load it before moving there (and ahead of time).
$photos_preload_attrs = static function ( $post ) {
	$image_id = $post ? (int) get_post_thumbnail_id( $post ) : 0;
	$src      = $image_id ? wp_get_attachment_image_src( $image_id, 'full' ) : false;
	if ( ! $src ) {
		return '';
	}
	$srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
	return sprintf( ' data-photos-src="%s" data-photos-srcset="%s"', esc_url( $src[0] ), esc_attr( $srcset ? $srcset : '' ) );
};

wp_interactivity_state( 'photos/page', array( 'loading' => false ) );
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'photos-viewer photos-photo-page' ) ); ?>
	data-wp-interactive="photos/page"
	data-wp-class--is-loading="state.loading"
	data-wp-init="callbacks.preloadNeighbours"
	data-wp-on-window--pageshow="actions.onPageshow"
	data-wp-on-document--keydown="actions.onKeydown"
	data-wp-on--touchstart="actions.onTouchStart"
	data-wp-on--touchend="actions.onTouchEnd"
>
	<a class="photos-viewer__close" href="<?php echo esc_url( $photos_position['url'] ); ?>" data-photos-nav="close" aria-label="<?php esc_attr_e( 'Close', 'photos-core' ); ?>">
		<?php echo $photos_icon( 'M6 6l12 12M18 6L6 18' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</a>

	<div class="photos-viewer__stage">
		<?php if ( $photos_newer ) : ?>
			<a class="photos-viewer__prev" href="<?php echo esc_url( get_permalink( $photos_newer ) ); ?>" rel="prev" data-photos-nav="prev"<?php echo $photos_preload_attrs( $photos_newer ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?> data-wp-on--click="actions.onLinkClick" aria-label="<?php esc_attr_e( 'Previous photo', 'photos-core' ); ?>">
				<?php echo $photos_icon( 'M15 5l-7 7 7 7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		<?php endif; ?>

		<?php
		if ( $photos_image_id ) {
			echo wp_get_attachment_image(
				$photos_image_id,
				'full',
				false,
				array(
					'class'         => 'photos-viewer__image',
					'sizes'         => '(min-width: 900px) calc(100vw - 22rem), 100vw',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'decoding'      => 'async',
				)
			);
		}
		?>

		<span class="photos-viewer__spinner" role="status" data-wp-bind--hidden="!state.loading" hidden>
			<span class="photos-viewer__visually-hidden"><?php esc_html_e( 'Loading photo', 'photos-core' ); ?></span>
		</span>

		<?php if ( $photos_older ) : ?>
			<a class="photos-viewer__next" href="<?php echo esc_url( get_permalink( $photos_older ) ); ?>" rel="next" data-photos-nav="next"<?php echo $photos_preload_attrs( $photos_older ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?> data-wp-on--click="actions.onLinkClick" aria-label="<?php esc_attr_e( 'Next photo', 'photos-core' ); ?>">
				<?php echo $photos_icon( 'M9 5l7 7-7 7' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		<?php endif; ?>
	</div>

	<aside class="photos-viewer__panel">
		<?php if ( $photos_position['index'] ) : ?>
			<p class="photos-viewer__position"><?php echo esc_html( $photos_position['index'] . ' / ' . $photos_position['total'] ); ?></p>
		<?php endif; ?>
		<h1 class="photos-viewer__title"><?php echo esc_html( get_the_title( $photos_post ) ); ?></h1>
		<?php if ( $photos_desc ) : ?>
			<p class="photos-viewer__description"><?php echo esc_html( $photos_desc ); ?></p>
		<?php endif; ?>
		<?php if ( $photos_items ) : ?>
			<dl class="photos-details">
				<?php foreach ( $photos_items as $photos_item ) : ?>
					<div class="photos-details__item photos-details__item--<?php echo esc_attr( $photos_item['key'] ); ?>">
						<dt><?php echo esc_html( $photos_item['label'] ); ?></dt>
						<dd><?php echo esc_html( $photos_item['value'] ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	</aside>
</div>
