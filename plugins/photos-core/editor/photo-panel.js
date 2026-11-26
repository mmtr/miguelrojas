/**
 * "Photo details" / "Album details" panel in the editor sidebar: edit the location and
 * see the camera data read from the featured image (photos) or the photo count (albums).
 * A photo without a location of its own shows its image's (set in the Media Library), so
 * the field shows that one as its placeholder.
 */
( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel, store: editorStore } = wp.editor;
	const { TextControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { __, _n, sprintf } = wp.i18n;
	const el = wp.element.createElement;

	const round1 = ( n ) => Math.round( n * 10 ) / 10;

	function summarize( meta ) {
		const camera =
			meta.make && ! ( meta.camera || '' ).toLowerCase().startsWith( meta.make.toLowerCase() )
				? `${ meta.make } ${ meta.camera || '' }`.trim()
				: meta.camera || meta.make || '';
		const settings = [
			+meta.focal_length && `${ round1( meta.focal_length ) } mm`,
			+meta.aperture && `ƒ/${ round1( meta.aperture ) }`,
			+meta.shutter_speed &&
				( meta.shutter_speed < 1 ? `1/${ Math.round( 1 / meta.shutter_speed ) } s` : `${ round1( meta.shutter_speed ) } s` ),
			+meta.iso && `ISO ${ meta.iso }`,
		].filter( Boolean );
		return [ camera, meta.lens, settings.join( ' · ' ) ].filter( Boolean );
	}

	function PhotoDetailsPanel() {
		const { postType, location, mediaId, imageMeta, imageLocation, imageCount } = useSelect( ( select ) => {
			const editor = select( editorStore );
			const blockEditor = select( 'core/block-editor' );
			const id = editor.getEditedPostAttribute( 'featured_media' );
			const media = id ? select( 'core' ).getMedia( id ) : null;
			return {
				postType: editor.getCurrentPostType(),
				location: ( editor.getEditedPostAttribute( 'meta' ) || {} ).photos_location || '',
				mediaId: id,
				imageMeta: media?.media_details?.image_meta,
				imageLocation: ( media?.meta?.photos_location || '' ).trim(),
				imageCount: blockEditor
					.getClientIdsWithDescendants()
					.filter( ( clientId ) => blockEditor.getBlockName( clientId ) === 'core/image' && blockEditor.getBlockAttributes( clientId )?.id ).length,
			};
		}, [] );
		const { editPost } = useDispatch( editorStore );

		if ( postType !== 'post' && postType !== 'album' ) {
			return null;
		}

		let help = __( 'Shown with the photo, for example "Lisbon, Portugal".', 'photos-core' );
		if ( postType === 'album' ) {
			help = __( 'Shown with every photo in the album, unless a photo has its own location in the Media Library.', 'photos-core' );
		} else if ( imageLocation && ! location.trim() ) {
			help = __( 'Empty, so the photo shows its image\'s location from the Media Library.', 'photos-core' );
		} else if ( imageLocation && location.trim() !== imageLocation ) {
			/* translators: %s: location set on the image in the Media Library. */
			help = sprintf( __( 'Shown instead of the image\'s location from the Media Library (%s).', 'photos-core' ), imageLocation );
		}

		const locationField = el( TextControl, {
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
			label: __( 'Location', 'photos-core' ),
			help,
			placeholder: postType === 'post' ? imageLocation : undefined,
			value: location,
			onChange: ( value ) => editPost( { meta: { photos_location: value } } ),
		} );

		if ( postType === 'album' ) {
			return el(
				PluginDocumentSettingPanel,
				{ name: 'photos-details', title: __( 'Album details', 'photos-core' ) },
				locationField,
				el(
					'p',
					{ style: { marginTop: '16px' } },
					/* translators: %d: number of photos in the album. */
					sprintf( _n( '%d photo in this album.', '%d photos in this album.', imageCount, 'photos-core' ), imageCount ),
					' ',
					__( 'Add photos to the gallery; camera data is read from each file. Without a featured image, the first photo is the cover.', 'photos-core' )
				)
			);
		}

		let cameraInfo;
		if ( ! mediaId ) {
			cameraInfo = el( 'p', null, __( 'Set a featured image to read its camera settings.', 'photos-core' ) );
		} else if ( imageMeta === undefined ) {
			cameraInfo = null; // Still loading.
		} else {
			const lines = summarize( imageMeta || {} );
			cameraInfo = lines.length
				? el( 'p', null, ...lines.flatMap( ( line, i ) => ( i ? [ el( 'br', { key: i } ), line ] : [ line ] ) ) )
				: el( 'p', null, __( 'No camera data found in the featured image.', 'photos-core' ) );
		}

		return el(
			PluginDocumentSettingPanel,
			{ name: 'photos-details', title: __( 'Photo details', 'photos-core' ) },
			locationField,
			el( 'h3', { style: { fontSize: '11px', textTransform: 'uppercase', margin: '20px 0 8px' } }, __( 'From the photo file', 'photos-core' ) ),
			cameraInfo
		);
	}

	registerPlugin( 'photos-details', { render: PhotoDetailsPanel } );
} )( window.wp );
