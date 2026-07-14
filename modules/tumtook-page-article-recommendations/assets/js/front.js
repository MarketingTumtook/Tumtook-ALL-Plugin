(function () {
  var selectors = {
    root: "[data-ttar-slider]",
    track: "[data-ttar-track]",
    slide: ".ttar-card",
    dots: "[data-ttar-pagination]",
    prev: "[data-ttar-prev]",
    next: "[data-ttar-next]"
  };

  function setActiveSlide(root, index) {
    var slides = Array.prototype.slice.call(root.querySelectorAll(selectors.slide));
    var dots = Array.prototype.slice.call(root.querySelectorAll(".ttar-dot"));
    var isMobileDots = window.matchMedia("(max-width: 767px)").matches;
    var maxVisibleDots = 5;
    var activeDotIndex = 0;
    var startIndex = 0;
    var endIndex = dots.length - 1;

    slides.forEach(function (slide) {
      var slideRealIndex = parseInt(slide.getAttribute("data-real-index") || "0", 10) || 0;
      slide.classList.toggle("is-active", slideRealIndex === index);
    });

    dots.forEach(function (dot, dotIndex) {
      var dotSlideIndex = parseInt(dot.getAttribute("data-slide-index") || String(dotIndex), 10) || 0;

      if (dotSlideIndex <= index) {
        activeDotIndex = dotIndex;
      }
    });

    if (isMobileDots && dots.length > maxVisibleDots) {
      startIndex = Math.max(0, activeDotIndex - Math.floor(maxVisibleDots / 2));
      endIndex = startIndex + maxVisibleDots - 1;

      if (endIndex >= dots.length) {
        endIndex = dots.length - 1;
        startIndex = Math.max(0, endIndex - maxVisibleDots + 1);
      }
    }

    dots.forEach(function (dot, dotIndex) {
      dot.classList.toggle("is-active", dotIndex === activeDotIndex);
      dot.classList.toggle("is-hidden", isMobileDots && dots.length > maxVisibleDots && (dotIndex < startIndex || dotIndex > endIndex));
    });
  }

  function setupSlider(root) {
    var track = root.querySelector(selectors.track);
    var slides = Array.prototype.slice.call(root.querySelectorAll(selectors.slide));
    var dotsWrap = root.querySelector(selectors.dots);
    var prev = root.querySelector(selectors.prev);
    var next = root.querySelector(selectors.next);
    var autoplayDelay = Number(root.getAttribute("data-autoplay-delay") || 4000);
    var currentIndex = 0;
    var isPointerDown = false;
    var isProgrammaticScroll = false;
    var scrollSettleTimer = null;
    var pendingReorder = null;
    var lastManualDirection = 0;
    var lastScrollLeft = 0;
    var autoAdvanceTimer = null;
    var skipBoundaryLoopOnSettle = false;
    var desktopInputQuery = window.matchMedia ? window.matchMedia("(min-width: 1025px)") : null;
    var isDesktopDragging = false;
    var desktopDragPointerId = null;
    var desktopDragStartX = 0;
    var desktopDragStartLeft = 0;
    var desktopDragMoved = false;
    var suppressTrackClick = false;
    var wheelStepLocked = false;
    var wheelStepTimer = null;

    if (!track || !slides.length) {
      return;
    }

    var totalSlides = slides.length;
    var loopEnabled = totalSlides > 1;

    slides.forEach(function (slide, index) {
      slide.setAttribute("data-real-index", String(index));
    });

    function refreshSlides() {
      slides = Array.prototype.slice.call(track.querySelectorAll(selectors.slide));
    }

    function getRealIndexFromSlide(slide) {
      if (!slide) {
        return 0;
      }

      return parseInt(slide.getAttribute("data-real-index") || "0", 10) || 0;
    }

    function findSlideByRealIndex(realIndex) {
      var normalizedIndex = ((realIndex % totalSlides) + totalSlides) % totalSlides;

      return slides.find(function (slide) {
        return getRealIndexFromSlide(slide) === normalizedIndex;
      }) || null;
    }

    function clearAutoAdvance() {
      if (autoAdvanceTimer) {
        window.clearInterval(autoAdvanceTimer);
        autoAdvanceTimer = null;
      }
    }

    function startAutoAdvance() {
      clearAutoAdvance();
      return;
    }

    function setCurrentIndex(targetIndex) {
      currentIndex = ((targetIndex % totalSlides) + totalSlides) % totalSlides;
      setActiveSlide(root, currentIndex);
    }

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

    function syncDesktopContainerInset() {
      if (window.innerWidth < 768) {
        root.style.removeProperty("--ttar-first-card-inset");
        return;
      }

      var containerMaxWidth = getContainerMaxWidth();
      var containerInset = Math.max(
        getHeaderContentInset(),
        (window.innerWidth - containerMaxWidth) / 2,
        0
      );
      var trackRect = track.getBoundingClientRect();

      root.style.setProperty("--ttar-first-card-inset", Math.max(0, containerInset - trackRect.left) + "px");
    }

    function getTrackStartInset() {
      var styles = window.getComputedStyle(track);
      var rootStyles = window.getComputedStyle(root);

      return Math.max(
        getPixelValue(styles.scrollPaddingLeft),
        getPixelValue(styles.paddingLeft),
        getPixelValue(rootStyles.getPropertyValue("--ttar-first-card-inset"))
      );
    }

    function getSlideTargetLeft(slide) {
      if (!slide) {
        return 0;
      }

      return slide.offsetLeft - track.offsetLeft - getTrackStartInset();
    }

    function getClampedSlideTargetLeft(slide) {
      return Math.max(0, Math.min(getSlideTargetLeft(slide), Math.max(track.scrollWidth - track.clientWidth, 0)));
    }

    function getReachableIndexes() {
      var maxScrollLeft = Math.max(track.scrollWidth - track.clientWidth, 0);
      var positions = [];

      slides.forEach(function (slide, index) {
        var targetLeft = Math.max(0, Math.min(getSlideTargetLeft(slide), maxScrollLeft));
        var isDuplicate = positions.some(function (position) {
          return Math.abs(position.left - targetLeft) < 2;
        });

        if (!isDuplicate) {
          positions.push({
            index: index,
            left: targetLeft
          });
        }
      });

      return positions.map(function (position) {
        return position.index;
      });
    }

    function getCurrentPageIndex(reachableIndexes) {
      var currentLeft = track.scrollLeft;
      var bestPageIndex = 0;
      var bestDistance = Number.POSITIVE_INFINITY;

      reachableIndexes.forEach(function (slideIndex, pageIndex) {
        var slide = findSlideByRealIndex(slideIndex);
        var targetLeft = slide
          ? Math.max(0, Math.min(getSlideTargetLeft(slide), Math.max(track.scrollWidth - track.clientWidth, 0)))
          : 0;
        var distance = Math.abs(targetLeft - currentLeft);

        if (distance < bestDistance) {
          bestDistance = distance;
          bestPageIndex = pageIndex;
        }
      });

      return bestPageIndex;
    }

    function renderPagination() {
      var reachableIndexes = getReachableIndexes();
      var canScroll = reachableIndexes.length > 1;

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

      reachableIndexes.forEach(function (slideIndex, pageIndex) {
        var dot = document.createElement("button");
        dot.type = "button";
        dot.className = "ttar-dot" + (pageIndex === 0 ? " is-active" : "");
        dot.setAttribute("aria-label", "Go to article group " + (pageIndex + 1));
        dot.setAttribute("data-slide-index", String(slideIndex));
        dot.addEventListener("click", function () {
          scrollToSlideIndex(slideIndex);
          startAutoAdvance();
        });
        dotsWrap.appendChild(dot);
      });
    }

    function getNearestSlide() {
      var currentLeft = track.scrollLeft;
      var nearestSlide = slides[0] || null;
      var nearestDistance = Number.POSITIVE_INFINITY;

      slides.forEach(function (slide) {
        var targetLeft = getClampedSlideTargetLeft(slide);
        var distance = Math.abs(targetLeft - currentLeft);

        if (distance < nearestDistance) {
          nearestDistance = distance;
          nearestSlide = slide;
        }
      });

      return nearestSlide;
    }

    function getSettledSlide(direction) {
      var currentLeft;
      var tolerance;
      var candidates;
      var bestSlide;
      var bestDistance;

      if (!direction) {
        return getNearestSlide();
      }

      currentLeft = track.scrollLeft;
      tolerance = 8;
      candidates = slides.filter(function (slide) {
        var offset = getSlideTargetLeft(slide);
        return direction > 0
          ? offset >= currentLeft - tolerance
          : offset <= currentLeft + tolerance;
      });

      if (!candidates.length) {
        return getNearestSlide();
      }

      bestSlide = candidates[0];
      bestDistance = Number.POSITIVE_INFINITY;

      candidates.forEach(function (slide) {
        var offset = getSlideTargetLeft(slide);
        var distance = Math.abs(offset - currentLeft);

        if (distance < bestDistance) {
          bestDistance = distance;
          bestSlide = slide;
        }
      });

      return bestSlide;
    }

    function preserveViewportWhile(mutateSlides) {
      var anchorSlide = getNearestSlide();
      var anchorOffset = anchorSlide ? getSlideTargetLeft(anchorSlide) : 0;

      mutateSlides();
      refreshSlides();

      if (anchorSlide) {
        var nextOffset = getSlideTargetLeft(anchorSlide);
        track.scrollLeft += nextOffset - anchorOffset;
      }

      lastScrollLeft = track.scrollLeft;
    }

    function getStepSize() {
      var firstSlide = slides[0];

      if (!firstSlide) {
        return 0;
      }

      var styles = window.getComputedStyle(track);
      var gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
      return firstSlide.getBoundingClientRect().width + gap;
    }

    function isDesktopViewport() {
      return desktopInputQuery ? desktopInputQuery.matches : window.innerWidth >= 1025;
    }

    function isDesktopSliderInput(event) {
      return isDesktopViewport() && (!event.pointerType || event.pointerType === "mouse" || event.pointerType === "pen");
    }

    function getWheelDirection(event) {
      var delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;

      if (Math.abs(delta) < 10) {
        return 0;
      }

      return delta > 0 ? 1 : -1;
    }

    function lockWheelStep() {
      wheelStepLocked = true;

      if (wheelStepTimer) {
        window.clearTimeout(wheelStepTimer);
      }

      wheelStepTimer = window.setTimeout(function () {
        wheelStepLocked = false;
        wheelStepTimer = null;
      }, 360);
    }

    function isAtLoopBoundary(direction, tolerance) {
      var edgeTolerance = typeof tolerance === "number" ? tolerance : 2;
      var maxScrollLeft = Math.max(track.scrollWidth - track.clientWidth, 0);

      if (direction > 0) {
        return maxScrollLeft - track.scrollLeft <= edgeTolerance;
      }

      if (direction < 0) {
        return track.scrollLeft <= edgeTolerance;
      }

      return false;
    }

    function prepareLoopEdge(direction) {
      if (!loopEnabled) {
        return false;
      }

      var stepSize = getStepSize();

      if (!stepSize || totalSlides < 2) {
        return false;
      }

      if (direction > 0 && isAtLoopBoundary(direction)) {
        var firstSlide = track.firstElementChild;

        if (firstSlide) {
          preserveViewportWhile(function () {
            track.appendChild(firstSlide);
          });
          lastManualDirection = 0;
          return true;
        }
      }

      if (direction < 0 && isAtLoopBoundary(direction)) {
        var lastSlide = track.lastElementChild;

        if (lastSlide) {
          preserveViewportWhile(function () {
            track.insertBefore(lastSlide, track.firstElementChild);
          });
          lastManualDirection = 0;
          return true;
        }
      }

      return false;
    }

    function interruptProgrammaticScroll() {
      if (!isProgrammaticScroll && !pendingReorder) {
        return;
      }

      track.scrollTo({
        left: track.scrollLeft,
        behavior: "auto"
      });

      if (scrollSettleTimer) {
        window.clearTimeout(scrollSettleTimer);
        scrollSettleTimer = null;
      }

      if (pendingReorder) {
        finalizeReorder();
      } else {
        isProgrammaticScroll = false;
        skipBoundaryLoopOnSettle = false;
      }
    }

    function scrollToSlideIndex(targetIndex, behavior) {
      var scrollBehavior = behavior || "smooth";
      var targetSlide;

      if (scrollBehavior === "smooth" && (isProgrammaticScroll || pendingReorder)) {
        interruptProgrammaticScroll();
      }

      setCurrentIndex(targetIndex);
      targetSlide = findSlideByRealIndex(targetIndex);

      if (!targetSlide) {
        return;
      }

      isProgrammaticScroll = scrollBehavior === "smooth";
      skipBoundaryLoopOnSettle = scrollBehavior === "smooth";

      track.scrollTo({
        left: getSlideTargetLeft(targetSlide),
        behavior: scrollBehavior
      });
    }

    function finalizeReorder() {
      var stepSize = getStepSize();

      if (!stepSize) {
        pendingReorder = null;
        isProgrammaticScroll = false;
        skipBoundaryLoopOnSettle = false;
        return;
      }

      if (pendingReorder === "next") {
        var firstSlide = track.firstElementChild;

        if (firstSlide) {
          preserveViewportWhile(function () {
            track.appendChild(firstSlide);
          });
        }
      } else if (pendingReorder === "prev") {
        var lastSlide = track.lastElementChild;

        if (lastSlide) {
          preserveViewportWhile(function () {
            track.insertBefore(lastSlide, track.firstElementChild);
          });
        }
      }

      pendingReorder = null;
      isProgrammaticScroll = false;
      lastManualDirection = 0;
      skipBoundaryLoopOnSettle = false;
      setActiveSlide(root, currentIndex);
    }

    renderPagination();

    function scrollToSlide(direction) {
      if (isProgrammaticScroll || pendingReorder) {
        interruptProgrammaticScroll();
      }

      if (totalSlides < 2 || !direction) {
        return false;
      }

      var reachableIndexes = getReachableIndexes();
      var currentPageIndex = getCurrentPageIndex(reachableIndexes);
      var targetPageIndex = Math.max(0, Math.min(reachableIndexes.length - 1, currentPageIndex + direction));
      var targetIndex = reachableIndexes[targetPageIndex];

      if (targetIndex === undefined || targetPageIndex === currentPageIndex) {
        return false;
      }

      scrollToSlideIndex(targetIndex);
      return true;
    }

    function handleDesktopWheel(event) {
      var direction;
      var didMove;

      if (!isDesktopViewport() || event.ctrlKey || event.metaKey || totalSlides < 2) {
        return;
      }

      direction = getWheelDirection(event);

      if (!direction) {
        return;
      }

      if (wheelStepLocked) {
        if (event.cancelable) {
          event.preventDefault();
        }
        return;
      }

      clearAutoAdvance();
      didMove = scrollToSlide(direction);

      if (!didMove) {
        startAutoAdvance();
        return;
      }

      if (event.cancelable) {
        event.preventDefault();
      }

      lockWheelStep();
      startAutoAdvance();
    }

    function startDesktopDrag(event) {
      if (!isDesktopSliderInput(event) || totalSlides < 2) {
        return;
      }

      isDesktopDragging = true;
      desktopDragPointerId = event.pointerId;
      desktopDragStartX = event.clientX;
      desktopDragStartLeft = track.scrollLeft;
      desktopDragMoved = false;
      track.classList.add("is-dragging");

      if (typeof track.setPointerCapture === "function" && event.pointerId !== undefined) {
        try {
          track.setPointerCapture(event.pointerId);
        } catch (error) {
          // Ignore browsers that decline pointer capture for this event.
        }
      }
    }

    function moveDesktopDrag(event) {
      var deltaX;

      if (!isDesktopDragging || event.pointerId !== desktopDragPointerId) {
        return;
      }

      deltaX = event.clientX - desktopDragStartX;

      if (Math.abs(deltaX) < 3) {
        return;
      }

      desktopDragMoved = true;
      pendingReorder = null;
      isProgrammaticScroll = false;
      track.scrollLeft = desktopDragStartLeft - deltaX;

      if (event.cancelable) {
        event.preventDefault();
      }
    }

    function finishDesktopDrag(event) {
      var didMove = desktopDragMoved;
      var nearestSlide;

      if (!isDesktopDragging || event.pointerId !== desktopDragPointerId) {
        return false;
      }

      if (typeof track.releasePointerCapture === "function" && event.pointerId !== undefined) {
        try {
          track.releasePointerCapture(event.pointerId);
        } catch (error) {
          // Ignore browsers that already released pointer capture.
        }
      }

      isDesktopDragging = false;
      desktopDragPointerId = null;
      desktopDragMoved = false;
      track.classList.remove("is-dragging");

      if (didMove) {
        suppressTrackClick = true;
        window.setTimeout(function () {
          suppressTrackClick = false;
        }, 0);

        nearestSlide = getNearestSlide();
        scrollToSlideIndex(getRealIndexFromSlide(nearestSlide));
      }

      return didMove;
    }

    if (prev) {
      prev.addEventListener("click", function () {
        scrollToSlide(-1);
        startAutoAdvance();
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        scrollToSlide(1);
        startAutoAdvance();
      });
    }

    track.addEventListener("pointerdown", function (event) {
      isPointerDown = true;
      pendingReorder = null;
      isProgrammaticScroll = false;
      clearAutoAdvance();
      startDesktopDrag(event);
    });

    track.addEventListener("pointermove", moveDesktopDrag);

    track.addEventListener("pointerup", function (event) {
      finishDesktopDrag(event);
      isPointerDown = false;
      startAutoAdvance();
    });

    track.addEventListener("pointercancel", function (event) {
      finishDesktopDrag(event);
      isPointerDown = false;
      startAutoAdvance();
    });

    track.addEventListener("click", function (event) {
      if (!suppressTrackClick) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    }, true);

    track.addEventListener("dragstart", function (event) {
      if (isDesktopViewport()) {
        event.preventDefault();
      }
    });

    track.addEventListener("wheel", handleDesktopWheel, { passive: false });

    track.addEventListener("keydown", function (event) {
      if (!isDesktopViewport()) {
        return;
      }

      if (event.key === "ArrowRight" && scrollToSlide(1)) {
        event.preventDefault();
      } else if (event.key === "ArrowLeft" && scrollToSlide(-1)) {
        event.preventDefault();
      }
    });

    track.addEventListener("scroll", function () {
      var currentScrollLeft = track.scrollLeft;

      if (!isProgrammaticScroll) {
        var delta = currentScrollLeft - lastScrollLeft;

        if (Math.abs(delta) > 1) {
          lastManualDirection = Math.sign(delta);
        }
      }

      lastScrollLeft = currentScrollLeft;

      if (!isProgrammaticScroll && !isPointerDown && !pendingReorder) {
        var nearestSlide = getNearestSlide();
        setCurrentIndex(getRealIndexFromSlide(nearestSlide));
      }

      if (scrollSettleTimer) {
        window.clearTimeout(scrollSettleTimer);
      }

      scrollSettleTimer = window.setTimeout(function () {
        if (!isPointerDown) {
          var shouldSkipBoundaryLoop = skipBoundaryLoopOnSettle;
          var nearestSlide = getSettledSlide(lastManualDirection);

          if (pendingReorder) {
            finalizeReorder();
          } else {
            isProgrammaticScroll = false;
          }

          if (shouldSkipBoundaryLoop) {
            setActiveSlide(root, currentIndex);
          } else {
            setCurrentIndex(getRealIndexFromSlide(nearestSlide));
          }
          skipBoundaryLoopOnSettle = false;
          lastManualDirection = 0;
          startAutoAdvance();
        }
      }, 140);
    });

    root.addEventListener("mouseenter", clearAutoAdvance);
    root.addEventListener("mouseleave", startAutoAdvance);

    window.addEventListener("resize", function () {
      syncDesktopContainerInset();
      renderPagination();
      scrollToSlideIndex(currentIndex, "auto");
    });

    syncDesktopContainerInset();
    renderPagination();
    if (!track.hasAttribute("tabindex")) {
      track.setAttribute("tabindex", "0");
    }
    setCurrentIndex(0);
    startAutoAdvance();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var sliders = document.querySelectorAll(selectors.root);

    if (sliders.length) {
      document.documentElement.classList.add("ttar-slider-page");
      document.body.classList.add("ttar-slider-page");
    }

    Array.prototype.forEach.call(sliders, setupSlider);
  });
})();
