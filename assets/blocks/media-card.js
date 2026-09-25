const { registerBlockType } = wp.blocks;
const { TextControl } = wp.components;

registerBlockType('apex/media-card', {
    title: 'ACGN 条目预览',
    icon: 'format-image',
    category: 'widgets',

    attributes: {
        mediaId: { type: 'number' },
    },

    edit({ attributes, setAttributes }) {
        return wp.element.createElement(TextControl, {
            label: '输入条目 ID',
            type: 'number',
            value: attributes.mediaId || '',
            onChange: (val) => setAttributes({ mediaId: parseInt(val || 0) })
        });
    },

    save() {
        return null; // 动态渲染
    },
});