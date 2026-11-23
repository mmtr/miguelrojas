/**
 * "Photo details" panel in the post sidebar: edit the location and see the camera
 * data read from the featured image.
 */
( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel, store: editorStore } = wp.editor;
	const { TextControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { __ } = wp.i18n;
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
		const { postType, location, mediaId, imageMeta } = useSelect( ( select ) => {
			const editor = select( editorStore );
			const id = editor.getEditedPostAttribute( 'featured_media' );
			const media = id ? select( 'core' ).getMedia( id ) : null;
			return {
				postType: editor.getCurrentPostType(),
				location: ( editor.getEditedPostAttribute( 'meta' ) || {} ).photos_location || '',
				mediaId: id,
				imageMeta: media?.media_details?.image_meta,
			};
		}, [] );
		const { editPost } = useDispatch( editorStore );

		if ( postType !== 'post' ) {
			return null;
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
			el( TextControl, {
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				label: __( 'Location', 'photos-core' ),
				help: __( 'Shown with the photo, for example "Lisbon, Portugal".', 'photos-core' ),
				value: location,
				onChange: ( value ) => editPost( { meta: { photos_location: value } } ),
			} ),
			el( 'h3', { style: { fontSize: '11px', textTransform: 'uppercase', margin: '20px 0 8px' } }, __( 'From the photo file', 'photos-core' ) ),
			cameraInfo
		);
	}

	registerPlugin( 'photos-details', { render: PhotoDetailsPanel } );
} )( window.wp );
