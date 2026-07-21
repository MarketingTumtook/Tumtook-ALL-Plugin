(function () {
  var config = {
    root: "[data-ttar-slider]",
    track: "[data-ttar-track]",
    slide: ".ttar-card",
    dotClass: "ttar-dot",
    dots: "[data-ttar-pagination]",
    prev: "[data-ttar-prev]",
    next: "[data-ttar-next]",
    cssPrefix: "ttar",
    pageLabel: "Go to article group "
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
    var isPointerDown = false;
    var isPointerDragging = false;
    var suppressNextClick = false;
    var dragStartX = 0;
    var dragStartScrollLeft = 0;
    var activePointerId = null;
    var hasPointerCapture = false;
    var dragAnimationFrame = null;
    var pendingDragScrollLeft = null;
    var dragFollowEase = 0.72;

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

    function cancelDragAnimation() {
      if (dragAnimationFrame) {
        window.cancelAnimationFrame(dragAnimationFrame);
      }

      dragAnimationFrame = null;
      pendingDragScrollLeft = null;
    }

    function releaseNativeWheelScroll() {
      if (programmaticScrollTimer) {
        window.clearTimeout(programmaticScrollTimer);
        programmaticScrollTimer = null;
      }

      cancelScrollAnimation();
      cancelDragAnimation();
      isProgrammaticScroll = false;
    }

    function scheduleDragScroll() {
      if (dragAnimationFrame) {
        return;
      }

      dragAnimationFrame = window.requestAnimationFrame(function () {
        var targetLeft = pendingDragScrollLeft;
        var distance;

        dragAnimationFrame = null;

        if (targetLeft === null) {
          return;
        }

        distance = targetLeft - track.scrollLeft;

        if (Math.abs(distance) < 0.5) {
          track.scrollLeft = targetLeft;

          if (!isPointerDown) {
            pendingDragScrollLeft = null;
          }

          return;
        }

        track.scrollLeft += distance * dragFollowEase;
        scheduleDragScroll();
      });
    }

    function animateScrollTo(targetLeft, duration) {
      var startLeft = track.scrollLeft;
      var distance = targetLeft - startLeft;
      var startTime = window.performance.now();
      var scrollDuration = duration || 360;

      cancelScrollAnimation();
      cancelDragAnimation();

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
        cancelDragAnimation();
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
      return !!target.closest("button, input, textarea, select, iframe");
    }

    function startViewportDrag(event) {
      if (event.button !== undefined && event.button !== 0) {
        return;
      }

      if (isSliderControl(event.target)) {
        return;
      }

      isPointerDown = true;
      isPointerDragging = false;
      activePointerId = event.pointerId !== undefined ? event.pointerId : null;
      hasPointerCapture = false;
      dragStartX = event.clientX;
      dragStartScrollLeft = track.scrollLeft;
      suppressNextClick = false;

      if (programmaticScrollTimer) {
        window.clearTimeout(programmaticScrollTimer);
        programmaticScrollTimer = null;
      }

      cancelScrollAnimation();
      cancelDragAnimation();
      isProgrammaticScroll = false;
      track.classList.add("is-pointer-down");
    }

    function dragViewport(event) {
      var deltaX;

      if (!isPointerDown) {
        return;
      }

      deltaX = event.clientX - dragStartX;

      if (!isPointerDragging && Math.abs(deltaX) > 5) {
        isPointerDragging = true;
        suppressNextClick = true;
        track.classList.add("is-dragging");

        if (!hasPointerCapture && typeof track.setPointerCapture === "function" && activePointerId !== null) {
          try {
            track.setPointerCapture(activePointerId);
            hasPointerCapture = true;
          } catch (error) {
            // Ignore browsers that do not allow pointer capture on this element.
          }
        }
      }

      if (!isPointerDragging) {
        return;
      }

      event.preventDefault();
      pendingDragScrollLeft = Math.max(0, Math.min(getMaxScrollLeft(), dragStartScrollLeft - deltaX));
      scheduleDragScroll();
    }

    function stopViewportDrag() {
      var shouldUpdateActiveCard;

      if (!isPointerDown) {
        return;
      }

      shouldUpdateActiveCard = isPointerDragging;
      isPointerDown = false;
      isPointerDragging = false;
      track.classList.remove("is-pointer-down", "is-dragging");

      if (hasPointerCapture && typeof track.releasePointerCapture === "function" && activePointerId !== null) {
        try {
          track.releasePointerCapture(activePointerId);
        } catch (error) {
          // Ignore browsers that already released pointer capture.
        }
      }

      activePointerId = null;
      hasPointerCapture = false;

      if (shouldUpdateActiveCard) {
        setCurrentIndex(getNearestSlideIndex(pendingDragScrollLeft !== null ? pendingDragScrollLeft : track.scrollLeft));
        window.setTimeout(function () {
          suppressNextClick = false;
        }, 250);
      }
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
    track.addEventListener("click", function (event) {
      if (!suppressNextClick) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      suppressNextClick = false;
    }, true);

    window.addEventListener("resize", function () {
      syncDesktopContainerInset();
      renderPagination();
    }, { passive: true });

    setCurrentIndex(0);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var sliders = document.querySelectorAll(config.root);

    if (sliders.length) {
      document.documentElement.classList.add("ttar-slider-page");
      document.body.classList.add("ttar-slider-page");
    }

    Array.prototype.forEach.call(sliders, setupSlider);
  });
})();
