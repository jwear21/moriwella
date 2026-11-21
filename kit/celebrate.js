/* Moriwella milestone celebrations.
   Moriwella.celebrate({ level: "small", origin: element })            quick sparkle for an everyday check-off
   Moriwella.celebrate({ level: "splash", origin: element })           water droplets and ripples
   Moriwella.celebrate({ level: "burst", origin: element })            confetti burst for a goal
   Moriwella.celebrate({ level: "milestone", title, sub })              fireworks and a badge you can dismiss (or the kitten walk-on when the person chose it; style:"kitten" forces it)
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

  var WATER = ["#7FB7CC", "#A9D3E0", "#DDF0F5", "#4F93AD", "#F9F6ED"];
  function scale() { var b = document.body; return b && b.getAttribute("data-celebrate-size") === "gentle" ? 0.4 : 1; }

  /* bursts: [{x, y, n, at, kind, cols}] with x, y in pixels and at in ms from start.
     kind: "burst" (default, radial), "splash" (droplets thrown upward), "ring" (expanding ripple), "rain" (confetti falling from the top). */
  function run(bursts, opts) {
    opts = opts || {};
    var cv = makeCanvas(), parts = [], rings = [], t0 = performance.now(), last = t0, stopped = false, F = scale();
    function spawn(b) {
      var cols = b.cols || COLORS;
      if (b.kind === "ring") { rings.push({ x: b.x, y: b.y, life: 0, dur: b.dur || 650, rmax: (b.rmax || 70) * (F < 1 ? .7 : 1), col: b.col || cols[0] }); return; }
      var n = Math.max(4, Math.round(b.n * F));
      for (var i = 0; i < n; i++) {
        var p = { x: b.x, y: b.y, life: 0, col: cols[(Math.random() * cols.length) | 0], rot: Math.random() * 6.28, vr: (Math.random() - .5) * .3, sq: !!b.confetti && Math.random() < .55 };
        if (b.kind === "splash") {
          var a = -Math.PI / 2 + (Math.random() - .5) * 2, s = (opts.speed || 5.2) * (.45 + Math.random() * .7);
          p.vx = Math.cos(a) * s; p.vy = Math.sin(a) * s; p.max = 800 + Math.random() * 500; p.r = 2.2 + Math.random() * 2.8; p.g = 0.0075;
        } else if (b.kind === "rain") {
          p.x = Math.random() * innerWidth; p.y = -12; p.vx = (Math.random() - .5) * 1.6; p.vy = 1.6 + Math.random() * 2.2;
          p.max = 1800 + Math.random() * 900; p.r = 3 + Math.random() * 3; p.sq = true; p.g = 0.0009;
        } else {
          var a2 = Math.random() * Math.PI * 2, s2 = (opts.speed || 5.4) * (.35 + Math.random() * .65);
          p.vx = Math.cos(a2) * s2; p.vy = Math.sin(a2) * s2; p.max = 1000 + Math.random() * 900; p.r = 2.2 + Math.random() * 3; p.g = 0.0032;
        }
        parts.push(p);
      }
    }
    function frame(now) {
      if (stopped) return;
      var dt = Math.min(now - last, 40); last = now;
      cv.ctx.clearRect(0, 0, innerWidth, innerHeight);
      while (bursts.length && now - t0 >= bursts[0].at) spawn(bursts.shift());
      for (var j = rings.length - 1; j >= 0; j--) {
        var g = rings[j]; g.life += dt;
        if (g.life >= g.dur) { rings.splice(j, 1); continue; }
        var k = g.life / g.dur;
        cv.ctx.globalAlpha = .75 * (1 - k); cv.ctx.strokeStyle = g.col; cv.ctx.lineWidth = 3 * (1 - k) + 1;
        cv.ctx.beginPath(); cv.ctx.arc(g.x, g.y, 6 + (g.rmax - 6) * (1 - Math.pow(1 - k, 3)), 0, 6.2832); cv.ctx.stroke();
      }
      for (var i = parts.length - 1; i >= 0; i--) {
        var p = parts[i]; p.life += dt;
        if (p.life >= p.max) { parts.splice(i, 1); continue; }
        p.vy += p.g * dt; p.vx *= 0.987; p.x += p.vx * dt / 16; p.y += p.vy * dt / 16; p.rot += p.vr;
        cv.ctx.globalAlpha = Math.min(1, 2.2 * (1 - p.life / p.max));
        cv.ctx.fillStyle = p.col;
        if (p.sq) { cv.ctx.save(); cv.ctx.translate(p.x, p.y); cv.ctx.rotate(p.rot); cv.ctx.fillRect(-p.r, -p.r * .55, p.r * 2, p.r * 1.1); cv.ctx.restore(); }
        else { cv.ctx.beginPath(); cv.ctx.arc(p.x, p.y, p.r, 0, 6.2832); cv.ctx.fill(); }
      }
      if (bursts.length || parts.length || rings.length) requestAnimationFrame(frame); else done();
    }
    function done() { stopped = true; if (cv.c.parentNode) cv.c.parentNode.removeChild(cv.c); }
    requestAnimationFrame(frame);
    return done;
  }

  function buzz(p) { try { if (navigator.vibrate) navigator.vibrate(p); } catch (e) {} }

  function center(origin) {
    var r = origin && origin.getBoundingClientRect ? origin.getBoundingClientRect() : { left: innerWidth / 2, top: innerHeight / 2, width: 0, height: 0 };
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
  }
  /* quick everyday check-off: a pop of sparkles with a ripple */
  function sparkle(origin) {
    if (calm()) return;
    var c = center(origin);
    run([{ x: c.x, y: c.y, n: 40, at: 0 }, { x: c.x, y: c.y, kind: "ring", at: 0, rmax: 60 }], { speed: 4 });
    buzz(14);
  }
  /* one glass of water: droplets thrown up and two ripples */
  function splash(origin) {
    if (calm()) return;
    var c = center(origin);
    run([{ x: c.x, y: c.y, n: 46, at: 0, kind: "splash", cols: WATER },
         { x: c.x, y: c.y, kind: "ring", at: 0, rmax: 80, col: "#7FB7CC" }, { x: c.x, y: c.y, kind: "ring", at: 140, rmax: 125, col: "#A9D3E0" }]);
    buzz(18);
  }
  /* a goal checked off: three staggered bursts with confetti */
  function burst(origin) {
    if (calm()) return;
    var c = center(origin);
    run([{ x: c.x, y: c.y, n: 46, at: 0, confetti: true }, { x: c.x - 40, y: c.y - 30, n: 36, at: 140, confetti: true },
         { x: c.x + 40, y: c.y - 50, n: 36, at: 280, confetti: true }, { x: c.x, y: c.y, kind: "ring", at: 0, rmax: 95 }], { speed: 5.8 });
    buzz([20, 30, 40]);
  }

  var openBadge = null;
  function milestone(o) {
    if (openBadge) openBadge();
    var stopFx = function () {};
    if (!calm()) {
      var W = innerWidth, H = innerHeight;
      stopFx = run([
        { x: W * .3, y: H * .35, n: 80, at: 0, confetti: true }, { x: W * .7, y: H * .3, n: 80, at: 300, confetti: true },
        { x: W * .5, y: H * .2, n: 100, at: 600, confetti: true }, { x: W * .2, y: H * .5, n: 70, at: 950 },
        { x: W * .8, y: H * .5, n: 70, at: 1150 }, { x: W * .35, y: H * .6, n: 60, at: 1450 }, { x: W * .65, y: H * .6, n: 60, at: 1650 },
        { x: W * .5, y: H * .35, n: 120, at: 1900, confetti: true },
        { x: 0, y: 0, n: 70, at: 0, kind: "rain" }, { x: 0, y: 0, n: 70, at: 1100, kind: "rain" },
        { x: W * .5, y: H * .4, kind: "ring", at: 0, rmax: 220 }, { x: W * .5, y: H * .4, kind: "ring", at: 250, rmax: 300 }
      ], { speed: 6.4 });
      buzz([30, 40, 60, 40, 80]);
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


  /* Kitten walk-on: the kitten trots in from the left, sits in the middle, licks a paw, claps, cheers, then trots off to the right.
     Frames are in /kit/kitten/. Reduced motion: the cheering kitten simply appears, with the badge. */
  var KIT = ["sit", "walk1", "walk2", "lick", "clap", "cheer"], kitImgs = null;
  function kitLoad() { if (!kitImgs) { kitImgs = {}; KIT.forEach(function (n) { var i = new Image(); i.src = "/kit/kitten/" + n + ".png"; kitImgs[n] = i; }); } }
  function kitten(o) {
    if (openBadge) openBadge();
    kitLoad();
    var box = document.createElement("div");
    box.className = "mw-cele mw-kit"; box.setAttribute("role", "status");
    var size = Math.min(innerWidth * .5, 260);
    box.innerHTML = '<img class="mw-kitimg" alt="" aria-hidden="true" src="/kit/kitten/sit.png" width="' + size + '">' +
      '<div class="mw-card"><b class="mw-t"></b><span class="mw-s"></span><button type="button" class="mw-x">Done</button></div>';
    box.querySelector(".mw-t").textContent = o.title || "Nicely done";
    box.querySelector(".mw-s").textContent = o.sub || "";
    var img = box.querySelector(".mw-kitimg"), timers = [], raf = 0, done = false;
    document.body.appendChild(box);
    function close() {
      if (done) return; done = true; timers.forEach(clearTimeout); cancelAnimationFrame(raf);
      document.removeEventListener("keydown", onKey); if (box.parentNode) box.parentNode.removeChild(box); openBadge = null;
    }
    function onKey(e) { if (e.key === "Escape") close(); }
    box.querySelector(".mw-x").addEventListener("click", close);
    document.addEventListener("keydown", onKey);
    openBadge = close;
    function pose(n) { img.src = "/kit/kitten/" + n + ".png"; }
    function at(ms, fn) { timers.push(setTimeout(fn, ms)); }
    if (calm()) { pose("cheer"); img.style.left = "calc(50% - " + size / 2 + "px)"; at(o.duration || 7000, close); return close; }
    var W = innerWidth, y = innerHeight - size * 1.02 - 8;
    img.style.top = Math.max(8, y) + "px";
    var x0 = -size, xm = W / 2 - size / 2, x1 = W + 10, t0 = performance.now(), walkIn = 2200, walkOut = 2200, stay = 4600;
    function setX(x, bob) { img.style.transform = "translate(" + x + "px," + (bob || 0) + "px)"; }
    var phase = "in";
    function frame(now) {
      var t = now - t0, step = Math.floor(t / 170) % 2 ? "walk2" : "walk1";
      if (phase === "in") {
        var p = Math.min(1, t / walkIn), e = 1 - Math.pow(1 - p, 2);
        setX(x0 + (xm - x0) * e, -Math.abs(Math.sin(t / 170 * Math.PI)) * 6);
        if (p < 1) { if (img.getAttribute("data-p") !== step) { pose(step); img.setAttribute("data-p", step); } }
        else { phase = "stay"; setX(xm, 0); pose("sit"); img.setAttribute("data-p", "sit"); buzz([20, 30, 40]);
          at(500, function () { pose("lick"); }); at(1900, function () { pose("clap"); buzz([15, 40, 15, 40]); }); at(3000, function () { pose("cheer"); buzz([30, 40, 60]); });
          at(stay, function () { phase = "out"; t0 = performance.now(); }); }
      } else if (phase === "out") {
        var q = Math.min(1, t / walkOut);
        setX(xm + (x1 - xm) * q * q, -Math.abs(Math.sin(t / 170 * Math.PI)) * 6);
        if (img.getAttribute("data-p") !== step) { pose(step); img.setAttribute("data-p", step); }
        if (q >= 1) { phase = "gone"; img.style.visibility = "hidden"; }
      }
      if (phase !== "gone" && !done) raf = requestAnimationFrame(frame);
    }
    setX(x0, 0); raf = requestAnimationFrame(frame);
    at(o.duration || 11500, close);
    return close;
  }

  w.Moriwella = w.Moriwella || {};
  w.Moriwella.celebrate = function (o) { o = o || {}; if (o.level === "milestone" && (o.style || (document.body && document.body.getAttribute("data-celebrate-style"))) === "kitten") return kitten(o); return o.level === "small" ? sparkle(o.origin) : o.level === "splash" ? splash(o.origin) : o.level === "burst" ? burst(o.origin) : milestone(o); };
})(window);
