(function () {
  var selector = 'img.tthpr-image,img.ttar-image,img.ttpc-image,img.ttpr-image,img.ttwc-image,.aiv-media img,.ttg-media img';

  function recovered(image) {
    var holder = image.parentNode;
    var retry = image.__tumtookRetryButton;
    image.hidden = false;
    image.style.removeProperty('display');
    ['tthpr', 'ttar', 'ttpc', 'ttpr', 'ttwc'].forEach(function (prefix) {
      holder.classList.remove(prefix + '-image-link--missing');
    });
    var placeholder = holder.querySelector('.aiv-noimage,.ttg-noimage');
    if (placeholder) placeholder.hidden = true;
    if (retry) retry.hidden = true;
  }

  function failed(image) {
    var original = image.getAttribute('src');
    if (!original) return;
    var retry = image.__tumtookRetryButton;
    if (!retry) {
      retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'tumtook-image-retry';
      retry.textContent = 'ลองโหลดรูปอีกครั้ง';
      retry.style.cssText = 'position:absolute;bottom:12px;left:12px;z-index:40;padding:8px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;color:#222;cursor:pointer;font:inherit;';
      retry.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        retry.disabled = true;
        image.hidden = false;
        image.style.removeProperty('display');
        // Only an explicit user click requests the original image again.
        image.removeAttribute('srcset');
        image.removeAttribute('sizes');
        image.removeAttribute('src');
        image.setAttribute('src', original);
      });
      image.parentNode.appendChild(retry);
      image.__tumtookRetryButton = retry;
    }
    retry.hidden = false;
    retry.disabled = false;
  }

  document.addEventListener('error', function (event) {
    if (event.target.matches && event.target.matches(selector)) failed(event.target);
  }, true);
  document.addEventListener('load', function (event) {
    if (event.target.__tumtookRetryButton && event.target.matches && event.target.matches(selector)) recovered(event.target);
  }, true);
  function inspect() {
    document.querySelectorAll(selector).forEach(function (image) {
      if (image.complete && !image.naturalWidth && image.currentSrc) failed(image);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inspect, { once: true });
  } else {
    inspect();
  }
})();
