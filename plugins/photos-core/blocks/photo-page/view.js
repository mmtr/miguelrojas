/**
 * Photo page: the same keys, swipes and loading behaviour as the viewer, following the
 * page's own links (← newer photo, → older photo, Esc back to the grid).
 *
 * Moving to another photo first downloads its image while the current photo stays, dimmed,
 * with a spinner; then the page changes, with the new photo already loaded. The photos on
 * either side are preloaded in the background, so most moves are instant.
 */
import { store, withSyncEvent } from '@wordpress/interactivity';

const KEYS = { ArrowLeft: 'prev', ArrowRight: 'next', Escape: 'close' };
const SWIPE_DISTANCE = 50;
// Must match the image's sizes attribute, so preloading fetches the same file.
const IMAGE_SIZES = '(min-width: 900px) calc(100vw - 22rem), 100vw';
const MAX_WAIT = 8000; // Go anyway if the image takes longer than this.

let touchStartX = null;
let leaving = false;

const linkFor = ( nav ) => document.querySelector( `.photos-photo-page [data-photos-nav="${ nav }"]` );

// Download and decode a neighbour's image off-screen. Always resolves.
const preload = ( link ) => {
	const src = link?.dataset.photosSrc;
	if ( ! src ) {
		return Promise.resolve();
	}
	const img = new Image();
	img.sizes = IMAGE_SIZES;
	if ( link.dataset.photosSrcset ) {
		img.srcset = link.dataset.photosSrcset;
	}
	img.src = src;
	return img.decode().catch( () => {} );
};

const go = ( nav ) => {
	const link = linkFor( nav );
	if ( ! link ) {
		return false;
	}
	if ( leaving ) {
		return true;
	}
	leaving = true;
	if ( nav === 'close' ) {
		window.location.href = link.href;
		return true;
	}
	state.loading = true;
	const timeout = new Promise( ( resolve ) => setTimeout( resolve, MAX_WAIT ) );
	Promise.race( [ preload( link ), timeout ] ).then( () => {
		window.location.href = link.href;
	} );
	return true;
};

const { state } = store( 'photos/page', {
	actions: {
		onLinkClick: withSyncEvent( ( event ) => {
			// Let modified clicks (new tab, new window) through.
			if ( event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			event.preventDefault();
			go( event.currentTarget.dataset.photosNav );
		} ),
		onKeydown: withSyncEvent( ( event ) => {
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey ) {
				return;
			}
			if ( event.target.closest?.( 'input, textarea, select, [contenteditable]' ) ) {
				return;
			}
			const nav = KEYS[ event.key ];
			if ( nav && go( nav ) ) {
				event.preventDefault();
			}
		} ),
		onTouchStart( event ) {
			touchStartX = event.changedTouches[ 0 ].clientX;
		},
		onTouchEnd( event ) {
			if ( touchStartX === null ) {
				return;
			}
			const distance = event.changedTouches[ 0 ].clientX - touchStartX;
			touchStartX = null;
			if ( distance > SWIPE_DISTANCE ) {
				go( 'prev' );
			} else if ( distance < -SWIPE_DISTANCE ) {
				go( 'next' );
			}
		},
		// Coming back with the browser's back button can restore the page as it was left
		// (dimmed, mid-navigation): reset it.
		onPageshow( event ) {
			if ( event.persisted ) {
				state.loading = false;
				leaving = false;
			}
		},
	},
	callbacks: {
		// After this photo has loaded, get the ones on either side ready.
		preloadNeighbours() {
			const run = () => {
				preload( linkFor( 'next' ) );
				preload( linkFor( 'prev' ) );
			};
			if ( document.readyState === 'complete' ) {
				run();
			} else {
				window.addEventListener( 'load', run, { once: true } );
			}
		},
	},
} );
