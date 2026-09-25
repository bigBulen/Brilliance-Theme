/*
Author: Mimosa233
Author URI: https://loneapex.cn/
*/
(function(){
  function qs(el, sel){ return el.querySelector(sel); }
  function qsa(el, sel){ return Array.prototype.slice.call(el.querySelectorAll(sel)); }
  function clamp(n, min, max){ return Math.max(min, Math.min(max, n)); }
  function closest(el, sel){ return el && el.closest ? el.closest(sel) : null; }

  // Per list state
  const stateMap = new WeakMap();
  function getState(root){
    let st = stateMap.get(root);
    if (!st){
      const sortSelect = qs(root, '.apex-sort-select');
      st = {
        status: 'all',
        sort: sortSelect ? (sortSelect.value || 'default') : 'default',
        filter: { keyword:'', year:'', quarter:'' }
      };
      stateMap.set(root, st);
    }
    return st;
  }

  function collectCards(root){
    return qsa(root, '.apex-card');
  }

  function passFilters(st, card){
    // status
    if (st.status !== 'all'){
      const s = (card.getAttribute('data-status')||'').trim();
      if (s !== st.status) return false;
    }
    // keyword
    if (st.filter.keyword){
      const kw = st.filter.keyword.toLowerCase();
      const title = (card.getAttribute('data-title')||'').toLowerCase();
      const review= (card.getAttribute('data-review')||'').toLowerCase();
      if (title.indexOf(kw) === -1 && review.indexOf(kw) === -1) return false;
    }
    // year
    if (st.filter.year){
      const y = (card.getAttribute('data-year')||'').trim();
      if (String(y) !== String(st.filter.year)) return false;
    }
    // quarter
    if (st.filter.quarter){
      const q = (card.getAttribute('data-quarter')||'').trim();
      if (q !== st.filter.quarter) return false;
    }
    return true;
  }

  function sortCards(st, list){
    const arr = list.slice();
    if (st.sort === 'score'){
      arr.sort((a,b)=>{
        const ha = parseInt(a.getAttribute('data-honmei')||'0',10);
        const hb = parseInt(b.getAttribute('data-honmei')||'0',10);
        if (hb !== ha) return hb - ha; // 本命作最高优先级
        const sa = clamp(parseFloat(a.getAttribute('data-score')||'0'),0,10);
        const sb = clamp(parseFloat(b.getAttribute('data-score')||'0'),0,10);
        if (sb !== sa) return sb - sa;
        const ca = parseInt(a.getAttribute('data-created')||'0',10);
        const cb = parseInt(b.getAttribute('data-created')||'0',10);
        if (cb !== ca) return cb - ca;
        const ua = parseInt(a.getAttribute('data-updated')||'0',10);
        const ub = parseInt(b.getAttribute('data-updated')||'0',10);
        if (ub !== ua) return ub - ua;
        return 0;
      });
    } else if (st.sort === 'time'){
      arr.sort((a,b)=>{
        const ya = parseInt(a.getAttribute('data-year')||'0',10);
        const yb = parseInt(b.getAttribute('data-year')||'0',10);
        if (!!ya !== !!yb) return ya ? -1 : 1;
        if (yb !== ya) return yb - ya;
        const qa = parseInt(a.getAttribute('data-qorder')||'0',10);
        const qb = parseInt(b.getAttribute('data-qorder')||'0',10);
        if (qb !== qa) return qb - qa;
        const ca = parseInt(a.getAttribute('data-created')||'0',10);
        const cb = parseInt(b.getAttribute('data-created')||'0',10);
        return cb - ca;
      });
    } else {
      // default: 按 created_at 倒序
      arr.sort((a,b)=>{
        const ca = parseInt(a.getAttribute('data-created')||'0',10);
        const cb = parseInt(b.getAttribute('data-created')||'0',10);
        if (cb !== ca) return cb - ca;
        const ua = parseInt(a.getAttribute('data-updated')||'0',10);
        const ub = parseInt(b.getAttribute('data-updated')||'0',10);
        if (ub !== ua) return ub - ua;
        return 0;
      });
    }
    return arr;
  }

  function render(root){
    const grid = qs(root, '.apex-media-grid');
    if (!grid) return;
    const st = getState(root);
    const cards = collectCards(root);

    const visible = cards.filter(c => passFilters(st, c));
    const sorted = sortCards(st, visible);

    const frag = document.createDocumentFragment();
    sorted.forEach(c => {
      c.style.display = '';
      frag.appendChild(c);
    });
    cards.forEach(c => {
      if (!passFilters(st, c)){
        c.style.display = 'none';
        frag.appendChild(c);
      }
    });

    grid.innerHTML = '';
    grid.appendChild(frag);
  }

  function setupYears(root){
    const sel = qs(root, '.filter-year');
    if (!sel) return;
    const set = new Set();
    collectCards(root).forEach(c=>{
      const y = parseInt(c.getAttribute('data-year')||'', 10);
      if (!isNaN(y) && y > 0) set.add(y);
    });
    const years = Array.from(set).sort((a,b)=>b-a);
    sel.innerHTML = '<option value="">全部年份</option>';
    years.forEach(y=>{
      const opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      sel.appendChild(opt);
    });
  }

  function openModal(m){ if (!m) return; m.classList.add('show'); m.setAttribute('aria-hidden','false'); }
  function closeModal(m){ if (!m) return; m.classList.remove('show'); m.setAttribute('aria-hidden','true'); }

  function renderAll(){
    qsa(document, '.apex-media-list').forEach(render);
  }

  // Initial normalize
  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', renderAll);
  }else{
    renderAll();
  }

  // PJAX hooks (best effort)
  ['pjax:complete','pjax:end','pjax:success'].forEach(function(evt){
    document.addEventListener(evt, function(){ renderAll(); }, false);
  });
  if (typeof jQuery !== 'undefined' && jQuery(document).on){
    jQuery(document).on('ajaxComplete', function(){ renderAll(); });
  }

  // Grade tooltip
  const gradeTip = document.createElement('div');
  gradeTip.className = 'apex-grade-tooltip';
  document.body.appendChild(gradeTip);

  function hideGradeTip(){
    gradeTip.classList.remove('show');
    gradeTip.style.display = 'none';
  }
  function showGradeTip(target){
    const grade = target.getAttribute('data-grade') || '';
    const descRaw = target.getAttribute('data-grade-desc') || '';
    if (!grade && !descRaw){ hideGradeTip(); return; }
    const desc = (descRaw || '').replace(/\\n/g,'<br>').replace(/\n/g,'<br>');
    gradeTip.innerHTML = '<div class="apex-grade-tip-title">'+grade+'</div><div class="apex-grade-tip-desc">'+desc+'</div>';
    gradeTip.style.display = 'block';
    gradeTip.classList.add('show');
    const rect = target.getBoundingClientRect();
    const tipRect = gradeTip.getBoundingClientRect();
    const top = window.scrollY + rect.top - tipRect.height - 10;
    const left = window.scrollX + rect.left + rect.width/2;
    gradeTip.style.top = top + 'px';
    gradeTip.style.left = left + 'px';
  }

  // Delegated events
  document.addEventListener('click', function(e){
    // 点击提示框本身：不关闭
    if (closest(e.target, '.apex-grade-tooltip')){
      e.stopPropagation();
      return;
    }

    // Grade tooltip (click)
    const gradeEl = closest(e.target, '.apex-grade-label');
    if (gradeEl){
      e.stopPropagation();
      showGradeTip(gradeEl);
      return;
    }
    hideGradeTip();

    // Tabs
    const tab = closest(e.target, '.apex-media-tabs .tab');
    if (tab){
      const root = closest(tab, '.apex-media-list');
      if (!root) return;
      const st = getState(root);
      const tabs = qsa(root, '.apex-media-tabs .tab');
      tabs.forEach(b=>b.classList.remove('active'));
      tab.classList.add('active');
      st.status = tab.getAttribute('data-status') || 'all';
      render(root);
      return;
    }

    // Open filter modal
    const btnFilter = closest(e.target, '.btn-filter');
    if (btnFilter){
      const root = closest(btnFilter, '.apex-media-list');
      if (!root) return;
      setupYears(root);
      const st = getState(root);
      const modal = qs(root, '.apex-filter-modal');
      if (modal){
        const kw = qs(modal, '.filter-keyword');
        const yr = qs(modal, '.filter-year');
        const qt = qs(modal, '.filter-quarter');
        if (kw) kw.value = st.filter.keyword || '';
        if (yr) yr.value = st.filter.year || '';
        if (qt) qt.value = st.filter.quarter || '';
        openModal(modal);
      }
      return;
    }

    // Filter modal apply
    const btnApply = closest(e.target, '.btn-filter-apply');
    if (btnApply){
      const root = closest(btnApply, '.apex-media-list');
      if (!root) return;
      const st = getState(root);
      const modal = closest(btnApply, '.apex-filter-modal');
      const kw = modal ? qs(modal, '.filter-keyword') : null;
      const yr = modal ? qs(modal, '.filter-year') : null;
      const qt = modal ? qs(modal, '.filter-quarter') : null;
      st.filter.keyword = kw ? (kw.value||'').trim() : '';
      st.filter.year = yr ? (yr.value||'').trim() : '';
      st.filter.quarter = qt ? (qt.value||'').trim() : '';
      closeModal(modal);
      render(root);
      return;
    }

    // Filter modal reset
    const btnReset = closest(e.target, '.btn-filter-reset');
    if (btnReset){
      const modal = closest(btnReset, '.apex-filter-modal');
      if (modal){
        const kw = qs(modal, '.filter-keyword');
        const yr = qs(modal, '.filter-year');
        const qt = qs(modal, '.filter-quarter');
        if (kw) kw.value = '';
        if (yr) yr.value = '';
        if (qt) qt.value = '';
      }
      return;
    }

    // Close modals (mask or close button)
    const closeBtn = closest(e.target, '.apex-modal .apex-modal-close, .apex-filter-modal .apex-modal-close');
    const mask = closest(e.target, '.apex-modal .apex-modal-mask, .apex-filter-modal .apex-modal-mask');
    if (closeBtn || mask){
      const modal = closest(e.target, '.apex-modal, .apex-filter-modal');
      closeModal(modal);
      return;
    }

    // Readmore
    const readBtn = closest(e.target, '.btn-readmore');
    if (readBtn){
      const root = closest(readBtn, '.apex-media-list');
      if (!root) return;
      const card = closest(readBtn, '.apex-card');
      const modal = qs(root, '.apex-modal');
      if (!card || !modal) return;
      const header = qs(modal, '.apex-modal-title');
      const body = qs(modal, '.apex-modal-body');
      const title = (card.getAttribute('data-title')||'').trim();
      const review = (card.getAttribute('data-review')||'').trim();
      if (header) header.textContent = title || '评价';
      if (body){
        body.textContent = '';
        const p = document.createElement('div');
        p.style.whiteSpace = 'pre-wrap';
        p.textContent = review || '';
        body.appendChild(p);
      }
      openModal(modal);
      return;
    }
  }, false);

  // Delegated change for sort select
  document.addEventListener('change', function(e){
    const sel = closest(e.target, '.apex-sort-select');
    if (sel){
      const root = closest(sel, '.apex-media-list');
      if (!root) return;
      const st = getState(root);
      st.sort = sel.value || 'default';
      render(root);
    }
  }, false);

  // 悬停显示 / 离开隐藏
  document.addEventListener('mouseover', function(e){
    const gradeEl = closest(e.target, '.apex-grade-label');
    if (gradeEl){
      showGradeTip(gradeEl);
    }
  }, true);
  document.addEventListener('mouseout', function(e){
    const from = closest(e.target, '.apex-grade-label') || closest(e.target, '.apex-grade-tooltip');
    if (!from) return;
    const to = e.relatedTarget;
    const stillInside = to && (closest(to, '.apex-grade-label') || closest(to, '.apex-grade-tooltip'));
    if (!stillInside) hideGradeTip();
  }, true);

  window.addEventListener('scroll', hideGradeTip, true);
  window.addEventListener('resize', hideGradeTip, true);
  gradeTip.addEventListener('mouseleave', hideGradeTip, false);
})();
