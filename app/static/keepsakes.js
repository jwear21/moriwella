/* Shared album and scrapbook controls on an experience: copy the link, select photos, and watch a scrapbook being made. Copyright (c) JSW Software Designs */
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-copy]') : null; if (!b) return;
    var inp = b.parentNode.querySelector('[data-copy-src]'); if (!inp) return; inp.select(); inp.setSelectionRange(0, 9999);
    var done = function () { var t = b.textContent; b.textContent = b.getAttribute('data-done') || 'Copied'; setTimeout(function () { b.textContent = t; }, 1800); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(inp.value).then(done, function () { try { document.execCommand('copy'); done(); } catch (_) {} });
    else { try { document.execCommand('copy'); done(); } catch (_) {} }
  });
  var form = document.querySelector('[data-pick-form]');
  if (form) {
    var boxes = form.querySelectorAll('.pick input'), cnt = form.querySelector('[data-pick-count]'), fmt = cnt ? cnt.getAttribute('data-fmt') : '';
    var upd = function () { var n = 0; boxes.forEach(function (b) { if (b.checked) n++; }); if (cnt) cnt.textContent = fmt.replace('{n}', n).replace('{total}', boxes.length); };
    boxes.forEach(function (b) { b.addEventListener('change', upd); }); upd();
    var all = form.querySelector('[data-pick-all]'), none = form.querySelector('[data-pick-none]');
    if (all) all.onclick = function () { boxes.forEach(function (b) { b.checked = true; }); upd(); };
    if (none) none.onclick = function () { boxes.forEach(function (b) { b.checked = false; }); upd(); };
  }
  var row = document.querySelector('.scrap-row[data-status="queued"], .scrap-row[data-status="rendering"]');
  if (row) {
    var bar = row.querySelector('.bar'), pct = row.querySelector('.scrap-pct');
    (function tick() {
      fetch(row.getAttribute('data-url'), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.status === 'done' || d.status === 'failed') { location.reload(); return; }
        if (bar) bar.style.width = d.progress + '%'; if (pct) pct.textContent = d.progress + '%'; setTimeout(tick, 4000);
      }).catch(function () { setTimeout(tick, 8000); });
    })();
  }
})();
