(function (blocks, blockEditor, element, i18n) {
	'use strict';

	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;
	const __ = i18n.__;

	blocks.registerBlockType('tumtook/comparison-table', {
		edit: function () {
			const blockProps = useBlockProps({ className: 'ttct-block-preview' });

			return el(
				'div',
				blockProps,
				el('div', { className: 'ttct-block-preview__box' },
					el('strong', {}, __('Tumtook Comparison Table', 'tumtook-dynamic-comparison-table')),
					el('p', {}, __('The table is rendered dynamically from this Page meta on the frontend.', 'tumtook-dynamic-comparison-table'))
				)
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n);
