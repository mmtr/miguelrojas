( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { __ } = wp.i18n;
	const el = wp.element.createElement;

	registerBlockType( 'photos/photo-page', {
		edit() {
			return el(
				'div',
				useBlockProps( { className: 'photos-block-placeholder' } ),
				el( 'strong', null, __( 'Photo page', 'photos-core' ) ),
				' · ',
				__( 'The photo full-screen on a dark background, with its details beside it, like the photo viewer.', 'photos-core' )
			);
		},
	} );
} )( window.wp );
