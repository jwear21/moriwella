/* Full-screen photo viewer for the journal, days and experiences: tap a photo to enlarge it; swipe, arrows or keys to move; pinch, wheel or
   double-tap to zoom; drag when zoomed; Esc or tap outside to close. Copyright (c) JSW Software Designs */
(function () {
  'use strict';
  var cfg = document.querySelector('[data-viewer-cfg]'); if (!cfg) return;
  var rtl = document.documentElement.dir === 'rtl';
  function L(k) { return cfg.getAttribute('data-' + k) || ''; }
  function big(src) { return src.replace('/thumb', '/web'); }
  var group = [], cur = 0, lb, img, cnt, scale = 1, tx = 0, ty = 0, pts = {}, startD = 0, startS = 1, sx = 0, sy = 0, moved = false, lastTap = 0, opener = null;
  function mk(tag, cls, txt, label) { var e = document.createElement(tag); e.className = cls; if (txt) e.textContent = txt; if (label) e.setAttribute('aria-label', label); if (tag === 'button') e.type = 'button'; return e; }
  function apply() { img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')'; lb.classList.toggle('pv-zoomed', scale > 1); }
  function reset() { scale = 1; tx = ty = 0; apply(); }
  function show(i) {
    cur = (i + group.length) % group.length; reset();
    img.src = group[cur].src; cnt.textContent = group.length > 1 ? (cur + 1) + ' / ' + group.length : '';
    lb.classList.toggle('pv-one', group.length < 2); lb.querySelector('.pv-e').hidden = !(group[cur].pid && cfg.hasAttribute('data-can-edit'));
    if (group.length > 1) { var p = new Image(); p.src = group[(cur + 1) % group.length].src; }
  }
  function build() {
    lb = mk('div', 'pv'); lb.hidden = true; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
    img = document.createElement('img'); img.alt = ''; img.draggable = false;
    cnt = mk('div', 'pv-c');
    var ed = mk('button', 'pv-e', L('edit')); ed.onclick = function () { var id = group[cur].pid; close(); if (id && window.mwEditPhoto) window.mwEditPhoto(id, opener); }; var x = mk('button', 'pv-x', '×', L('close')), p = mk('button', 'pv-p', rtl ? '›' : '‹', L('prev')), n = mk('button', 'pv-n', rtl ? '‹' : '›', L('next'));
    lb.appendChild(img); lb.appendChild(cnt); lb.appendChild(p); lb.appendChild(n); lb.appendChild(x); lb.appendChild(ed); document.body.appendChild(lb);
    x.onclick = close; p.onclick = function () { go(-1); }; n.onclick = function () { go(1); };
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    lb.addEventListener('wheel', function (e) { e.preventDefault(); zoomAt(scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15)); }, { passive: false });
    lb.addEventListener('pointerdown', function (e) {
      if (e.target !== img) return; pts[e.pointerId] = { x: e.clientX, y: e.clientY }; moved = false;
      try { img.setPointerCapture(e.pointerId); } catch (z) {}
      var ids = Object.keys(pts); sx = e.clientX; sy = e.clientY;
      if (ids.length === 2) { startD = dist(); startS = scale; }
    });
    lb.addEventListener('pointermove', function (e) {
      if (!pts[e.pointerId]) return; var old = pts[e.pointerId]; pts[e.pointerId] = { x: e.clientX, y: e.clientY };
      var ids = Object.keys(pts);
      if (ids.length === 2) { zoomAt(startS * dist() / startD); moved = true; }
      else if (scale > 1) { tx += e.clientX - old.x; ty += e.clientY - old.y; moved = true; apply(); }
    });
    function up(e) {
      if (!pts[e.pointerId]) return; delete pts[e.pointerId];
      if (Object.keys(pts).length) return;
      var dx = e.clientX - sx, dy = e.clientY - sy;
      if (scale <= 1 && Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) { go((dx < 0) !== rtl ? 1 : -1); return; }
      if (scale <= 1 && dy > 90 && Math.abs(dy) > Math.abs(dx)) { close(); return; }
      if (!moved) { var t = Date.now(); if (t - lastTap < 320) { scale > 1 ? reset() : zoomAt(2.5); lastTap = 0; } else lastTap = t; }
    }
    lb.addEventListener('pointerup', up); lb.addEventListener('pointercancel', function (e) { delete pts[e.pointerId]; });
  }
  function dist() { var k = Object.keys(pts); if (k.length < 2) return 1; var a = pts[k[0]], b = pts[k[1]]; return Math.max(1, Math.hypot(a.x - b.x, a.y - b.y)); }
  function zoomAt(s) { scale = Math.min(6, Math.max(1, s)); if (scale === 1) { tx = ty = 0; } apply(); }
  function open(i) { if (!lb) build(); show(i); lb.hidden = false; document.body.style.overflow = 'hidden'; lb.querySelector('.pv-x').focus(); }
  function close() { lb.hidden = true; img.removeAttribute('src'); document.body.style.overflow = ''; if (opener && opener.focus) opener.focus(); }
  function go(d) { if (group.length > 1) show(cur + d); }
  document.addEventListener('keydown', function (e) {
    if (!lb || lb.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') go(rtl ? 1 : -1);
    else if (e.key === 'ArrowRight') go(rtl ? -1 : 1);
    else if (e.key === '+' || e.key === '=') zoomAt(scale * 1.25);
    else if (e.key === '-') zoomAt(scale / 1.25);
    else if (e.key === '0') reset();
  });
  /* any photo marked data-pv (an <img>, or a link around one) opens the viewer with the others in the same .thumbs group */
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-pv]') : null;
    /* the book stacks its pages, so another page's text can sit over a photo: look under the pointer for a photo */
    if (!el && document.elementsFromPoint && !(e.target.closest && e.target.closest('a,button,input,textarea,label,select'))) {
      var under = document.elementsFromPoint(e.clientX, e.clientY);
      for (var u = 0; u < under.length && !el; u++) { var f = under[u].closest && under[u].closest('[data-pv]'); if (f) el = f; }
    }
    if (!el || e.defaultPrevented) return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button) return;
    var scope = el.closest('.thumbs'), all = scope ? Array.prototype.slice.call(scope.querySelectorAll('[data-pv]')) : [el];
    group = all.map(function (n) { var im = n.tagName === 'IMG' ? n : n.querySelector('img'); return { src: n.getAttribute('data-src') || n.getAttribute('href') || (im ? big(im.getAttribute('src')) : ''), pid: n.getAttribute('data-pid') || '' }; });
    e.preventDefault(); opener = el; open(all.indexOf(el));
  });
})();
