/* Large-text support. Phones can be set to bigger text (Settings > Display > Font size). Chrome scales our text to match,
   which is good, but fixed layouts then collide. This measures the scale and flags the page so the CSS can adapt. */
(function () {
  var root = document.documentElement;
  function scale() {
    var s = 1;
    try {
      s = parseFloat(getComputedStyle(root).fontSize) / 16;
      /* Some browsers scale pixel text but not the root size: compare a 16px probe against a fixed-width reference. */
      var p = document.createElement('span');
      p.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;font:16px/1 sans-serif;left:-9999px;top:0';
      p.textContent = 'MMMMMMMMMM';
      (document.body || root).appendChild(p);
      var w = p.getBoundingClientRect().width;
      p.parentNode.removeChild(p);
      if (w > 0) s = Math.max(s, w / 135); /* ten Ms at 16px sans-serif are about 135px at normal size */
    } catch (e) {}
    if (!isFinite(s) || s < 1) s = 1;
    return Math.min(s, 3);
  }
  function apply() {
    var s = scale();
    root.style.setProperty('--ts', s.toFixed(2));
    root.classList.toggle('ts-lg', s >= 1.2);
    root.classList.toggle('ts-xl', s >= 1.5);
    /* Safety net: if the bottom tab labels still do not fit, show only the current label. */
    var tb = document.querySelector('.tabbar');
    if (tb) {
      root.classList.remove('ts-tabs');
      var tight = false;
      tb.querySelectorAll('a span').forEach(function (sp) { if (sp.scrollWidth > sp.clientWidth + 1) tight = true; });
      if (tight) root.classList.add('ts-tabs');
      root.style.setProperty('--tabbar-h', Math.ceil(tb.getBoundingClientRect().height) + 'px');
    }
  }
  window.mwTextScale = apply;
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  window.addEventListener('load', apply);
  window.addEventListener('resize', apply);
})();
