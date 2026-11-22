/* Adds a language choice to the footer of the static pages (home, privacy, terms, delete account). Picking a language opens the translated
   version of the legal pages; English stays the official text. Copyright (c) JSW Software Designs */
(function () {
  'use strict';
  var wrap = document.querySelector('.foot .wrap'); if (!wrap || !window.fetch) return;
  var m = location.pathname.match(/\/(privacy|terms|delete-account)\.html$/), doc = m ? m[1] : 'privacy';
  function cookie() { var c = document.cookie.match(/(?:^|; )mw_legal_lang=([^;]+)/); return c ? decodeURIComponent(c[1]) : ''; }
  fetch('/app/legal/langs?doc=' + doc, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
    var codes = Object.keys(j.langs || {}); if (codes.length < 2) return;
    var p = document.createElement('p'), l = document.createElement('label'), s = document.createElement('select');
    l.textContent = 'Privacy, terms and account deletion in your language: '; l.style.marginRight = '.4rem';
    codes.forEach(function (c) { var o = document.createElement('option'); o.value = c; o.textContent = j.langs[c]; s.appendChild(o); });
    var cur = cookie(); s.value = j.langs[cur] ? cur : 'en';
    function links(c) {
      document.querySelectorAll('.foot a').forEach(function (a) {
        var h = a.getAttribute('href') || '', mm = h.match(/^(?:\/app\/legal\/|\/|)(privacy|terms|delete-account)(?:\.html)?(?:[?#].*)?$/);
        if (mm) a.setAttribute('href', c === 'en' ? (location.hostname ? '/' + mm[1] + '.html' : h) : '/app/legal/' + mm[1] + '?lang=' + encodeURIComponent(c));
      });
    }
    s.addEventListener('change', function () {
      var c = s.value; document.cookie = 'mw_legal_lang=' + encodeURIComponent(c) + ';path=/;max-age=31536000;samesite=lax';
      if (m) { location.href = c === 'en' ? location.pathname : '/app/legal/' + doc + '?lang=' + encodeURIComponent(c); return; }
      links(c);
    });
    if (!m && s.value !== 'en') links(s.value);
    l.appendChild(s); p.appendChild(l); wrap.insertBefore(p, wrap.lastElementChild);
  }).catch(function () {});
})();
