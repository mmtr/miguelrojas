/**
 * Photo viewer: opens grid photos in a full-screen <dialog>, keeps the URL in sync with the
 * photo being shown, and supports Esc, arrow keys, swipe and the browser's back button.
 *
 * It also turns the grid's pagination into infinite scroll: near the bottom, the next
 * grid page is fetched, its photos are appended and added to the viewer. The page links
 * stay in the HTML as the fallback without JavaScript (or if loading fails).
 */
import { store, getElement, withSyncEvent } from '@wordpress/interactivity';

const EMPTY = { title: '', description: '', url: null, imageId: null, index: null, items: [], src: null, srcset: null, width: null, height: null, alt: '', social: false };
const PHOTO_HASH = /^#photo-(\d+)$/;
const SWIPE_DISTANCE = 50;
const LOAD_AHEAD = 1000; // Start loading the next page this many pixels before the end.

let nextPageUrl = null; // Next grid page to load, from the pagination's "next" link.
let loading = null; // The page load in progress, if any.
let sentinel = null; // The pagination element; reaching it loads the next page.
let observer = null;

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

/*
 * Likes and comments: the viewer takes the Like button and the comments from the photo's
 * page and puts them in the bar under the photo and below it. Jetpack sets up its comment
 * form only when its script runs, so the script runs again for each photo; its Like
 * script looks for new buttons on scroll.
 */
const SOCIAL_DELAY = 200; // Quick arrow presses don't load every photo's comments.
const SOCIAL_MAX_WAIT = 3000; // Longest wait for Jetpack's parts before showing them.
const SOCIAL_PENDING = '.jetpack-comments:not(.is-mounted):not(.is-plain), .jetpack-likes-widget-unloaded, .jetpack-likes-widget-loading';
const LIKE_SETTLE = 150; // The Like button draws itself just after its frame loads.
const socialParts = new Map(); // Photo URL → promise of { like, comments } HTML.
let socialTimer = 0;
let socialToken = 0;
let likeCount = 0;

const fetchSocial = ( url ) => {
	if ( ! socialParts.has( url ) ) {
		const request = fetch( url, { credentials: 'same-origin' } )
			.then( ( response ) => ( response.ok ? response.text() : Promise.reject( new Error( response.statusText ) ) ) )
			.then( ( html ) => {
				const page = new DOMParser().parseFromString( html, 'text/html' );
				return {
					like: page.querySelector( '.photos-photo-page .photos-viewer__like' )?.innerHTML ?? '',
					comments: page.querySelector( '.photos-photo-page .photos-viewer__comments' )?.innerHTML ?? '',
				};
			} )
			.catch( ( error ) => {
				socialParts.delete( url ); // Try again next time.
				throw error;
			} );
		socialParts.set( url, request );
	}
	return socialParts.get( url );
};

// Jetpack's Like script remembers each button's id; a photo shown again needs a new one.
const withNewLikeId = ( html ) => {
	const id = html.match( /like-post-wrapper-\d+-\d+-(\w+)/ )?.[ 1 ];
	return id ? html.replaceAll( id, `${ id }${ ++likeCount }` ) : html;
};

// Scripts added with innerHTML don't run: replace them with copies that do
// (e.g. Akismet's timestamp on the comment form).
const runInlineScripts = ( root ) => {
	root.querySelectorAll( 'script' ).forEach( ( original ) => {
		const script = document.createElement( 'script' );
		for ( const { name, value } of original.attributes ) {
			script.setAttribute( name, value );
		}
		script.textContent = original.textContent;
		original.replaceWith( script );
	} );
};

const runScriptAgain = ( id ) => {
	const src = document.getElementById( id )?.src;
	if ( ! src ) {
		return;
	}
	const script = document.createElement( 'script' );
	script.src = src;
	script.onload = script.onerror = () => script.remove();
	document.body.append( script );
};

// Jetpack's comment form and Like button fill in after the HTML is added: wait for them,
// so they appear in one go.
const whenSocialReady = ( roots ) =>
	new Promise( ( resolve ) => {
		const start = Date.now();
		const pending = () => roots.some( ( root ) => root.querySelector( SOCIAL_PENDING ) );
		const check = () => {
			if ( pending() && Date.now() - start < SOCIAL_MAX_WAIT ) {
				setTimeout( check, 100 );
			} else {
				setTimeout( resolve, roots.some( ( root ) => root.querySelector( '.jetpack-likes-widget-wrapper' ) ) ? LIKE_SETTLE : 0 );
			}
		};
		check();
	} );

const showSocial = ( photo ) => {
	const like = document.querySelector( '.photos-viewer__like-content' );
	const comments = document.querySelector( '.photos-viewer__comments-inner' );
	const content = comments?.querySelector( '.photos-viewer__comments-content' );
	if ( ! like || ! comments || ! content ) {
		return;
	}
	const setLoading = ( isLoading ) => {
		like.classList.toggle( 'is-loading', isLoading );
		comments.classList.toggle( 'is-loading', isLoading );
		comments.setAttribute( 'aria-busy', String( isLoading ) );
	};
	clearTimeout( socialTimer );
	const token = ++socialToken;
	setLoading( true ); // The previous photo's stay hidden from now on.
	if ( ! photo?.social ) {
		like.replaceChildren();
		content.replaceChildren();
		return;
	}
	socialTimer = setTimeout( () => {
		fetchSocial( photo.url )
			.then( ( parts ) => {
				if ( token !== socialToken ) {
					return;
				}
				like.innerHTML = withNewLikeId( parts.like );
				content.innerHTML = parts.comments;
				runInlineScripts( content );
				runScriptAgain( 'jetpack-comments-js' );
				window.dispatchEvent( new Event( 'scroll' ) );
				return whenSocialReady( [ like, content ] );
			} )
			.catch( () => {
				like.replaceChildren();
				content.replaceChildren();
			} )
			.finally( () => {
				if ( token === socialToken ) {
					setLoading( false );
				}
			} );
	}, SOCIAL_DELAY );
};

const nextLinkIn = ( root ) => root.querySelector( '.photos-grid .wp-block-query-pagination-next' )?.href || null;

const stopInfiniteScroll = ( showLinks ) => {
	observer?.disconnect();
	observer = null;
	if ( showLinks ) {
		document.querySelector( '.photos-grid' )?.classList.remove( 'is-infinite' );
	} else {
		sentinel?.remove();
	}
	nextPageUrl = null;
};

// Fetch the next grid page, append its items and add its photos to the viewer.
// Resolves to true when photos were added.
const loadNextPage = () => {
	if ( ! nextPageUrl ) {
		return Promise.resolve( false );
	}
	if ( loading ) {
		return loading;
	}
	loading = ( async () => {
		try {
			const response = await fetch( nextPageUrl, { credentials: 'same-origin' } );
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}
			const doc = new DOMParser().parseFromString( await response.text(), 'text/html' );
			const list = document.querySelector( '.photos-grid .wp-block-post-template' );
			for ( const item of doc.querySelectorAll( '.photos-grid .wp-block-post-template > li' ) ) {
				list.append( document.importNode( item, true ) );
			}

			// The fetched page carries its photos in the same viewer state: add the new ones.
			const data = JSON.parse( doc.getElementById( 'wp-script-module-data-@wordpress/interactivity' )?.textContent || '{}' );
			const known = new Set( state.photos.map( ( photo ) => photo.url ) );
			const added = ( data?.state?.photos?.photos || [] ).filter( ( photo ) => ! known.has( photo.url ) );
			state.photos.push( ...added );
			state.totalPhotos = state.totalPhotos || data?.state?.photos?.totalPhotos || null;

			nextPageUrl = nextLinkIn( doc );
			if ( ! nextPageUrl ) {
				stopInfiniteScroll( false );
			}
			return added.length > 0;
		} catch ( error ) {
			// Fall back to the page links.
			stopInfiniteScroll( true );
			return false;
		} finally {
			loading = null;
			state.hasMorePages = Boolean( nextPageUrl );
		}
	} )();
	return loading;
};

// Keep loading while the end of the grid is still within reach (short pages, tall screens).
const loadWhileNearEnd = async () => {
	while ( sentinel?.isConnected && nextPageUrl && sentinel.getBoundingClientRect().top < window.innerHeight + LOAD_AHEAD ) {
		if ( ! ( await loadNextPage() ) ) {
			break;
		}
	}
};

// Must match the viewer image's sizes attribute, so preloading fetches the same file.
const IMAGE_SIZES = '(min-width: 900px) calc(100vw - 9rem), 100vw';
const ready = new Set(); // Photos (by src) whose image is downloaded and decoded.
let navigation = 0; // Bumped on every navigation, so only the latest one is shown.
let target = -1; // Photo being navigated to (shown or still loading); arrows step from it.

// Download and decode a photo's image off-screen. Always resolves, even on errors.
const preload = ( photo ) => {
	if ( ! photo?.src || ready.has( photo.src ) ) {
		return Promise.resolve();
	}
	const img = new Image();
	img.sizes = IMAGE_SIZES;
	if ( photo.srcset ) {
		img.srcset = photo.srcset;
	}
	img.src = photo.src;
	return img
		.decode()
		.catch( () => {} )
		.then( () => ready.add( photo.src ) );
};

const { state, actions, callbacks } = store( 'photos', {
	state: {
		// Open while a photo is shown, or while the first one is still loading.
		get isOpen() {
			return state.currentIndex >= 0 || state.opening;
		},
		get current() {
			return state.photos[ state.currentIndex ] || EMPTY;
		},
		get hasPrev() {
			return state.currentIndex > 0;
		},
		// Also true at the last loaded photo when more grid pages can be loaded.
		get hasNext() {
			return state.isOpen && ( state.currentIndex < state.photos.length - 1 || state.hasMorePages );
		},
		// The title line: place and date taken.
		get meta() {
			return ( state.current.items || [] )
				.filter( ( item ) => item.key === 'location' || item.key === 'taken' )
				.map( ( item ) => item.value )
				.join( ' · ' );
		},
		// The bar under the photo: the camera settings.
		get exif() {
			return ( state.current.items || [] ).filter( ( item ) => item.key !== 'location' && item.key !== 'taken' );
		},
		// Portrait photos get the details beside them on wide screens (see style.css).
		get isPortrait() {
			const { width, height } = state.current;
			return Boolean( width ) && height > width;
		},
		get hasMore() {
			return Boolean( state.current.description || state.current.social );
		},
	},
	actions: {
		// Show a photo once its image is ready, so the image, details, title and URL all
		// change together. Until then the current photo stays (dimmed, with a spinner).
		show( index, historyMode ) {
			const photo = state.photos[ index ];
			if ( ! photo ) {
				return;
			}
			if ( ! state.isOpen ) {
				pageTitle = document.title;
				// Opened from a #photo-<id> link: closing returns to the plain album URL.
				pageUrl = window.location.href.replace( /#photo-\d+$/, '' );
				state.opening = true;
			}
			const thisNavigation = ++navigation;
			target = index;
			state.loading = ! ready.has( photo.src );

			preload( photo ).then( () => {
				if ( thisNavigation !== navigation ) {
					return; // Another photo was requested meanwhile.
				}
				state.loading = false;
				state.opening = false;
				state.currentIndex = index;
				document.querySelector( 'dialog.photos-viewer' )?.scrollTo( 0, 0 );
				showSocial( photo );
				document.title = `${ photo.title } – ${ state.siteName }`;

				if ( historyMode === 'push' ) {
					window.history.pushState( historyState( index ), '', photo.url );
					pushedEntry = true;
				} else if ( historyMode === 'replace' ) {
					window.history.replaceState( historyState( index ), '', photo.url );
				}

				// Get the neighbours ready so the next arrow press is instant.
				preload( state.photos[ index + 1 ] );
				preload( state.photos[ index - 1 ] );
			} );
		},
		hide() {
			showSocial( null );
			navigation++;
			target = -1;
			state.loading = false;
			state.opening = false;
			state.currentIndex = -1;
			pushedEntry = false;
			document.title = pageTitle;
		},
		close() {
			if ( ! state.isOpen ) {
				return;
			}
			if ( state.currentIndex < 0 ) {
				actions.hide(); // Still loading the first photo: just cancel.
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
			const index = ( target >= 0 ? target : state.currentIndex ) - 1;
			if ( index >= 0 ) {
				actions.show( index, 'replace' );
			}
		},
		next() {
			const index = ( target >= 0 ? target : state.currentIndex ) + 1;
			if ( state.photos[ index ] ) {
				actions.show( index, 'replace' );
			} else if ( state.hasMorePages ) {
				// At the last loaded photo: load the next grid page, then move on.
				loadNextPage().then( () => {
					state.hasMorePages = Boolean( nextPageUrl );
					if ( state.isOpen && state.photos[ index ] ) {
						actions.show( index, 'replace' );
					}
				} );
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
			if ( ! state.isOpen || event.target.closest?.( 'input, textarea, select, [contenteditable]' ) ) {
				return; // Arrow keys in the comment form move the cursor.
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
		// In the comment form, Esc only leaves the field.
		onCancel: withSyncEvent( ( event ) => {
			event.preventDefault();
			const field = document.activeElement?.closest?.( 'input, textarea, select, [contenteditable]' );
			if ( field ) {
				field.blur();
				return;
			}
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
		// One init directive (named data-wp-init--* variants aren't run by every version).
		init() {
			callbacks.openFromHash();
			callbacks.initInfiniteScroll();
		},
		// Replace the grid's page links with loading as you scroll.
		initInfiniteScroll() {
			sentinel = document.querySelector( '.photos-grid .wp-block-query-pagination' );
			nextPageUrl = nextLinkIn( document );
			state.hasMorePages = Boolean( nextPageUrl );
			if ( ! sentinel || ! nextPageUrl || ! ( 'IntersectionObserver' in window ) ) {
				return;
			}
			document.querySelector( '.photos-grid' ).classList.add( 'is-infinite' );
			observer = new IntersectionObserver(
				( entries ) => {
					if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
						loadWhileNearEnd().then( () => {
							state.hasMorePages = Boolean( nextPageUrl );
						} );
					}
				},
				{ rootMargin: `0px 0px ${ LOAD_AHEAD }px 0px` }
			);
			observer.observe( sentinel );
		},
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
