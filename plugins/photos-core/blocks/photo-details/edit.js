( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { __ } = wp.i18n;
	const el = wp.element.createElement;

	const EXAMPLE = [
		[ __( 'Location', 'photos-core' ), 'Lisbon, Portugal' ],
		[ __( 'Taken', 'photos-core' ), 'May 14, 2025' ],
		[ __( 'Camera', 'photos-core' ), 'FUJIFILM X-T5' ],
		[ __( 'Focal length', 'photos-core' ), '23 mm' ],
		[ __( 'Aperture', 'photos-core' ), 'ƒ/8' ],
		[ __( 'Shutter speed', 'photos-core' ), '1/125 s' ],
		[ __( 'ISO', 'photos-core' ), '160' ],
	];

	registerBlockType( 'photos/photo-details', {
		edit() {
			return el(
				'div',
				useBlockProps( { className: 'photos-block-placeholder' } ),
				el( 'p', { className: 'photos-block-placeholder__note' }, __( 'Example values. Each photo shows its own details.', 'photos-core' ) ),
				el(
					'dl',
					{ className: 'photos-details' },
					EXAMPLE.map( ( [ label, value ] ) =>
						el( 'div', { key: label, className: 'photos-details__item' }, el( 'dt', null, label ), el( 'dd', null, value ) )
					)
				)
			);
		},
	} );
} )( window.wp );
