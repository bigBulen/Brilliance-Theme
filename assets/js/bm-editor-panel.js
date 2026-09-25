/**
 * 古腾堡「关联地点」文档设置面板
 *
 * - 可搜索多选（REST /brilliance-map/v1/locations）
 * - 选中状态写入 post meta（brilliance_map_location_ids），随文章保存
 *   → PHP 端 save_post 时原子化同步关系表（幂等、防重复）
 * - 已保存的选择通过 meta → REST include 回显名称
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.plugins || !wp.editPost || !wp.editPost.PluginDocumentSettingPanel) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var apiFetch = wp.apiFetch;
    var __ = wp.i18n.__;
    var TextControl = wp.components.TextControl;
    var Spinner = wp.components.Spinner;
    var CheckboxControl = wp.components.CheckboxControl;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;

    var EDITOR = window.BM_EDITOR || {};
    var META_KEY = EDITOR.metaKey || 'brilliance_map_location_ids';
    var REST_BASE = (EDITOR.restBase || '/wp-json/brilliance-map/v1').replace(/\/$/, '');
    var ALLOWED_TYPES = EDITOR.postTypes || ['post', 'shuoshuo'];

    function LocationPanel() {
        var metaIds = useSelect(function (select) {
            var meta = select('core/editor').getEditedPostAttribute('meta') || {};
            return Array.isArray(meta[META_KEY]) ? meta[META_KEY].map(Number) : [];
        }, []);
        var editPost = useDispatch('core/editor').editPost;

        var labelsState = useState({});
        var labels = labelsState[0];
        var setLabels = labelsState[1];

        var termState = useState('');
        var term = termState[0];
        var setTerm = termState[1];

        var resultsState = useState(null);
        var results = resultsState[0];
        var setResults = resultsState[1];

        var busyState = useState(false);
        var busy = busyState[0];
        var setBusy = busyState[1];

        var timerRef = useRef(null);

        function remember(items) {
            if (!items || !items.length) return;
            var next = {};
            items.forEach(function (it) { next[it.id] = it; });
            setLabels(function (prev) { return Object.assign({}, prev, next); });
        }

        /* 已选地点回显名称 */
        useEffect(function () {
            var missing = metaIds.filter(function (id) { return !labels[id]; });
            if (!missing.length) return;
            apiFetch({ url: REST_BASE + '/locations?include=' + missing.join(',') + '&per_page=50' })
                .then(remember)
                .catch(function () { /* 忽略 */ });
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [metaIds.join(',')]);

        /* 防抖搜索 */
        useEffect(function () {
            if (timerRef.current) clearTimeout(timerRef.current);
            if (!term) {
                setResults(null);
                setBusy(false);
                return;
            }
            setBusy(true);
            timerRef.current = setTimeout(function () {
                apiFetch({ url: REST_BASE + '/locations?search=' + encodeURIComponent(term) + '&per_page=20' })
                    .then(function (items) {
                        remember(items);
                        setResults(items);
                        setBusy(false);
                    })
                    .catch(function () {
                        setResults([]);
                        setBusy(false);
                    });
            }, 300);
            return function () {
                if (timerRef.current) clearTimeout(timerRef.current);
            };
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [term]);

        function commit(nextIds) {
            var currentMeta = wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
            var merged = Object.assign({}, currentMeta);
            merged[META_KEY] = nextIds.map(Number);
            editPost({ meta: merged });
        }

        function toggle(id) {
            id = Number(id);
            var next = metaIds.indexOf(id) !== -1
                ? metaIds.filter(function (x) { return x !== id; })
                : metaIds.concat([id]);
            commit(next);
        }

        return el(PluginDocumentSettingPanel, {
            name: 'brilliance-map-locations',
            title: __('关联地点', 'brilliance-map'),
            icon: 'location-alt',
            className: 'bm-editor-panel'
        },
            el(Fragment, null,
                /* 已选 chips */
                el('div', { className: 'bm-chips' },
                    metaIds.length === 0
                        ? el('span', { className: 'bm-chips__empty' }, __('尚未关联地点', 'brilliance-map'))
                        : metaIds.map(function (id) {
                            var it = labels[id];
                            var text = it
                                ? it.name + (it.city ? ' · ' + it.city : '')
                                : '#' + id + ' ' + __('(加载中…)', 'brilliance-map');
                            return el('span', { className: 'bm-chip', key: id },
                                el('span', { className: 'bm-chip__text' }, text),
                                el('button', {
                                    type: 'button',
                                    className: 'bm-chip__x',
                                    onClick: function () { toggle(id); },
                                    'aria-label': __('移除', 'brilliance-map')
                                }, '×')
                            );
                        })
                ),
                /* 搜索框 */
                el(TextControl, {
                    value: term,
                    onChange: setTerm,
                    placeholder: __('搜索地点名称 / 城市 / 国家…', 'brilliance-map'),
                    className: 'bm-search'
                }),
                busy ? el('div', { className: 'bm-searching' }, el(Spinner)) : null,
                /* 搜索结果 */
                results
                    ? el('div', { className: 'bm-results' },
                        results.length === 0
                            ? el('p', { className: 'bm-results__empty' }, __('无匹配地点（可在后台「足迹地图」中新增）', 'brilliance-map'))
                            : results.map(function (it) {
                                var on = metaIds.indexOf(Number(it.id)) !== -1;
                                return el('label', { className: 'bm-result' + (on ? ' is-on' : ''), key: it.id },
                                    el(CheckboxControl, {
                                        checked: on,
                                        onChange: function () { toggle(it.id); }
                                    }),
                                    el('span', { className: 'bm-result__text' },
                                        el('strong', null, it.name),
                                        (it.city || it.country_name)
                                            ? el('small', null, ' · ' + [it.city, it.country_name].filter(Boolean).join(' · '))
                                            : null
                                    )
                                );
                            })
                    )
                    : null,
                el('p', { className: 'bm-panel-help' },
                    __('关联随文章一起保存。新地点请前往后台「足迹地图 → 地点管理」创建。', 'brilliance-map'))
            )
        );
    }

    wp.plugins.registerPlugin('brilliance-map-locations', {
        render: function () {
            var postType = useSelect(function (select) {
                return select('core/editor').getCurrentPostType();
            }, []);
            if (!postType || ALLOWED_TYPES.indexOf(postType) === -1) {
                return null;
            }
            return el(LocationPanel);
        },
        icon: 'location-alt'
    });
})(window.wp);
