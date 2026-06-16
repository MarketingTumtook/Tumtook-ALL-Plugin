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

    root.querySelectorAll(".ttbs-showcase__dot").forEach((dot, dotIndex) => {
      dot.classList.toggle("is-active", dotIndex === index);
    });
  };

  const setupSlider = (root) => {
    const track = root.querySelector(selectors.track);
    const slides = [...root.querySelectorAll(selectors.slide)];
    const dotsWrap = root.querySelector(selectors.dots);
    const prev = root.querySelector(selectors.prev);
    const next = root.querySelector(selectors.next);
    let currentIndex = 0;
    const slideVisibility = new Map();
    let isProgrammaticScroll = false;
    let programmaticScrollTimer = null;

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

    const syncDesktopContainerInset = () => {
      if (window.innerWidth < 768) {
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

    if (dotsWrap) {
      dotsWrap.innerHTML = "";
      slides.forEach((_, index) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = `ttbs-showcase__dot${index === 0 ? " is-active" : ""}`;
        dot.setAttribute("aria-label", `Go to slide ${index + 1}`);
        dot.addEventListener("click", () => {
          scrollToSlideIndex(index);
        });
        dotsWrap.appendChild(dot);
      });
    }

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
      scrollToSlideIndex(currentIndex - 1);
    });

    next?.addEventListener("click", () => {
      scrollToSlideIndex(currentIndex + 1);
    });

    window.addEventListener(
      "resize",
      () => {
        syncDesktopContainerInset();
        scrollToSlideIndex(currentIndex, "auto");
      },
      { passive: true }
    );

    setCurrentIndex(0);
  };

  document.querySelectorAll(selectors.root).forEach(setupSlider);
})();
