<?php
/**
 * Photo Page block: a photo post laid out like the viewer. The first screen is the photo,
 * as large as it fits, with the title, place and date just above it and the camera
 * settings and Like button just below; the description and comments follow, their top
 * peeking in at the bottom of the screen. It's what a photo's own URL shows (a shared
 * link, a reload, a search result), so instead of the viewer's arrows and close button
 * it has the site's logo at the top, as on the home page, linking home.
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
$photos_meta     = photos_details_meta_items( $photos_items );
$photos_exif     = photos_details_exif_items( $photos_items );
$photos_social   = photos_has_social( $photos_post );
$photos_src      = $photos_image_id ? wp_get_attachment_image_src( $photos_image_id, 'full' ) : false;
$photos_portrait = $photos_src && $photos_src[2] > $photos_src[1]; // Leaves more room for the settings (style.css).

// The theme's logo block (name, slogan, viewfinder corners), as at the top of the home
// page, shown light on this dark page; else the theme's wordmark, else the site name.
$photos_brand = WP_Block_Type_Registry::get_instance()->is_registered( 'photos/logo' )
	? render_block(
		array(
			'blockName'    => 'photos/logo',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	)
	: '';
$photos_logo_file = get_theme_file_path( 'assets/logo.svg' );
$photos_logo      = ! $photos_brand && is_readable( $photos_logo_file ) ? file_get_contents( $photos_logo_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions

$photos_details_list = static function ( $items, $class ) {
	if ( ! $items ) {
		return '';
	}
	$html = '';
	foreach ( $items as $item ) {
		$html .= sprintf(
			'<li class="photos-exif__item" data-key="%s"><span class="photos-viewer__visually-hidden">%s: </span>%s</li>',
			esc_attr( $item['key'] ),
			esc_html( $item['label'] ),
			esc_html( $item['value'] )
		);
	}
	return '<ul class="photos-exif ' . esc_attr( $class ) . '">' . $html . '</ul>';
};
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'photos-viewer photos-photo-page' . ( $photos_portrait ? ' is-portrait' : '' ) ) ); ?>>
	<?php if ( $photos_brand ) : ?>
		<header class="photos-viewer__brand"><?php echo $photos_brand; // phpcs:ignore WordPress.Security.EscapeOutput -- rendered block. ?></header>
	<?php else : ?>
		<a class="photos-viewer__home" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php echo $photos_logo ? $photos_logo : esc_html( get_bloginfo( 'name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme file, not user input. ?>
		</a>
	<?php endif; ?>

	<div class="photos-viewer__screen">
		<div class="photos-viewer__figure">
			<header class="photos-viewer__head">
				<h1 class="photos-viewer__title"><?php echo esc_html( get_the_title( $photos_post ) ); ?></h1>
				<?php echo $photos_details_list( $photos_meta, 'photos-viewer__meta' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
			</header>

			<div class="photos-viewer__photo">
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
				<?php echo $photos_details_list( $photos_exif, '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
				<?php if ( $photos_social ) : ?>
					<div class="photos-viewer__like">
						<?php echo photos_render_like( $photos_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks. ?>
					</div>
				<?php endif; ?>
			</footer>
		</div>
	</div>

	<?php if ( $photos_desc || $photos_social ) : ?>
		<section class="photos-viewer__more" id="comments">
			<?php if ( $photos_desc ) : ?>
				<p class="photos-viewer__description"><?php echo esc_html( $photos_desc ); ?></p>
			<?php endif; ?>
			<?php if ( $photos_social ) : ?>
				<h2 class="photos-viewer__comments-title"><?php echo esc_html( photos_comments_heading( $photos_post ) ); ?></h2>
				<div class="photos-viewer__comments">
					<?php echo photos_render_comments( $photos_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks. ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
