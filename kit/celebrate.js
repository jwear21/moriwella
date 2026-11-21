/* Moriwella milestone celebrations.
   Moriwella.celebrate({ level: "small", origin: element })            quick sparkle for an everyday check-off
   Moriwella.celebrate({ level: "splash", origin: element })           water droplets and ripples
   Moriwella.celebrate({ level: "burst", origin: element })            confetti burst for a goal
   Moriwella.celebrate({ level: "milestone", title, sub })              fireworks and a badge you can dismiss (or the kitten or baboon walk-on when the person chose it; style:"kitten" or "baboon" forces it)
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

  /* Meow: a short synthesized "mrrow" (rising then falling pitch through a vowel-like filter). No sound files. Quieter when the person chose gentle. */
  var actx = null;
  function meow(v, pitch) {
    try {
      var AC = w.AudioContext || w.webkitAudioContext; if (!AC) return;
      actx = actx || new AC(); if (actx.state === "suspended") actx.resume();
      var t = actx.currentTime + .02, d = .55 + Math.random() * .1, base = (pitch || 1) * (600 + Math.random() * 60), vol = (scale() < 1 ? .05 : .13) * (v || 1);
      var o = actx.createOscillator(), o2 = actx.createOscillator(), f = actx.createBiquadFilter(), g = actx.createGain(), vib = actx.createOscillator(), vg = actx.createGain();
      o.type = "sawtooth"; o2.type = "triangle";
      o.frequency.setValueAtTime(base * .8, t); o.frequency.linearRampToValueAtTime(base * 1.45, t + d * .35); o.frequency.exponentialRampToValueAtTime(base * .85, t + d);
      o2.frequency.setValueAtTime(base * 1.6, t); o2.frequency.linearRampToValueAtTime(base * 2.9, t + d * .35); o2.frequency.exponentialRampToValueAtTime(base * 1.7, t + d);
      vib.frequency.value = 22; vg.gain.value = 9; vib.connect(vg); vg.connect(o.frequency);
      f.type = "bandpass"; f.Q.value = 4; f.frequency.setValueAtTime(900, t); f.frequency.linearRampToValueAtTime(2300, t + d * .4); f.frequency.linearRampToValueAtTime(1100, t + d);
      g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(vol, t + .06); g.gain.setValueAtTime(vol, t + d * .6); g.gain.exponentialRampToValueAtTime(0.0001, t + d);
      o.connect(f); o2.connect(f); f.connect(g); g.connect(actx.destination);
      o.start(t); o2.start(t); vib.start(t); o.stop(t + d + .05); o2.stop(t + d + .05); vib.stop(t + d + .05);
    } catch (e) {}
  }

  /* Purr: low rumble whose loudness pulses about 25 times a second. */
  function purr(sec, v) {
    try {
      var AC = w.AudioContext || w.webkitAudioContext; if (!AC) return;
      actx = actx || new AC(); if (actx.state === "suspended") actx.resume();
      var t = actx.currentTime + .02, vol = (scale() < 1 ? .04 : .11) * (v || 1);
      var n = actx.createBufferSource(), len = actx.sampleRate * 2, buf = actx.createBuffer(1, len, actx.sampleRate), d = buf.getChannelData(0);
      for (var i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
      n.buffer = buf; n.loop = true;
      var f = actx.createBiquadFilter(); f.type = "lowpass"; f.frequency.value = 220; f.Q.value = 2;
      var am = actx.createGain(), g = actx.createGain(), lfo = actx.createOscillator(), lg = actx.createGain();
      am.gain.value = .5; lfo.frequency.value = 25; lg.gain.value = .5; lfo.connect(lg); lg.connect(am.gain);
      g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(vol * 4, t + .25); g.gain.setValueAtTime(vol * 4, t + sec - .3); g.gain.linearRampToValueAtTime(0.0001, t + sec);
      n.connect(f); f.connect(am); am.connect(g); g.connect(actx.destination);
      n.start(t); lfo.start(t); n.stop(t + sec + .05); lfo.stop(t + sec + .05);
    } catch (e) {}
  }
  /* Lick: a few quick wet swishes. */
  function lick(count) {
    try {
      var AC = w.AudioContext || w.webkitAudioContext; if (!AC) return;
      actx = actx || new AC(); if (actx.state === "suspended") actx.resume();
      var vol = scale() < 1 ? .03 : .09;
      for (var k = 0; k < (count || 3); k++) {
        var t = actx.currentTime + .02 + k * .32, len = Math.floor(actx.sampleRate * .16), buf = actx.createBuffer(1, len, actx.sampleRate), d = buf.getChannelData(0);
        for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.sin(Math.PI * i / len);
        var n = actx.createBufferSource(); n.buffer = buf;
        var f = actx.createBiquadFilter(); f.type = "bandpass"; f.Q.value = 3; f.frequency.setValueAtTime(2600, t); f.frequency.linearRampToValueAtTime(4800, t + .14);
        var g = actx.createGain(); g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.0001, t + .16);
        n.connect(f); f.connect(g); g.connect(actx.destination); n.start(t);
      }
    } catch (e) {}
  }
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
    if (calm()) { pose("cheer"); meow(.8, 1.1); img.style.left = "calc(50% - " + size / 2 + "px)"; at(o.duration || 7000, close); return close; }
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
        else { phase = "stay"; setX(xm, 0); pose("sit"); img.setAttribute("data-p", "sit"); buzz([20, 30, 40]); meow(1, 1);
          at(500, function () { pose("lick"); lick(4); }); at(1900, function () { pose("clap"); buzz([15, 40, 15, 40]); purr(1.4, 1); }); at(3000, function () { pose("cheer"); buzz([30, 40, 60]); meow(1.1, 1.25); });
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

  /* Baboon walk-on: strolls in from the left, stands in the middle, claps, cheers "woo-hoo", turns around, shakes its red rump (boing), then strolls off.
     Frames are in /kit/baboon/. All sounds are synthesized (no sound files). Reduced motion: the cheering baboon simply appears, with the badge. */
  function ctx() { var AC = w.AudioContext || w.webkitAudioContext; if (!AC) return null; actx = actx || new AC(); if (actx.state === "suspended") actx.resume(); return actx; }
  /* One vocal hoot: sawtooth through a vowel filter. from/to are pitch in Hz, vowel is the filter centre in Hz. */
  function voice(at, dur, from, to, vowel, vol, vibrato) {
    var a = ctx(); if (!a) return; var t = a.currentTime + .02 + at;
    var o = a.createOscillator(), f = a.createBiquadFilter(), g = a.createGain(), vb = a.createOscillator(), vg = a.createGain();
    o.type = "sawtooth"; o.frequency.setValueAtTime(from, t); o.frequency.linearRampToValueAtTime(to, t + dur * .7); o.frequency.linearRampToValueAtTime(to * .92, t + dur);
    vb.frequency.value = vibrato || 7; vg.gain.value = to * .02; vb.connect(vg); vg.connect(o.frequency);
    f.type = "bandpass"; f.Q.value = 5; f.frequency.setValueAtTime(vowel * .8, t); f.frequency.linearRampToValueAtTime(vowel, t + dur * .5); f.frequency.linearRampToValueAtTime(vowel * 1.1, t + dur);
    g.gain.setValueAtTime(.0001, t); g.gain.linearRampToValueAtTime(vol, t + .04); g.gain.setValueAtTime(vol, t + dur * .65); g.gain.exponentialRampToValueAtTime(.0001, t + dur);
    o.connect(f); f.connect(g); g.connect(a.destination); o.start(t); vb.start(t); o.stop(t + dur + .05); vb.stop(t + dur + .05);
  }
  function vq() { return scale() < 1 ? .06 : .17; }
  /* Happy ape chatter: "oo oo ah ah" climbing. */
  function chatter() { var v = vq(); voice(0, .16, 330, 420, 450, v); voice(.2, .16, 360, 460, 450, v); voice(.42, .2, 420, 560, 800, v); voice(.66, .24, 450, 600, 800, v); }
  /* "Woo-hoo!": a rising "woo" then a higher "hoo", with a wobble. */
  function woohoo() { var v = vq() * 1.15; voice(0, .3, 260, 480, 380, v, 9); voice(.32, .46, 520, 780, 650, v, 9); }
  /* Hand clap: a few short noise snaps. */
  function clapSound(n) {
    var a = ctx(); if (!a) return; var vol = scale() < 1 ? .05 : .16;
    for (var k = 0; k < (n || 4); k++) {
      var t = a.currentTime + .02 + k * .2, len = Math.floor(a.sampleRate * .07), buf = a.createBuffer(1, len, a.sampleRate), d = buf.getChannelData(0);
      for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 3);
      var s = a.createBufferSource(); s.buffer = buf; var f = a.createBiquadFilter(); f.type = "bandpass"; f.frequency.value = 1700; f.Q.value = .8;
      var g = a.createGain(); g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(.0001, t + .07); s.connect(f); f.connect(g); g.connect(a.destination); s.start(t);
    }
  }
  /* Cartoon boing for each rump shake: a quick spring wobble. */
  function boing(at) {
    var a = ctx(); if (!a) return; var t = a.currentTime + .02 + (at || 0), o = a.createOscillator(), g = a.createGain(), vol = scale() < 1 ? .04 : .12;
    o.type = "sine"; o.frequency.setValueAtTime(220, t); o.frequency.exponentialRampToValueAtTime(520, t + .08); o.frequency.exponentialRampToValueAtTime(180, t + .3);
    g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(.0001, t + .32); o.connect(g); g.connect(a.destination); o.start(t); o.stop(t + .35);
  }
  var BAB = ["walk", "clap", "cheer", "rump"], babImgs = null;
  function babLoad() { if (!babImgs) { babImgs = {}; BAB.forEach(function (n) { var i = new Image(); i.src = "/kit/baboon/" + n + ".png"; babImgs[n] = i; }); } }
  function baboon(o) {
    if (openBadge) openBadge();
    babLoad();
    var box = document.createElement("div");
    box.className = "mw-cele mw-kit mw-bab"; box.setAttribute("role", "status");
    var size = Math.min(innerWidth * .52, 270);
    box.innerHTML = '<img class="mw-kitimg" alt="" aria-hidden="true" src="/kit/baboon/walk.png" width="' + size + '">' +
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
    function pose(n) { img.src = "/kit/baboon/" + n + ".png"; }
    function at(ms, fn) { timers.push(setTimeout(fn, ms)); }
    if (calm()) { pose("cheer"); woohoo(); img.style.left = "calc(50% - " + size / 2 + "px)"; at(o.duration || 7000, close); return close; }
    var W = innerWidth, y = innerHeight - size - 8;
    img.style.top = Math.max(8, y) + "px";
    var x0 = -size, xm = W / 2 - size / 2, x1 = W + 10, t0 = performance.now(), walkIn = 2000, walkOut = 2000, stay = 7200;
    var phase = "in", shake = false, sk0 = 0;
    function setX(x, bob, rot) { img.style.transform = "translate(" + x + "px," + (bob || 0) + "px) rotate(" + (rot || 0) + "deg)"; }
    function frame(now) {
      var t = now - t0;
      if (phase === "in") {
        var p = Math.min(1, t / walkIn), e = 1 - Math.pow(1 - p, 2);
        setX(x0 + (xm - x0) * e, -Math.abs(Math.sin(t / 190 * Math.PI)) * 10, Math.sin(t / 190 * Math.PI) * 3);
        if (p >= 1) { phase = "stay"; setX(xm, 0, 0); pose("cheer"); buzz([20, 30, 40]); chatter();
          at(900, function () { pose("clap"); buzz([15, 40, 15, 40]); clapSound(5); });
          at(2400, function () { pose("cheer"); woohoo(); buzz([30, 40, 60]); });
          at(3700, function () { pose("rump"); shake = true; sk0 = performance.now(); buzz([20, 30, 20, 30, 20, 30, 20]); boing(0); boing(.28); boing(.56); boing(.84); at(300, woohoo); });
          at(5700, function () { shake = false; pose("cheer"); chatter(); });
          at(stay, function () { phase = "out"; t0 = performance.now(); pose("walk"); }); }
      } else if (phase === "stay") {
        if (shake) { var st = (now - sk0) / 1000; setX(xm + Math.sin(st * 2 * Math.PI * 3.6) * 14, -Math.abs(Math.sin(st * 2 * Math.PI * 1.8)) * 10, Math.sin(st * 2 * Math.PI * 3.6) * 5); }
      } else if (phase === "out") {
        var q = Math.min(1, t / walkOut);
        setX(xm + (x1 - xm) * q * q, -Math.abs(Math.sin(t / 190 * Math.PI)) * 10, Math.sin(t / 190 * Math.PI) * 3);
        if (q >= 1) { phase = "gone"; img.style.visibility = "hidden"; }
      }
      if (phase !== "gone" && !done) raf = requestAnimationFrame(frame);
    }
    setX(x0, 0, 0); raf = requestAnimationFrame(frame);
    at(o.duration || 11800, close);
    return close;
  }

  /* Object celebrations (no artwork files): a rocket launch and a bunch of rising balloons. Sounds are synthesized. */
  function objBox(o) {
    if (openBadge) openBadge();
    var box = document.createElement("div");
    box.className = "mw-cele mw-kit mw-obj"; box.setAttribute("role", "status");
    box.innerHTML = '<div class="mw-stage" aria-hidden="true" style="position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:101"></div>' +
      '<div class="mw-card"><b class="mw-t"></b><span class="mw-s"></span><button type="button" class="mw-x">Done</button></div>';
    box.querySelector(".mw-t").textContent = o.title || "Nicely done";
    box.querySelector(".mw-s").textContent = o.sub || "";
    var timers = [], done = false, anims = [];
    document.body.appendChild(box);
    function close() {
      if (done) return; done = true; timers.forEach(clearTimeout); anims.forEach(function (a) { try { a.cancel(); } catch (e) {} });
      document.removeEventListener("keydown", onKey); if (box.parentNode) box.parentNode.removeChild(box); openBadge = null;
    }
    function onKey(e) { if (e.key === "Escape") close(); }
    box.querySelector(".mw-x").addEventListener("click", close);
    document.addEventListener("keydown", onKey);
    openBadge = close;
    return { box: box, stage: box.querySelector(".mw-stage"), close: close, at: function (ms, fn) { timers.push(setTimeout(fn, ms)); }, anim: function (el, kf, op) { var a = el.animate(kf, op); anims.push(a); return a; } };
  }
  /* A sweet little chime: rising notes. */
  function chime(notes) {
    var a = ctx(); if (!a) return; var vol = scale() < 1 ? .03 : .09;
    notes.forEach(function (hz, i) {
      var t = a.currentTime + .02 + i * .11, o = a.createOscillator(), g = a.createGain(); o.type = "triangle"; o.frequency.value = hz;
      g.gain.setValueAtTime(.0001, t); g.gain.linearRampToValueAtTime(vol, t + .02); g.gain.exponentialRampToValueAtTime(.0001, t + .5);
      o.connect(g); g.connect(a.destination); o.start(t); o.stop(t + .55);
    });
  }
  function beep(at, hz) {
    var a = ctx(); if (!a) return; var t = a.currentTime + .02 + at, o = a.createOscillator(), g = a.createGain(), vol = scale() < 1 ? .03 : .08;
    o.type = "square"; o.frequency.value = hz; g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(.0001, t + .14); o.connect(g); g.connect(a.destination); o.start(t); o.stop(t + .16);
  }
  /* Whoosh: filtered noise whose pitch climbs. */
  function whoosh(at, sec) {
    var a = ctx(); if (!a) return; var t = a.currentTime + .02 + at, len = Math.floor(a.sampleRate * sec), buf = a.createBuffer(1, len, a.sampleRate), d = buf.getChannelData(0);
    for (var i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    var n = a.createBufferSource(); n.buffer = buf; var f = a.createBiquadFilter(); f.type = "bandpass"; f.Q.value = 1.2; f.frequency.setValueAtTime(300, t); f.frequency.exponentialRampToValueAtTime(2800, t + sec * .9);
    var g = a.createGain(), vol = scale() < 1 ? .06 : .2; g.gain.setValueAtTime(.0001, t); g.gain.linearRampToValueAtTime(vol, t + sec * .25); g.gain.exponentialRampToValueAtTime(.0001, t + sec);
    n.connect(f); f.connect(g); g.connect(a.destination); n.start(t);
  }
  /* Balloon pop: a tiny noise burst. */
  function pop(at) {
    var a = ctx(); if (!a) return; var t = a.currentTime + .02 + at, len = Math.floor(a.sampleRate * .06), buf = a.createBuffer(1, len, a.sampleRate), d = buf.getChannelData(0);
    for (var i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 2);
    var n = a.createBufferSource(); n.buffer = buf; var f = a.createBiquadFilter(); f.type = "lowpass"; f.frequency.value = 2200;
    var g = a.createGain(); g.gain.value = scale() < 1 ? .06 : .18; n.connect(f); f.connect(g); g.connect(a.destination); n.start(t);
  }
  var ROCKET_SVG = '<svg viewBox="0 0 120 220" width="100%" xmlns="http://www.w3.org/2000/svg"><path d="M60 8C84 34 92 78 90 128H30C28 78 36 34 60 8Z" fill="#FBF7EE" stroke="#1B4D3E" stroke-width="4"/><path d="M60 8C70 20 77 34 81 50H39C43 34 50 20 60 8Z" fill="#DDA12F"/><circle cx="60" cy="86" r="17" fill="#8FB59A" stroke="#1B4D3E" stroke-width="4"/><circle cx="54" cy="80" r="5" fill="#FBF7EE" opacity=".8"/><path d="M30 104L8 146 30 138Z M90 104L112 146 90 138Z" fill="#1B4D3E"/><rect x="42" y="128" width="36" height="12" rx="3" fill="#1B4D3E"/><g class="flame"><path d="M46 140Q60 210 74 140Z" fill="#DDA12F"/><path d="M52 140Q60 184 68 140Z" fill="#FBF7EE"/></g></svg>';
  function rocket(o) {
    var b = objBox(o), W = innerWidth, H = innerHeight, size = Math.min(W * .3, 150);
    if (calm()) { b.stage.innerHTML = '<div style="position:absolute;left:calc(50% - ' + size / 2 + 'px);bottom:6vh;width:' + size + 'px">' + ROCKET_SVG + '</div>'; chime([660, 880, 1100]); b.at(o.duration || 6000, b.close); return b.close; }
    var r = document.createElement("div"); r.style.cssText = "position:absolute;left:" + (W / 2 - size / 2) + "px;top:" + (H - size * 1.9) + "px;width:" + size + "px"; r.innerHTML = ROCKET_SVG; b.stage.appendChild(r);
    var fl = r.querySelector(".flame"); fl.style.transformOrigin = "60px 140px"; b.anim(fl, [{ transform: "scaleY(.7)" }, { transform: "scaleY(1.25)" }, { transform: "scaleY(.8)" }], { duration: 180, iterations: Infinity });
    beep(0, 520); beep(.5, 520); beep(1, 520); beep(1.5, 880); buzz([20, 30, 20, 30, 20, 30, 80]);
    b.anim(r, [{ transform: "translate(0,0)" }, { transform: "translate(-3px,0)" }, { transform: "translate(3px,1px)" }, { transform: "translate(-3px,-1px)" }, { transform: "translate(0,0)" }], { duration: 120, iterations: 13 });
    b.at(1600, function () {
      whoosh(0, 1.6); buzz([40, 40, 80]);
      b.anim(r, [{ transform: "translateY(0)" }, { transform: "translateY(" + -(H + size * 2) + "px)" }], { duration: 1700, easing: "cubic-bezier(.5,0,.9,.4)", fill: "forwards" });
      for (var i = 0; i < 16; i++) (function (i) { b.at(i * 90, function () {
        var p = document.createElement("div"), x = W / 2 + (Math.random() - .5) * size * .5, y = H - size * .5 - (H * .9) * (i / 16) * .7, d = 24 + Math.random() * 26;
        p.style.cssText = "position:absolute;left:" + (x - d / 2) + "px;top:" + y + "px;width:" + d + "px;height:" + d + "px;border-radius:50%;background:rgba(251,247,238,.85)"; b.stage.appendChild(p);
        b.anim(p, [{ opacity: .9, transform: "scale(.5)" }, { opacity: 0, transform: "scale(2.2) translateY(30px)" }], { duration: 900, fill: "forwards" }); }); })(i);
    });
    b.at(3000, function () { chime([660, 880, 1100, 1320]);
      for (var k = 0; k < 14; k++) { var st = document.createElement("div"), x = Math.random() * W, y = Math.random() * H * .5; st.textContent = "✦"; st.style.cssText = "position:absolute;left:" + x + "px;top:" + y + "px;color:#DDA12F;font-size:" + (16 + Math.random() * 22) + "px";
        b.stage.appendChild(st); b.anim(st, [{ opacity: 0, transform: "scale(.2) rotate(0)" }, { opacity: 1, transform: "scale(1.1) rotate(90deg)", offset: .4 }, { opacity: 0, transform: "scale(.4) rotate(180deg)" }], { duration: 1200 + Math.random() * 600, delay: Math.random() * 500, fill: "forwards" }); } });
    b.at(o.duration || 7500, b.close);
    return b.close;
  }
  var BAL = ["#1B4D3E", "#DDA12F", "#C95C5C", "#8FB59A", "#F2C94C", "#6BA3BE", "#B58CC9"];
  function balloons(o) {
    var b = objBox(o), W = innerWidth, H = innerHeight, n = Math.max(9, Math.min(16, Math.round(W / 40)));
    chime([523, 659, 784, 1047]);
    for (var i = 0; i < n; i++) (function (i) {
      var s = 46 + Math.random() * 26, x = Math.random() * (W - s), col = BAL[i % BAL.length], el = document.createElement("div");
      el.style.cssText = "position:absolute;left:" + x + "px;top:" + H + "px;width:" + s + "px";
      el.innerHTML = '<svg viewBox="0 0 60 100" width="100%" xmlns="http://www.w3.org/2000/svg"><path d="M30 2C12 2 4 18 6 34c2 18 16 30 24 34 8-4 22-16 24-34C56 18 48 2 30 2Z" fill="' + col + '"/><ellipse cx="20" cy="22" rx="6" ry="10" fill="#fff" opacity=".35" transform="rotate(-20 20 22)"/><path d="M26 68h8l-4 6z" fill="' + col + '"/><path d="M30 74c-6 8 6 14 0 24" fill="none" stroke="#1B4D3E" stroke-width="1.5" opacity=".6"/></svg>';
      b.stage.appendChild(el);
      var delay = calm() ? 0 : i * 140, rise = calm() ? 0 : H + s * 2, sway = (Math.random() - .5) * 90;
      if (calm()) { el.style.top = (H * .2 + Math.random() * H * .5) + "px"; return; }
      b.anim(el, [{ transform: "translate(0,0)" }, { transform: "translate(" + sway + "px," + -rise * .5 + "px)", offset: .5 }, { transform: "translate(" + -sway + "px," + -rise + "px)" }], { duration: 3400 + Math.random() * 1600, delay: delay, easing: "ease-in", fill: "forwards" });
      if (i % 3 === 0) b.at(delay + 1200 + Math.random() * 1200, function () { pop(0); });
    })(i);
    buzz([20, 30, 20, 30, 40]);
    b.at(o.duration || 7000, b.close);
    return b.close;
  }

  w.Moriwella = w.Moriwella || {};
  var WALKONS = { kitten: kitten, baboon: baboon, rocket: rocket, balloons: balloons };
  w.Moriwella.celebrate = function (o) { o = o || {}; var st = o.style || (document.body && document.body.getAttribute("data-celebrate-style")); if (o.level === "milestone" && WALKONS[st]) return WALKONS[st](o); return o.level === "small" ? sparkle(o.origin) : o.level === "splash" ? splash(o.origin) : o.level === "burst" ? burst(o.origin) : milestone(o); };
})(window);
