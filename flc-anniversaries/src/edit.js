import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

export default function Edit() {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<Placeholder
				icon="groups"
				label="FLC Anniversaries"
				instructions="Displays milestone anniversaries for a given month with prev/next navigation."
			/>
		</div>
	);
}
