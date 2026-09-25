(function (plugins, editor, element, components, blockEditor, data, i18n) {
    'use strict';

    var el = element.createElement;
    var PanelRow = components.PanelRow;
    var Button = components.Button;

    var PluginDocumentSettingPanel = editor.PluginDocumentSettingPanel;
    var MediaUpload = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;

    var useSelect = data.useSelect;
    var useDispatch = data.useDispatch;
    var __ = i18n.__;

    var META_KEY = 'mimosa_feature_title_image_id';

    function FeatureTitlePanel() {
        var meta = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);

        var editPost = useDispatch('core/editor').editPost;

        var imageId = parseInt(meta[META_KEY], 10) || 0;

        var imageUrl = useSelect(function (select) {
            if (!imageId) return '';

            var media = select('core').getMedia(imageId);

            return media && media.source_url ? media.source_url : '';
        }, [imageId]);

        var onSelect = function (media) {
            var update = {};
            update[META_KEY] = media.id;
            editPost({ meta: update });
        };

        var onRemove = function () {
            var update = {};
            update[META_KEY] = 0;
            editPost({ meta: update });
        };

        return el(PluginDocumentSettingPanel, {
                name: 'brilliance-feature-title',
                title: __('特色标题图', 'mimosa'),
                icon: 'format-image',
                className: 'brilliance-feature-title-panel'
            },

            el(MediaUploadCheck, {
                    fallback: el(
                        'p',
                        {},
                        __('当前用户没有媒体库权限', 'mimosa')
                    )
                },

                el(MediaUpload, {
                    onSelect: onSelect,
                    allowedTypes: ['image'],

                    render: function (picker) {
                        return el(
                            'div',
                            {},

                            (imageId && imageUrl)
                                ? el(
                                    'div',
                                    {
                                        className:
                                            'brilliance-feature-title-panel__preview'
                                    },
                                    el('img', {
                                        src: imageUrl,
                                        alt: __('特色标题预览', 'mimosa')
                                    })
                                )
                                : null,

                            el(
                                Button,
                                {
                                    isPrimary: !imageId,
                                    isSecondary: !!imageId,
                                    onClick: picker.open,
                                    style:
                                        (imageId && imageUrl)
                                            ? { marginTop: '8px' }
                                            : null
                                },
                                imageId
                                    ? __('更换图片', 'mimosa')
                                    : __('上传特色标题图', 'mimosa')
                            )
                        );
                    }
                })
            ),

            imageId
                ? el(
                    PanelRow,
                    {},
                    el(
                        Button,
                        {
                            isSmall: true,
                            isDestructive: true,
                            onClick: onRemove
                        },
                        __('移除特色标题图', 'mimosa')
                    )
                )
                : null,

            el(
                'p',
                {
                    className:
                        'brilliance-feature-title-panel__help'
                },
                __(
                    '设置后，详情页将用该图片替换文字标题。建议使用横版艺术字图片。',
                    'mimosa'
                )
            )
        );
    }

    function renderOnlyForPostPage() {
        var postType = useSelect(function (select) {
            return select('core/editor').getCurrentPostType();
        }, []);

        if (postType !== 'post' && postType !== 'page') {
            return null;
        }

        return el(FeatureTitlePanel);
    }

    plugins.registerPlugin('brilliance-feature-title', {
        render: renderOnlyForPostPage
    });

})(
    window.wp.plugins,
    window.wp.editor,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.data,
    window.wp.i18n
);