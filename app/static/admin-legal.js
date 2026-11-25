/* Admin: translate the legal pages one piece at a time so no request runs long. Copyright (c) JSW Software Designs */
(function () {
  var box = document.getElementById('lg-box'), go = document.getElementById('lg-go'); if (!box || !go) return;
  var st = document.getElementById('lg-status'), csrf = box.getAttribute('data-csrf'), url = box.getAttribute('data-url');
  var pieces = JSON.parse(box.getAttribute('data-pieces') || '{}'), todo = JSON.parse(document.getElementById('lg-todo').textContent || '{}');
  var jobs = []; Object.keys(todo).forEach(function (d) { todo[d].forEach(function (c) { jobs.push([d, c]); }); });
  function post(d, c, i) {
    var b = new URLSearchParams(); b.set('_csrf', csrf); b.set('doc', d); b.set('lang', c); b.set('piece', i);
    return fetch(url, { method: 'POST', body: b, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); });
  }
  function job(n) {
    if (n >= jobs.length) { st.textContent = 'Done. Reloading…'; setTimeout(function () { location.reload(); }, 1200); return; }
    var d = jobs[n][0], c = jobs[n][1], total = pieces[d] || 1;
    (function piece(i) {
      if (i >= total) { job(n + 1); return; }
      st.textContent = d + ' in ' + c + ': part ' + (i + 1) + ' of ' + total + ' (page ' + (n + 1) + ' of ' + jobs.length + ')…';
      post(d, c, i).then(function (j) {
        if (!j.ok) { st.textContent = 'Stopped at ' + d + ' / ' + c + ': ' + j.error; go.disabled = false; return; }
        piece(i + 1);
      }).catch(function () { st.textContent = 'Stopped at ' + d + ' / ' + c + ': the request failed. Press the button to continue.'; go.disabled = false; });
    })(0);
  }
  go.addEventListener('click', function () { if (!jobs.length) { st.textContent = 'Everything is current. Nothing to translate.'; return; } go.disabled = true; job(0); });
})();
