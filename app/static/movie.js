/* Memory movie progress: polls while a movie is being made and refreshes the page when it finishes. Copyright (c) JSW Software Designs */
(function () {
  var row = document.querySelector('.movie-row[data-status="queued"], .movie-row[data-status="rendering"]');
  if (!row) return;
  var bar = row.querySelector('.bar'), pct = row.querySelector('.movie-pct'), state = row.querySelector('.movie-state');
  function tick() {
    fetch(row.getAttribute('data-url'), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.status === 'done' || d.status === 'failed') { location.reload(); return; }
      if (bar) bar.style.width = d.progress + '%';
      if (pct) pct.textContent = d.progress + '%';
      if (state) state.textContent = d.status === 'queued' ? 'Waiting for its turn' : T('js.making_your_movie', 'Making your movie');
      setTimeout(tick, 4000);
    }).catch(function () { setTimeout(tick, 8000); });
  }
  setTimeout(tick, 3000);
})();
