(function (blocks, element, blockEditor, components, richText, serverSideRender) {
    'use strict';

    var el = element.createElement;
    var RichText = blockEditor.RichText;
    var InspectorControls = blockEditor.InspectorControls;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var PanelBody = components.PanelBody;
    var Button = components.Button;

    function textEdit(props, className, placeholder) {
        return el(RichText, {
            tagName: 'div',
            className: className,
            placeholder: placeholder,
            value: props.attributes.content,
            onChange: function (content) { props.setAttributes({ content: content }); }
        });
    }

    function registerContentBlock(name, title, icon, className, placeholder) {
        blocks.registerBlockType('brilliance/' + name, {
            title: title,
            icon: icon,
            category: 'brilliance',
            keywords: ['brilliance', title],
            attributes: { content: { type: 'string', default: '' } },
            edit: function (props) { return textEdit(props, className, placeholder); },
            save: function (props) {
                return el(RichText.Content, { tagName: 'div', className: className, value: props.attributes.content });
            }
        });
    }

    blocks.registerBlockType('brilliance/checkbox', {
        title: '待办事项', icon: 'yes-alt', category: 'brilliance',
        attributes: { content: { type: 'string', default: '' }, checked: { type: 'boolean', default: false } },
        edit: function (props) {
            return el('div', { className: 'shortcode-todo' },
                el('input', { type: 'checkbox', checked: props.attributes.checked, onChange: function (event) { props.setAttributes({ checked: event.target.checked }); } }),
                el(RichText, { tagName: 'label', value: props.attributes.content, placeholder: '待办内容', onChange: function (content) { props.setAttributes({ content: content }); } })
            );
        },
        save: function (props) {
            return el('div', { className: 'shortcode-todo' },
                el('input', { type: 'checkbox', checked: props.attributes.checked || null, readOnly: true }),
                el(RichText.Content, { tagName: 'label', value: props.attributes.content })
            );
        }
    });

    blocks.registerBlockType('brilliance/alert', {
        title: '提示区块', icon: 'info-outline', category: 'brilliance',
        attributes: { content: { type: 'string', default: '' }, color: { type: 'string', default: 'primary' } },
        edit: function (props) {
            return el('div', { className: 'alert alert-' + props.attributes.color },
                textEdit(props, 'alert-inner--text', '提示内容'),
                el(InspectorControls, {}, el(PanelBody, { title: '提示设置' }, el(TextControl, { label: '颜色（primary / success / danger / warning / info）', value: props.attributes.color, onChange: function (color) { props.setAttributes({ color: color || 'primary' }); } })))
            );
        },
        save: function (props) { return el(RichText.Content, { tagName: 'div', className: 'alert alert-' + props.attributes.color, value: props.attributes.content }); }
    });

    blocks.registerBlockType('brilliance/admonition', {
        title: '警告区块', icon: 'warning', category: 'brilliance',
        attributes: { title: { type: 'string', default: '注意' }, content: { type: 'string', default: '' }, color: { type: 'string', default: 'primary' } },
        edit: function (props) {
            return el('div', { className: 'admonition admonition-' + props.attributes.color },
                el(RichText, { tagName: 'div', className: 'admonition-title', value: props.attributes.title, placeholder: '标题', onChange: function (title) { props.setAttributes({ title: title }); } }),
                textEdit(props, 'admonition-body', '警告内容'),
                el(InspectorControls, {}, el(PanelBody, { title: '警告设置' }, el(TextControl, { label: '颜色（primary / success / danger / warning / info）', value: props.attributes.color, onChange: function (color) { props.setAttributes({ color: color || 'primary' }); } })))
            );
        },
        save: function (props) {
            return el('div', { className: 'admonition admonition-' + props.attributes.color },
                el(RichText.Content, { tagName: 'div', className: 'admonition-title', value: props.attributes.title }),
                el(RichText.Content, { tagName: 'div', className: 'admonition-body', value: props.attributes.content })
            );
        }
    });

    blocks.registerBlockType('brilliance/collapse', {
        title: '折叠区块', icon: 'arrow-down-alt2', category: 'brilliance',
        attributes: { title: { type: 'string', default: '' }, content: { type: 'string', default: '' }, collapsed: { type: 'boolean', default: true } },
        edit: function (props) {
            return el('div', { className: 'collapse-block' },
                el(RichText, { tagName: 'div', className: 'collapse-block-title', value: props.attributes.title, placeholder: '折叠标题', onChange: function (title) { props.setAttributes({ title: title }); } }),
                textEdit(props, 'collapse-block-body', '折叠内容'),
                el(InspectorControls, {}, el(PanelBody, { title: '折叠设置' }, el(ToggleControl, { label: '默认折叠', checked: props.attributes.collapsed, onChange: function (collapsed) { props.setAttributes({ collapsed: collapsed }); } })))
            );
        },
        save: function (props) {
            return el('div', { className: 'collapse-block' + (props.attributes.collapsed ? ' collapsed' : '') },
                el(RichText.Content, { tagName: 'div', className: 'collapse-block-title', value: props.attributes.title }),
                el(RichText.Content, { tagName: 'div', className: 'collapse-block-body', value: props.attributes.content, style: props.attributes.collapsed ? { display: 'none' } : undefined })
            );
        }
    });

    blocks.registerBlockType('brilliance/timeline', {
        title: '时间轴', icon: 'calendar-alt', category: 'brilliance',
        attributes: { items: { type: 'array', default: [{ id: 1, time: '', content: '' }] } },
        edit: function (props) {
            var items = props.attributes.items || [];
            function updateItem(id, field, value) {
                props.setAttributes({ items: items.map(function (item) {
                    return item.id === id ? Object.assign({}, item, (function () { var update = {}; update[field] = value; return update; })()) : item;
                }) });
            }
            function addItem() {
                props.setAttributes({ items: items.concat([{ id: Date.now(), time: '', content: '' }]) });
            }
            function removeItem(id) {
                props.setAttributes({ items: items.filter(function (item) { return item.id !== id; }) });
            }
            return el('div', { className: 'brilliance-timeline' },
                items.map(function (item, index) {
                    return el('div', { className: 'brilliance-timeline-item', key: item.id },
                        el(RichText, { tagName: 'div', className: 'brilliance-timeline-title', value: item.time, placeholder: '时间 / 标题', onChange: function (time) { updateItem(item.id, 'time', time); } }),
                        el(RichText, { tagName: 'div', className: 'brilliance-timeline-content', value: item.content, placeholder: '事件内容', onChange: function (content) { updateItem(item.id, 'content', content); } }),
                        items.length > 1 ? el(Button, { isDestructive: true, onClick: function () { removeItem(item.id); } }, '删除此条目') : null
                    );
                }),
                el(Button, { variant: 'primary', onClick: addItem }, '添加时间轴条目')
            );
        },
        save: function (props) {
            return el('div', { className: 'brilliance-timeline' }, (props.attributes.items || []).map(function (item) {
                return el('div', { className: 'brilliance-timeline-item', key: item.id },
                    el(RichText.Content, { tagName: 'div', className: 'brilliance-timeline-title', value: item.time }),
                    el(RichText.Content, { tagName: 'div', className: 'brilliance-timeline-content', value: item.content })
                );
            }));
        }
    });

    blocks.registerBlockType('brilliance/acgn', {
        title: 'ACGN 作品卡片', icon: 'format-image', category: 'brilliance',
        attributes: { id: { type: 'number', default: 0 } },
        edit: function (props) {
            return el('div', { className: 'brilliance-acgn-card' }, el(TextControl, { label: 'ACGN 作品 ID', type: 'number', value: props.attributes.id || '', onChange: function (id) { props.setAttributes({ id: parseInt(id, 10) || 0 }); } }));
        },
        save: function () { return null; }
    });

    blocks.registerBlockType('brilliance/activity-heatmap', {
        title: '站点活跃热力图', icon: 'chart-bar', category: 'brilliance',
        keywords: ['brilliance', 'heatmap', '热力图', '活跃度'],
        edit: function () {
            return el('div', { className: 'brilliance-block-placeholder' }, el('span', { className: 'brilliance-block-placeholder__title' }, '站点活跃热力图'), el('span', { className: 'brilliance-block-placeholder__desc' }, '前端动态渲染，按天展示近一年文章 / 说说 / 评论活跃度'));
        },
        save: function () { return null; }
    });

    blocks.registerBlockType('brilliance/post-reference', {
        title: '文章引用卡片', icon: 'admin-links', category: 'brilliance',
        keywords: ['brilliance', 'post', 'reference', '引用', '文章'],
        attributes: { postId: { type: 'number', default: 0 } },
        edit: function (props) {
            return el('div', { className: 'brilliance-post-reference' },
                el(TextControl, { type: 'number', label: '文章 ID', value: props.attributes.postId || '', onChange: function (v) { props.setAttributes({ postId: parseInt(v, 10) || 0 }); } }),
                props.attributes.postId
                    ? el(serverSideRender, { block: 'brilliance/post-reference', attributes: props.attributes })
                    : el('p', {}, '输入文章 ID 后实时渲染引用卡片')
            );
        },
        save: function () { return null; }
    });

    blocks.registerBlockType('brilliance/status-monitor', {
        title: '服务器状态监测', icon: 'dashboard', category: 'brilliance',
        keywords: ['brilliance', 'status', 'monitor', '服务器', '状态'],
        edit: function () {
            return el('div', { className: 'brilliance-block-placeholder' }, el('span', { className: 'brilliance-block-placeholder__title' }, '服务器状态监测'), el('span', { className: 'brilliance-block-placeholder__desc' }, '前端动态渲染，展示 CPU / 内存 / 硬盘 / 网络 / 延迟 / SSL'));
        },
        save: function () { return null; }
    });

    blocks.registerBlockType('brilliance/map', {
        title: '足迹小地图', icon: 'location-alt', category: 'brilliance',
        keywords: ['brilliance', 'map', '足迹', '地图', '地点'],
        attributes: {
            title: { type: 'string', default: '' },
            mapUrl: { type: 'string', default: '' }
        },
        edit: function (props) {
            return el('div', { className: 'brilliance-block-placeholder' },
                el('span', { className: 'brilliance-block-placeholder__title' }, '足迹小地图'),
                el('span', { className: 'brilliance-block-placeholder__desc' }, '前端动态渲染，静态展示所有足迹标点，点击跳转大地图'),
                el(InspectorControls, {}, el(PanelBody, { title: '足迹小地图设置' },
                    el(TextControl, { label: '标题（留空不显示）', value: props.attributes.title || '', onChange: function (v) { props.setAttributes({ title: v }); } }),
                    el(TextControl, { label: '大地图 URL（留空使用地图设置）', value: props.attributes.mapUrl || '', onChange: function (v) { props.setAttributes({ mapUrl: v }); } })
                ))
            );
        },
        save: function () { return null; }
    });

    function registerHiddenFormat(name, title, className, icon) {
        richText.registerFormatType('brilliance/' + name, {
            title: title,
            tagName: 'span',
            className: className,
            edit: function (props) {
                return el(blockEditor.RichTextToolbarButton, { icon: icon, title: title, onClick: function () { props.onChange(richText.toggleFormat(props.value, { type: 'brilliance/' + name })); }, isActive: props.isActive });
            }
        });
    }

    registerHiddenFormat('spoiler', '剧透文本', 'brilliance-spoiler', 'visibility');
    registerHiddenFormat('black', '黑幕文本', 'brilliance-black', 'hidden');
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.richText, window.wp.serverSideRender);
