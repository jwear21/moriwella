/* Fingerprint / face sign-in inside the Android app. Runs only where the native biometric plugin exists (the Capacitor shell).
   A random device token is kept behind the phone's biometrics; unlocking it signs this phone in. Copyright (c) JSW Software Designs */
(function () {
  'use strict';
  var C = window.Capacitor, NB = C && C.Plugins && C.Plugins.NativeBiometric;
  if (!NB) return;
  var SERVER = 'moriwella.com', ANY = 2;
  var root = document.querySelector('[data-bio=login]') || document.querySelector('[data-bio=settings]') || document.querySelector('[data-bio=offer]');
  if (!root) return;
  var mode = root.getAttribute('data-bio');
  function T(k) { return root.getAttribute('data-' + k) || ''; }
  function csrf() { var i = document.querySelector('input[name=_csrf]'); return i ? i.value : (root.getAttribute('data-csrf') || ''); }
  function post(path, fields) {
    var b = new URLSearchParams(); b.set('_csrf', csrf()); for (var k in fields) b.set(k, fields[k]);
    return fetch(path, { method: 'POST', body: b, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { j.status = r.status; return j; }); });
  }
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) {} return null; }
  function available() { return NB.isAvailable({ useFallback: false }).then(function (r) { return !!(r && r.isAvailable); }, function () { return false; }); }
  function saved() { return NB.isCredentialsSaved({ server: SERVER }).then(function (r) { return !!(r && r.isSaved); }, function () { return false; }); }
  function forget() { ls('mwBio', null); ls('mwBioId', null); return NB.deleteCredentials({ server: SERVER }).catch(function () {}); }

  function enable() {
    var token, email;
    return post('/app/device/register', {}).then(function (j) {
      if (!j.ok) throw new Error('register');
      token = j.token; email = j.email; ls('mwBioId', String(j.id || ''));
      return NB.setCredentials({ username: email, password: token, server: SERVER, accessControl: ANY, title: T('title'), negativeButtonText: T('cancel') });
    }).then(function () { ls('mwBio', '1'); ls('mwBioNo', null); return true; }).catch(function () {
      if (token) post('/app/device/revoke', { token: token });
      return forget().then(function () { return false; });
    });
  }
  function disable() {
    var id = ls('mwBioId');
    return (id ? post('/app/device/revoke', { id: id }) : Promise.resolve()).catch(function () {}).then(forget);
  }

  /* ----- sign-in page ----- */
  function loginPage() {
    var btn = root.querySelector('[data-bio-go]'), msg = root.querySelector('[data-bio-msg]');
    function say(t) { if (msg) { msg.textContent = t; msg.hidden = !t; } }
    function go() {
      say('');
      return NB.getSecureCredentials({ server: SERVER, title: T('title'), subtitle: T('reason'), negativeButtonText: T('cancel') }).then(function (cr) {
        return post('/app/device/login', { email: cr.username, token: cr.password });
      }).then(function (j) {
        if (j && j.ok) { location.href = j.next || '/app/'; return; }
        if (j && j.status === 401) return forget().then(function () { btn.hidden = true; say(T('failed')); });
        say(T('failed'));
      }, function () { /* cancelled or not recognised: the password form is right there */ });
    }
    Promise.all([available(), saved()]).then(function (r) {
      if (!r[0] || !r[1]) return;
      root.hidden = false; btn.addEventListener('click', go);
      var tried = false; try { tried = sessionStorage.getItem('mwBioTried') === '1'; sessionStorage.setItem('mwBioTried', '1'); } catch (e) {}
      if (!tried) go();
    });
  }

  /* ----- account settings ----- */
  function settingsPage() {
    var state = root.querySelector('[data-bio-state]'), on = root.querySelector('[data-bio-on]'), off = root.querySelector('[data-bio-off]'), msg = root.querySelector('[data-bio-msg]');
    function say(t) { msg.textContent = t; msg.hidden = !t; }
    function paint(isOn) { state.textContent = isOn ? T('is-on') : T('is-off'); on.hidden = isOn; off.hidden = !isOn; }
    available().then(function (ok) {
      root.hidden = false;
      if (!ok) { state.textContent = T('unavailable'); on.hidden = true; off.hidden = true; return; }
      saved().then(function (s) { paint(s); });
      on.addEventListener('click', function () { on.disabled = true; say(''); enable().then(function (done) { on.disabled = false; if (done) { paint(true); say(T('enabled-ok')); } else say(T('enable-err')); }); });
      off.addEventListener('click', function () { off.disabled = true; disable().then(function () { off.disabled = false; paint(false); say(T('disabled-ok')); }); });
    });
  }

  /* ----- one-time offer after signing in ----- */
  function offer() {
    if (ls('mwBio') === '1' || ls('mwBioNo') === '1') return;
    var yes = root.querySelector('[data-bio-yes]'), no = root.querySelector('[data-bio-no]');
    Promise.all([available(), saved()]).then(function (r) {
      if (!r[0] || r[1]) return;
      root.hidden = false;
      yes.addEventListener('click', function () { yes.disabled = true; enable().then(function (done) { root.hidden = true; if (!done) ls('mwBioNo', '1'); }); });
      no.addEventListener('click', function () { ls('mwBioNo', '1'); root.hidden = true; });
    });
  }

  if (mode === 'login') loginPage(); else if (mode === 'settings') settingsPage(); else if (mode === 'offer') offer();
})();
