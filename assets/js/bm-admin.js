/**
 * 足迹地图后台：地点查重提示 + AJAX 删除
 */
(function () {
    'use strict';

    var cfg = window.BM_ADMIN || {};

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function debounce(fn, wait) {
        var t = null;
        return function () {
            var self = this, args = arguments;
            if (t) clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    /* ══ 表单页：查重提示（国家+城市+名称模糊匹配，仅提示不阻止） ══ */
    var form = document.getElementById('bm-location-form');
    if (form) {
        var nameEl = form.querySelector('#bm_name');
        var cityEl = form.querySelector('#bm_city');
        var ccEl = form.querySelector('#bm_country_code');
        var idEl = form.querySelector('[name="id"]');
        var hint = document.getElementById('bm-dup-hint');

        function renderHint(rows) {
            if (!hint) return;
            if (!rows || !rows.length) {
                hint.innerHTML = '';
                return;
            }
            var html = '<div class="bm-dup"><strong>' + escapeHtml(cfg.dupTitle || '发现疑似重复地点') + '</strong><ul>';
            rows.forEach(function (r) {
                html += '<li><a href="' + escapeHtml(r.url) + '">#' + r.id + ' ' + escapeHtml(r.name) + '</a>';
                if (r.city || r.country) {
                    html += ' <small>(' + escapeHtml([r.city, r.country].filter(Boolean).join(' · ')) + ')</small>';
                }
                html += '</li>';
            });
            html += '</ul><em>仅作提示，不会阻止保存；如确认不是重复可继续创建。</em></div>';
            hint.innerHTML = html;
        }

        function checkDup() {
            var name = (nameEl && nameEl.value ? nameEl.value : '').trim();
            if (!name) {
                renderHint([]);
                return;
            }
            var body = new URLSearchParams();
            body.append('action', 'bm_dup_check');
            body.append('nonce', cfg.nonce || '');
            body.append('name', name);
            body.append('city', cityEl && cityEl.value ? cityEl.value.trim() : '');
            body.append('country_code', ccEl && ccEl.value ? ccEl.value.trim() : '');
            body.append('exclude_id', idEl && idEl.value ? idEl.value : '0');
            fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    renderHint(res && res.success && Array.isArray(res.data) ? res.data : []);
                })
                .catch(function () { /* 静默失败，不阻塞表单 */ });
        }

        if (nameEl) nameEl.addEventListener('input', debounce(checkDup, 400));
        if (cityEl) cityEl.addEventListener('change', checkDup);
        if (ccEl) ccEl.addEventListener('change', checkDup);
    }

    /* ══ 设置页：清空数据二次确认 ══ */
    Array.prototype.forEach.call(document.querySelectorAll('.bm-wipe-form'), function (form) {
        form.addEventListener('submit', function (e) {
            var btn = form.querySelector('.bm-wipe-data');
            var name = (btn && btn.getAttribute('data-name')) || '该操作';
            if (!window.confirm('确定要' + name + '？\n全部地点与关联关系将被删除，且不可恢复。')) {
                e.preventDefault();
            }
        });
    });

    /* ══ 列表页：AJAX 删除 ══ */
    Array.prototype.forEach.call(document.querySelectorAll('.bm-delete'), function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            if (!window.confirm('确定删除地点「' + name + '」(#' + id + ')？\n其文章关联将一并移除，不可恢复。')) {
                return;
            }
            var body = new URLSearchParams();
            body.append('action', 'bm_delete_location');
            body.append('nonce', cfg.nonce || '');
            body.append('id', id || '0');
            fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        window.location.reload();
                    } else {
                        window.alert((res && res.data) || '删除失败');
                    }
                })
                .catch(function () {
                    window.alert('网络错误，删除失败');
                });
        });
    });
})();
