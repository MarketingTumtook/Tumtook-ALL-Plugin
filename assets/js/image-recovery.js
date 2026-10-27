// Compatibility asset for older cached HTML. Does not add controls or request images.
(function () {
  var selector = 'img.tthpr-image,img.ttar-image,img.ttpc-image,img.ttpr-image,img.ttwc-image,.aiv-media img,.ttg-media img';
  document.addEventListener('load', function (event) {
    var image = event.target;
    if (!image.matches || !image.matches(selector)) return;
    image.hidden = false;
    image.style.removeProperty('display');
    var holder = image.parentNode;
    ['tthpr', 'ttar', 'ttpc', 'ttpr', 'ttwc'].forEach(function (prefix) {
      holder.classList.remove(prefix + '-image-link--missing');
    });
    var placeholder = holder.querySelector('.aiv-noimage,.ttg-noimage');
    if (placeholder) placeholder.hidden = true;
  }, true);
})();
