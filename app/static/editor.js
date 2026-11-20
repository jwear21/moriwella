/* Moriwella Photo Studio: crop, rotate, looks and adjustments, entirely in the browser.
   The original photo always stays on the server; saving stores an edited copy that can be reverted. */
(function () {
  var script = document.currentScript, BASE = script.getAttribute('data-base') || '', CSRF = script.getAttribute('data-csrf') || '';
  var LOOKS = [
    { id: 'original', name: T('js.original', 'Original'), p: {} },
    { id: 'golden', name: T('js.golden_hour', 'Golden hour'), p: { b: 4, c: 8, s: 14, w: 26 } },
    { id: 'coast', name: T('js.coastline', 'Coastline'), p: { b: 2, c: 10, s: 8, w: -22 } },
    { id: 'fade', name: T('js.soft_fade', 'Soft fade'), p: { b: 6, c: -14, s: -10, w: 8, fade: 22 } },
    { id: 'vivid', name: T('js.vivid', 'Vivid'), p: { c: 14, s: 38 } },
    { id: 'mono', name: T('js.black_white', 'Black & white'), p: { c: 10, s: -100 } },
    { id: 'sepia', name: T('js.keepsake', 'Keepsake'), p: { c: -4, sepia: 70, b: 2 } }
  ];
  var ASPECTS = [['free', T('js.free', 'Free'), 0], ['orig', T('js.original', 'Original'), -1], ['sq', T('js.square', 'Square'), 1], ['45', '4:5', 4 / 5], ['32', '3:2', 3 / 2], ['169', '16:9', 16 / 9]];
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

  /* Pixel pipeline: the same math is used for previews and for the saved file */
  function applyLook(ctx, w, h, a, look) {
    var q = {}, k; for (k in look) q[k] = look[k];
    var b = (q.b || 0) + (a.b || 0), c = (q.c || 0) + (a.c || 0), s = (q.s || 0) + (a.s || 0), wm = (q.w || 0) + (a.w || 0), fade = q.fade || 0, sep = q.sepia || 0;
    if (!b && !c && !s && !wm && !fade && !sep) return;
    var img = ctx.getImageData(0, 0, w, h), d = img.data, n = d.length;
    var bf = b * 2.55, cf = (259 * (c * 2.55 + 255)) / (255 * (259 - c * 2.55)), sf = 1 + s / 100, wr = wm * 0.6, wb = -wm * 0.6, f = fade * 0.9, sp = sep / 100;
    for (var i = 0; i < n; i += 4) {
      var r = d[i], g = d[i + 1], bl = d[i + 2];
      r = cf * (r - 128) + 128 + bf; g = cf * (g - 128) + 128 + bf; bl = cf * (bl - 128) + 128 + bf;
      var l = 0.299 * r + 0.587 * g + 0.114 * bl;
      r = l + (r - l) * sf; g = l + (g - l) * sf; bl = l + (bl - l) * sf;
      r += wr; bl += wb; g += wm * 0.12;
      if (sp) { var tr = l * 1.07 + 20, tg = l * 0.95 + 8, tb = l * 0.78; r = r + (tr - r) * sp; g = g + (tg - g) * sp; bl = bl + (tb - bl) * sp; }
      if (f) { r = r + (235 - r) * f / 255 * 0.5 + f * 0.3; g = g + (235 - g) * f / 255 * 0.5 + f * 0.3; bl = bl + (235 - bl) * f / 255 * 0.5 + f * 0.3; }
      d[i] = r < 0 ? 0 : r > 255 ? 255 : r; d[i + 1] = g < 0 ? 0 : g > 255 ? 255 : g; d[i + 2] = bl < 0 ? 0 : bl > 255 ? 255 : bl;
    }
    ctx.putImageData(img, 0, 0);
  }

  /* Draw the source rotated and flipped into a canvas whose long edge is at most max */
  function transformed(img, st, max) {
    var sw = img.naturalWidth, sh = img.naturalHeight, swap = st.rot % 2 === 1;
    var tw = swap ? sh : sw, th = swap ? sw : sh, sc = Math.min(1, max / Math.max(tw, th));
    var cw = Math.round(tw * sc), ch = Math.round(th * sc), cv = document.createElement('canvas'); cv.width = cw; cv.height = ch;
    var x = cv.getContext('2d'); x.translate(cw / 2, ch / 2); x.rotate(st.rot * Math.PI / 2); if (st.flip) x.scale(-1, 1);
    var dw = Math.round(sw * sc), dh = Math.round(sh * sc); x.drawImage(img, -dw / 2, -dh / 2, dw, dh);
    return cv;
  }

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }

  function open(photoId, opener) {
    var img = new Image(), back = el('div', 'ps-back'), box = el('div', 'ps'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', T('js.photo_studio', 'Photo studio'));
    box.innerHTML = '<div class="ps-head"><h2 class="ps-title">Photo studio</h2><button type="button" class="ps-x" aria-label="Close">&times;</button></div><div class="ps-load">Opening your photo…</div>';
    back.appendChild(box); document.body.appendChild(back); document.body.style.overflow = 'hidden';
    var closeBtn = box.querySelector('.ps-x');
    function close() { document.body.style.overflow = ''; back.remove(); document.removeEventListener('keydown', onKey); if (opener) opener.focus(); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey); closeBtn.addEventListener('click', close);
    back.addEventListener('mousedown', function (e) { if (e.target === back) close(); });
    img.onerror = function () { box.querySelector('.ps-load').textContent = T('js.that_photo_could_not_be', 'That photo could not be opened.'); };
    img.onload = function () { build(); };
    img.src = BASE + '/photo/' + photoId + '/orig?t=' + Date.now();

    function build() {
      var st = { rot: 0, flip: false, look: 'original', adj: { b: 0, c: 0, s: 0, w: 0 }, aspect: 'free', crop: { x: 0, y: 0, w: 1, h: 1 }, tab: 'crop' };
      var work, view = el('canvas'), vctx = view.getContext('2d', { willReadFrequently: true }), raf = 0;
      box.querySelector('.ps-load').remove();
      var body = el('div', 'ps-body'), stage = el('div', 'ps-stage'), wrap = el('div', 'ps-wrap'), cropEl = el('div', 'ps-crop on');
      wrap.appendChild(view); wrap.appendChild(cropEl); stage.appendChild(wrap);
      var side = el('div', 'ps-side'), tabs = el('div', 'ps-tabs'); tabs.setAttribute('role', 'tablist');
      var panels = {};
      [['crop', T('js.crop', 'Crop')], ['look', T('js.looks', 'Looks')], ['adjust', T('js.adjust', 'Adjust')]].forEach(function (t) {
        var b = el('button', 'ps-tab', t[1]); b.type = 'button'; b.setAttribute('role', 'tab'); b.dataset.tab = t[0];
        b.addEventListener('click', function () { setTab(t[0]); }); tabs.appendChild(b);
        panels[t[0]] = el('div', 'ps-panel'); panels[t[0]].setAttribute('role', 'tabpanel');
      });
      side.appendChild(tabs); Object.keys(panels).forEach(function (k) { side.appendChild(panels[k]); });
      body.appendChild(stage); body.appendChild(side);

      /* Crop panel */
      var pc = panels.crop;
      pc.appendChild(el('div', 'ps-lbl', T('js.shape', 'Shape')));
      var chips = el('div', 'ps-chips'); pc.appendChild(chips);
      ASPECTS.forEach(function (a) {
        var b = el('button', 'ps-chip', a[1]); b.type = 'button'; b.dataset.a = a[0]; b.setAttribute('aria-pressed', a[0] === 'free' ? 'true' : 'false');
        b.addEventListener('click', function () { st.aspect = a[0]; chips.querySelectorAll('.ps-chip').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); applyAspect(); drawCrop(); });
        chips.appendChild(b);
      });
      pc.appendChild(el('div', 'ps-lbl', T('js.turn', 'Turn')));
      var turn = el('div', 'ps-chips'); pc.appendChild(turn);
      [[T('js.rotate_left', 'Rotate left'), function () { st.rot = (st.rot + 3) % 4; resetCrop(); }], [T('js.rotate_right', 'Rotate right'), function () { st.rot = (st.rot + 1) % 4; resetCrop(); }], [T('js.mirror', 'Mirror'), function () { st.flip = !st.flip; render(); }]].forEach(function (a) {
        var b = el('button', 'ps-chip', a[0]); b.type = 'button'; b.addEventListener('click', a[1]); turn.appendChild(b);
      });
      pc.appendChild(el('p', 'ps-msg', T('js.drag_the_gold_corners_to', 'Drag the gold corners to crop. Drag inside the box to move it.')));

      /* Looks panel */
      var pl = panels.look, grid = el('div', 'ps-looks'); pl.appendChild(grid);
      var lookBtns = {};
      LOOKS.forEach(function (L) {
        var b = el('button', 'ps-look'); b.type = 'button'; b.setAttribute('aria-pressed', L.id === 'original' ? 'true' : 'false');
        var cv = el('canvas'); b.appendChild(cv); b.appendChild(document.createTextNode(L.name)); lookBtns[L.id] = { b: b, cv: cv, L: L };
        b.addEventListener('click', function () { st.look = L.id; Object.keys(lookBtns).forEach(function (k) { lookBtns[k].b.setAttribute('aria-pressed', k === L.id ? 'true' : 'false'); }); render(); });
        grid.appendChild(b);
      });

      /* Adjust panel */
      var pa = panels.adjust, outs = {};
      [['b', T('js.brightness', 'Brightness')], ['c', T('js.contrast', 'Contrast')], ['s', T('js.color', 'Color')], ['w', T('js.warmth', 'Warmth')]].forEach(function (a) {
        var wr = el('div', 'ps-sl'), id = 'ps-' + a[0], lb = el('label'); lb.htmlFor = id; lb.innerHTML = '<span>' + a[1] + '</span>'; var o = el('output', '', '0'); lb.appendChild(o);
        var r = el('input'); r.type = 'range'; r.min = -50; r.max = 50; r.value = 0; r.id = id; outs[a[0]] = o;
        r.addEventListener('input', function () { st.adj[a[0]] = +r.value; o.textContent = r.value; render(); });
        wr.appendChild(lb); wr.appendChild(r); pa.appendChild(wr); outs[a[0] + 'r'] = r;
      });
      var reset = el('button', 'ps-link', T('js.reset_adjustments', 'Reset adjustments')); reset.type = 'button';
      reset.addEventListener('click', function () { ['b', 'c', 's', 'w'].forEach(function (k) { st.adj[k] = 0; outs[k + 'r'].value = 0; outs[k].textContent = '0'; }); render(); }); pa.appendChild(reset);

      /* Footer */
      var foot = el('div', 'ps-foot'), msg = el('span', 'ps-msg'), g1 = el('div', 'grp'), g2 = el('div', 'grp');
      var revert = el('button', 'ps-link', T('js.revert_to_original', 'Revert to original')); revert.type = 'button';
      var cancel = el('button', 'ps-btn', T('js.cancel', 'Cancel')); cancel.type = 'button'; cancel.addEventListener('click', close);
      var save = el('button', 'ps-btn ps-btn--go', T('js.save_photo', 'Save photo')); save.type = 'button';
      g1.appendChild(revert); g1.appendChild(msg); g2.appendChild(cancel); g2.appendChild(save); foot.appendChild(g1); foot.appendChild(g2);
      box.appendChild(body); box.appendChild(foot);

      function setTab(t) {
        st.tab = t;
        tabs.querySelectorAll('.ps-tab').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tab === t ? 'true' : 'false'); });
        Object.keys(panels).forEach(function (k) { panels[k].hidden = k !== t; });
        cropEl.style.display = t === 'crop' ? '' : 'none';
      }

      /* Working canvas and rendering */
      function currentLook() { for (var i = 0; i < LOOKS.length; i++) if (LOOKS[i].id === st.look) return LOOKS[i].p; return {}; }
      function fit() {
        var maxW = Math.max(240, stage.clientWidth - 32), maxH = Math.max(200, Math.min(window.innerHeight * 0.62, stage.clientHeight - 32 || 480));
        return Math.max(300, Math.min(1400, Math.min(maxW * (window.devicePixelRatio > 1 ? 1.5 : 1), maxH * 1.8)));
      }
      function render() {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
          if (!work) { work = transformed(img, st, 1400); }
          view.width = work.width; view.height = work.height; vctx.drawImage(work, 0, 0);
          applyLook(vctx, view.width, view.height, st.adj, currentLook());
          drawCrop();
        });
      }
      function rebuildWork() { work = transformed(img, st, 1400); }
      function resetCrop() { st.crop = { x: 0, y: 0, w: 1, h: 1 }; rebuildWork(); applyAspect(); render(); }
      function aspectValue() {
        if (st.aspect === 'free') return 0;
        if (st.aspect === 'orig') return work.width / work.height;
        for (var i = 0; i < ASPECTS.length; i++) if (ASPECTS[i][0] === st.aspect) return ASPECTS[i][2];
        return 0;
      }
      function applyAspect() {
        var r = aspectValue(); if (!r) return;
        var cw = work.width, ch = work.height, w = cw, h = cw / r; if (h > ch) { h = ch; w = ch * r; }
        st.crop = { x: (cw - w) / 2 / cw, y: (ch - h) / 2 / ch, w: w / cw, h: h / ch };
      }

      /* Crop overlay */
      var shade = [0, 1, 2, 3].map(function () { var s = el('div', 'ps-shade'); cropEl.appendChild(s); return s; });
      var cbox = el('div', 'ps-box'); cropEl.appendChild(cbox);
      var handles = ['nw', 'ne', 'sw', 'se'].map(function (n) { var h = el('div', 'ps-h'); h.dataset.h = n; cropEl.appendChild(h); return h; });
      function drawCrop() {
        if (!work) return;
        var W = view.clientWidth || work.width, H = view.clientHeight || work.height, c = st.crop;
        var x = c.x * W, y = c.y * H, w = c.w * W, h = c.h * H;
        function pos(e, l, t, ww, hh) { e.style.left = l + 'px'; e.style.top = t + 'px'; e.style.width = Math.max(0, ww) + 'px'; e.style.height = Math.max(0, hh) + 'px'; }
        pos(shade[0], 0, 0, W, y); pos(shade[1], 0, y + h, W, H - y - h); pos(shade[2], 0, y, x, h); pos(shade[3], x + w, y, W - x - w, h);
        pos(cbox, x, y, w, h);
        handles.forEach(function (hd) { hd.style.left = (hd.dataset.h.indexOf('w') > -1 ? x : x + w) + 'px'; hd.style.top = (hd.dataset.h.indexOf('n') > -1 ? y : y + h) + 'px'; });
      }
      var drag = null;
      cropEl.addEventListener('pointerdown', function (e) {
        var rect = view.getBoundingClientRect(), px = (e.clientX - rect.left) / rect.width, py = (e.clientY - rect.top) / rect.height, c = st.crop;
        var hnd = e.target.dataset && e.target.dataset.h;
        if (hnd) drag = { mode: 'size', h: hnd, c0: { x: c.x, y: c.y, w: c.w, h: c.h } };
        else if (px >= c.x && px <= c.x + c.w && py >= c.y && py <= c.y + c.h) drag = { mode: 'move', ox: px - c.x, oy: py - c.y };
        else return;
        cropEl.setPointerCapture(e.pointerId); e.preventDefault();
      });
      cropEl.addEventListener('pointermove', function (e) {
        if (!drag) return;
        var rect = view.getBoundingClientRect(), px = clamp((e.clientX - rect.left) / rect.width, 0, 1), py = clamp((e.clientY - rect.top) / rect.height, 0, 1), c = st.crop, minS = 0.06;
        if (drag.mode === 'move') { c.x = clamp(px - drag.ox, 0, 1 - c.w); c.y = clamp(py - drag.oy, 0, 1 - c.h); }
        else {
          var o = drag.c0, ax = drag.h.indexOf('w') > -1 ? o.x + o.w : o.x, ay = drag.h.indexOf('n') > -1 ? o.y + o.h : o.y;
          var nw = Math.max(minS, Math.abs(px - ax)), nh = Math.max(minS, Math.abs(py - ay)), r = aspectValue();
          if (r) { var pr = r * work.height / work.width; nh = nw / pr; if (nh > 1) { nh = 1; nw = pr; } }
          var left = px < ax ? ax - nw : ax, top = py < ay ? ay - nh : ay;
          left = clamp(left, 0, 1 - nw); top = clamp(top, 0, 1 - nh);
          if (left + nw > 1) nw = 1 - left; if (top + nh > 1) nh = 1 - top;
          c.x = left; c.y = top; c.w = nw; c.h = nh;
        }
        drawCrop();
      });
      function endDrag() { drag = null; }
      cropEl.addEventListener('pointerup', endDrag); cropEl.addEventListener('pointercancel', endDrag);
      window.addEventListener('resize', drawCrop);

      /* Look thumbnails */
      function paintLookThumbs() {
        var tw = 120, sc = tw / Math.max(img.naturalWidth, img.naturalHeight), w = Math.max(1, Math.round(img.naturalWidth * sc)), h = Math.max(1, Math.round(img.naturalHeight * sc));
        Object.keys(lookBtns).forEach(function (k) { var cv = lookBtns[k].cv; cv.width = w; cv.height = h; var x = cv.getContext('2d', { willReadFrequently: true }); x.drawImage(img, 0, 0, w, h); applyLook(x, w, h, { b: 0, c: 0, s: 0, w: 0 }, lookBtns[k].L.p); });
      }

      /* Save: render at full quality from the original */
      function exportBlob(cb) {
        var full = transformed(img, st, 3600), c = st.crop, sx = Math.round(c.x * full.width), sy = Math.round(c.y * full.height), sw = Math.max(1, Math.round(c.w * full.width)), sh = Math.max(1, Math.round(c.h * full.height));
        var out = el('canvas'); out.width = sw; out.height = sh; var x = out.getContext('2d', { willReadFrequently: true });
        x.fillStyle = '#fff'; x.fillRect(0, 0, sw, sh); x.drawImage(full, sx, sy, sw, sh, 0, 0, sw, sh);
        applyLook(x, sw, sh, st.adj, currentLook());
        out.toBlob(cb, 'image/jpeg', 0.92);
      }
      function refreshOnPage(v) {
        document.querySelectorAll('[data-photo="' + photoId + '"] img, [data-photo-img="' + photoId + '"]').forEach(function (i) { i.src = BASE + '/photo/' + photoId + '/thumb?v=' + v; });
        document.querySelectorAll('[data-photo="' + photoId + '"] a').forEach(function (a) { a.href = BASE + '/photo/' + photoId + '/web?v=' + v; });
      }
      save.addEventListener('click', function () {
        save.disabled = true; save.textContent = 'Saving…'; msg.textContent = '';
        exportBlob(function (blob) {
          if (!blob) { save.disabled = false; save.textContent = T('js.save_photo', 'Save photo'); msg.textContent = T('js.this_browser_could_not_export', 'This browser could not export the photo.'); return; }
          var fd = new FormData(); fd.append('_csrf', CSRF); fd.append('edited', blob, 'edited.jpg');
          fetch(BASE + '/photos/' + photoId + '/edit', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (!j.ok) throw new Error(j.error || T('js.could_not_save', 'Could not save.')); refreshOnPage(j.v); close(); })
            .catch(function (er) { save.disabled = false; save.textContent = T('js.save_photo', 'Save photo'); msg.textContent = er.message || T('js.could_not_save_please_try', 'Could not save. Please try again.'); });
        });
      });
      revert.addEventListener('click', function () {
        if (!confirm(T('js.go_back_to_the_original', 'Go back to the original photo? Your edits will be removed.'))) return;
        var fd = new FormData(); fd.append('_csrf', CSRF);
        fetch(BASE + '/photos/' + photoId + '/revert', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (j) { if (j.ok) { refreshOnPage(j.v); close(); } else msg.textContent = T('js.could_not_revert', 'Could not revert.'); })
          .catch(function () { msg.textContent = T('js.could_not_revert', 'Could not revert.'); });
      });

      setTab('crop'); rebuildWork(); paintLookThumbs(); render();
      closeBtn.focus();
    }
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-edit-photo]');
    if (b) { e.preventDefault(); open(b.getAttribute('data-edit-photo'), b); }
  });
})();
