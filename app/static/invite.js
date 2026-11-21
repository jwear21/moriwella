(function () {
  var input = document.querySelector('[data-invite-link]');
  var copy = document.querySelector('[data-invite-copy]');
  var share = document.querySelector('[data-invite-share]');
  if (!input || !copy) return;
  copy.addEventListener('click', function () {
    var label = copy.textContent;
    function done() { copy.textContent = copy.getAttribute('data-done') || 'Copied'; setTimeout(function () { copy.textContent = label; }, 1800); }
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(input.value).then(done, function () { input.select(); document.execCommand('copy'); done(); }); }
    else { input.select(); document.execCommand('copy'); done(); }
  });
  if (navigator.share && share) {
    share.hidden = false;
    share.addEventListener('click', function () { navigator.share({ title: 'Moriwella', text: 'Try Moriwella: remember your life, organize your days, and live well.', url: input.value }).catch(function () {}); });
  }
})();
