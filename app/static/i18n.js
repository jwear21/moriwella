/* Translated phrases for scripts. The server writes the current language's "js.*" phrases into #mw-i18n;
   T(key, englishText) returns the translation or the English text, and fills {name} placeholders. */
(function () {
  var dict = {};
  try { var n = document.getElementById('mw-i18n'); if (n) dict = JSON.parse(n.textContent) || {}; } catch (e) {}
  window.T = function (key, fallback, vars) {
    var s = Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : fallback;
    if (vars) for (var k in vars) if (Object.prototype.hasOwnProperty.call(vars, k)) s = s.split('{' + k + '}').join(String(vars[k]));
    return s;
  };
})();
