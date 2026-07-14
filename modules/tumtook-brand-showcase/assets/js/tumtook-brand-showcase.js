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
    let slides = [...root.querySelectorAll(selectors.slide)];
    const dotsWrap = root.querySelector(selectors.dots);
    const prev = root.querySelector(selectors.prev);
    const next = root.querySelector(selectors.next);
    let currentIndex = 0;
    const slideVisibility = new Map();
    let isProgrammaticScroll = false;
    let programmaticScrollTimer = null;
    const desktopInputQuery = window.matchMedia ? window.matchMedia("(min-width: 1025px)") : null;
    let isDesktopDragging = false;
    let desktopDragPointerId = null;
    let desktopDragStartX = 0;
    let desktopDragStartLeft = 0;
    let desktopDragMoved = false;
    let suppressTrackClick = false;
    let wheelStepLocked = false;
    let wheelStepTimer = null;

    if (!track || !slides.length) {
      return;
    }

    const totalSlides = slides.length;

    const refreshSlides = () => {
      slides = [...track.querySelectorAll(selectors.slide)];
    };

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
        (slide) =>
          getRealIndexFromSlide(slide) ===
          ((realIndex % totalSlides) + totalSlides) % totalSlides
      ) || null;

    const getMaxScrollLeft = () => Math.max(track.scrollWidth - track.clientWidth, 0);

    const getSlideRawScrollLeft = (slide) => {
      if (!slide) {
        return 0;
      }

      const raw = slide.offsetLeft - track.offsetLeft;
      if (window.innerWidth < 768) {
        return raw - (track.clientWidth - slide.clientWidth) / 2;
      }

      const trackStyle = window.getComputedStyle(track);
      const trackPaddingLeft = getPixelValue(trackStyle.paddingLeft);
      const firstCardInset = getPixelValue(
        window.getComputedStyle(root).getPropertyValue("--ttbs-first-card-inset")
      );

      return raw - Math.max(trackPaddingLeft, firstCardInset);
    };

    const getSlideScrollLeft = (slide) => {
      if (!slide) {
        return 0;
      }

      return Math.max(
        0,
        Math.min(getMaxScrollLeft(), getSlideRawScrollLeft(slide))
      );
    };

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

    const getCurrentPageIndex = (reachableIndexes) => {
      let bestPageIndex = 0;
      let bestDistance = Number.POSITIVE_INFINITY;

      reachableIndexes.forEach((slideIndex, pageIndex) => {
        const slide = findSlideByRealIndex(slideIndex);
        const distance = Math.abs((slide ? getSlideScrollLeft(slide) : 0) - track.scrollLeft);

        if (distance < bestDistance) {
          bestDistance = distance;
          bestPageIndex = pageIndex;
        }
      });

      return bestPageIndex;
    };

    const renderPagination = () => {
      const reachableIndexes = getReachableIndexes();
      const canScroll = reachableIndexes.length > 1;

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

      reachableIndexes.forEach((slideIndex, pageIndex) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = `ttbs-showcase__dot${pageIndex === 0 ? " is-active" : ""}`;
        dot.dataset.slideIndex = slideIndex.toString();
        dot.setAttribute("aria-label", `Go to slide group ${pageIndex + 1}`);
        dot.addEventListener("click", () => {
          scrollToSlideIndex(slideIndex);
        });
        dotsWrap.appendChild(dot);
      });
    };

    const getNearestSlide = () => {
      const currentLeft = track.scrollLeft;
      let nearestSlide = slides[0] || null;
      let nearestDistance = Number.POSITIVE_INFINITY;

      slides.forEach((slide) => {
        const distance = Math.abs(getSlideScrollLeft(slide) - currentLeft);

        if (distance < nearestDistance) {
          nearestDistance = distance;
          nearestSlide = slide;
        }
      });

      return nearestSlide;
    };

    const preserveViewportWhile = (mutateSlides) => {
      const anchorSlide = findSlideByRealIndex(currentIndex) || getNearestSlide();
      const anchorOffset = anchorSlide ? getSlideRawScrollLeft(anchorSlide) : 0;

      mutateSlides();
      refreshSlides();

      if (anchorSlide) {
        track.scrollLeft += getSlideRawScrollLeft(anchorSlide) - anchorOffset;
      }
    };

    const setCurrentIndex = (targetIndex) => {
      currentIndex = ((targetIndex % totalSlides) + totalSlides) % totalSlides;
      setActiveSlide(root, currentIndex);
    };

    const scrollToSlideIndex = (targetIndex, behavior = "smooth") => {
      const safeIndex = ((targetIndex % totalSlides) + totalSlides) % totalSlides;

      isProgrammaticScroll = true;
      if (programmaticScrollTimer) {
        window.clearTimeout(programmaticScrollTimer);
      }

      currentIndex = safeIndex;
      setActiveSlide(root, safeIndex);

      const targetSlide = findSlideByRealIndex(safeIndex);
      if (targetSlide) {
        track.scrollTo({
          left: getSlideScrollLeft(targetSlide),
          behavior,
        });
      }

      programmaticScrollTimer = window.setTimeout(() => {
        isProgrammaticScroll = false;
      }, 500);
    };

    const scrollToSlide = (direction, behavior = "smooth") => {
      if (!direction || totalSlides < 2) {
        return false;
      }

      const reachableIndexes = getReachableIndexes();
      const currentPageIndex = getCurrentPageIndex(reachableIndexes);
      const targetPageIndex = Math.max(0, Math.min(reachableIndexes.length - 1, currentPageIndex + direction));
      const targetIndex = reachableIndexes[targetPageIndex];

      if (targetIndex === undefined || targetPageIndex === currentPageIndex) {
        return false;
      }

      scrollToSlideIndex(targetIndex, behavior);
      return true;
    };

    const isDesktopViewport = () =>
      desktopInputQuery ? desktopInputQuery.matches : window.innerWidth >= 1025;

    const isDesktopSliderInput = (event) =>
      isDesktopViewport() && (!event.pointerType || event.pointerType === "mouse" || event.pointerType === "pen");

    const getWheelDirection = (event) => {
      const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;

      if (Math.abs(delta) < 10) {
        return 0;
      }

      return delta > 0 ? 1 : -1;
    };

    const lockWheelStep = () => {
      wheelStepLocked = true;

      if (wheelStepTimer) {
        window.clearTimeout(wheelStepTimer);
      }

      wheelStepTimer = window.setTimeout(() => {
        wheelStepLocked = false;
        wheelStepTimer = null;
      }, 360);
    };

    const handleDesktopWheel = (event) => {
      let direction;
      let didMove;

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

      didMove = scrollToSlide(direction);

      if (!didMove) {
        return;
      }

      if (event.cancelable) {
        event.preventDefault();
      }

      lockWheelStep();
    };

    const startDesktopDrag = (event) => {
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
    };

    const moveDesktopDrag = (event) => {
      let deltaX;

      if (!isDesktopDragging || event.pointerId !== desktopDragPointerId) {
        return;
      }

      deltaX = event.clientX - desktopDragStartX;

      if (Math.abs(deltaX) < 3) {
        return;
      }

      desktopDragMoved = true;
      isProgrammaticScroll = false;
      track.scrollLeft = desktopDragStartLeft - deltaX;

      if (event.cancelable) {
        event.preventDefault();
      }
    };

    const finishDesktopDrag = (event) => {
      const didMove = desktopDragMoved;
      const nearestSlide = getNearestSlide();

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
        window.setTimeout(() => {
          suppressTrackClick = false;
        }, 0);

        scrollToSlideIndex(getRealIndexFromSlide(nearestSlide));
      }

      return didMove;
    };

    renderPagination();

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          slideVisibility.set(entry.target, entry.intersectionRatio || 0);
        });

        if (isProgrammaticScroll) {
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
            setActiveSlide(root, currentIndex);
          }
        }
      },
      { root: track, threshold: [0, 0.25, 0.5, 0.75, 1] }
    );

    slides.forEach((slide) => observer.observe(slide));

    prev?.addEventListener("click", () => {
      scrollToSlide(-1);
    });

    next?.addEventListener("click", () => {
      scrollToSlide(1);
    });

    track.addEventListener("pointerdown", startDesktopDrag);
    track.addEventListener("pointermove", moveDesktopDrag);
    track.addEventListener("pointerup", finishDesktopDrag);
    track.addEventListener("pointercancel", finishDesktopDrag);
    track.addEventListener(
      "click",
      (event) => {
        if (!suppressTrackClick) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
      },
      true
    );
    track.addEventListener("dragstart", (event) => {
      if (isDesktopViewport()) {
        event.preventDefault();
      }
    });
    track.addEventListener("wheel", handleDesktopWheel, { passive: false });
    track.addEventListener("keydown", (event) => {
      if (!isDesktopViewport()) {
        return;
      }

      if (event.key === "ArrowRight" && scrollToSlide(1)) {
        event.preventDefault();
      } else if (event.key === "ArrowLeft" && scrollToSlide(-1)) {
        event.preventDefault();
      }
    });

    window.addEventListener(
      "resize",
      () => {
        syncDesktopContainerInset();
        renderPagination();
        scrollToSlideIndex(currentIndex, "auto");
      },
      { passive: true }
    );

    renderPagination();
    if (!track.hasAttribute("tabindex")) {
      track.setAttribute("tabindex", "0");
    }
    setCurrentIndex(0);
  };

  document.querySelectorAll(selectors.root).forEach(setupSlider);
})();
