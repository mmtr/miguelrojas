<?php
/**
 * Photo Details block: location, date taken and camera settings as a list.
 *
 * @package photos-core
 *
 * @var WP_Block $block Block instance.
 */

$photos_post_id = $block->context['postId'] ?? get_the_ID();
$photos_items   = $photos_post_id ? photos_get_details_items( $photos_post_id ) : array();

if ( ! $photos_items ) {
	return;
}
?>
<dl <?php echo get_block_wrapper_attributes( array( 'class' => 'photos-details' ) ); ?>>
	<?php foreach ( $photos_items as $photos_item ) : ?>
		<div class="photos-details__item photos-details__item--<?php echo esc_attr( $photos_item['key'] ); ?>">
			<dt><?php echo esc_html( $photos_item['label'] ); ?></dt>
			<dd><?php echo esc_html( $photos_item['value'] ); ?></dd>
		</div>
	<?php endforeach; ?>
</dl>
