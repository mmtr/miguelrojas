( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { __ } = wp.i18n;
	const el = wp.element.createElement;

	registerBlockType( 'photos/photo-viewer', {
		edit() {
			return el(
				'div',
				useBlockProps( { className: 'photos-block-placeholder' } ),
				el( 'strong', null, __( 'Photo viewer', 'photos-core' ) ),
				' · ',
				__( 'Opens grid photos full-screen with their details. Hidden until a photo is clicked.', 'photos-core' )
			);
		},
	} );
} )( window.wp );
