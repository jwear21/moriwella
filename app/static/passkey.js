/* Passkey sign-in (fingerprint, face or PIN of a laptop, tablet or phone) through the browser's WebAuthn. Copyright (c) JSW Software Designs */
(function () {
  'use strict';
  var root = document.querySelector('[data-pk=login]') || document.querySelector('[data-pk=settings]');
  if (!root || !window.PublicKeyCredential || !navigator.credentials) return;
  var mode = root.getAttribute('data-pk');
  function T(k) { return root.getAttribute('data-' + k) || ''; }
  function csrf() { var i = document.querySelector('input[name=_csrf]'); return i ? i.value : ''; }
  function b2u(buf) { var s = '', a = new Uint8Array(buf); for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function u2b(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; var r = atob(s), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a.buffer; }
  function post(path, f) {
    var b = new URLSearchParams(); b.set('_csrf', csrf()); for (var k in f) b.set(k, f[k]);
    return fetch(path, { method: 'POST', body: b, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { j.status = r.status; return j; }); });
  }
  function msg(t, err) { var m = root.querySelector('[data-pk-msg]'); if (!m) return; m.textContent = t || ''; m.hidden = !t; m.className = 'note' + (err ? ' note--err' : ''); }
  function show(el, on) { if (el) el.hidden = !on; }

  function signIn() {
    msg('');
    return post('/app/passkey/login-options', {}).then(function (o) {
      if (!o.ok) throw new Error('opts');
      var pk = o.publicKey; pk.challenge = u2b(pk.challenge);
      return navigator.credentials.get({ publicKey: pk });
    }).then(function (c) {
      var r = c.response;
      return post('/app/passkey/login', { id: c.id, clientData: b2u(r.clientDataJSON), authData: b2u(r.authenticatorData), signature: b2u(r.signature) });
    }).then(function (j) { if (j.ok) location.href = j.next; else throw new Error('bad'); })
      .catch(function (e) { if (!e || e.name !== 'NotAllowedError') msg(T('failed'), true); });
  }
  function add() {
    msg('');
    return post('/app/passkey/reg-options', {}).then(function (o) {
      if (!o.ok) throw new Error('opts');
      var pk = o.publicKey; pk.challenge = u2b(pk.challenge); pk.user.id = u2b(pk.user.id);
      (pk.excludeCredentials || []).forEach(function (x) { x.id = u2b(x.id); });
      return navigator.credentials.create({ publicKey: pk });
    }).then(function (c) {
      var r = c.response, label = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';
      return post('/app/passkey/register', { clientData: b2u(r.clientDataJSON), attestation: b2u(r.attestationObject), label: label });
    }).then(function (j) { if (!j.ok) throw new Error('bad'); location.reload(); })
      .catch(function (e) { if (!e || e.name !== 'NotAllowedError') msg(T('enable-err'), true); });
  }

  if (mode === 'login') {
    var btn = root.querySelector('[data-pk-go]');
    show(root, true); btn.addEventListener('click', signIn);
  } else {
    show(root, true);
    root.querySelector('[data-pk-add]').addEventListener('click', add);
    Array.prototype.forEach.call(root.querySelectorAll('[data-pk-del]'), function (b) {
      b.addEventListener('click', function () { post('/app/passkey/remove', { id: b.getAttribute('data-pk-del') }).then(function () { location.reload(); }); });
    });
  }
})();
