/* Shared album lightbox: tap a photo to enlarge it, swipe or use the arrows to move, Esc to close. Copyright (c) JSW Software Designs */
(function () {
  var grid = document.getElementById('alb-grid'); if (!grid) return;
  var links = Array.prototype.slice.call(grid.querySelectorAll('.alb-ph')), cur = 0, lb = null, img, cnt, dl, sx = null;
  var canDl = grid.getAttribute('data-dl') === '1';
  function mk(tag, cls, txt, label) { var e = document.createElement(tag); e.className = cls; if (txt) e.textContent = txt; if (label) e.setAttribute('aria-label', label); return e; }
  function build() {
    lb = mk('div', 'lb'); lb.hidden = true; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
    img = document.createElement('img'); img.alt = '';
    var x = mk('button', 'lb-x', '×', grid.getAttribute('data-close')), p = mk('button', 'lb-p', '‹', grid.getAttribute('data-prev')), n = mk('button', 'lb-n', '›', grid.getAttribute('data-next'));
    x.type = p.type = n.type = 'button'; cnt = mk('div', 'lb-c');
    dl = mk('a', 'lb-d', grid.getAttribute('data-save')); if (!canDl) dl.hidden = true;
    lb.appendChild(img); lb.appendChild(cnt); lb.appendChild(p); lb.appendChild(n); lb.appendChild(x); lb.appendChild(dl); document.body.appendChild(lb);
    x.onclick = close; p.onclick = function () { go(-1); }; n.onclick = function () { go(1); };
    lb.addEventListener('click', function (e) { if (e.target === lb || e.target === img) close(); });
    lb.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; sx = null; if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1); });
  }
  function show(i) {
    cur = (i + links.length) % links.length; var a = links[cur];
    img.src = a.getAttribute('href'); cnt.textContent = (cur + 1) + ' / ' + links.length;
    if (canDl) dl.setAttribute('href', a.getAttribute('data-full'));
    var nx = links[(cur + 1) % links.length]; if (nx) { var pre = new Image(); pre.src = nx.getAttribute('href'); }
  }
  function open(i) { if (!lb) build(); show(i); lb.hidden = false; document.body.style.overflow = 'hidden'; lb.querySelector('.lb-x').focus(); }
  function close() { lb.hidden = true; document.body.style.overflow = ''; links[cur].focus(); }
  function go(d) { show(cur + d); }
  links.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); open(i); }); });
  document.addEventListener('keydown', function (e) {
    if (!lb || lb.hidden) return;
    if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') go(-1); else if (e.key === 'ArrowRight') go(1);
  });
})();
