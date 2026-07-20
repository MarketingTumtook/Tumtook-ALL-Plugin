(() => {
  const selectors = {
    root: ".ttbs-showcase",
    track: "[data-slider-track]",
    slide: "[data-slide]",
    dots: "[data-slider-dots]",
    prev: "[data-slider-prev]",
    next: "[data-slider-next]",
  };

  const setActiveSlide = (root, index) => {
    const slides = [...root.querySelectorAll(selectors.slide)];

    slides.forEach((slide) => {
      const slideRealIndex =
        Number.parseInt(slide.dataset.realIndex || "0", 10) || 0;
      slide.classList.toggle("is-active", slideRealIndex === index);
    });

    const dots = [...root.querySelectorAll(".ttbs-showcase__dot")];
    let activeDotIndex = 0;

    dots.forEach((dot, dotIndex) => {
      const dotSlideIndex =
        Number.parseInt(dot.dataset.slideIndex || dotIndex.toString(), 10) || 0;

      if (dotSlideIndex <= index) {
        activeDotIndex = dotIndex;
      }
    });

    dots.forEach((dot, dotIndex) => {
      dot.classList.toggle("is-active", dotIndex === activeDotIndex);
    });
  };

  const setupSlider = (root) => {
    const track = root.querySelector(selectors.track);
    const slides = [...root.querySelectorAll(selectors.slide)];
    const dotsWrap = root.querySelector(selectors.dots);
    const prev = root.querySelector(selectors.prev);
    const next = root.querySelector(selectors.next);
    const slideVisibility = new Map();
    let currentIndex = 0;
    let isProgrammaticScroll = false;
    let programmaticScrollTimer = null;
    let scrollAnimationFrame = null;
    let isPointerDown = false;
    let isPointerDragging = false;
    let suppressNextClick = false;
    let dragStartX = 0;
    let dragStartScrollLeft = 0;
    let activePointerId = null;
    let hasPointerCapture = false;
    let dragAnimationFrame = null;
    let pendingDragScrollLeft = null;
    const dragFollowEase = 0.28;

    if (!track || !slides.length) {
      return;
    }

    const totalSlides = slides.length;

    const getPixelValue = (value) => {
      const parsed = Number.parseFloat(value || "");
      return Number.isFinite(parsed) ? parsed : 0;
    };

    const getContainerMaxWidth = () => {
      let current = root;

      while (current) {
        const containerMaxWidth = getPixelValue(
          window.getComputedStyle(current).getPropertyValue("--container-max-width")
        );

        if (containerMaxWidth > 0) {
          return containerMaxWidth;
        }

        current = current.parentElement;
      }

      return 1920;
    };

    const getHeaderContentInset = () => {
      const headerElement = document.querySelector(
        ".elementor-location-header img, header img.custom-logo, header .site-logo img, .site-header img"
      );

      if (!headerElement) {
        return 0;
      }

      const rect = headerElement.getBoundingClientRect();

      if (rect.left <= 0 || rect.left >= window.innerWidth / 2) {
        return 0;
      }

      return rect.left;
    };

    const syncFullBleedWidth = () => {
      root.style.setProperty("--ttbs-viewport-width", `${window.innerWidth}px`);
      root.style.setProperty("--ttbs-viewport-shift", "0px");

      const rootLeft = root.getBoundingClientRect().left;
      root.style.setProperty("--ttbs-viewport-shift", `${-rootLeft}px`);
    };

    const syncDesktopContainerInset = () => {
      syncFullBleedWidth();

      if (window.innerWidth <= 1024) {
        root.style.removeProperty("--ttbs-first-card-inset");
        return;
      }

      const containerMaxWidth = getContainerMaxWidth();
      const containerInset = Math.max(
        getHeaderContentInset(),
        (window.innerWidth - containerMaxWidth) / 2,
        0
      );
      const trackRect = track.getBoundingClientRect();

      root.style.setProperty("--ttbs-first-card-inset", `${Math.max(0, containerInset - trackRect.left)}px`);
    };

    syncDesktopContainerInset();
    window.addEventListener("resize", syncDesktopContainerInset, { passive: true });

    slides.forEach((slide, index) => {
      slide.dataset.realIndex = index.toString();
    });

    const getRealIndexFromSlide = (slide) => {
      if (!slide) {
        return 0;
      }

      return Number.parseInt(slide.dataset.realIndex || "0", 10) || 0;
    };

    const findSlideByRealIndex = (realIndex) =>
      slides.find(
        (slide) => getRealIndexFromSlide(slide) === realIndex
      ) || null;

    const getMaxScrollLeft = () => Math.max(track.scrollWidth - track.clientWidth, 0);

    const getTrackPaddingLeft = () =>
      getPixelValue(window.getComputedStyle(track).paddingLeft);

    const getSlideRawScrollLeft = (slide) => {
      if (!slide) {
        return 0;
      }

      const slideRect = slide.getBoundingClientRect();
      const trackRect = track.getBoundingClientRect();

      return slideRect.left - trackRect.left + track.scrollLeft - getTrackPaddingLeft();
    };

    const getSlideScrollLeft = (slide) =>
      Math.max(0, Math.min(getMaxScrollLeft(), getSlideRawScrollLeft(slide)));

    const getReachableIndexes = () => {
      const positions = [];

      slides.forEach((slide, index) => {
        const left = getSlideScrollLeft(slide);
        const isDuplicate = positions.some((position) => Math.abs(position.left - left) < 2);

        if (!isDuplicate) {
          positions.push({ index, left });
        }
      });

      return positions.map((position) => position.index);
    };

    const getCurrentReachableIndex = (reachableIndexes = getReachableIndexes()) => {
      let pageIndex = 0;

      reachableIndexes.forEach((slideIndex, index) => {
        if (slideIndex <= currentIndex) {
          pageIndex = index;
        }
      });

      return pageIndex;
    };

    const setCurrentIndex = (targetIndex) => {
      const reachableIndexes = getReachableIndexes();

      currentIndex = Math.max(0, Math.min(totalSlides - 1, targetIndex));
      setActiveSlide(root, currentIndex);

      if (prev) {
        prev.disabled = getCurrentReachableIndex(reachableIndexes) <= 0;
      }

      if (next) {
        next.disabled = getCurrentReachableIndex(reachableIndexes) >= reachableIndexes.length - 1;
      }
    };

    const getNearestSlideIndex = (scrollLeft = track.scrollLeft) => {
      let nearestIndex = currentIndex;
      let nearestDistance = Number.POSITIVE_INFINITY;

      slides.forEach((slide) => {
        const distance = Math.abs(getSlideScrollLeft(slide) - scrollLeft);

        if (distance < nearestDistance) {
          nearestDistance = distance;
          nearestIndex = getRealIndexFromSlide(slide);
        }
      });

      return nearestIndex;
    };

    const cancelScrollAnimation = () => {
      if (!scrollAnimationFrame) {
        return;
      }

      window.cancelAnimationFrame(scrollAnimationFrame);
      scrollAnimationFrame = null;
    };

    const cancelDragAnimation = () => {
      if (dragAnimationFrame) {
        window.cancelAnimationFrame(dragAnimationFrame);
      }

      dragAnimationFrame = null;
      pendingDragScrollLeft = null;
    };

    const scheduleDragScroll = () => {
      if (dragAnimationFrame) {
        return;
      }

      dragAnimationFrame = window.requestAnimationFrame(() => {
        const targetLeft = pendingDragScrollLeft;

        dragAnimationFrame = null;

        if (targetLeft === null) {
          return;
        }

        const distance = targetLeft - track.scrollLeft;

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
    };

    const animateScrollTo = (targetLeft, duration = 560) => {
      const startLeft = track.scrollLeft;
      const distance = targetLeft - startLeft;
      const startTime = window.performance.now();

      cancelScrollAnimation();
      cancelDragAnimation();

      if (Math.abs(distance) < 1) {
        track.scrollLeft = targetLeft;
        return;
      }

      const easeOutCubic = (progress) => 1 - Math.pow(1 - progress, 3);

      const step = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        track.scrollLeft = startLeft + distance * easeOutCubic(progress);

        if (progress < 1) {
          scrollAnimationFrame = window.requestAnimationFrame(step);
          return;
        }

        track.scrollLeft = targetLeft;
        scrollAnimationFrame = null;
      };

      scrollAnimationFrame = window.requestAnimationFrame(step);
    };

    const scrollToSlideIndex = (targetIndex, behavior = "smooth") => {
      const safeIndex = Math.max(0, Math.min(totalSlides - 1, targetIndex));
      const targetSlide = findSlideByRealIndex(safeIndex);

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
          behavior,
        });
      } else {
        animateScrollTo(getSlideScrollLeft(targetSlide));
      }

      programmaticScrollTimer = window.setTimeout(() => {
        isProgrammaticScroll = false;
      }, behavior === "auto" ? 0 : 620);
    };

    const scrollToReachableDirection = (direction) => {
      const reachableIndexes = getReachableIndexes();
      const currentReachableIndex = getCurrentReachableIndex(reachableIndexes);
      const nextReachableIndex = Math.max(
        0,
        Math.min(reachableIndexes.length - 1, currentReachableIndex + direction)
      );
      const targetIndex = reachableIndexes[nextReachableIndex];

      if (targetIndex === undefined || nextReachableIndex === currentReachableIndex) {
        return;
      }

      scrollToSlideIndex(targetIndex);
    };

    const renderPagination = () => {
      const canScroll = slides.length > 1 && getMaxScrollLeft() > 1;

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

      getReachableIndexes().forEach((slideIndex, dotIndex) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = `ttbs-showcase__dot${dotIndex === getCurrentReachableIndex() ? " is-active" : ""}`;
        dot.dataset.slideIndex = slideIndex.toString();
        dot.setAttribute("aria-label", `Go to slide ${slideIndex + 1}`);
        dot.addEventListener("click", () => {
          scrollToSlideIndex(slideIndex);
        });
        dotsWrap.appendChild(dot);
      });

      setActiveSlide(root, currentIndex);
    };

    const isSliderControl = (target) =>
      !!target.closest("button, input, textarea, select, iframe");

    const startViewportDrag = (event) => {
      if (event.button !== undefined && event.button !== 0) {
        return;
      }

      if (isSliderControl(event.target)) {
        return;
      }

      isPointerDown = true;
      isPointerDragging = false;
      activePointerId = event.pointerId ?? null;
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
    };

    const dragViewport = (event) => {
      if (!isPointerDown) {
        return;
      }

      const deltaX = event.clientX - dragStartX;

      if (!isPointerDragging && Math.abs(deltaX) > 5) {
        isPointerDragging = true;
        suppressNextClick = true;
        track.classList.add("is-dragging");

        if (
          !hasPointerCapture &&
          typeof track.setPointerCapture === "function" &&
          activePointerId !== null
        ) {
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
      pendingDragScrollLeft = Math.max(
        0,
        Math.min(getMaxScrollLeft(), dragStartScrollLeft - deltaX)
      );
      scheduleDragScroll();
    };

    const stopViewportDrag = (event) => {
      if (!isPointerDown) {
        return;
      }

      const shouldUpdateActiveCard = isPointerDragging;
      isPointerDown = false;
      isPointerDragging = false;
      track.classList.remove("is-pointer-down", "is-dragging");

      if (
        hasPointerCapture &&
        typeof track.releasePointerCapture === "function" &&
        activePointerId !== null
      ) {
        try {
          track.releasePointerCapture(activePointerId);
        } catch (error) {
          // Ignore browsers that already released pointer capture.
        }
      }

      activePointerId = null;
      hasPointerCapture = false;

      if (shouldUpdateActiveCard) {
        setCurrentIndex(getNearestSlideIndex(pendingDragScrollLeft ?? track.scrollLeft));
        window.setTimeout(() => {
          suppressNextClick = false;
        }, 250);
      }
    };

    renderPagination();

    prev?.addEventListener("click", () => {
      scrollToReachableDirection(-1);
    });

    next?.addEventListener("click", () => {
      scrollToReachableDirection(1);
    });

    track.addEventListener("pointerdown", startViewportDrag);
    track.addEventListener("pointermove", dragViewport);
    track.addEventListener("pointerup", stopViewportDrag);
    track.addEventListener("pointercancel", stopViewportDrag);
    track.addEventListener(
      "click",
      (event) => {
        if (!suppressNextClick) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        suppressNextClick = false;
      },
      true
    );

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          slideVisibility.set(entry.target, entry.intersectionRatio || 0);
        });

        if (isProgrammaticScroll || isPointerDown) {
          return;
        }

        let activeSlide = null;
        let maxRatio = 0;

        slides.forEach((slide) => {
          const ratio = slideVisibility.get(slide) || 0;

          if (ratio > maxRatio) {
            maxRatio = ratio;
            activeSlide = slide;
          }
        });

        if (activeSlide && maxRatio >= 0.5) {
          const realIndex = getRealIndexFromSlide(activeSlide);

          if (realIndex !== currentIndex) {
            currentIndex = realIndex;
            setCurrentIndex(currentIndex);
          }
        }
      },
      { root: track, threshold: [0, 0.25, 0.5, 0.75, 1] }
    );

    slides.forEach((slide) => observer.observe(slide));

    window.addEventListener(
      "resize",
      () => {
        syncDesktopContainerInset();
        renderPagination();
      },
      { passive: true }
    );

    setCurrentIndex(0);
  };

  document.querySelectorAll(selectors.root).forEach(setupSlider);
})();
