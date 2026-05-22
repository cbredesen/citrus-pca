import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RadioControl,
	SelectControl,
	ToggleControl,
	Spinner,
	Placeholder,
	Notice,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const { mode, selectedFile, showRawLink } = attributes;
	const [ files, setFiles ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		setLoading( true );
		apiFetch( { path: '/flc-file-include/v1/files' } )
			.then( ( data ) => {
				setFiles( data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Could not load file list.', 'flc-file-include' ) );
				setLoading( false );
			} );
	}, [] );

	const blockProps = useBlockProps( {
		className: 'flc-file-include-editor-preview',
	} );

	const fileOptions = [
		{ label: __( '— Choose a file —', 'flc-file-include' ), value: '' },
		...files.map( ( f ) => ( { label: f.name, value: f.name } ) ),
	];

	function getPlaceholderLabel() {
		if ( mode === 'latest' ) {
			if ( loading ) return __( 'Loading files…', 'flc-file-include' );
			if ( files.length > 0 ) {
				return sprintf(
					/* translators: %s: filename */
					__( 'Latest: %s', 'flc-file-include' ),
					files[ 0 ].name
				);
			}
			return __( 'No HTML files found in configured directory.', 'flc-file-include' );
		}
		if ( selectedFile ) {
			return sprintf(
				/* translators: %s: filename */
				__( 'File: %s', 'flc-file-include' ),
				selectedFile
			);
		}
		return __( 'Select a file in the block settings panel.', 'flc-file-include' );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'File Settings', 'flc-file-include' ) }>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<RadioControl
						label={ __( 'File Selection Mode', 'flc-file-include' ) }
						selected={ mode }
						options={ [
							{
								label: __( 'Latest file', 'flc-file-include' ),
								value: 'latest',
							},
							{
								label: __( 'Specific file', 'flc-file-include' ),
								value: 'specific',
							},
						] }
						onChange={ ( value ) => setAttributes( { mode: value } ) }
					/>
					{ mode === 'specific' && (
						loading ? (
							<Spinner />
						) : (
							<SelectControl
								label={ __( 'Select File', 'flc-file-include' ) }
								value={ selectedFile }
								options={ fileOptions }
								onChange={ ( value ) =>
									setAttributes( { selectedFile: value } )
								}
							/>
						)
					) }
					<ToggleControl
						label={ __( 'Show link to raw file', 'flc-file-include' ) }
						help={ __( 'Displays a link above the content to open the original file directly.', 'flc-file-include' ) }
						checked={ showRawLink }
						onChange={ ( value ) => setAttributes( { showRawLink: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Placeholder
					icon="media-code"
					label={ __( 'File Include', 'flc-file-include' ) }
					instructions={ getPlaceholderLabel() }
				/>
			</div>
		</>
	);
}
