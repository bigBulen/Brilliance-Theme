/**
 * 足迹地图（Leaflet + markercluster）
 *
 * 数据由 PHP 经 window.BM_MAP_DATA / BM_MAP_CONFIG 注入：
 * - 标点按关联文章数分颜色梯度（0/1/2-3/4+，visit_count 参与取大）
 * - 弹窗文章卡片经 REST 懒加载；设置 customLink 的标点点击直接跳转
 * - 工具栏：分类筛选 + 只看有文章；统计面板随筛选实时去重计算
 * - ?location_id=N：定位、缩放并高亮该标点（自动展开聚合）
 */
(function () {
    'use strict';

    var root = document.getElementById('bm-root');
    var mapEl = document.getElementById('bm-map');
    if (!root || !mapEl) return;

    var DATA = Array.isArray(window.BM_MAP_DATA) ? window.BM_MAP_DATA : [];
    var CFG = window.BM_MAP_CONFIG || {};
    var STR = CFG.strings || {};
    var markersEnabled = CFG.showMarkers !== false;
    var showTiles = CFG.showTiles !== false && !!CFG.tileUrl;

    /* ══ 地图基础 ══ */
    var map = L.map(mapEl, {
        center: CFG.center || [35.0, 105.0],
        zoom: CFG.zoom || 4,
        minZoom: 2,
        // 无底图时限制放大级别，避免放大后只剩空白
        maxZoom: showTiles ? 18 : 10,
        worldCopyJump: true,
        attributionControl: showTiles
    });

    if (showTiles) {
        L.tileLayer(CFG.tileUrl, {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
    } else {
        mapEl.classList.add('bm-map--no-tiles');
    }

    /** 国家轮廓样式：有底图时淡作背景；无底图时加重填充并投影成「阴影」 */
    function outlineStyle() {
        if (showTiles) {
            return {
                color: 'rgba(120,140,180,0.45)',
                weight: 0.8,
                fillColor: 'rgba(120,140,180,0.10)',
                fillOpacity: 1
            };
        }
        return {
            color: 'rgba(110,125,160,0.75)',
            weight: 1,
            fillColor: 'rgba(120,140,180,0.18)',
            fillOpacity: 1
        };
    }

    /* ══ GeoJSON 国家轮廓（独立 pane：瓦片之上、标点之下，不可交互） ══ */
    if (CFG.showOutlines !== false && CFG.geoJsonUrl) {
        map.createPane('bmOutlines');
        var pane = map.getPane('bmOutlines');
        pane.style.zIndex = 350;
        pane.style.pointerEvents = 'none';
        if (!showTiles) {
            pane.style.filter = 'drop-shadow(0 1px 3px rgba(0, 0, 0, 0.18))';
        }
        fetch(CFG.geoJsonUrl)
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                L.geoJSON(geo, {
                    pane: 'bmOutlines',
                    interactive: false,
                    style: outlineStyle
                }).addTo(map);
            })
            .catch(function () { /* 轮廓加载失败不影响标点 */ });
    }

    /* ══ 标点层（聚合可选，默认关闭允许重叠） ══ */
    var clusterEnabled = CFG.enableCluster === true && typeof L.markerClusterGroup === 'function';
    var layerHost = clusterEnabled
        ? L.markerClusterGroup({ chunkedLoading: true, maxClusterRadius: 56, showCoverageOnHover: false })
        : L.layerGroup();
    map.addLayer(layerHost);

    /* 地名标签独立成层，便于按屏幕空间做去重叠控制 */
    var labelLayer = L.layerGroup().addTo(map);

    var markers = {};
    var pairs = [];   // [{ loc, marker }]

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function levelOf(loc) {
        var n = loc.postCount || 0;
        if (loc.visitCount && loc.visitCount > n) n = loc.visitCount;
        return n;
    }

    function tierOf(loc) {
        var n = levelOf(loc);
        if (n <= 0) return 't0';
        if (n === 1) return 't1';
        if (n <= 3) return 't2';
        return 't3';
    }

    function iconFor(loc) {
        var html = '<span class="bm-pin bm-pin--' + tierOf(loc) + '" title="' + escapeHtml(loc.name) + '">';
        if (CFG.showBadge) {
            var n = loc.postCount || 0;
            if (loc.visitCount && loc.visitCount > n) n = loc.visitCount;
            if (n > 0) html += '<b class="bm-pin__count">' + n + '</b>';
        }
        html += '</span>';
        return L.divIcon({
            className: '',
            html: html,
            iconSize: [20, 20],
            iconAnchor: [10, 10],
            popupAnchor: [0, -11]
        });
    }

    function popupHtml(loc) {
        var meta = [];
        if (loc.city) meta.push(escapeHtml(loc.city));
        if (loc.countryName) meta.push(escapeHtml(loc.countryName));
        var html = '<div class="bm-popup">';
        html += '<div class="bm-popup__title">' + escapeHtml(loc.name) + '</div>';
        if (meta.length || loc.category || loc.visitDate) {
            html += '<div class="bm-popup__meta">' + meta.join(' · ');
            if (loc.category) html += ' <span class="bm-popup__cat">' + escapeHtml(loc.category) + '</span>';
            html += '</div>';
        }
        if (loc.visitDate) {
            html += '<div class="bm-popup__date">' + escapeHtml(loc.visitDate);
            if (loc.visitCount) html += ' · ×' + loc.visitCount;
            html += '</div>';
        }
        if (loc.description) {
            // description 已在服务端 wp_kses_post，此处直接注入
            html += '<div class="bm-popup__desc">' + loc.description + '</div>';
        }
        html += '<div class="bm-popup__posts" data-loaded="0">';
        html += '<span class="bm-popup__loading">' + escapeHtml(STR.loading || '加载中…') + '</span>';
        html += '</div></div>';
        return html;
    }

    function loadPosts(loc, box) {
        if (!box || box.getAttribute('data-loaded') === '1') return;
        box.setAttribute('data-loaded', '1');
        var base = (CFG.restUrl || '').replace(/\/$/, '');
        fetch(base + '/location/' + loc.id + '/posts')
            .then(function (r) { return r.json(); })
            .then(function (posts) {
                if (!posts || !posts.length) {
                    box.textContent = STR.noPosts || '暂无关联文章';
                    box.classList.add('is-empty');
                    return;
                }
                var html = '';
                posts.forEach(function (p) {
                    html += '<a class="bm-post-card" href="' + escapeHtml(p.url) + '">';
                    if (p.thumb) {
                        html += '<img class="bm-post-card__thumb" src="' + escapeHtml(p.thumb) + '" alt="" loading="lazy">';
                    }
                    html += '<span class="bm-post-card__body">';
                    html += '<span class="bm-post-card__title">' + escapeHtml(p.title) + '</span>';
                    if (p.excerpt) html += '<span class="bm-post-card__excerpt">' + escapeHtml(p.excerpt) + '</span>';
                    html += '<span class="bm-post-card__meta">' + escapeHtml(p.date || '') + (p.type ? ' · ' + escapeHtml(p.type) : '') + '</span>';
                    html += '</span></a>';
                });
                box.innerHTML = html;
            })
            .catch(function () {
                box.textContent = STR.loadFail || '加载失败';
                box.classList.add('is-empty');
            });
    }

    DATA.forEach(function (loc) {
        if (typeof loc.lat !== 'number' || typeof loc.lng !== 'number') return;
        var marker = L.marker([loc.lat, loc.lng], { icon: iconFor(loc), title: loc.name });
        if (loc.customLink) {
            marker.on('click', function () { window.location.href = loc.customLink; });
        } else {
            marker.bindPopup(popupHtml(loc), { maxWidth: 320, minWidth: 240, className: 'bm-popup-wrap' });
            marker.on('popupopen', function (e) {
                var box = e.popup.getElement().querySelector('.bm-popup__posts');
                loadPosts(loc, box);
            });
        }
        markers[loc.id] = marker;
        pairs.push({ loc: loc, marker: marker });
    });

    /* ══ 地名标签（标记点下方，屏幕空间去重叠） ══ */
    var LABEL_FONT = '600 12px -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif';
    var labelMeasure = document.createElement('canvas').getContext('2d');
    labelMeasure.font = LABEL_FONT;
    var labelWidthCache = {};

    function labelWidth(text) {
        if (labelWidthCache[text] == null) {
            labelWidthCache[text] = labelMeasure.measureText(text).width;
        }
        return labelWidthCache[text];
    }

    /* 聚合开启时，标签只给「未被并入气泡」的可见标点 */
    function isLabelEligible(marker) {
        if (!clusterEnabled) return true;
        if (typeof layerHost.getVisibleParent === 'function') {
            return layerHost.getVisibleParent(marker) === marker;
        }
        return true;
    }

    function boxesOverlap(a, b, pad) {
        return !(a.right + pad < b.left || b.right + pad < a.left || a.bottom + pad < b.top || b.bottom + pad < a.top);
    }

    /**
     * 重算当前视野内应显示哪些地名：
     * 按「关联文章数」优先排序，逐个与已接受的标签做屏幕矩形碰撞检测，
     * 重叠的跳过；缩放/平移后标点分散开，自然会有更多标签显示出来。
     */
    function updateLabels() {
        labelLayer.clearLayers();
        if (!markersEnabled) return;

        var viewBounds = map.getBounds().pad(0.15);
        var candidates = [];
        pairs.forEach(function (p) {
            var ll = p.marker.getLatLng();
            if (!viewBounds.contains(ll)) return;
            if (!isLabelEligible(p.marker)) return;
            candidates.push({
                loc: p.loc,
                marker: p.marker,
                pt: map.latLngToContainerPoint(ll)
            });
        });

        candidates.sort(function (a, b) {
            var d = (b.loc.postCount || 0) - (a.loc.postCount || 0);
            if (d !== 0) return d;
            return a.loc.id - b.loc.id;
        });

        var accepted = [];
        var pad = 3;
        candidates.forEach(function (c) {
            var text = c.loc.name || '';
            if (!text) return;
            var w = labelWidth(text) + 12; // 文本宽 + 左右内边距
            var h = 18;
            var box = {
                left: c.pt.x - w / 2,
                right: c.pt.x + w / 2,
                top: c.pt.y + 11,
                bottom: c.pt.y + 11 + h
            };
            for (var i = 0; i < accepted.length; i++) {
                if (boxesOverlap(box, accepted[i], pad)) return;
            }
            accepted.push(box);
            L.tooltip({
                permanent: true,
                direction: 'bottom',
                offset: [0, 11],
                className: 'bm-label',
                opacity: 1,
                interactive: false
            }).setLatLng(c.marker.getLatLng()).setContent(escapeHtml(text)).addTo(labelLayer);
        });
    }

    var labelTimer = null;
    function scheduleLabels() {
        if (labelTimer) clearTimeout(labelTimer);
        labelTimer = setTimeout(updateLabels, 60);
    }
    map.on('zoomend moveend', scheduleLabels);
    if (clusterEnabled) {
        // 聚合使用 chunkedLoading，气泡重排后补算一次标签
        layerHost.on('animationend', scheduleLabels);
    }

    /* ══ 筛选 + 统计 ══ */
    var catSel = root.querySelector('.bm-filter-category');
    var onlyPosts = root.querySelector('.bm-filter-has-posts');
    var filterState = {
        category: '',
        onlyWithPosts: onlyPosts ? !!onlyPosts.checked : CFG.defaultFilter === 'with_posts'
    };

    function filteredIds() {
        var ids = {};
        DATA.forEach(function (l) {
            if (filterState.category && l.category !== filterState.category) return;
            if (filterState.onlyWithPosts && !l.postIds.length) return;
            ids[l.id] = true;
        });
        return ids;
    }

    function updateStats(ids) {
        var locs = 0, posts = {}, countries = {}, cities = {};
        DATA.forEach(function (l) {
            if (!ids[l.id]) return;
            locs++;
            l.postIds.forEach(function (pid) { posts[pid] = 1; });
            if (l.countryCode) countries[l.countryCode] = 1;
            if (l.city) cities[(l.countryCode || '_') + '|' + l.city] = 1;
        });
        setText('.bm-stat-locations', locs);
        setText('.bm-stat-posts', Object.keys(posts).length);
        setText('.bm-stat-countries', Object.keys(countries).length);
        setText('.bm-stat-cities', Object.keys(cities).length);
    }

    function setText(sel, val) {
        var el = root.querySelector(sel);
        if (el) el.textContent = String(val);
    }

    function applyFilter() {
        var ids = filteredIds();
        var visible = [];
        if (markersEnabled) {
            pairs.forEach(function (p) {
                if (ids[p.loc.id]) visible.push(p.marker);
            });
        }
        layerHost.clearLayers();
        if (clusterEnabled) {
            layerHost.addLayers(visible);
        } else {
            visible.forEach(function (m) { layerHost.addLayer(m); });
        }
        updateStats(ids);
        scheduleLabels();
    }

    if (catSel) {
        catSel.addEventListener('change', function () {
            filterState.category = catSel.value;
            applyFilter();
        });
    }
    if (onlyPosts) {
        onlyPosts.addEventListener('change', function () {
            filterState.onlyWithPosts = !!onlyPosts.checked;
            applyFilter();
        });
    }

    applyFilter();
    setTimeout(scheduleLabels, 500); // 聚合分块加载/首帧布局后的补算

    /* ══ ?location_id= 定位高亮（自动展开聚合） ══ */
    var focusId = CFG.focusId ? parseInt(CFG.focusId, 10) : 0;
    if (markersEnabled && focusId && markers[focusId]) {
        var focusMarker = markers[focusId];
        var revealFocus = function () {
            var el = focusMarker.getElement();
            if (!el) return;
            var pin = el.querySelector('.bm-pin') || el;
            pin.classList.add('bm-pin--focus');
            if (focusMarker.getPopup()) {
                focusMarker.openPopup();
            }
        };
        if (clusterEnabled && typeof layerHost.zoomToShowLayer === 'function') {
            layerHost.zoomToShowLayer(focusMarker, revealFocus);
        } else {
            map.setView(focusMarker.getLatLng(), Math.max(map.getZoom(), 8));
            revealFocus();
        }
        setTimeout(function () {
            var el = focusMarker.getElement();
            if (el) {
                var pin = el.querySelector('.bm-pin');
                if (pin) pin.classList.remove('bm-pin--focus');
            }
        }, 5000);
    }
})();
