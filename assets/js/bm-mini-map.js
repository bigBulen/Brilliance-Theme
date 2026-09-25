/**
 * 足迹小地图（侧边栏 Widget 静态展示）
 *
 * 每个 .bm-mini-map 内嵌 <script type="application/json" class="bm-mini-map__data">
 * 提供 { config, locations }；渲染 Leaflet 静态地图（国家轮廓 + 标点），
 * 关闭全部交互并让容器 pointer-events:none，从而由外层 <a> 接管点击跳转。
 */
(function () {
    'use strict';
    if (typeof window.L === 'undefined') return;

    function tierOf(loc) {
        var n = loc.postCount || 0;
        if (loc.visitCount && loc.visitCount > n) n = loc.visitCount;
        if (n <= 0) return 't0';
        if (n === 1) return 't1';
        if (n <= 3) return 't2';
        return 't3';
    }

    function iconFor(loc, badge) {
        var n = loc.postCount || 0;
        if (loc.visitCount && loc.visitCount > n) n = loc.visitCount;
        var html = '<span class="bm-pin bm-pin--' + tierOf(loc) + '">';
        if (badge && n > 0) html += '<b class="bm-pin__count">' + n + '</b>';
        html += '</span>';
        return L.divIcon({ className: '', html: html, iconSize: [20, 20], iconAnchor: [10, 10] });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.bm-mini-map[data-bm-mini]'), function (node) {
        var ref = node.querySelector('script.bm-mini-map__data');
        if (!ref) return;

        var payload;
        try {
            payload = JSON.parse(ref.textContent);
        } catch (e) {
            return;
        }
        ref.parentNode.removeChild(ref);

        var cfg = payload.config || {};
        var locs = payload.locations || [];
        var showTiles = cfg.showTiles !== false && !!cfg.tileUrl;

        var map = L.map(node, {
            center: cfg.center || [20, 0],
            zoom: cfg.zoom || 3,
            minZoom: 1,
            maxZoom: showTiles ? 12 : 6,
            zoomControl: false,
            attributionControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            touchZoom: false,
            inertia: false,
            zoomSnap: 0.5
        });

        if (showTiles) {
            L.tileLayer(cfg.tileUrl, { maxZoom: 19 }).addTo(map);
        } else {
            node.classList.add('bm-mini-map--no-tiles');
        }

        if (cfg.showOutlines !== false && cfg.geoJsonUrl) {
            map.createPane('bmMiniOutlines');
            var pane = map.getPane('bmMiniOutlines');
            pane.style.zIndex = 350;
            pane.style.pointerEvents = 'none';
            if (!showTiles) {
                pane.style.filter = 'drop-shadow(0 1px 2px rgba(0, 0, 0, 0.18))';
            }
            fetch(cfg.geoJsonUrl)
                .then(function (r) { return r.json(); })
                .then(function (geo) {
                    L.geoJSON(geo, {
                        pane: 'bmMiniOutlines',
                        interactive: false,
                        style: function () {
                            if (showTiles) {
                                return {
                                    color: 'rgba(120,140,180,0.45)',
                                    weight: 0.6,
                                    fillColor: 'rgba(120,140,180,0.10)',
                                    fillOpacity: 1
                                };
                            }
                            return {
                                color: 'rgba(110,125,160,0.75)',
                                weight: 0.8,
                                fillColor: 'rgba(120,140,180,0.18)',
                                fillOpacity: 1
                            };
                        }
                    }).addTo(map);
                })
                .catch(function () { /* 轮廓失败不影响标点 */ });
        }

        if (cfg.showMarkers !== false && locs.length) {
            var layer = L.layerGroup();
            locs.forEach(function (loc) {
                if (typeof loc.lat !== 'number' || typeof loc.lng !== 'number') return;
                layer.addLayer(L.marker([loc.lat, loc.lng], {
                    interactive: false,
                    keyboard: false,
                    icon: iconFor(loc, cfg.showBadge)
                }));
            });
            layer.addTo(map);
        }

        var container = map.getContainer();
        if (container) {
            container.style.pointerEvents = 'none';
        }
    });
})();
