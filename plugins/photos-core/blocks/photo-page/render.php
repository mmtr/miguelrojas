<?php
/**
 * Photo Page block: a photo post laid out like the viewer (dark background, photo on the
 * left, details on the right). It's what a photo's own URL shows (a shared link, a reload,
 * a search result), so instead of the viewer's arrows and close button it has the site
 * logo, top left, linking to the home page.
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

// The theme's wordmark if it has one (shown light on this dark page), else the site name.
$photos_logo_file = get_theme_file_path( 'assets/logo.svg' );
$photos_logo      = is_readable( $photos_logo_file ) ? file_get_contents( $photos_logo_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'photos-viewer photos-photo-page' ) ); ?>>
	<a class="photos-viewer__home" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<?php echo $photos_logo ? $photos_logo : esc_html( get_bloginfo( 'name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme file, not user input. ?>
	</a>

	<div class="photos-viewer__stage">
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
		<?php if ( photos_has_social( $photos_post ) ) : ?>
			<section class="photos-viewer__social" id="comments">
				<?php echo photos_render_social( $photos_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks. ?>
			</section>
		<?php endif; ?>
	</aside>
</div>
