( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { __ } = wp.i18n;
	const el = wp.element.createElement;

	registerBlockType( 'photos/album-summary', {
		edit() {
			return el( 'p', useBlockProps(), __( '12 photos · Location · Month Year (example)', 'photos-core' ) );
		},
	} );
} )( window.wp );
