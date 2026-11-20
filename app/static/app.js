(function () {
  var celebOn = document.body.getAttribute('data-celebrate-on') !== '0';
  function celebrate(o) { if (celebOn && window.Moriwella) Moriwella.celebrate(o); }
  function showUnlocked(list, delay) {
    list.forEach(function (u, i) { setTimeout(function () { celebrate(u.level === 'small' ? { level: 'small' } : { level: 'milestone', title: u.title, sub: u.sub }); }, (delay || 0) + i * 6700); });
  }
  try { var pend = document.body.getAttribute('data-unlocked'); if (pend) showUnlocked(JSON.parse(pend), 900); } catch (e) {}
  try {
    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (tz && document.cookie.indexOf('mw_tz=' + encodeURIComponent(tz)) === -1) document.cookie = 'mw_tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax';
  } catch (e) {}
  /* Copy buttons */
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.querySelector(b.getAttribute('data-copy')); if (!i) return;
      i.select();
      var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy link'; }, 2000); };
      if (navigator.clipboard) navigator.clipboard.writeText(i.value).then(done, function () { document.execCommand('copy'); done(); });
      else { document.execCommand('copy'); done(); }
    });
  });

  /* Confirm before delete */
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  /* Use the person's own date and keep an unsaved draft on this device */
  var dateIn = document.getElementById('entry-date');
  if (dateIn && document.getElementById('today-label')) {
    var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
    dateIn.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    var lbl = document.getElementById('today-label');
    if (lbl) lbl.textContent = d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
  }
  var ta = document.querySelector('textarea[data-draft]');
  if (ta) {
    var key = ta.getAttribute('data-draft'), state = document.getElementById('draft-state'), t;
    try { if (!ta.value) { var s = localStorage.getItem(key); if (s) { ta.value = s; if (state) state.textContent = 'Restored your unsaved draft.'; } } } catch (e) {}
    ta.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () {
        try { localStorage.setItem(key, ta.value); if (state) state.textContent = 'Draft saved on this device.'; } catch (e) {}
      }, 500);
    });
    var f = document.getElementById('entry-form');
    if (f) f.addEventListener('submit', function () { try { localStorage.removeItem(key); } catch (e) {} });
  }

  /* Celebrations after a save */
  var c = document.getElementById('saved-note');
  if (c) {
    if (c.getAttribute('data-celebrate') === 'first') celebrate({ level: 'milestone', title: 'Your first entry', sub: 'The first page of your story is in.' });
    else celebrate({ level: 'small', origin: c });
  }

  /* Show the title box only when starting a separate entry */
  var sep = document.getElementById('separate'), tw = document.getElementById('title-wrap');
  if (sep && tw) sep.addEventListener('change', function () { tw.hidden = !sep.checked; if (sep.checked) { var ti = document.getElementById('title'); if (ti) ti.focus(); } });

  /* Photo previews before saving (gallery picker and camera) */
  var pin = document.getElementById('photos'), cam = document.getElementById('camera'), prev = document.getElementById('photo-preview'), ps = document.getElementById('photo-state');
  function showPreviews() {
    if (!prev) return;
    prev.innerHTML = '';
    var files = [];
    [pin, cam].forEach(function (i) { if (i && i.files) files = files.concat(Array.prototype.slice.call(i.files)); });
    if (ps) ps.textContent = files.length ? files.length + (files.length === 1 ? ' photo' : ' photos') + ' ready to save' : '';
    files.slice(0, 12).forEach(function (f) {
      var im = document.createElement('img'); im.alt = 'Selected photo'; im.src = URL.createObjectURL(f);
      im.onload = function () { URL.revokeObjectURL(im.src); };
      prev.appendChild(im);
    });
  }
  [pin, cam].forEach(function (i) { if (i) i.addEventListener('change', showPreviews); });

  /* Save with a visible progress bar, so a big upload never looks frozen */
  var ef = document.getElementById('entry-form'), sb = document.getElementById('save-btn'), sbText = sb ? sb.textContent : 'Save entry';
  if (ef && sb && window.XMLHttpRequest && window.FormData) {
    ef.addEventListener('submit', function (e) {
      var hasFiles = (pin && pin.files.length) || (cam && cam.files.length);
      sb.disabled = true; sb.textContent = hasFiles ? 'Saving photos…' : 'Saving…';
      if (!hasFiles) return;               /* plain save: let the browser do it */
      e.preventDefault();
      var bar = document.getElementById('upload-bar'), wrap = document.getElementById('upload-progress'), x = new XMLHttpRequest();
      if (wrap) wrap.hidden = false;
      x.open('POST', ef.action || location.href); x.setRequestHeader('X-Requested-With', 'fetch');
      x.upload.onprogress = function (p) {
        if (p.lengthComputable && bar) { var pct = Math.round(p.loaded / p.total * 100); bar.style.width = pct + '%'; sb.textContent = pct < 100 ? 'Uploading ' + pct + '%' : 'Processing photos…'; }
      };
      x.onload = function () { try { localStorage.removeItem('mw-draft-body'); } catch (er) {} var to = location.href; try { to = JSON.parse(x.responseText).redirect || to; } catch (er) {} window.location.href = to; };
      x.onerror = function () { sb.disabled = false; sb.textContent = sbText; if (ps) ps.textContent = 'The upload didn\'t go through. Your text is still here, so try again.'; if (wrap) wrap.hidden = true; };
      x.send(new FormData(ef));
    });
  }

  /* Dictation (works in Chrome and Android where speech recognition exists) */
  var dBtn = document.getElementById('dictate-btn'), bodyTa = document.getElementById('body');
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (dBtn && bodyTa && SR) {
    dBtn.hidden = false;
    var rec = null, on = false, base = '';
    var stop = function () { on = false; dBtn.setAttribute('aria-pressed', 'false'); dBtn.textContent = 'Dictate'; if (rec) { try { rec.stop(); } catch (er) {} } };
    dBtn.addEventListener('click', function () {
      if (on) { stop(); return; }
      rec = new SR(); rec.lang = navigator.language || 'en-US'; rec.continuous = true; rec.interimResults = true;
      base = bodyTa.value && !/\s$/.test(bodyTa.value) ? bodyTa.value + ' ' : bodyTa.value;
      var finalText = '';
      rec.onresult = function (ev) {
        var interim = '';
        for (var i = ev.resultIndex; i < ev.results.length; i++) { var t = ev.results[i][0].transcript; if (ev.results[i].isFinal) finalText += t + ' '; else interim += t; }
        bodyTa.value = base + finalText + interim;
        bodyTa.dispatchEvent(new Event('input'));
      };
      rec.onerror = function (ev) { if (ev.error === 'not-allowed' && ps) ps.textContent = 'Allow the microphone to dictate.'; stop(); };
      rec.onend = function () { if (on) { try { rec.start(); } catch (er) { stop(); } } };
      try { rec.start(); on = true; dBtn.setAttribute('aria-pressed', 'true'); dBtn.textContent = 'Stop dictating'; } catch (er) { stop(); }
    });
  }

  /* Water tracker */
  var w = document.querySelector('.water');
  if (w) {
    var oz = parseInt(w.getAttribute('data-oz'), 10) || 0;
    var cups = w.querySelectorAll('.cup'), label = document.getElementById('water-oz');
    var paint = function () { cups.forEach(function (b, i) { var on = oz >= (i + 1) * 8; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }); label.textContent = oz; };
    cups.forEach(function (b) {
      b.addEventListener('click', function () {
        var n = parseInt(b.getAttribute('data-cup'), 10) * 8;
        var prevOz = oz; oz = (oz === n) ? n - 8 : n; paint(); if (oz > prevOz && oz < 64) celebrate({ level: 'splash', origin: b });
        var body = new URLSearchParams({ day: w.getAttribute('data-day'), oz: oz, _csrf: w.getAttribute('data-csrf') });
        fetch(w.getAttribute('data-url'), { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) { if (j.unlocked && j.unlocked.length) showUnlocked(j.unlocked, 150); else if (j.goal) celebrate(j.allDone ? { level: 'milestone', title: 'All your goals are done', sub: 'Water, and everything else. Great day.' } : { level: 'milestone', title: '64 ounces', sub: 'You hit your water goal for the day.' }); })
          .catch(function () { oz = prevOz; paint(); });
      });
    });
  }

  /* Experience section prompts */
  var sp = document.getElementById('section-pick'), bodyEl = document.getElementById('body');
  if (sp && bodyEl) {
    var setPrompt = function () { var o = sp.options[sp.selectedIndex]; bodyEl.placeholder = (o && o.getAttribute('data-prompt')) || 'What happened?'; };
    sp.addEventListener('change', setPrompt); setPrompt();
  }

  /* Daily goals */
  var gc = document.getElementById('goals-card');
  if (gc) {
    gc.querySelectorAll('.check').forEach(function (b) {
      b.addEventListener('click', function () {
        var li = b.closest('.item');
        fetch(b.getAttribute('data-goal-url'), { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: new URLSearchParams({ day: gc.getAttribute('data-day'), _csrf: gc.getAttribute('data-csrf') }), credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (!j.ok) return;
            li.classList.toggle('done', j.done); b.setAttribute('aria-checked', j.done ? 'true' : 'false');
            document.getElementById('goals-done').textContent = gc.querySelectorAll('.item.done').length;
            if (j.done) { celebrate({ level: 'burst', origin: b }); if (j.unlocked && j.unlocked.length) showUnlocked(j.unlocked, 700); else if (j.allDone) setTimeout(function () { celebrate({ level: 'milestone', title: 'All your goals are done', sub: 'Every goal checked off today. Great day.' }); }, 700); }
          }).catch(function () {});
      });
    });
  }

  /* List check-offs without a page reload */
  document.querySelectorAll('.toggle-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = f.querySelector('.check'), li = f.closest('.item');
      fetch(f.action, { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: new URLSearchParams(new FormData(f)), credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) return;
          li.classList.toggle('done', j.done); btn.setAttribute('aria-checked', j.done ? 'true' : 'false');
          if (j.done && j.next) { celebrate({ level: 'burst', origin: btn }); setTimeout(function () { location.reload(); }, 1100); return; }
          if (j.done) { celebrate({ level: 'burst', origin: btn }); if (j.unlocked && j.unlocked.length) showUnlocked(j.unlocked, 600); else if (j.allDone) setTimeout(function () { celebrate({ level: 'milestone', title: 'List complete', sub: 'Everything on "' + j.list + '" is done.' }); }, 500); }
        }).catch(function () { f.submit(); });
    });
  });
})();
/* show paragraph times in the device's own local time */
document.querySelectorAll('time.stamp[data-ts]').forEach(function (t) {
  try { t.textContent = new Date(Number(t.dataset.ts) * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (e) {}
});
/* little things (Track) */
(function () {
  var card = document.getElementById('little'); if (!card) return;
  function save(row, value, done) {
    fetch(card.getAttribute('data-url'), { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin',
      body: new URLSearchParams({ key: row.getAttribute('data-key'), day: card.getAttribute('data-day'), value: value == null ? '' : value, _csrf: card.getAttribute('data-csrf') }) })
      .then(function (r) { return r.json(); }).then(function (j) { if (done) done(j); }).catch(function () {});
  }
  card.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return; var row = b.closest('.lt-row'); if (!row) return;
    if (b.classList.contains('lt-check')) {
      var on = b.getAttribute('aria-checked') !== 'true'; b.setAttribute('aria-checked', on ? 'true' : 'false'); save(row, on ? 1 : 0);
    } else if (b.classList.contains('lt-inc') || b.classList.contains('lt-dec')) {
      var st = row.querySelector('.lt-step'), out = st.querySelector('output'), max = parseInt(st.getAttribute('data-max'), 10) || 12;
      var n = Math.max(0, Math.min(max, (parseInt(out.textContent, 10) || 0) + (b.classList.contains('lt-inc') ? 1 : -1)));
      out.textContent = n; save(row, n);
    } else if (b.getAttribute('role') === 'radio') {
      var was = b.getAttribute('aria-checked') === 'true';
      row.querySelectorAll('[role=radio]').forEach(function (x) { x.setAttribute('aria-checked', 'false'); });
      if (!was) b.setAttribute('aria-checked', 'true'); save(row, was ? 0 : b.getAttribute('data-v'));
    }
  });
  card.addEventListener('change', function (e) {
    var inp = e.target; if (inp.type !== 'number') return; save(inp.closest('.lt-row'), inp.value);
  });
})();
/* ===== Moriwella loader: the logo floats in the middle with a shadow gliding across it ===== */
(function () {
  var el = null;
  function build() {
    el = document.createElement('div'); el.className = 'mw-loader'; el.setAttribute('role', 'status'); el.setAttribute('aria-live', 'polite');
    el.innerHTML = '<div class="mw-loader-logo"><img src="/assets/logo_loader.svg" alt="Moriwella"><span class="sweep" aria-hidden="true"></span></div><p class="mw-loader-msg"></p><p class="mw-loader-detail"></p>';
    document.body.appendChild(el);
  }
  window.mwLoader = {
    show: function (msg, detail) {
      if (!el) build();
      el.querySelector('.mw-loader-msg').textContent = msg || 'One moment';
      el.querySelector('.mw-loader-detail').textContent = detail || '';
      void el.offsetWidth; el.classList.add('on');
    },
    hide: function () { if (el) el.classList.remove('on'); }
  };
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f || e.defaultPrevented) return;
    var dl = f.getAttribute && f.getAttribute('data-download');
    if (dl) {
      e.preventDefault(); window.mwLoader.show(dl + '…', f.getAttribute('data-detail'));
      fetch(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin' }).then(function (r) {
        if (!r.ok || (r.headers.get('content-type') || '').indexOf('zip') === -1) throw new Error('bad');
        var name = (/filename="([^"]+)"/.exec(r.headers.get('content-disposition') || '') || [])[1] || 'moriwella-journal.zip';
        return r.blob().then(function (b) {
          var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name; document.body.appendChild(a); a.click();
          setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 4000);
        });
      }).then(function () { window.mwLoader.hide(); }).catch(function () { window.mwLoader.hide(); alert('Sorry, the export could not be made. Please try again.'); });
      return;
    }
    var m = f.getAttribute && f.getAttribute('data-loader');
    if (m) setTimeout(function () { if (!e.defaultPrevented) window.mwLoader.show(m + '…', f.getAttribute('data-detail')); }, 0);
  });
  window.addEventListener('pageshow', function (ev) { if (ev.persisted && el) el.classList.remove('on'); });
  var fi = document.getElementById('xfer-file');
  if (fi) {
    fi.addEventListener('change', function () { document.getElementById('filepick-n').textContent = fi.files[0] ? fi.files[0].name : 'No file chosen'; });
    document.querySelectorAll('.src input[type=radio]').forEach(function (r) {
      function set() { if (r.checked) fi.setAttribute('accept', r.getAttribute('data-accept') || ''); }
      r.addEventListener('change', set); set();
    });
  }
})();
