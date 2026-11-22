/* Desktop webcam capture. Phones honor <input capture>; laptops ignore it and open a file picker, so on a
   non-touch device with a camera we open a small live preview instead. Copyright (c) JSW Software Designs */
(function () {
  var md = navigator.mediaDevices;
  if (!md || !md.getUserMedia || !window.DataTransfer) return;
  var touch = (window.matchMedia && matchMedia('(pointer:coarse)').matches) || (navigator.maxTouchPoints || 0) > 1;
  if (touch) return;
  document.addEventListener('click', function (e) {
    var lab = e.target.closest && e.target.closest('label[for=camera],label[for=wCam]');
    if (!lab) return;
    var input = document.getElementById(lab.getAttribute('for'));
    if (!input) return;
    e.preventDefault();
    var title = (lab.textContent || '').trim();
    md.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false }).then(function (stream) {
      var ov = document.createElement('div');
      ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-label', title);
      ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(10,20,16,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:16px';
      var v = document.createElement('video');
      v.autoplay = true; v.playsInline = true; v.muted = true; v.srcObject = stream;
      v.style.cssText = 'max-width:min(92vw,900px);max-height:70vh;border-radius:14px;background:#000;transform:scaleX(-1)';
      var row = document.createElement('div'); row.style.cssText = 'display:flex;gap:12px';
      var snap = document.createElement('button'); snap.type = 'button'; snap.textContent = title;
      snap.style.cssText = 'font:600 17px system-ui,sans-serif;padding:14px 28px;border-radius:999px;border:0;background:#DDA12F;color:#21362F;cursor:pointer';
      var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.setAttribute('aria-label', 'Close');
      x.style.cssText = 'font:600 18px system-ui,sans-serif;padding:14px 20px;border-radius:999px;border:1px solid rgba(255,255,255,.5);background:none;color:#fff;cursor:pointer';
      row.appendChild(snap); row.appendChild(x); ov.appendChild(v); ov.appendChild(row); document.body.appendChild(ov);
      function close() { stream.getTracks().forEach(function (t) { t.stop(); }); ov.remove(); document.removeEventListener('keydown', key); }
      function key(ev) { if (ev.key === 'Escape') close(); }
      document.addEventListener('keydown', key);
      x.onclick = close;
      snap.onclick = function () {
        var c = document.createElement('canvas'); c.width = v.videoWidth || 1280; c.height = v.videoHeight || 720;
        c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
        c.toBlob(function (b) {
          if (b) {
            var f = new File([b], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
            var dt = new DataTransfer(); dt.items.add(f); input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
          close();
        }, 'image/jpeg', 0.92);
      };
    }).catch(function () { input.click(); });   // no camera or permission refused: fall back to the file picker
  }, true);
})();
