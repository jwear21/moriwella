/* Admin > Backups: start a backup in the background and show the Moriwella loader until it finishes.   Copyright (c) JSW Software Designs */
(function () {
  var box = document.getElementById('bk-box'); if (!box) return;
  var statusUrl = box.getAttribute('data-status'), form = document.getElementById('bk-start'), timer = null, t0 = Date.now();
  function show(msg) { if (window.mwLoader) window.mwLoader.show('Backing up Moriwella…', msg || 'Please keep this page open.'); }
  function poll() {
    fetch(statusUrl, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (s) {
      if (s.state === 'running') {
        show();
        timer = setTimeout(poll, 2000);
      } else { if (window.mwLoader) window.mwLoader.hide(); location.href = location.pathname + '?tab=backups'; }
    }).catch(function () { timer = setTimeout(poll, 4000); });
  }
  if (box.getAttribute('data-running') === '1') { show(); poll(); }
  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault(); show('Starting'); t0 = Date.now();
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (r) { if (r.ok) poll(); else { if (window.mwLoader) window.mwLoader.hide(); alert(r.error || 'Could not start the backup.'); } })
      .catch(function () { if (window.mwLoader) window.mwLoader.hide(); alert('Could not start the backup.'); });
  });
})();
