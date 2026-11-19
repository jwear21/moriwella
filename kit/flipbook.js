/* Moriwella page-turning book.
   var book = Moriwella.FlipBook(container, [htmlOrNode, ...], { onChange(label) })
   book.next(), book.prev()
   Two-page spread on wide screens, one page at a time on phones. Swipe, arrow keys and buttons all work.
   Reduced motion: pages cross-fade instead of turning. */
(function (w) {
  var MQ = "(max-width: 700px)";
  function calm() { return w.matchMedia("(prefers-reduced-motion: reduce)").matches; }
  function el(cls) { var d = document.createElement("div"); d.className = cls; return d; }
  function fill(face, page) {
    var inner = el("fb-page");
    if (typeof page === "string") inner.innerHTML = page; else inner.appendChild(page.cloneNode(true));
    face.appendChild(inner);
  }

  function FlipBook(root, pages, opts) {
    opts = opts || {};
    var single = w.matchMedia(MQ).matches, sheets = [], flipped = 0, busy = false;

    function build() {
      root.innerHTML = ""; sheets = []; flipped = 0;
      root.classList.add("fb"); root.classList.toggle("fb--single", single);
      root.setAttribute("role", "group"); root.setAttribute("aria-roledescription", "book"); root.tabIndex = 0;
      var step = single ? 1 : 2, n = Math.ceil(pages.length / step);
      for (var i = 0; i < n; i++) {
        var s = el("fb-sheet"), f = el("fb-face fb-front"), b = el("fb-face fb-back");
        fill(f, pages[i * step]);
        if (!single) { if (pages[i * step + 1] != null) fill(b, pages[i * step + 1]); }
        s.appendChild(f); if (!single) s.appendChild(b);
        s.style.zIndex = n - i; root.appendChild(s); sheets.push(s);
      }
      announce();
    }

    function label() {
      var step = single ? 1 : 2;
      if (single) return "Page " + (flipped + 1) + " of " + pages.length;
      var l = flipped * 2, r = flipped * 2 + 1;
      var left = l >= 1 ? l : null, right = r <= pages.length ? r : null;
      if (left && right) return "Pages " + left + " and " + right + " of " + pages.length;
      return "Page " + (left || right) + " of " + pages.length;
    }
    function announce() { if (opts.onChange) opts.onChange(label(), flipped, sheets.length); }

    function go(dir) {
      if (busy) return;
      var i = dir > 0 ? flipped : flipped - 1;
      if (i < 0 || i >= sheets.length) return;
      var s = sheets[i], n = sheets.length;
      busy = true;
      if (calm()) {
        s.classList.toggle("is-flipped", dir > 0); s.style.zIndex = dir > 0 ? i + 1 : n - i;
        flipped += dir; busy = false; announce(); return;
      }
      s.style.zIndex = 200;
      void s.offsetWidth;
      s.classList.toggle("is-flipped", dir > 0);
      var finished = false;
      function end() {
        if (finished) return; finished = true;
        s.style.zIndex = dir > 0 ? i + 1 : n - i;
        flipped += dir; busy = false; announce();
      }
      s.addEventListener("transitionend", function h(e) { if (e.target === s) { s.removeEventListener("transitionend", h); end(); } });
      setTimeout(end, 1100);
    }

    /* swipe: left swipe turns forward, right swipe turns back */
    var sx = null;
    root.addEventListener("pointerdown", function (e) { sx = e.clientX; });
    root.addEventListener("pointerup", function (e) {
      if (sx === null) return; var dx = e.clientX - sx; sx = null;
      if (Math.abs(dx) > 40) go(dx < 0 ? 1 : -1);
    });
    root.addEventListener("pointercancel", function () { sx = null; });
    root.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight" || e.key === "PageDown") { e.preventDefault(); go(1); }
      if (e.key === "ArrowLeft" || e.key === "PageUp") { e.preventDefault(); go(-1); }
    });
    w.matchMedia(MQ).addEventListener("change", function (e) { single = e.matches; build(); });

    build();
    return { next: function () { go(1); }, prev: function () { go(-1); } };
  }
  w.Moriwella = w.Moriwella || {};
  w.Moriwella.FlipBook = FlipBook;
})(window);
