(function () {
  var config = {
    root: "[data-ttpr-slider]",
    track: "[data-ttpr-track]",
    slide: ".ttpr-card",
    dotClass: "ttpr-dot",
    dots: "[data-ttpr-pagination]",
    prev: "[data-ttpr-prev]",
    next: "[data-ttpr-next]",
    cssPrefix: "ttpr",
    pageLabel: "Go to product group "
  };

  function setupSlider(root) {
    var track = root.querySelector(config.track);
    var slides = Array.prototype.slice.call(root.querySelectorAll(config.slide));
    var dotsWrap = root.querySelector(config.dots);
    var prev = root.querySelector(config.prev);
    var next = root.querySelector(config.next);
    var currentIndex = 0;
    var isProgrammaticScroll = false;
    var programmaticScrollTimer = null;
    var scrollAnimationFrame = null;
    var isMousePointerDown = false;
    var isMouseDragging = false;
    var mouseDragStartX = 0;
    var mouseDragStartScrollLeft = 0;
    var activePointerId = null;
    var hasPointerCapture = false;
    var suppressNextClick = false;
    var momentumAnimationFrame = null;
    var mouseDragLastX = 0;
    var mouseDragLastTime = 0;
    var mouseDragVelocity = 0;
    var dragMomentumFriction = 0.94;
    var dragMomentumMinVelocity = 0.04;
    var dragMomentumMaxVelocity = 2.8;

    if (!track || !slides.length) {
      return;
    }

    slides.forEach(function (slide, index) {
      slide.setAttribute("data-real-index", String(index));
    });

    function getPixelValue(value) {
      var parsed = parseFloat(value || "");
      return Number.isFinite(parsed) ? parsed : 0;
    }

    function getContainerMaxWidth() {
      var current = root;

      while (current) {
        var containerMaxWidth = getPixelValue(
          window.getComputedStyle(current).getPropertyValue("--container-max-width")
        );

        if (containerMaxWidth > 0) {
          return containerMaxWidth;
        }

        current = current.parentElement;
      }

      return 1920;
    }

    function getHeaderContentInset() {
      var headerElement = document.querySelector(
        ".elementor-location-header img, header img.custom-logo, header .site-logo img, .site-header img"
      );

      if (!headerElement) {
        return 0;
      }

      var rect = headerElement.getBoundingClientRect();

      if (rect.left <= 0 || rect.left >= window.innerWidth / 2) {
        return 0;
      }

      return rect.left;
    }

    function syncFullBleedWidth() {
      var viewportWidth = document.documentElement.clientWidth || window.innerWidth;

      root.style.setProperty("--" + config.cssPrefix + "-viewport-width", viewportWidth + "px");
      root.style.setProperty("--" + config.cssPrefix + "-viewport-shift", "0px");
      root.style.setProperty("--" + config.cssPrefix + "-viewport-shift", Math.round(-root.getBoundingClientRect().left) + "px");
    }

    function syncDesktopContainerInset() {
      syncFullBleedWidth();

      if (window.innerWidth <= 1024) {
        root.style.removeProperty("--" + config.cssPrefix + "-first-card-inset");
        return;
      }

      var containerMaxWidth = getContainerMaxWidth();
      var containerInset = Math.max(
        getHeaderContentInset(),
        (window.innerWidth - containerMaxWidth) / 2,
        0
      );
      var trackRect = track.getBoundingClientRect();

      root.style.setProperty("--" + config.cssPrefix + "-first-card-inset", Math.max(0, containerInset - trackRect.left) + "px");
    }

    function getRealIndexFromSlide(slide) {
      if (!slide) {
        return 0;
      }

      return parseInt(slide.getAttribute("data-real-index") || "0", 10) || 0;
    }

    function findSlideByRealIndex(realIndex) {
      return slides.find(function (slide) {
        return getRealIndexFromSlide(slide) === realIndex;
      }) || null;
    }

    function getMaxScrollLeft() {
      return Math.max(track.scrollWidth - track.clientWidth, 0);
    }

    function getTrackPaddingLeft() {
      return getPixelValue(window.getComputedStyle(track).paddingLeft);
    }

    function getSlideRawScrollLeft(slide) {
      if (!slide) {
        return 0;
      }

      var slideRect = slide.getBoundingClientRect();
      var trackRect = track.getBoundingClientRect();

      return slideRect.left - trackRect.left + track.scrollLeft - getTrackPaddingLeft();
    }

    function getSlideScrollLeft(slide) {
      return Math.max(0, Math.min(getMaxScrollLeft(), getSlideRawScrollLeft(slide)));
    }

    function getReachableIndexes() {
      var positions = [];

      slides.forEach(function (slide, index) {
        var left = getSlideScrollLeft(slide);
        var isDuplicate = positions.some(function (position) {
          return Math.abs(position.left - left) < 2;
        });

        if (!isDuplicate) {
          positions.push({ index: index, left: left });
        }
      });

      return positions.map(function (position) {
        return position.index;
      });
    }

    function getCurrentReachableIndex(reachableIndexes) {
      var indexes = reachableIndexes || getReachableIndexes();
      var pageIndex = 0;

      indexes.forEach(function (slideIndex, index) {
        if (slideIndex <= currentIndex) {
          pageIndex = index;
        }
      });

      return pageIndex;
    }

    function setActiveSlide(index) {
      var dots = Array.prototype.slice.call(root.querySelectorAll("." + config.dotClass));
      var activeDotIndex = 0;

      slides.forEach(function (slide) {
        slide.classList.toggle("is-active", getRealIndexFromSlide(slide) === index);
      });

      dots.forEach(function (dot, dotIndex) {
        var dotSlideIndex = parseInt(dot.getAttribute("data-slide-index") || String(dotIndex), 10) || 0;

        if (dotSlideIndex <= index) {
          activeDotIndex = dotIndex;
        }
      });

      dots.forEach(function (dot, dotIndex) {
        dot.classList.toggle("is-active", dotIndex === activeDotIndex);
      });
    }

    function setCurrentIndex(targetIndex) {
      var reachableIndexes = getReachableIndexes();

      currentIndex = Math.max(0, Math.min(slides.length - 1, targetIndex));
      setActiveSlide(currentIndex);

      if (prev) {
        prev.disabled = getCurrentReachableIndex(reachableIndexes) <= 0;
      }

      if (next) {
        next.disabled = getCurrentReachableIndex(reachableIndexes) >= reachableIndexes.length - 1;
      }
    }

    function getNearestSlideIndex(scrollLeft) {
      var left = typeof scrollLeft === "number" ? scrollLeft : track.scrollLeft;
      var nearestIndex = currentIndex;
      var nearestDistance = Number.POSITIVE_INFINITY;

      slides.forEach(function (slide) {
        var distance = Math.abs(getSlideScrollLeft(slide) - left);

        if (distance < nearestDistance) {
          nearestDistance = distance;
          nearestIndex = getRealIndexFromSlide(slide);
        }
      });

      return nearestIndex;
    }

    function cancelScrollAnimation() {
      if (!scrollAnimationFrame) {
        return;
      }

      window.cancelAnimationFrame(scrollAnimationFrame);
      scrollAnimationFrame = null;
    }

    function releaseNativeWheelScroll() {
      if (programmaticScrollTimer) {
        window.clearTimeout(programmaticScrollTimer);
        programmaticScrollTimer = null;
      }

      cancelScrollAnimation();
      cancelMomentumAnimation();
      isProgrammaticScroll = false;
    }

    function cancelMomentumAnimation() {
      if (momentumAnimationFrame) {
        window.cancelAnimationFrame(momentumAnimationFrame);
      }

      momentumAnimationFrame = null;
      track.classList.remove("is-momentum-scrolling");
    }

    function animateScrollTo(targetLeft, duration) {
      var startLeft = track.scrollLeft;
      var distance = targetLeft - startLeft;
      var startTime = window.performance.now();
      var scrollDuration = duration || 360;

      cancelScrollAnimation();
      cancelMomentumAnimation();

      if (Math.abs(distance) < 1) {
        track.scrollLeft = targetLeft;
        return;
      }

      function easeOutCubic(progress) {
        return 1 - Math.pow(1 - progress, 3);
      }

      function step(currentTime) {
        var elapsed = currentTime - startTime;
        var progress = Math.min(elapsed / scrollDuration, 1);

        track.scrollLeft = startLeft + distance * easeOutCubic(progress);

        if (progress < 1) {
          scrollAnimationFrame = window.requestAnimationFrame(step);
          return;
        }

        track.scrollLeft = targetLeft;
        scrollAnimationFrame = null;
      }

      scrollAnimationFrame = window.requestAnimationFrame(step);
    }

    function startMomentumScroll(initialVelocity, onComplete) {
      var velocity = Math.max(-dragMomentumMaxVelocity, Math.min(dragMomentumMaxVelocity, initialVelocity));
      var previousTime = window.performance.now();

      cancelMomentumAnimation();

      if (Math.abs(velocity) < dragMomentumMinVelocity) {
        if (onComplete) {
          onComplete();
        }
        return false;
      }

      track.classList.add("is-momentum-scrolling");

      function step(currentTime) {
        var elapsed = Math.min(currentTime - previousTime, 32);
        var maxScrollLeft = getMaxScrollLeft();
        var nextLeft;

        previousTime = currentTime;
        nextLeft = Math.max(0, Math.min(maxScrollLeft, track.scrollLeft + velocity * elapsed));
        track.scrollLeft = nextLeft;

        if ((nextLeft <= 0 && velocity < 0) || (nextLeft >= maxScrollLeft && velocity > 0)) {
          velocity = 0;
        } else {
          velocity *= Math.pow(dragMomentumFriction, elapsed / 16.67);
        }

        if (Math.abs(velocity) < dragMomentumMinVelocity) {
          momentumAnimationFrame = null;
          track.classList.remove("is-momentum-scrolling");
          if (onComplete) {
            onComplete();
          }
          return;
        }

        momentumAnimationFrame = window.requestAnimationFrame(step);
      }

      momentumAnimationFrame = window.requestAnimationFrame(step);
      return true;
    }

    function scrollToSlideIndex(targetIndex, behavior) {
      var safeIndex = Math.max(0, Math.min(slides.length - 1, targetIndex));
      var targetSlide = findSlideByRealIndex(safeIndex);

      if (!targetSlide) {
        return;
      }

      isProgrammaticScroll = true;

      if (programmaticScrollTimer) {
        window.clearTimeout(programmaticScrollTimer);
      }

      setCurrentIndex(safeIndex);

      if (behavior === "auto") {
        cancelScrollAnimation();
        track.scrollTo({
          left: getSlideScrollLeft(targetSlide),
          behavior: "auto"
        });
      } else {
        animateScrollTo(getSlideScrollLeft(targetSlide));
      }

      programmaticScrollTimer = window.setTimeout(function () {
        isProgrammaticScroll = false;
      }, behavior === "auto" ? 0 : 620);
    }

    function scrollToReachableDirection(direction) {
      var reachableIndexes = getReachableIndexes();
      var currentReachableIndex = getCurrentReachableIndex(reachableIndexes);
      var nextReachableIndex = Math.max(0, Math.min(reachableIndexes.length - 1, currentReachableIndex + direction));
      var targetIndex = reachableIndexes[nextReachableIndex];

      if (targetIndex === undefined || nextReachableIndex === currentReachableIndex) {
        return;
      }

      scrollToSlideIndex(targetIndex);
    }

    function renderPagination() {
      var canScroll = slides.length > 1 && getMaxScrollLeft() > 1;

      if (dotsWrap) {
        dotsWrap.innerHTML = "";
        dotsWrap.hidden = !canScroll;
      }

      if (prev) {
        prev.hidden = !canScroll;
      }

      if (next) {
        next.hidden = !canScroll;
      }

      if (!dotsWrap || !canScroll) {
        return;
      }

      getReachableIndexes().forEach(function (slideIndex, dotIndex) {
        var dot = document.createElement("button");

        dot.type = "button";
        dot.className = config.dotClass + (dotIndex === getCurrentReachableIndex() ? " is-active" : "");
        dot.setAttribute("aria-label", config.pageLabel + (dotIndex + 1));
        dot.setAttribute("data-slide-index", String(slideIndex));
        dot.addEventListener("click", function () {
          scrollToSlideIndex(slideIndex);
        });
        dotsWrap.appendChild(dot);
      });

      setActiveSlide(currentIndex);
    }

    function isSliderControl(target) {
      return !!target.closest("a, button, input, textarea, select, iframe");
    }

    function startMouseDrag(event) {
      if (event.pointerType && event.pointerType !== "mouse") {
        return;
      }

      if (event.button !== undefined && event.button !== 0) {
        return;
      }

      if (isSliderControl(event.target)) {
        return;
      }

      isMousePointerDown = true;
      isMouseDragging = false;
      mouseDragStartX = event.clientX;
      mouseDragStartScrollLeft = track.scrollLeft;
      mouseDragLastX = event.clientX;
      mouseDragLastTime = window.performance.now();
      mouseDragVelocity = 0;
      activePointerId = event.pointerId !== undefined ? event.pointerId : null;
      hasPointerCapture = false;
      suppressNextClick = false;
      cancelScrollAnimation();
      cancelMomentumAnimation();
      isProgrammaticScroll = false;
    }

    function moveMouseDrag(event) {
      var deltaX;

      if (!isMousePointerDown) {
        return;
      }

      deltaX = event.clientX - mouseDragStartX;

      if (!isMouseDragging && Math.abs(deltaX) <= 8) {
        return;
      }

      if (!isMouseDragging) {
        isMouseDragging = true;
        suppressNextClick = true;
        track.classList.add("is-dragging");

        if (typeof track.setPointerCapture === "function" && activePointerId !== null) {
          try {
            track.setPointerCapture(activePointerId);
            hasPointerCapture = true;
          } catch (error) {
            // Some browsers disallow capture on fast pointer transitions.
          }
        }
      }

      event.preventDefault();
      var currentTime = window.performance.now();
      var elapsed = Math.max(currentTime - mouseDragLastTime, 8);
      var instantVelocity = (mouseDragLastX - event.clientX) / elapsed;

      mouseDragVelocity = mouseDragVelocity * 0.55 + instantVelocity * 0.45;
      mouseDragLastX = event.clientX;
      mouseDragLastTime = currentTime;
      track.scrollLeft = Math.max(0, Math.min(getMaxScrollLeft(), mouseDragStartScrollLeft - deltaX));
    }

    function stopMouseDrag() {
      var shouldUpdateActiveCard = isMouseDragging;

      if (!isMousePointerDown && !isMouseDragging) {
        return;
      }

      isMousePointerDown = false;
      isMouseDragging = false;
      track.classList.remove("is-dragging");

      if (shouldUpdateActiveCard) {
        if (!startMomentumScroll(mouseDragVelocity, function () {
          setCurrentIndex(getNearestSlideIndex(track.scrollLeft));
        })) {
          setCurrentIndex(getNearestSlideIndex(track.scrollLeft));
        }
      }

      if (hasPointerCapture && typeof track.releasePointerCapture === "function" && activePointerId !== null) {
        try {
          track.releasePointerCapture(activePointerId);
        } catch (error) {
          // Ignore browsers that already released pointer capture.
        }
      }

      activePointerId = null;
      hasPointerCapture = false;
    }

    syncDesktopContainerInset();
    renderPagination();

    if (prev) {
      prev.addEventListener("click", function () {
        scrollToReachableDirection(-1);
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        scrollToReachableDirection(1);
      });
    }

    track.addEventListener("wheel", releaseNativeWheelScroll, { passive: true });
    track.addEventListener("pointerdown", startMouseDrag);
    track.addEventListener("pointermove", moveMouseDrag);
    track.addEventListener("pointerup", stopMouseDrag);
    track.addEventListener("pointercancel", stopMouseDrag);
    track.addEventListener("click", function (event) {
      var card;
      var url;

      if (suppressNextClick) {
        event.preventDefault();
        event.stopPropagation();
        suppressNextClick = false;
        return;
      }

      if (event.target.closest("a, button, input, textarea, select, iframe")) {
        return;
      }

      card = event.target.closest(".ttpr-card[data-card-url]");

      if (!card || !track.contains(card)) {
        return;
      }

      url = card.getAttribute("data-card-url");

      if (url) {
        window.location.assign(url);
      }
    });

    window.addEventListener("resize", function () {
      syncDesktopContainerInset();
      renderPagination();
    }, { passive: true });

    setCurrentIndex(0);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var sliders = document.querySelectorAll(config.root);

    if (sliders.length) {
      document.documentElement.classList.add("ttpr-slider-page");
      document.body.classList.add("ttpr-slider-page");
    }

    Array.prototype.forEach.call(sliders, setupSlider);
  });
})();
