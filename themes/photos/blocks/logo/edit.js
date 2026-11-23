( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const ServerSideRender = wp.serverSideRender;
	const el = wp.element.createElement;

	registerBlockType( 'photos/logo', {
		edit() {
			// Same markup as the site, so the editor shows the real logo.
			return el( 'div', useBlockProps(), el( ServerSideRender, { block: 'photos/logo' } ) );
		},
	} );
} )( window.wp );
