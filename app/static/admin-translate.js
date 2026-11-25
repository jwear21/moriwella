/* Admin: translate missing phrases one language at a time so no request runs long. Copyright (c) JSW Software Designs */
(function () {
  var box = document.getElementById('tr-box'), go = document.getElementById('tr-go'); if (!box || !go) return;
  var st = document.getElementById('tr-status'), csrf = box.getAttribute('data-csrf'), url = box.getAttribute('data-url');
  var langs = []; try { langs = JSON.parse(go.getAttribute('data-langs') || '[]'); } catch (e) {} var total = 0;
  function one(i) {
    if (i >= langs.length) { st.textContent = total ? 'Done: ' + total + ' phrases translated. Reloading…' : 'All ' + langs.length + ' languages checked. Nothing was missing. Reloading…'; setTimeout(function () { location.reload(); }, 1200); return; }
    var c = langs[i]; st.textContent = 'Translating ' + c + ' (' + (i + 1) + ' of ' + langs.length + ')…';
    (function pass() {
      var b = new URLSearchParams(); b.set('_csrf', csrf); b.set('lang', c);
      fetch(url, { method: 'POST', body: b, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { st.textContent = 'Stopped at ' + c + ': ' + j.error; go.disabled = false; return; }
        total += j.done; var n = box.querySelector('[data-tr-n="' + c + '"]'); if (n) n.textContent = j.left;
        if (j.left > 0 && j.done > 0) pass(); else one(i + 1);
      }).catch(function () { st.textContent = 'Stopped at ' + c + ': the request failed. Try again.'; go.disabled = false; });
    })();
  }
  go.addEventListener('click', function () { go.disabled = true; total = 0; one(0); });
})();
