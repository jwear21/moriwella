/* Admin: translate missing phrases one language at a time so no request runs long. Copyright (c) JSW Software Designs */
(function () {
  var box = document.getElementById('tr-box'), go = document.getElementById('tr-go'); if (!box || !go) return;
  var st = document.getElementById('tr-status'), csrf = box.getAttribute('data-csrf'), url = box.getAttribute('data-url');
  var langs = []; try { langs = JSON.parse(go.getAttribute('data-langs') || '[]'); } catch (e) {} var total = 0;
  function one(i) {
    if (i >= langs.length) { legalPass(); return; }
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
  var lurl = box.getAttribute('data-legal-url'), pieces = {}, todo = {}, jobs = [];
  try { pieces = JSON.parse(box.getAttribute('data-pieces') || '{}'); todo = JSON.parse(document.getElementById('lg-todo').textContent || '{}'); } catch (e) {}
  Object.keys(todo).forEach(function (d) { todo[d].forEach(function (c) { jobs.push([d, c]); }); });
  function finish() { st.textContent = (total || jobs.length) ? 'Done: ' + total + ' phrases and ' + jobs.length + ' legal page translations. Reloading…' : 'All ' + langs.length + ' languages and the legal pages checked. Nothing was missing. Reloading…'; setTimeout(function () { location.reload(); }, 1200); }
  function legalPass() {
    (function job(n) {
      if (n >= jobs.length) { finish(); return; }
      var d = jobs[n][0], c = jobs[n][1], parts = pieces[d] || 1;
      (function piece(i) {
        if (i >= parts) { job(n + 1); return; }
        st.textContent = 'Legal page ' + d + ' in ' + c + ': part ' + (i + 1) + ' of ' + parts + ' (' + (n + 1) + ' of ' + jobs.length + ')…';
        var b = new URLSearchParams(); b.set('_csrf', csrf); b.set('doc', d); b.set('lang', c); b.set('piece', i);
        fetch(lurl, { method: 'POST', body: b, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) { st.textContent = 'Stopped at ' + d + ' / ' + c + ': ' + j.error; go.disabled = false; return; }
          piece(i + 1);
        }).catch(function () { st.textContent = 'Stopped at ' + d + ' / ' + c + ': the request failed. Press the button to continue.'; go.disabled = false; });
      })(0);
    })(0);
  }
  go.addEventListener('click', function () { go.disabled = true; total = 0; one(0); });
})();
