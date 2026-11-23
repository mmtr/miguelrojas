<?php
/**
 * Entry Label block: overlays album covers in the grid with "Album · N photos" and the title.
 *
 * @package photos-core
 *
 * @var WP_Block $block Block instance.
 */

$photos_post_id = (int) ( $block->context['postId'] ?? 0 );
if ( ! $photos_post_id || 'album' !== get_post_type( $photos_post_id ) ) {
	return;
}

$photos_count = count( photos_get_album_images( $photos_post_id ) );
/* translators: %s: number of photos in the album. */
$photos_kind = sprintf( _n( 'Album · %s photo', 'Album · %s photos', $photos_count, 'photos-core' ), number_format_i18n( $photos_count ) );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'photos-entry-label' ) ); ?>>
	<span class="photos-entry-label__kind"><?php echo esc_html( $photos_kind ); ?></span>
	<span class="photos-entry-label__title"><?php echo esc_html( get_the_title( $photos_post_id ) ); ?></span>
</div>
