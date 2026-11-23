( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { __ } = wp.i18n;
	const el = wp.element.createElement;

	registerBlockType( 'photos/entry-label', {
		edit( { context } ) {
			const props = useBlockProps( { className: 'photos-block-placeholder' } );
			return context.postType === 'album'
				? el( 'div', props, __( 'Album label (title and photo count)', 'photos-core' ) )
				: el( 'div', props, __( 'Album label: shown on album covers only', 'photos-core' ) );
		},
	} );
} )( window.wp );
