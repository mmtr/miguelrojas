<?php
/**
 * Photo Page block: a photo post laid out like the viewer. The first screen is the photo,
 * as large as it fits, with the title, place and date above it and the camera settings
 * and Like button below; the description and comments follow, a scroll away. It's what a
 * photo's own URL shows (a shared link, a reload, a search result), so instead of the
 * viewer's arrows and close button it has the site logo, top left, linking home.
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
$photos_desc     = photos_get_description( $photos_post );
$photos_items    = photos_get_details_items( $photos_post->ID );
$photos_meta     = photos_details_meta_line( $photos_items );
$photos_exif     = photos_details_exif_items( $photos_items );
$photos_social   = photos_has_social( $photos_post );
$photos_src      = $photos_image_id ? wp_get_attachment_image_src( $photos_image_id, 'full' ) : false;
$photos_portrait = $photos_src && $photos_src[2] > $photos_src[1]; // Details go beside portrait photos on wide screens.

// The theme's wordmark if it has one (shown light on this dark page), else the site name.
$photos_logo_file = get_theme_file_path( 'assets/logo.svg' );
$photos_logo      = is_readable( $photos_logo_file ) ? file_get_contents( $photos_logo_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'photos-viewer photos-photo-page' . ( $photos_portrait ? ' is-portrait' : '' ) ) ); ?>>
	<div class="photos-viewer__screen">
		<a class="photos-viewer__home" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php echo $photos_logo ? $photos_logo : esc_html( get_bloginfo( 'name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme file, not user input. ?>
		</a>

		<header class="photos-viewer__head">
			<h1 class="photos-viewer__title"><?php echo esc_html( get_the_title( $photos_post ) ); ?></h1>
			<?php if ( $photos_meta ) : ?>
				<p class="photos-viewer__meta"><?php echo esc_html( $photos_meta ); ?></p>
			<?php endif; ?>
		</header>

		<div class="photos-viewer__stage">
			<?php
			if ( $photos_image_id ) {
				echo wp_get_attachment_image(
					$photos_image_id,
					'full',
					false,
					array(
						'class'         => 'photos-viewer__image',
						'sizes'         => '(min-width: 900px) calc(100vw - 9rem), 100vw',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
			}
			?>
		</div>

		<footer class="photos-viewer__bar">
			<?php if ( $photos_exif ) : ?>
				<ul class="photos-exif">
					<?php foreach ( $photos_exif as $photos_item ) : ?>
						<li class="photos-exif__item" data-key="<?php echo esc_attr( $photos_item['key'] ); ?>"><span class="photos-viewer__visually-hidden"><?php echo esc_html( $photos_item['label'] ); ?>: </span><?php echo esc_html( $photos_item['value'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $photos_social ) : ?>
				<div class="photos-viewer__like">
					<?php echo photos_render_like( $photos_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks. ?>
				</div>
			<?php endif; ?>
		</footer>
	</div>

	<?php if ( $photos_desc || $photos_social ) : ?>
		<section class="photos-viewer__more" id="comments">
			<?php if ( $photos_desc ) : ?>
				<p class="photos-viewer__description"><?php echo esc_html( $photos_desc ); ?></p>
			<?php endif; ?>
			<?php if ( $photos_social ) : ?>
				<div class="photos-viewer__comments">
					<?php echo photos_render_comments( $photos_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks. ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
