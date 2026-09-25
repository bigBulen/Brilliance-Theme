/**
 * 印象集「Toward Our Dream」渲染引擎
 *
 * 原生 Canvas 2D + requestAnimationFrame，无外部依赖。
 * 作品从始至终是「正方形大圆角封面」：默认略缩的小方卡，Hover 时平滑放大为完整大卡，
 * 在封面上叠渐变遮罩 + 印象语/标题/类型。中心文字区域为禁入区，作品不会遮挡它。
 * 数据来自 window.IMPRESSIONS_CONFIG.apiUrl（一次拉取），按「本命作 > 评分」向中心聚拢。
 */
(function () {
    'use strict';

    var cfg = window.IMPRESSIONS_CONFIG || {};

    var canvas = document.getElementById('impressions-canvas');
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext('2d');

    var W = 0, H = 0, DPR = 1;

    /* ── 可调参数 ── */
    var P = {
        starCount: 420,
        stateBRadius: 200,        // 靠近感应半径（世界单位）
        easingFactor: 0.13,       // 状态逼近速率
        hoverThreshold: 0.82,     // proximity 超过该值进入 Hover（放大 + 显示信息）
        hoverUp: 0.13,            // 进入 Hover 速率
        hoverDown: 0.11,          // 退出 Hover 速率
        nearGrow: 0.15,           // 靠近态轻微放大比例
        fullSize: 320,            // Hover 完整卡片边长（px，世界=屏幕 1:1）
        hoverDim: 0.18,           // 周围后退透明幅度
        recessionShrink: 0.16,    // 周围后退缩放幅度
        cornerRadius: 26,         // 完整卡片圆角（按卡片尺寸等比缩放）
        text: {                   // 以下为「完整卡片」下的字号/颜色
            pad: 16,
            impressionSize: 19,
            titleSize: 13,
            typeSize: 10,
            impressionColor: 'rgba(255,244,224,0.98)',
            titleColor: 'rgba(255,255,255,0.62)',
            typeColor: 'rgba(255,255,255,0.42)',
            overlayTop: 'rgba(8,10,18,0)',
            overlayBottom: 'rgba(6,8,14,0.92)',
            dim: 0.22
        },
        center: {
            fontSize: 30,
            color: 'rgba(255,255,255,0.55)',
            letterSpacing: 9
        },
        entrance: {
            starFade: 600,
            centerDelay: 200,
            centerDur: 800,
            dropDelay: 800,
            batchGap: 110,
            dropDurMin: 500,
            dropDurMax: 900
        }
    };

    // 环层（世界半径区间）：中心文字禁入区已包含在第 0 环起点的推算中
    var TIER_RANGES = [
        [290, 420],    // 0 本命作
        [430, 580],    // 1（评分 9+）
        [580, 760],    // 2（8+）
        [760, 960],    // 3（7+）
        [960, 1180],   // 4（6+）
        [1180, 1420],  // 5（5+）
        [1420, 1700]   // 6（更低/无评分）
    ];

    /* ── 运行状态 ── */
    var camera = { x: 0, y: 0 };
    var mouse = { x: 0, y: 0 };
    var works = [];
    var stars = [];
    var bgGrad = null;
    var startTime = null;
    var running = false;
    var camAnim = null;

    // 中心文字禁入区（世界坐标半宽/半高，加载数据前测量）
    var CENTER_HALF_W = 205;
    var CENTER_HALF_H = 22;

    var dragging = false, moved = false;
    var downX = 0, downY = 0, downCam = { x: 0, y: 0 };

    /* ── 工具 ── */
    function rand(min, max) { return min + Math.random() * (max - min); }
    function lerp(a, b, t) { return a + (b - a) * t; }
    function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
    function easeOutCubic(p) { return 1 - Math.pow(1 - p, 3); }
    function smoothstep(x) { x = clamp(x, 0, 1); return x * x * (3 - 2 * x); }
    function mod(n, m) { return ((n % m) + m) % m; }

    function toScreenX(wx) { return W / 2 + wx - camera.x; }
    function toScreenY(wy) { return H / 2 + wy - camera.y; }
    function worldMouse() {
        return { x: mouse.x - W / 2 + camera.x, y: mouse.y - H / 2 + camera.y };
    }

    function roundRectPath(c, x, y, w, h, r) {
        r = Math.min(r, w / 2, h / 2);
        if (r <= 0) { c.rect(x, y, w, h); return; }
        if (c.roundRect) {
            c.beginPath();
            c.roundRect(x, y, w, h, r);
            return;
        }
        c.beginPath();
        c.moveTo(x + r, y);
        c.arcTo(x + w, y, x + w, y + h, r);
        c.arcTo(x + w, y + h, x, y + h, r);
        c.arcTo(x, y + h, x, y, r);
        c.arcTo(x, y, x + w, y, r);
        c.closePath();
    }

    function drawCover(img, x, y, w, h) {
        var scale = Math.max(w / img.width, h / img.height);
        var dw = img.width * scale, dh = img.height * scale;
        ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
    }

    function wrapText(c, text, maxWidth) {
        if (!text) return [];
        var lines = [];
        var line = '';
        for (var i = 0; i < text.length; i++) {
            var ch = text.charAt(i);
            if (c.measureText(line + ch).width > maxWidth && line !== '') {
                lines.push(line);
                line = ch;
            } else {
                line += ch;
            }
        }
        if (line !== '') lines.push(line);
        return lines;
    }

    function truncateText(c, text, maxWidth) {
        if (!text) return '';
        if (c.measureText(text).width <= maxWidth) return text;
        var t = text;
        while (t.length > 1 && c.measureText(t + '…').width > maxWidth) t = t.slice(0, -1);
        return t + '…';
    }

    function drawSpacedText(c, text, cx, cy, spacing) {
        if (!text) return;
        var widths = [];
        var total = 0;
        for (var i = 0; i < text.length; i++) {
            var w = c.measureText(text.charAt(i)).width;
            widths.push(w);
            total += w;
        }
        total += spacing * (text.length - 1);
        var sx = cx - total / 2;
        c.save();
        c.textAlign = 'left';
        c.textBaseline = 'middle';
        for (var j = 0; j < text.length; j++) {
            c.fillText(text.charAt(j), sx + widths[j] / 2, cy);
            sx += widths[j] + spacing;
        }
        c.restore();
    }

    /* ── 尺寸 / 星空 ── */
    function resize() {
        DPR = Math.min(window.devicePixelRatio || 1, 2);
        W = window.innerWidth;
        H = window.innerHeight;
        canvas.width = Math.floor(W * DPR);
        canvas.height = Math.floor(H * DPR);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
        bgGrad = ctx.createLinearGradient(0, 0, 0, H);
        bgGrad.addColorStop(0, '#05060d');
        bgGrad.addColorStop(0.5, '#0b0d1a');
        bgGrad.addColorStop(1, '#070912');
        buildStars();
    }

    function buildStars() {
        stars = [];
        for (var i = 0; i < P.starCount; i++) {
            stars.push({
                x: Math.random() * W,
                y: Math.random() * H,
                r: rand(0.6, 1.9),
                alpha: rand(0.2, 0.8),
                phase: rand(0, Math.PI * 2),
                freq: rand(0.3, 0.9)
            });
        }
    }

    /* ── 中心文字禁入区测量 ── */
    function measureCenterText() {
        var text = cfg.centerText || '';
        if (!text) {
            CENTER_HALF_W = 0;
            return;
        }
        ctx.save();
        ctx.font = '300 ' + P.center.fontSize + 'px Georgia, "Times New Roman", "Songti SC", serif';
        var w = ctx.measureText(text).width + P.center.letterSpacing * Math.max(0, text.length - 1);
        ctx.restore();
        CENTER_HALF_W = w / 2;
        CENTER_HALF_H = P.center.fontSize * 0.8;
    }

    /* ── 分层 & 布局 ── */
    function tierForScore(score, isFavorite) {
        if (isFavorite) return 0;
        if (score == null || score <= 0) return 6;
        if (score >= 9) return 1;
        if (score >= 8) return 2;
        if (score >= 7) return 3;
        if (score >= 6) return 4;
        if (score >= 5) return 5;
        return 6;
    }

    // 默认（略缩）小方卡边长：越靠中心越大，强化层次
    function baseSizeFor(tier) {
        return Math.max(58, 86 - tier * 5);
    }

    // 把作品推离中心文字区域（禁入区 = 文字半宽 + 卡片半边长 + 余量）
    function pushOutOfCenter(p) {
        var hw = CENTER_HALF_W + p.size / 2 + 34;
        var hh = CENTER_HALF_H + p.size / 2 + 34;
        if (p.x > -hw && p.x < hw && p.y > -hh && p.y < hh) {
            var dU = Math.abs(p.y - (-hh)), dD = Math.abs(p.y - hh);
            var dL = Math.abs(p.x - (-hw)), dR = Math.abs(p.x - hw);
            var m = Math.min(dU, dD, dL, dR);
            if (m === dU) p.y = -hh;
            else if (m === dD) p.y = hh;
            else if (m === dL) p.x = -hw;
            else p.x = hw;
        }
    }

    function buildWorks(items) {
        measureCenterText();

        items.sort(function (a, b) {
            var fa = a.isFavorite ? 1 : 0, fb = b.isFavorite ? 1 : 0;
            if (fb !== fa) return fb - fa;
            var sa = a.score == null ? 0 : a.score, sb = b.score == null ? 0 : b.score;
            if (sb !== sa) return sb - sa;
            return a.id - b.id;
        });

        var placed = [];
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var tier = tierForScore(it.score, it.isFavorite);
            var range = TIER_RANGES[tier];
            var ang = rand(0, Math.PI * 2);
            var rad = rand(range[0], range[1]);
            placed.push({ x: Math.cos(ang) * rad, y: Math.sin(ang) * rad, size: baseSizeFor(tier), tier: tier, it: it });
            pushOutOfCenter(placed[placed.length - 1]);
        }

        for (var iter = 0; iter < 60; iter++) {
            relax(placed);
        }

        for (var k = 0; k < placed.length; k++) {
            pushOutOfCenter(placed[k]);
        }

        works = [];
        for (var j = 0; j < placed.length; j++) {
            works.push(makeWork(placed[j].it, placed[j].x, placed[j].y, placed[j].size));
        }
    }

    function relax(arr) {
        var minGap = 14;
        for (var i = 0; i < arr.length; i++) {
            for (var j = i + 1; j < arr.length; j++) {
                var a = arr[i], b = arr[j];
                var dx = b.x - a.x, dy = b.y - a.y;
                var d = Math.sqrt(dx * dx + dy * dy);
                var minDist = (a.size + b.size) / 2 + minGap;
                if (d < minDist) {
                    if (d < 0.0001) {
                        a.x += rand(-1, 1); a.y += rand(-1, 1);
                        continue;
                    }
                    var f = (minDist - d) / 2;
                    var nx = dx / d, ny = dy / d;
                    a.x -= nx * f; a.y -= ny * f;
                    b.x += nx * f; b.y += ny * f;
                }
            }
        }
    }

    function makeWork(it, x, y, size) {
        return {
            id: it.id, title: it.title || '', typeLabel: it.typeLabel || '',
            impression: it.impression || '', url: it.url || '',
            score: it.score, isFavorite: !!it.isFavorite,
            cover: it.cover || '', coverLarge: it.coverLarge || '',
            baseX: x, baseY: y, baseSize: size, fullSize: P.fullSize,
            baseAlpha: 0.85, sideCur: size,
            phase: rand(0, Math.PI * 2), freq: rand(0.18, 0.5), amp: rand(2.5, 5),
            alphaCur: 0, proxCur: 0, hoverT: 0,
            img: null, imgLarge: null,
            enterDelay: 0, dropDur: 900, dropFromY: y, entered: false, culled: true,
            sx: 0, sy: 0
        };
    }

    function planEntrance() {
        var lastTier = null, tierIndex = -1, delayBase = P.entrance.dropDelay;
        for (var i = 0; i < works.length; i++) {
            var w = works[i];
            var t = tierForScore(w.score, w.isFavorite);
            if (t !== lastTier) {
                lastTier = t;
                tierIndex++;
                delayBase = P.entrance.dropDelay + tierIndex * P.entrance.batchGap;
            }
            w.enterDelay = delayBase + rand(0, 120);
            w.dropDur = rand(P.entrance.dropDurMin, P.entrance.dropDurMax);
            w.dropFromY = w.baseY - rand(30, 60);
        }
    }

    /* ── 图片加载 ── */
    function loadImg(url, onDone) {
        var img = new Image();
        img.onload = function () { onDone(img); };
        img.onerror = function () { onDone(null); };
        img.src = url;
    }

    function loadCovers() {
        var i = 0;
        (function next() {
            var batch = works.slice(i, i + 12);
            if (!batch.length) return;
            for (var k = 0; k < batch.length; k++) {
                (function (w) {
                    if (!w.cover) return;
                    loadImg(w.cover, function (img) { if (img) { w.img = img; } });
                })(batch[k]);
            }
            i += 12;
            setTimeout(next, 50);
        })();
    }

    function loadLarge(w) {
        if (!w.coverLarge || w.imgLarge) return;
        loadImg(w.coverLarge, function (img) { if (img) w.imgLarge = img; });
    }

    /* ── 主循环 ── */
    function frame(now) {
        if (startTime == null) startTime = now;
        var t = now - startTime;

        if (camAnim) {
            var k = clamp((now - camAnim.start) / camAnim.dur, 0, 1);
            var e = easeOutCubic(k);
            camera.x = lerp(camAnim.from.x, camAnim.to.x, e);
            camera.y = lerp(camAnim.from.y, camAnim.to.y, e);
            if (k >= 1) camAnim = null;
        }

        drawBackground(t);
        drawCenter(t);
        updateAndDrawWorks(t);

        requestAnimationFrame(frame);
    }

    function drawBackground(t) {
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, W, H);

        var starAlpha = clamp(t / P.entrance.starFade, 0, 1);
        if (starAlpha <= 0) return;

        var px = camera.x * 0.35, py = camera.y * 0.35;
        ctx.save();
        for (var i = 0; i < stars.length; i++) {
            var s = stars[i];
            var sx = mod(s.x - px, W);
            var sy = mod(s.y - py, H);
            var tw = 0.55 + 0.45 * Math.sin(t / 1000 * s.freq * Math.PI * 2 + s.phase);
            ctx.globalAlpha = starAlpha * s.alpha * tw;
            ctx.fillStyle = '#cdd6f4';
            ctx.beginPath();
            ctx.arc(sx, sy, s.r, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.restore();
    }

    function drawCenter(t) {
        var text = cfg.centerText;
        if (!text) return;
        var p = (t - P.entrance.centerDelay) / P.entrance.centerDur;
        if (p <= 0) return;
        var e = easeOutCubic(clamp(p, 0, 1));
        var sx = toScreenX(0);
        var sy = toScreenY(0) - (1 - e) * 40;

        ctx.save();
        ctx.globalAlpha = clamp(p, 0, 1);
        ctx.fillStyle = P.center.color;
        ctx.font = '300 ' + P.center.fontSize + 'px Georgia, "Times New Roman", "Songti SC", serif';
        drawSpacedText(ctx, text, sx, sy, P.center.letterSpacing);
        ctx.restore();
    }

    function updateAndDrawWorks(t) {
        var wm = worldMouse();

        var hoverStrength = 0;
        var visible = [];
        var i, w;

        // 第一遍：proximity / culling / 大图懒加载
        for (i = 0; i < works.length; i++) {
            w = works[i];
            var sx0 = toScreenX(w.baseX), sy0 = toScreenY(w.baseY);
            w.culled = (sx0 < -520 || sx0 > W + 520 || sy0 < -520 || sy0 > H + 520);
            if (w.culled) continue;

            var dx = wm.x - w.baseX, dy = wm.y - w.baseY;
            var dist = Math.sqrt(dx * dx + dy * dy);
            var Rc = w.baseSize * 0.8;
            var p = 0;
            if (dist < P.stateBRadius) {
                p = dist <= Rc ? 1 : (P.stateBRadius - dist) / (P.stateBRadius - Rc);
            }
            w.proxCur = lerp(w.proxCur, p, P.easingFactor);
            if (w.proxCur > hoverStrength) hoverStrength = w.proxCur;
            if (w.proxCur > 0.4 && w.coverLarge && !w.imgLarge) loadLarge(w);
            visible.push(w);
        }

        // 第二遍：卡片尺寸 / 透明 / 入场 / 漂浮 / Hover 揭示度
        var back = [], front = [];
        for (i = 0; i < visible.length; i++) {
            w = visible[i];

            var hoverTarget = w.proxCur >= P.hoverThreshold ? 1 : 0;
            w.hoverT = lerp(w.hoverT, hoverTarget, hoverTarget ? P.hoverUp : P.hoverDown);

            var recession = clamp(hoverStrength - w.proxCur, 0, 1);

            // 尺寸：靠近轻微放大 → Hover 平滑放大到完整大卡；周围作品轻微退后缩小
            var nearFactor = 1 + w.proxCur * P.nearGrow;
            var smallSize = w.baseSize * nearFactor;
            var htS = smoothstep(w.hoverT);
            var sideTarget = lerp(smallSize, w.fullSize, htS) * (1 - recession * P.recessionShrink);
            w.sideCur = lerp(w.sideCur, sideTarget, P.easingFactor);

            var baseA = w.baseAlpha * (1 - recession * P.hoverDim);
            var alpha = baseA;
            var yOff = 0;
            if (!w.entered) {
                var et = t - w.enterDelay;
                if (et <= 0) continue;
                var ep = clamp(et / w.dropDur, 0, 1);
                var ee = easeOutCubic(ep);
                yOff = (w.dropFromY - w.baseY) * (1 - ee);
                alpha = baseA * ee;
                if (ep >= 1) w.entered = true;
            }

            // 漂浮（Hover 时减弱，保证文字稳定）
            var amp = w.amp * (w.hoverT > 0.5 ? 0.4 : 1);
            var fx = Math.sin(t / 1000 * w.freq * 6.2832 + w.phase) * amp;
            var fy = Math.cos(t / 1000 * w.freq * 6.2832 * 0.9 + w.phase) * amp * 0.7;
            w.sx = toScreenX(w.baseX) + fx;
            w.sy = toScreenY(w.baseY) + fy + yOff;
            w.alphaCur = alpha;

            if (w.hoverT > 0.02) front.push(w); else back.push(w);
        }

        for (i = 0; i < back.length; i++) drawWork(back[i]);
        for (i = 0; i < front.length; i++) drawWork(front[i]);
    }

    function drawWork(w) {
        var alpha = w.alphaCur;
        if (alpha <= 0.004) return;

        var side = w.sideCur;
        if (side < 1) return;
        var ts = side / w.fullSize;            // 相对完整卡片的缩放
        var r = P.cornerRadius * ts;
        var x = w.sx - side / 2, y = w.sy - side / 2;
        var htS = smoothstep(w.hoverT);

        ctx.save();
        ctx.globalAlpha = alpha;

        roundRectPath(ctx, x, y, side, side, r);
        ctx.clip();

        // 封面主体
        var img = w.imgLarge || w.img;
        if (img && img.naturalWidth > 0) {
            drawCover(img, x, y, side, side);
        } else {
            ctx.fillStyle = 'rgba(255,255,255,0.07)';
            ctx.fillRect(x, y, side, side);
        }

        // 靠近时轻微提亮
        var brighten = w.proxCur * 0.07 * (1 - htS);
        if (brighten > 0.004) {
            ctx.globalAlpha = alpha * brighten;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(x, y, side, side);
            ctx.globalAlpha = alpha;
        }

        // Hover：先变暗，再叠渐变遮罩
        if (htS > 0.004) {
            ctx.globalAlpha = alpha * htS * P.text.dim;
            ctx.fillStyle = '#000000';
            ctx.fillRect(x, y, side, side);

            var g = ctx.createLinearGradient(0, y, 0, y + side);
            g.addColorStop(0, P.text.overlayTop);
            g.addColorStop(1, P.text.overlayBottom);
            ctx.globalAlpha = alpha * htS;
            ctx.fillStyle = g;
            ctx.fillRect(x, y, side, side);
            ctx.globalAlpha = alpha;
        }

        // 边缘内描边（克制的卡片存在感）
        ctx.strokeStyle = 'rgba(255,255,255,0.10)';
        ctx.lineWidth = 1;
        roundRectPath(ctx, x + 0.5, y + 0.5, side - 1, side - 1, Math.max(1, r - 0.5));
        ctx.stroke();

        ctx.restore();

        if (htS > 0.02) {
            drawOverlayText(w, x, y, side, ts, htS);
        }
    }

    function drawOverlayText(w, x, y, side, ts, htS) {
        var impAlpha = clamp((htS - 0.3) / 0.45, 0, 1);
        var titleAlpha = clamp((htS - 0.48) / 0.42, 0, 1);
        var typeAlpha = clamp((htS - 0.66) / 0.34, 0, 1);
        if (impAlpha <= 0.004 && titleAlpha <= 0.004 && typeAlpha <= 0.004) return;

        var pad = P.text.pad * ts;
        var maxW = side - pad * 2;
        var impressionSize = P.text.impressionSize * ts;
        var titleSize = P.text.titleSize * ts;
        var typeSize = P.text.typeSize * ts;

        ctx.save();
        ctx.textBaseline = 'top';
        ctx.textAlign = 'left';

        // 印象语（主文字，先量行）
        ctx.font = '600 ' + impressionSize + 'px "PingFang SC","Microsoft YaHei",system-ui,sans-serif';
        var impLines = wrapText(ctx, w.impression, maxW).slice(0, 2);
        var impLineH = impressionSize * 1.3;
        var impH = impLines.length * impLineH;
        var typeH = typeSize * 1.5;
        var titleH = titleSize * 1.5;
        var gap = 6 * ts;

        // 自底向上排：类型（最下） → 印象语 → 标题（最上）
        var bottom = y + side - pad;
        var typeTop = bottom - typeH;
        var impBottom = typeTop - gap;
        var impTop = impBottom - impH;
        var titleBottom = impTop - gap;
        var titleTop = titleBottom - titleH;

        // 类型（第三优先级）
        if (typeAlpha > 0.004 && w.typeLabel) {
            ctx.globalAlpha = typeAlpha;
            ctx.fillStyle = P.text.typeColor;
            ctx.font = typeSize + 'px system-ui,sans-serif';
            ctx.fillText(w.typeLabel, x + pad, typeTop);
        }

        // 印象语（第一优先级）
        if (impAlpha > 0.004) {
            ctx.globalAlpha = impAlpha;
            ctx.fillStyle = P.text.impressionColor;
            ctx.font = '600 ' + impressionSize + 'px "PingFang SC","Microsoft YaHei",system-ui,sans-serif';

            // 第一行加「，最后一行加」
            for (var i = 0; i < impLines.length; i++) {
                var text = impLines[i];
                if (i === 0) {
                    text = '「' + text;  // 第一行开头加「
                }
                if (i === impLines.length - 1) {
                    text = text + '」';   // 最后一行末尾加」
                }
                ctx.fillText(text, x + pad, impTop + i * impLineH);
            }
        }

        // 标题（第二优先级，书名号，位于印象语上方）
        if (titleAlpha > 0.004 && w.title) {
            ctx.globalAlpha = titleAlpha;
            ctx.fillStyle = P.text.titleColor;
            ctx.font = titleSize + 'px system-ui,sans-serif';
            ctx.fillText('《' + truncateText(ctx, w.title, maxW) + '》', x + pad, titleTop);
        }

        ctx.restore();
    }

    /* ── 交互 ── */
    function onDown(e) {
        dragging = true;
        moved = false;
        downX = e.clientX;
        downY = e.clientY;
        downCam = { x: camera.x, y: camera.y };
        camAnim = null;
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
        canvas.classList.add('is-dragging');
    }

    function onMove(e) {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        if (!dragging) return;
        var dx = e.clientX - downX, dy = e.clientY - downY;
        if (!moved && Math.abs(dx) + Math.abs(dy) > 5) moved = true;
        if (moved) {
            camera.x = downCam.x - dx;
            camera.y = downCam.y - dy;
        }
    }

    function onUp(e) {
        if (dragging && !moved) handleClick(e.clientX, e.clientY);
        dragging = false;
        canvas.classList.remove('is-dragging');
        try { canvas.releasePointerCapture(e.pointerId); } catch (err) {}
    }

    function handleClick(sx, sy) {
        var wm = { x: sx - W / 2 + camera.x, y: sy - H / 2 + camera.y };
        var best = null, bestP = 0;
        for (var i = 0; i < works.length; i++) {
            var w = works[i];
            if (w.culled || !w.entered) continue;
            var dx = wm.x - w.baseX, dy = wm.y - w.baseY;
            var hitR = Math.max(w.baseSize * 0.8, w.sideCur * 0.5);
            if (Math.sqrt(dx * dx + dy * dy) <= hitR && w.proxCur > bestP) {
                best = w;
                bestP = w.proxCur;
            }
        }
        if (best && best.url) window.location.href = best.url;
    }

    function recenter() {
        camAnim = { from: { x: camera.x, y: camera.y }, to: { x: 0, y: 0 }, start: performance.now(), dur: 600 };
    }

    function bindEvents() {
        canvas.addEventListener('pointerdown', onDown);
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);

        var recenterBtn = document.getElementById('impressions-recenter');
        if (recenterBtn) {
            recenterBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                recenter();
            });
        }

        var homeBtn = document.getElementById('impressions-home');
        if (homeBtn) {
            homeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                window.location.href = cfg.homeUrl || '/';
            });
        }

        var hint = document.getElementById('impressions-hint');
        if (hint) {
            if (localStorage.getItem('impressions-hint-shown')) {
                hint.style.display = 'none';
            } else {
                setTimeout(function () { hint.classList.add('show'); }, 1200);
                setTimeout(function () {
                    hint.classList.remove('show');
                    try { localStorage.setItem('impressions-hint-shown', '1'); } catch (err) {}
                }, 4200);
            }
        }
    }

    /* ── 启动 ── */
    function start() {
        fetch(cfg.apiUrl || '/wp-json/acgn/v1/impressions')
            .then(function (r) { return r.json(); })
            .then(function (list) {
                if (!Array.isArray(list)) list = [];
                buildWorks(list);
                planEntrance();
                loadCovers();
                boot();
            })
            .catch(function () {
                boot();
            });
    }

    function boot() {
        if (running) return;
        running = true;
        startTime = null;
        requestAnimationFrame(frame);
    }

    resize();
    window.addEventListener('resize', function () { resize(); });
    bindEvents();
    start();
})();