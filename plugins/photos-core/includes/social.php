<?php
/**
 * Likes and comments on photo posts: Jetpack's Like button and the comments (core blocks;
 * Jetpack swaps in its own comment form). The photo page shows them under the details;
 * the viewer loads the same section from the photo page for each photo it shows.
 *
 * @package photos-core
 */

/**
 * Whether a post gets likes and comments: photo posts open for comments, or with some.
 *
 * @param WP_Post $post Post.
 * @return bool
 */
function photos_has_social( $post ) {
	return 'post' === $post->post_type && ( comments_open( $post ) || get_comments_number( $post ) > 0 );
}

/**
 * Like button and comments for a post.
 *
 * @param WP_Post $post Post.
 * @return string HTML.
 */
function photos_render_social( $post ) {
	$markup = '<!-- wp:jetpack/like /-->
<!-- wp:comments {"className":"photos-comments"} -->
<div class="wp-block-comments photos-comments"><!-- wp:comments-title {"showPostTitle":false,"level":2} /-->
<!-- wp:comment-template -->
<!-- wp:comment-author-name /-->
<!-- wp:comment-date /-->
<!-- wp:comment-content /-->
<!-- wp:comment-reply-link /-->
<!-- /wp:comment-template -->
<!-- wp:comments-pagination -->
<!-- wp:comments-pagination-previous /-->
<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->
<!-- wp:post-comments-form /--></div>
<!-- /wp:comments -->';

	// render_block() rather than WP_Block directly: Jetpack registers some of its blocks
	// (Like among them) only when render_block() first meets them on the front end.
	// Unregistered blocks (no Jetpack, e.g. locally) render nothing.
	$add_context = static function ( $context ) use ( $post ) {
		$context['postId']   = $post->ID;
		$context['postType'] = $post->post_type;
		return $context;
	};
	add_filter( 'render_block_context', $add_context );
	$html = '';
	foreach ( parse_blocks( $markup ) as $parsed ) {
		if ( ! empty( $parsed['blockName'] ) ) {
			$html .= render_block( $parsed );
		}
	}
	remove_filter( 'render_block_context', $add_context );

	// The photo page's section is #comments (also when there are none yet); the title
	// can't share it.
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( array( 'class_name' => 'wp-block-comments-title' ) ) ) {
		$tags->remove_attribute( 'id' );
	}
	return $tags->get_updated_html();
}

/**
 * Load the scripts and styles the likes and comments need on a page that doesn't show
 * them itself (the grid, whose viewer adds them later). Rendering the section once is
 * what makes Jetpack enqueue its Like and comment form scripts; the HTML is discarded.
 *
 * @param WP_Post $post Any post with likes and comments.
 */
function photos_enqueue_social_assets( $post ) {
	photos_render_social( $post );
}
