/**
 * Photo viewer: opens grid photos in a full-screen <dialog>, keeps the URL in sync with the
 * photo being shown, and supports Esc, arrow keys, swipe and the browser's back button.
 */
import { store, getElement, withSyncEvent } from '@wordpress/interactivity';

const EMPTY = { title: '', description: '', url: null, imageId: null, items: [], src: null, srcset: null, width: null, height: null, alt: '' };
const PHOTO_HASH = /^#photo-(\d+)$/;
const SWIPE_DISTANCE = 50;

let pushedEntry = false; // The open viewer added a history entry, so closing means going back.
let opener = null; // Link that opened the viewer; gets focus back when it closes.
let pageTitle = '';
let pageUrl = '';
let touchStartX = null;

// Keep the other keys of history.state: WordPress reloads the page on back/forward
// when its own marker (wpInteractivityId) is missing from the entry.
const historyState = ( photosViewer ) => {
	const { photosViewer: previous, ...rest } = window.history.state || {};
	return photosViewer === undefined ? rest : { ...rest, photosViewer };
};

const pathKey = ( href ) => {
	const url = new URL( href, window.location.href );
	return url.origin + url.pathname.replace( /\/$/, '' );
};

// Photo named in the URL (#photo-<id>), if it's on this page.
const photoIndexFromHash = () => {
	const imageId = Number( window.location.hash.match( PHOTO_HASH )?.[ 1 ] );
	return imageId ? state.photos.findIndex( ( photo ) => photo.imageId === imageId ) : -1;
};

// Which photo a click is for: album photos by their image (class wp-image-<id>),
// photo posts by a link to their page.
const findPhotoIndex = ( target ) => {
	const link = target.closest?.( 'a[href]' );
	const img = target.closest?.( 'img' ) || link?.querySelector( 'img' );
	const imageId = Number( img?.className.match( /\bwp-image-(\d+)\b/ )?.[ 1 ] );
	if ( imageId ) {
		const index = state.photos.findIndex( ( photo ) => photo.imageId === imageId );
		if ( index >= 0 ) {
			return index;
		}
	}
	if ( ! link ) {
		return -1;
	}
	const key = pathKey( link.href );
	return state.photos.findIndex( ( photo ) => ! photo.imageId && pathKey( photo.url ) === key );
};

const { state, actions } = store( 'photos', {
	state: {
		get isOpen() {
			return state.currentIndex >= 0;
		},
		get current() {
			return state.photos[ state.currentIndex ] || EMPTY;
		},
		get hasPrev() {
			return state.currentIndex > 0;
		},
		get hasNext() {
			return state.isOpen && state.currentIndex < state.photos.length - 1;
		},
		get position() {
			return state.isOpen ? `${ state.currentIndex + 1 } / ${ state.photos.length }` : '';
		},
	},
	actions: {
		show( index, historyMode ) {
			const photo = state.photos[ index ];
			if ( ! photo ) {
				return;
			}
			if ( ! state.isOpen ) {
				pageTitle = document.title;
				// Opened from a #photo-<id> link: closing returns to the plain album URL.
				pageUrl = window.location.href.replace( /#photo-\d+$/, '' );
			}
			state.currentIndex = index;
			document.title = `${ photo.title } – ${ state.siteName }`;

			if ( historyMode === 'push' ) {
				window.history.pushState( historyState( index ), '', photo.url );
				pushedEntry = true;
			} else if ( historyMode === 'replace' ) {
				window.history.replaceState( historyState( index ), '', photo.url );
			}
		},
		hide() {
			state.currentIndex = -1;
			pushedEntry = false;
			document.title = pageTitle;
		},
		close() {
			if ( ! state.isOpen ) {
				return;
			}
			if ( pushedEntry ) {
				window.history.back(); // onPopstate finishes closing.
				return;
			}
			window.history.replaceState( historyState(), '', pageUrl );
			actions.hide();
		},
		prev() {
			if ( state.hasPrev ) {
				actions.show( state.currentIndex - 1, 'replace' );
			}
		},
		next() {
			if ( state.hasNext ) {
				actions.show( state.currentIndex + 1, 'replace' );
			}
		},
		openFromLink: withSyncEvent( ( event ) => {
			if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			if ( event.target.closest?.( '.photos-viewer' ) ) {
				return;
			}
			const index = findPhotoIndex( event.target );
			if ( index < 0 ) {
				return;
			}
			event.preventDefault();
			opener = event.target.closest( 'a[href]' );
			actions.show( index, 'push' );
		} ),
		onKeydown: withSyncEvent( ( event ) => {
			if ( ! state.isOpen ) {
				return;
			}
			if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				actions.prev();
			} else if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				actions.next();
			}
		} ),
		// Esc: route through close() so the history entry is cleaned up too.
		onCancel: withSyncEvent( ( event ) => {
			event.preventDefault();
			actions.close();
		} ),
		// Clicking the dark area around the photo closes the viewer.
		onStageClick: withSyncEvent( ( event ) => {
			if ( event.target === event.currentTarget ) {
				actions.close();
			}
		} ),
		// Back/forward between viewer entries, or a jump to a #photo-<id> link on this page.
		onPopstate( event ) {
			const fromViewer = event.state?.photosViewer;
			const index = Number.isInteger( fromViewer ) ? fromViewer : photoIndexFromHash();
			if ( index >= 0 && state.photos[ index ] ) {
				actions.show( index, 'none' );
				pushedEntry = Number.isInteger( fromViewer );
			} else if ( state.isOpen ) {
				actions.hide();
			}
		},
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
				actions.prev();
			} else if ( distance < -SWIPE_DISTANCE ) {
				actions.next();
			}
		},
	},
	callbacks: {
		// A link to one album photo (album URL + #photo-<id>) opens it right away.
		openFromHash() {
			const index = photoIndexFromHash();
			if ( index >= 0 ) {
				actions.show( index, 'none' );
			}
		},
		syncDialog() {
			const { ref } = getElement();
			if ( state.isOpen && ! ref.open ) {
				ref.showModal();
			} else if ( ! state.isOpen && ref.open ) {
				ref.close();
				opener?.focus();
				opener = null;
			}
		},
	},
} );
