/* Moriwella milestone celebrations.
   Moriwella.celebrate({ level: "small", origin: element })            quick sparkle for an everyday check-off
   Moriwella.celebrate({ level: "milestone", title, sub })              fireworks and a badge you can dismiss
   Reduced motion: no particles or movement, the badge simply appears. */
(function (w) {
  var COLORS = ["#DDA12F", "#C9A763", "#F6DE9C", "#A9C5B3", "#6B8F77", "#F9F6ED"];
  function calm() { return w.matchMedia && w.matchMedia("(prefers-reduced-motion: reduce)").matches; }

  function makeCanvas() {
    var c = document.createElement("canvas");
    c.className = "mw-fx"; c.setAttribute("aria-hidden", "true");
    var dpr = Math.min(w.devicePixelRatio || 1, 2);
    c.width = innerWidth * dpr; c.height = innerHeight * dpr;
    c.style.width = innerWidth + "px"; c.style.height = innerHeight + "px";
    var ctx = c.getContext("2d"); ctx.scale(dpr, dpr);
    document.body.appendChild(c);
    return { c: c, ctx: ctx };
  }

  /* bursts: [{x, y, n, at}] with x, y in pixels and at in ms from start */
  function run(bursts, opts) {
    opts = opts || {};
    var cv = makeCanvas(), parts = [], t0 = performance.now(), last = t0, stopped = false;
    function spawn(b) {
      for (var i = 0; i < b.n; i++) {
        var a = Math.random() * Math.PI * 2, s = (opts.speed || 4.2) * (.35 + Math.random() * .65);
        parts.push({ x: b.x, y: b.y, vx: Math.cos(a) * s, vy: Math.sin(a) * s,
          life: 0, max: 900 + Math.random() * 700, r: 1.6 + Math.random() * 2,
          col: COLORS[(Math.random() * COLORS.length) | 0] });
      }
    }
    function frame(now) {
      if (stopped) return;
      var dt = Math.min(now - last, 40); last = now;
      cv.ctx.clearRect(0, 0, innerWidth, innerHeight);
      while (bursts.length && now - t0 >= bursts[0].at) spawn(bursts.shift());
      for (var i = parts.length - 1; i >= 0; i--) {
        var p = parts[i]; p.life += dt;
        if (p.life >= p.max) { parts.splice(i, 1); continue; }
        p.vy += 0.0032 * dt; p.vx *= 0.985; p.x += p.vx * dt / 16; p.y += p.vy * dt / 16;
        cv.ctx.globalAlpha = 1 - p.life / p.max;
        cv.ctx.fillStyle = p.col;
        cv.ctx.beginPath(); cv.ctx.arc(p.x, p.y, p.r, 0, 6.2832); cv.ctx.fill();
      }
      if (bursts.length || parts.length) requestAnimationFrame(frame); else done();
    }
    function done() { stopped = true; if (cv.c.parentNode) cv.c.parentNode.removeChild(cv.c); }
    requestAnimationFrame(frame);
    return done;
  }

  function buzz(p) { try { if (navigator.vibrate) navigator.vibrate(p); } catch (e) {} }

  function sparkle(origin) {
    if (calm()) return;
    var r = origin && origin.getBoundingClientRect ? origin.getBoundingClientRect() : { left: innerWidth / 2, top: innerHeight / 2, width: 0, height: 0 };
    run([{ x: r.left + r.width / 2, y: r.top + r.height / 2, n: 16, at: 0 }], { speed: 2.4 });
    buzz(12);
  }

  var openBadge = null;
  function milestone(o) {
    if (openBadge) openBadge();
    var stopFx = function () {};
    if (!calm()) {
      var W = innerWidth, H = innerHeight;
      stopFx = run([
        { x: W * .3, y: H * .35, n: 46, at: 0 }, { x: W * .7, y: H * .3, n: 46, at: 380 },
        { x: W * .5, y: H * .22, n: 60, at: 760 }, { x: W * .22, y: H * .5, n: 34, at: 1200 },
        { x: W * .78, y: H * .5, n: 34, at: 1450 }
      ]);
      buzz([30, 40, 60]);
    }
    var box = document.createElement("div");
    box.className = "mw-cele"; box.setAttribute("role", "status");
    box.innerHTML =
      '<div class="mw-card">' +
      '<svg class="mw-medal" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="44" fill="#DDA12F"/><circle cx="50" cy="50" r="36" fill="#F6DE9C"/>' +
      '<path d="M30 52 L45 66 L72 36" fill="none" stroke="#073B2B" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      '<b class="mw-t"></b><span class="mw-s"></span>' +
      '<button type="button" class="mw-x">Done</button></div>';
    box.querySelector(".mw-t").textContent = o.title || "Nicely done";
    box.querySelector(".mw-s").textContent = o.sub || "";
    document.body.appendChild(box);
    var timer;
    function close() {
      clearTimeout(timer); document.removeEventListener("keydown", onKey);
      stopFx(); if (box.parentNode) box.parentNode.removeChild(box); openBadge = null;
    }
    function onKey(e) { if (e.key === "Escape") close(); }
    box.querySelector(".mw-x").addEventListener("click", close);
    box.addEventListener("click", function (e) { if (e.target === box) close(); });
    document.addEventListener("keydown", onKey);
    timer = setTimeout(close, o.duration || 6000);
    openBadge = close;
    return close;
  }

  w.Moriwella = w.Moriwella || {};
  w.Moriwella.celebrate = function (o) { o = o || {}; return o.level === "small" ? sparkle(o.origin) : milestone(o); };
})(window);
