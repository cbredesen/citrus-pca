import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Placeholder, PanelBody, ToggleControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { showAllAnniversaries } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Anniversaries Settings',
						'flc-anniversaries'
					) }
				>
					<ToggleControl
						label={ __(
							'Show all anniversaries',
							'flc-anniversaries'
						) }
						help={
							showAllAnniversaries
								? __(
										'Showing every anniversary this month.',
										'flc-anniversaries'
								  )
								: __(
										'Showing only milestone anniversaries (5, 10, 15…).',
										'flc-anniversaries'
								  )
						}
						checked={ !! showAllAnniversaries }
						onChange={ ( value ) =>
							setAttributes( { showAllAnniversaries: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<Placeholder
					icon="groups"
					label={ __( 'Anniversaries', 'flc-anniversaries' ) }
					instructions={
						showAllAnniversaries
							? __(
									'Displays every anniversary for a given month with prev/next navigation.',
									'flc-anniversaries'
							  )
							: __(
									'Displays milestone anniversaries for a given month with prev/next navigation.',
									'flc-anniversaries'
							  )
					}
				/>
			</div>
		</>
	);
}
