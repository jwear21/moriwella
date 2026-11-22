/* Google Play purchase from inside the Android app. Runs only where the native plugin exists (the Capacitor shell); on the website the plan buttons post to Stripe instead.
   Copyright (c) JSW Software Designs */
(function () {
  'use strict';
  var cfg = window.MW_PLAY; if (!cfg) return;
  var msg = document.getElementById('play-msg');
  function say(t) { if (!msg) return; msg.textContent = t; msg.hidden = !t; }
  function plugin() { var c = window.Capacitor; return c && c.Plugins && c.Plugins.NativePurchases; }

  /* Pick the offer with the free trial when Google returns one, otherwise the plain monthly plan. */
  function pickOffer(products, productId, base) {
    var mine = (products || []).filter(function (p) { return p.planIdentifier === productId && p.identifier === base; });
    var trial = mine.filter(function (p) { return p.offerId && p.offerToken; });
    var withTrial = trial.filter(function (p) { return p.introductoryPrice || /trial/i.test(p.offerId || ''); });
    return (withTrial[0] || trial[0] || mine.filter(function (p) { return !p.offerId; })[0] || mine[0]) || null;
  }

  function buy(btn) {
    var NP = plugin(); var productId = btn.getAttribute('data-product'); var base = btn.getAttribute('data-base') || cfg.base;
    if (!NP) { say(cfg.fail); return; }
    var all = document.querySelectorAll('[data-play-plan]'); all.forEach(function (b) { b.disabled = true; });
    say(cfg.wait);
    NP.getProducts({ productIdentifiers: [productId], productType: 'subs' }).then(function (r) {
      var offer = pickOffer(r.products, productId, base); if (!offer) throw new Error('no offer');
      return NP.purchaseProduct({ productIdentifier: productId, planIdentifier: base, offerToken: offer.offerToken, productType: 'subs', appAccountToken: cfg.tag, autoAcknowledgePurchases: true });
    }).then(function (tx) {
      var token = tx && tx.purchaseToken; if (!token) throw new Error('no token');
      var body = new URLSearchParams(); body.set('_csrf', cfg.csrf); body.set('token', token);
      return fetch(cfg.verify, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } }).then(function (res) { return res.json(); });
    }).then(function (j) {
      if (j && j.ok) { location.href = cfg.next; return; }
      say((j && j.error) || cfg.fail); all.forEach(function (b) { b.disabled = false; });
    }).catch(function (e) {
      /* closing Google's sheet is not an error worth shouting about */
      var cancelled = e && /cancel/i.test(String(e.message || e)); say(cancelled ? '' : cfg.fail); all.forEach(function (b) { b.disabled = false; });
    });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-play-plan]'); if (b) { ev.preventDefault(); buy(b); }
  });
})();
