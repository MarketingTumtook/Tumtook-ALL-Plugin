(() => {
  const selectors = {
    root: ".video-rollup-slider",
    track: "[data-slider-track]",
    slide: "[data-slide]",
    dots: "[data-slider-dots]",
    prev: "[data-slider-prev]",
    next: "[data-slider-next]",
    play: "[data-play-toggle]",
    duration: "[data-duration]",
    progressTrack: "[data-progress-track]",
    progressFill: "[data-progress-fill]",
    volume: "[data-volume-slider]",
    volumeShell: "[data-volume-shell]",
    volumeToggle: "[data-volume-toggle]",
  };

  const formatTime = (seconds) => {
    if (!Number.isFinite(seconds) || seconds < 0) {
      return "0:00";
    }

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60)
      .toString()
      .padStart(2, "0");

    return `${mins}:${secs}`;
  };

  const updateDurationLabel = (video, durationNode) => {
    if (!durationNode) {
      return;
    }

    const current = formatTime(video.currentTime || 0);

    durationNode.textContent = current;
  };

  const pauseAllVideos = (root, currentVideo) => {
    root.querySelectorAll("video").forEach((video) => {
      if (video !== currentVideo) {
        video.pause();
        video.closest(".video-rollup-video-card")?.classList.remove("is-playing");
      }
    });
  };

  const setActiveSlide = (root, index) => {
    const slides = [...root.querySelectorAll(selectors.slide)];
    slides.forEach((slide) => {
      const slideRealIndex =
        Number.parseInt(slide.dataset.realIndex || "0", 10) || 0;
      slide.classList.toggle("is-active", slideRealIndex === index);
    });

    const dots = [...root.querySelectorAll(".video-rollup-slider__dot")];
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
    let autoplayDisabled = false;
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
    const dragFollowEase = 0.72;
    const isCoarsePointer = window.matchMedia
      ? window.matchMedia("(hover: none), (pointer: coarse)").matches
      : false;

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
        root.style.removeProperty("--video-rollup-first-card-inset");
        return;
      }

      const containerMaxWidth = getContainerMaxWidth();
      const containerInset = Math.max(
        getHeaderContentInset(),
        (window.innerWidth - containerMaxWidth) / 2,
        0
      );
      const trackRect = track.getBoundingClientRect();

      root.style.setProperty(
        "--video-rollup-first-card-inset",
        `${Math.max(0, containerInset - trackRect.left)}px`
      );
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
        (slide) => getRealIndexFromSlide(slide) === ((realIndex % totalSlides) + totalSlides) % totalSlides
      ) || null;

    const getViewportVisibilityRatio = (element) => {
      if (!element) {
        return 0;
      }
      const rect = element.getBoundingClientRect();
      const viewportHeight =
        window.innerHeight || document.documentElement.clientHeight || 0;
      const viewportWidth =
        window.innerWidth || document.documentElement.clientWidth || 0;
      const visibleWidth = Math.max(
        0,
        Math.min(rect.right, viewportWidth) - Math.max(rect.left, 0)
      );
      const visibleHeight = Math.max(
        0,
        Math.min(rect.bottom, viewportHeight) - Math.max(rect.top, 0)
      );
      const visibleArea = visibleWidth * visibleHeight;
      const totalArea = Math.max(rect.width * rect.height, 1);
      return visibleArea / totalArea;
    };

    const autoplayVisibleVideo = () => {
      if (autoplayDisabled) {
        return;
      }
      let activeManaged = null;
      let highestRatio = 0;

      slides.forEach((slide) => {
        const managed = slide.querySelector(".video-rollup-video-card")?.__videoRollup;
        const ratio = slideVisibility.get(slide) || 0;

        if (managed && ratio >= 0.5 && ratio >= highestRatio) {
          activeManaged = managed;
          highestRatio = ratio;
        }
      });

      if (!activeManaged) {
        return;
      }

      if (getViewportVisibilityRatio(activeManaged.video) < 0.5) {
        return;
      }

      pauseAllVideos(root, activeManaged.video);

      if (!activeManaged.video.paused) {
        activeManaged.syncPlayingState();
        return;
      }

      activeManaged.video.play().then(() => {
        activeManaged.syncPlayingState();
      }).catch(() => {});
    };

    const setCurrentIndex = (targetIndex) => {
      currentIndex = Math.max(0, Math.min(totalSlides - 1, targetIndex));
      setActiveSlide(root, currentIndex);

      const reachableIndexes = getReachableIndexes();
      const currentReachableIndex = getCurrentReachableIndex(reachableIndexes);

      if (prev) {
        prev.disabled = currentReachableIndex <= 0;
      }

      if (next) {
        next.disabled = currentReachableIndex >= reachableIndexes.length - 1;
      }
    };

    const clearAutoAdvance = () => {};
    const startAutoAdvance = () => {};

    const getMaxScrollLeft = () => Math.max(track.scrollWidth - track.clientWidth, 0);

    const getTrackPaddingLeft = () =>
      Number.parseFloat(window.getComputedStyle(track).paddingLeft || "0") || 0;

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

    const releaseNativeWheelScroll = () => {
      if (programmaticScrollTimer) {
        window.clearTimeout(programmaticScrollTimer);
        programmaticScrollTimer = null;
      }

      cancelScrollAnimation();
      cancelDragAnimation();
      isProgrammaticScroll = false;
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

    const animateScrollTo = (targetLeft, duration = 360) => {
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
        dot.className = `video-rollup-slider__dot${dotIndex === getCurrentReachableIndex() ? " is-active" : ""}`;
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
      !!target.closest(
        "button, input, textarea, select, iframe, .video-rollup-video-card__controls, .video-rollup-video-card__timeline, [data-play-toggle], [data-progress-track], [data-volume-shell], [data-volume-slider], [data-volume-toggle]"
      );

    const startViewportDrag = (event) => {
      if (event.pointerType && event.pointerType !== "mouse") {
        return;
      }

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

    const stopViewportDrag = () => {
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

    track.addEventListener("wheel", releaseNativeWheelScroll, { passive: true });
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
    window.addEventListener(
      "resize",
      () => {
        syncDesktopContainerInset();
        renderPagination();
      },
      { passive: true }
    );

    // Track visible cards for video autoplay and active state.
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          slideVisibility.set(entry.target, entry.intersectionRatio || 0);
        });

        autoplayVisibleVideo();

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
            setActiveSlide(root, currentIndex);
          }
        }
      },
      { root: track, threshold: [0, 0.25, 0.5, 0.75, 1.0] }
    );

    slides.forEach((slide) => observer.observe(slide));

    window.addEventListener("scroll", autoplayVisibleVideo, { passive: true });
    window.addEventListener("resize", autoplayVisibleVideo, { passive: true });

    slides.forEach((slide) => {
      const card = slide.querySelector(".video-rollup-video-card");
      const video = card?.querySelector("video");
      const playButton = card?.querySelector(selectors.play);
      const duration = card?.querySelector(selectors.duration);
      const progressTrack = card?.querySelector(selectors.progressTrack);
      const progressFill = card?.querySelector(selectors.progressFill);
      const volumeSlider = card?.querySelector(selectors.volume);
      const volumeShell = card?.querySelector(selectors.volumeShell);
      const volumeToggle = card?.querySelector(selectors.volumeToggle);
      const controls = card?.querySelector(".video-rollup-video-card__controls");
      let currentVolume = 0;
      let previousVolume = 0.6;
      let volumeOpenTimer = null;
      let isDraggingVolume = false;
      let isDraggingProgress = false;
      let suppressVolumeToggleClick = false;
      let videoTouchStart = null;

      if (!video || !card) {
        return;
      }

      const openVolumeControls = () => {
        if (!controls) {
          return;
        }

        if (volumeOpenTimer) {
          window.clearTimeout(volumeOpenTimer);
          volumeOpenTimer = null;
        }

        controls.classList.add("is-volume-open");
      };

      const closeVolumeControls = (delay = 120) => {
        if (!controls || isDraggingVolume) {
          return;
        }

        if (volumeOpenTimer) {
          window.clearTimeout(volumeOpenTimer);
        }

        volumeOpenTimer = window.setTimeout(() => {
          controls.classList.remove("is-volume-open");
          volumeOpenTimer = null;
        }, delay);
      };

      const applyVolume = (nextVolume) => {
        const safeVolume = Math.max(0, Math.min(1, Number(nextVolume || 0)));

        currentVolume = safeVolume;

        try {
          video.volume = safeVolume;
        } catch (error) {
          // Some mobile browsers ignore programmatic media volume.
        }

        video.muted = safeVolume === 0;

        if (safeVolume > 0) {
          previousVolume = safeVolume;
        }

        if (volumeSlider) {
          volumeSlider.value = safeVolume.toFixed(2);
        }

        if (volumeShell) {
          volumeShell.style.setProperty("--volume-level", safeVolume.toString());
        }

        if (volumeToggle) {
          const isMuted = safeVolume === 0;
          volumeToggle.classList.toggle("is-muted", isMuted);
          volumeToggle.setAttribute(
            "aria-label",
            isMuted ? "Unmute video" : "Mute video"
          );
        }
      };

      const updateProgress = () => {
        if (!progressTrack || !progressFill) {
          return;
        }

        const ratio =
          Number.isFinite(video.duration) && video.duration > 0
            ? Math.max(0, Math.min(1, video.currentTime / video.duration))
            : 0;

        progressFill.style.width = `calc(${(ratio * 100).toFixed(3)}% - ${ratio > 0 ? "8px" : "0px"})`;
        progressTrack.setAttribute("aria-valuenow", Math.round(ratio * 100).toString());
      };

      const seekFromPointer = (clientX) => {
        if (!progressTrack || !Number.isFinite(video.duration) || video.duration <= 0) {
          return;
        }

        const rect = progressTrack.getBoundingClientRect();
        const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
        video.currentTime = ratio * video.duration;
        updateDurationLabel(video, duration);
        updateProgress();
      };

      const applyVolumeFromPointer = (clientY) => {
        if (!volumeShell) {
          return;
        }

        const rect = volumeShell.getBoundingClientRect();
        const travel = Math.max(rect.height - 32, 1);
        const offsetFromBottom = rect.bottom - clientY - 16;
        const nextVolume = Math.max(0, Math.min(1, offsetFromBottom / travel));

        applyVolume(nextVolume);
      };

      const startVolumeDrag = (event) => {
        if (!volumeShell) {
          return;
        }

        if (event.pointerType === "touch") {
          return;
        }

        openVolumeControls();
        isDraggingVolume = true;

        event.preventDefault();

        const rect = volumeShell.getBoundingClientRect();
        const travel = Math.max(rect.height - 32, 1);

        const updateFromClientY = (clientY) => {
          const offsetFromBottom = rect.bottom - clientY - 16;
          const nextVolume = Math.max(0, Math.min(1, offsetFromBottom / travel));
          applyVolume(nextVolume);
        };

        updateFromClientY(event.clientY);

        const handlePointerMove = (moveEvent) => {
          updateFromClientY(moveEvent.clientY);
        };

        const handlePointerUp = () => {
          isDraggingVolume = false;
          suppressVolumeToggleClick = true;
          window.setTimeout(() => {
            suppressVolumeToggleClick = false;
          }, 0);
          window.removeEventListener("pointermove", handlePointerMove);
          window.removeEventListener("pointerup", handlePointerUp);
          closeVolumeControls(220);
        };

        if (typeof volumeShell.setPointerCapture === "function" && event.pointerId !== undefined) {
          try {
            volumeShell.setPointerCapture(event.pointerId);
          } catch (error) {
            // Ignore browsers that do not allow pointer capture here.
          }
        }

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);
      };

      const startTouchVolumeDrag = (event) => {
        const touch = event.touches && event.touches[0];

        if (!touch || !volumeShell) {
          return;
        }

        openVolumeControls();
        event.preventDefault();

        const rect = volumeShell.getBoundingClientRect();
        const travel = Math.max(rect.height - 32, 1);

        const updateFromClientY = (clientY) => {
          const offsetFromBottom = rect.bottom - clientY - 16;
          const nextVolume = Math.max(0, Math.min(1, offsetFromBottom / travel));
          applyVolume(nextVolume);
        };

        isDraggingVolume = true;
        updateFromClientY(touch.clientY);

        const handleTouchMove = (moveEvent) => {
          const nextTouch = moveEvent.touches && moveEvent.touches[0];

          if (!nextTouch) {
            return;
          }

          moveEvent.preventDefault();
          updateFromClientY(nextTouch.clientY);
        };

        const handleTouchEnd = () => {
          isDraggingVolume = false;
          suppressVolumeToggleClick = true;
          window.setTimeout(() => {
            suppressVolumeToggleClick = false;
          }, 0);
          window.removeEventListener("touchmove", handleTouchMove);
          window.removeEventListener("touchend", handleTouchEnd);
          window.removeEventListener("touchcancel", handleTouchEnd);
          closeVolumeControls(220);
        };

        window.addEventListener("touchmove", handleTouchMove, { passive: false });
        window.addEventListener("touchend", handleTouchEnd);
        window.addEventListener("touchcancel", handleTouchEnd);
      };

      video.muted = true;
      video.defaultMuted = true;
      video.playsInline = true;
      video.preload = "metadata";
      video.setAttribute("playsinline", "");
      video.setAttribute("webkit-playsinline", "");
      video.setAttribute("x5-playsinline", "");
      video.controls = false;
      applyVolume(0);
      updateProgress();

      const syncPlayingState = () => {
        card.classList.toggle("is-playing", !video.paused);
        playButton?.setAttribute("aria-label", video.paused ? "Play video" : "Pause video");
      };

      card.__videoRollup = {
        video,
        syncPlayingState,
      };

      const playCurrentVideo = () => {
        autoplayDisabled = false;
        clearAutoAdvance();
        pauseAllVideos(root, video);

        if (video.networkState === HTMLMediaElement.NETWORK_EMPTY) {
          video.load();
        }

        return video.play().then(() => {
          card.classList.remove("has-play-error");
          syncPlayingState();
        }).catch(() => {
          card.classList.add("has-play-error");
          syncPlayingState();
        });
      };

      playButton?.addEventListener("click", () => {
        if (video.paused) {
          playCurrentVideo();
        } else {
          autoplayDisabled = true;
          video.pause();
          startAutoAdvance();
        }
        syncPlayingState();
      });

      playButton?.addEventListener("touchend", (event) => {
        event.preventDefault();
        playButton.click();
      }, { passive: false });

      volumeSlider?.addEventListener("input", () => {
        openVolumeControls();
        applyVolume(volumeSlider.value);
      });

      volumeToggle?.addEventListener("mouseenter", openVolumeControls);
      volumeToggle?.addEventListener("focus", openVolumeControls);
      volumeToggle?.addEventListener("pointerdown", openVolumeControls);
      volumeToggle?.addEventListener("click", openVolumeControls);
      volumeSlider?.addEventListener("pointerdown", openVolumeControls);
      volumeSlider?.addEventListener("touchstart", openVolumeControls, { passive: true });
      volumeShell?.addEventListener("pointerdown", startVolumeDrag);
      volumeShell?.addEventListener("touchstart", startTouchVolumeDrag, { passive: false });
      volumeShell?.addEventListener("click", (event) => {
        event.preventDefault();
        openVolumeControls();
        applyVolumeFromPointer(event.clientY);
      });
      controls?.addEventListener("mouseenter", openVolumeControls);
      controls?.addEventListener("mouseleave", () => {
        closeVolumeControls(140);
      });
      controls?.addEventListener("focusin", openVolumeControls);
      controls?.addEventListener("focusout", () => {
        closeVolumeControls(140);
      });

      volumeToggle?.addEventListener("click", () => {
        if (suppressVolumeToggleClick) {
          return;
        }

        if (video.muted || currentVolume === 0) {
          applyVolume(previousVolume || 0.6);
        } else {
          applyVolume(0);
        }

        if (!isCoarsePointer && !controls?.matches(":hover") && document.activeElement !== volumeToggle) {
          closeVolumeControls(0);
        }
      });

      progressTrack?.addEventListener("pointerdown", (event) => {
        if (event.pointerType === "touch") {
          return;
        }

        isDraggingProgress = true;
        event.preventDefault();
        clearAutoAdvance();
        seekFromPointer(event.clientX);

        const handlePointerMove = (moveEvent) => {
          seekFromPointer(moveEvent.clientX);
        };

        const handlePointerUp = () => {
          isDraggingProgress = false;
          window.removeEventListener("pointermove", handlePointerMove);
          window.removeEventListener("pointerup", handlePointerUp);

          if (video.paused) {
            startAutoAdvance();
          }
        };

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);
      });

      progressTrack?.addEventListener("keydown", (event) => {
        if (!Number.isFinite(video.duration) || video.duration <= 0) {
          return;
        }

        const step = Math.min(5, video.duration / 10 || 1);

        if (event.key === "ArrowLeft") {
          event.preventDefault();
          video.currentTime = Math.max(0, video.currentTime - step);
        } else if (event.key === "ArrowRight") {
          event.preventDefault();
          video.currentTime = Math.min(video.duration, video.currentTime + step);
        } else {
          return;
        }

        updateDurationLabel(video, duration);
        updateProgress();
      });

      video.addEventListener("click", () => {
        playButton?.click();
      });

      video.addEventListener("touchstart", (event) => {
        const touch = event.touches && event.touches[0];
        videoTouchStart = touch ? { x: touch.clientX, y: touch.clientY } : null;
      }, { passive: true });

      video.addEventListener("touchend", (event) => {
        const touch = event.changedTouches && event.changedTouches[0];

        if (!touch || !videoTouchStart) {
          return;
        }

        const deltaX = Math.abs(touch.clientX - videoTouchStart.x);
        const deltaY = Math.abs(touch.clientY - videoTouchStart.y);
        videoTouchStart = null;

        if (deltaX > 10 || deltaY > 10) {
          return;
        }

        event.preventDefault();
        playButton?.click();
      }, { passive: false });

      video.addEventListener("play", () => {
        clearAutoAdvance();
        pauseAllVideos(root, video);
        syncPlayingState();
      });

      video.addEventListener("loadedmetadata", () => {
        card.classList.add("is-loaded");
        updateDurationLabel(video, duration);
        updateProgress();
      });

      video.addEventListener("loadeddata", () => {
        card.classList.add("is-loaded");
      });

      video.addEventListener("durationchange", () => {
        updateDurationLabel(video, duration);
        updateProgress();
      });

      video.addEventListener("pause", syncPlayingState);
      video.addEventListener("pause", () => {
        startAutoAdvance();
      });
      video.addEventListener("ended", syncPlayingState);
      video.addEventListener("ended", () => {
        video.currentTime = 0;
        updateDurationLabel(video, duration);
        updateProgress();
        startAutoAdvance();
      });
      video.addEventListener("error", () => {
        card.classList.add("has-play-error");
        syncPlayingState();
      });
      video.addEventListener("timeupdate", () => {
        updateDurationLabel(video, duration);
        updateProgress();
      });
    });

    setCurrentIndex(0);
    startAutoAdvance();
    startAutoAdvance();
  };

  document.querySelectorAll(selectors.root).forEach(setupSlider);
})();
