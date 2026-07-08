(function () {
  var selectors = {
    root: "[data-ttpr-slider]",
    track: "[data-ttpr-track]",
    slide: ".ttpr-card",
    dots: "[data-ttpr-pagination]",
    prev: "[data-ttpr-prev]",
    next: "[data-ttpr-next]"
  };

  function setActiveSlide(root, index) {
    var slides = Array.prototype.slice.call(root.querySelectorAll(selectors.slide));

    slides.forEach(function (slide) {
      var slideRealIndex = parseInt(slide.getAttribute("data-real-index") || "0", 10) || 0;
      slide.classList.toggle("is-active", slideRealIndex === index);
    });

    var dots = Array.prototype.slice.call(root.querySelectorAll(".ttpr-dot"));
    var activeDotIndex = 0;

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

  function setupSlider(root) {
    var track = root.querySelector(selectors.track);
    var slides = Array.prototype.slice.call(root.querySelectorAll(selectors.slide));
    var dotsWrap = root.querySelector(selectors.dots);
    var prev = root.querySelector(selectors.prev);
    var next = root.querySelector(selectors.next);
    var currentIndex = 0;
    var isPointerDown = false;
    var isProgrammaticScroll = false;
    var scrollSettleTimer = null;
    var pendingReorder = null;
    var lastManualDirection = 0;
    var lastScrollLeft = 0;
    var skipBoundaryLoopOnSettle = false;
    var pointerStartX = 0;
    var pointerStartY = 0;
    var suppressCardClick = false;

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
        root.style.removeProperty("--ttpr-first-card-inset");
        return;
      }

      var containerMaxWidth = getContainerMaxWidth();
      var containerInset = Math.max(
        getHeaderContentInset(),
        (window.innerWidth - containerMaxWidth) / 2,
        0
      );
      var trackRect = track.getBoundingClientRect();

      root.style.setProperty("--ttpr-first-card-inset", Math.max(0, containerInset - trackRect.left) + "px");
    }

    function getTrackStartInset() {
      var styles = window.getComputedStyle(track);
      var rootStyles = window.getComputedStyle(root);

      return Math.max(
        getPixelValue(styles.scrollPaddingLeft),
        getPixelValue(styles.paddingLeft),
        getPixelValue(rootStyles.getPropertyValue("--ttpr-first-card-inset"))
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
        dot.className = "ttpr-dot" + (pageIndex === 0 ? " is-active" : "");
        dot.setAttribute("aria-label", "Go to product group " + (pageIndex + 1));
        dot.setAttribute("data-slide-index", String(slideIndex));
        dot.addEventListener("click", function () {
          scrollToSlideIndex(slideIndex);
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

    function syncCurrentIndexFromViewport() {
      var nearestSlide = getNearestSlide();
      setCurrentIndex(getRealIndexFromSlide(nearestSlide));
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
        return;
      }

      var reachableIndexes = getReachableIndexes();
      var currentPageIndex = getCurrentPageIndex(reachableIndexes);
      var targetPageIndex = Math.max(0, Math.min(reachableIndexes.length - 1, currentPageIndex + direction));
      var targetIndex = reachableIndexes[targetPageIndex];

      if (targetIndex === undefined || targetPageIndex === currentPageIndex) {
        return;
      }

      scrollToSlideIndex(targetIndex);
    }

    if (prev) {
      prev.addEventListener("click", function () {
        scrollToSlide(-1);
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        scrollToSlide(1);
      });
    }

    track.addEventListener("pointerdown", function (event) {
      isPointerDown = true;
      pointerStartX = event.clientX;
      pointerStartY = event.clientY;
      suppressCardClick = false;
      pendingReorder = null;
      isProgrammaticScroll = false;
    });

    track.addEventListener("pointerup", function (event) {
      var deltaX = Math.abs(event.clientX - pointerStartX);
      var deltaY = Math.abs(event.clientY - pointerStartY);

      if (deltaX > 8 || deltaY > 8) {
        suppressCardClick = true;
        window.setTimeout(function () {
          suppressCardClick = false;
        }, 0);
      }

      isPointerDown = false;
      syncCurrentIndexFromViewport();
    });

    track.addEventListener("pointercancel", function () {
      suppressCardClick = true;
      window.setTimeout(function () {
        suppressCardClick = false;
      }, 0);
      isPointerDown = false;
      syncCurrentIndexFromViewport();
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

          if (!shouldSkipBoundaryLoop) {
            setCurrentIndex(getRealIndexFromSlide(nearestSlide));
          } else {
            setActiveSlide(root, currentIndex);
          }

          skipBoundaryLoopOnSettle = false;
          lastManualDirection = 0;
        }
      }, 140);
    });

    slides.forEach(function (slide) {
      slide.addEventListener("click", function (event) {
        var target = event.target;
        var detailLink = slide.querySelector(".ttpr-button");

        if (!detailLink || suppressCardClick) {
          return;
        }

        if (target && target.closest("a, button, input, textarea, select")) {
          return;
        }

        window.location.href = detailLink.href;
      });
    });

    window.addEventListener("resize", function () {
      syncDesktopContainerInset();
      renderPagination();
      scrollToSlideIndex(currentIndex, "auto");
    });

    syncDesktopContainerInset();
    renderPagination();
    setCurrentIndex(0);
  }

  document.addEventListener("DOMContentLoaded", function () {
    var sliders = document.querySelectorAll(selectors.root);
    Array.prototype.forEach.call(sliders, setupSlider);
  });
})();
