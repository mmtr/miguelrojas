<?php
/**
 * Album Summary block: "7 photos · Lisbon, Portugal · May 2025".
 *
 * @package photos-core
 *
 * @var WP_Block $block Block instance.
 */

$photos_parts = photos_get_album_summary_parts( (int) ( $block->context['postId'] ?? get_the_ID() ) );
if ( ! $photos_parts ) {
	return;
}
?>
<p <?php echo get_block_wrapper_attributes( array( 'class' => 'photos-album-summary' ) ); ?>><?php echo esc_html( implode( ' · ', $photos_parts ) ); ?></p>
