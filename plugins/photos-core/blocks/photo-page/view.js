/**
 * Photo page: the same keys and swipes as the viewer, following the page's own links
 * (← newer photo, → older photo, Esc back to the grid).
 */
import { store, withSyncEvent } from '@wordpress/interactivity';

const KEYS = { ArrowLeft: 'prev', ArrowRight: 'next', Escape: 'close' };
const SWIPE_DISTANCE = 50;
let touchStartX = null;

const follow = ( nav ) => {
	const link = document.querySelector( `.photos-photo-page [data-photos-nav="${ nav }"]` );
	if ( link ) {
		window.location.href = link.href;
	}
	return Boolean( link );
};

store( 'photos/page', {
	actions: {
		onKeydown: withSyncEvent( ( event ) => {
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey ) {
				return;
			}
			if ( event.target.closest?.( 'input, textarea, select, [contenteditable]' ) ) {
				return;
			}
			const nav = KEYS[ event.key ];
			if ( nav && follow( nav ) ) {
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
				follow( 'prev' );
			} else if ( distance < -SWIPE_DISTANCE ) {
				follow( 'next' );
			}
		},
	},
} );
