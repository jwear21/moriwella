(function () {
  var celebOn = document.body.getAttribute('data-celebrate-on') !== '0';
  function celebrate(o) { if (celebOn && window.Moriwella) Moriwella.celebrate(o); }
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

  /* Photo previews before saving */
  var pin = document.getElementById('photos'), prev = document.getElementById('photo-preview'), ps = document.getElementById('photo-state');
  if (pin && prev) pin.addEventListener('change', function () {
    prev.innerHTML = '';
    var files = Array.prototype.slice.call(pin.files);
    if (ps) ps.textContent = files.length ? files.length + (files.length === 1 ? ' photo' : ' photos') + ' ready to save' : '';
    files.slice(0, 12).forEach(function (f) {
      var im = document.createElement('img'); im.alt = 'Selected photo'; im.src = URL.createObjectURL(f);
      im.onload = function () { URL.revokeObjectURL(im.src); };
      prev.appendChild(im);
    });
  });

  /* Water tracker */
  var w = document.querySelector('.water');
  if (w) {
    var oz = parseInt(w.getAttribute('data-oz'), 10) || 0;
    var cups = w.querySelectorAll('.cup'), label = document.getElementById('water-oz');
    var paint = function () { cups.forEach(function (b, i) { var on = oz >= (i + 1) * 8; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }); label.textContent = oz; };
    cups.forEach(function (b) {
      b.addEventListener('click', function () {
        var n = parseInt(b.getAttribute('data-cup'), 10) * 8;
        var prevOz = oz; oz = (oz === n) ? n - 8 : n; paint();
        var body = new URLSearchParams({ day: w.getAttribute('data-day'), oz: oz, _csrf: w.getAttribute('data-csrf') });
        fetch(w.getAttribute('data-url'), { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) { if (j.goal) celebrate({ level: 'milestone', title: '64 ounces', sub: 'You hit your water goal for the day.' }); })
          .catch(function () { oz = prevOz; paint(); });
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
          if (j.done) { celebrate({ level: 'small', origin: btn }); if (j.allDone) setTimeout(function () { celebrate({ level: 'milestone', title: 'List complete', sub: 'Everything on "' + j.list + '" is done.' }); }, 500); }
        }).catch(function () { f.submit(); });
    });
  });
})();
