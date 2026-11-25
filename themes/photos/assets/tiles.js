/**
 * Photo grids: each thumbnail fades in once its image has loaded. The browser loads them
 * as they come near the screen (lazy loading); ones already loaded when this runs simply
 * show. Tiles added later by the infinite scroll get the same treatment.
 */
( () => {
	const IMAGES = '.photos-grid .wp-post-image, .photos-album .wp-block-gallery img';

	const prepare = ( img ) => {
		if ( 'photosTile' in img.dataset ) {
			return;
		}
		img.dataset.photosTile = '';
		const tile = img.closest( 'figure' );
		if ( ! tile || ( img.complete && img.naturalWidth ) ) {
			return; // Already loaded: no fade.
		}
		tile.classList.add( 'is-pending' );
		const reveal = () => tile.classList.replace( 'is-pending', 'is-revealed' );
		img.addEventListener( 'load', reveal, { once: true } );
		img.addEventListener( 'error', reveal, { once: true } );
	};

	const scan = ( root ) => root.querySelectorAll( IMAGES ).forEach( prepare );
	scan( document );

	const list = document.querySelector( '.photos-grid .wp-block-post-template' );
	if ( list ) {
		new MutationObserver( () => scan( list ) ).observe( list, { childList: true } );
	}
} )();
