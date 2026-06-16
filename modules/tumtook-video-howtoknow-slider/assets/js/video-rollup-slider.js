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

    root.querySelectorAll(".video-rollup-slider__dot").forEach((dot, dotIndex) => {
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
    let autoplayDisabled = false;
    let isProgrammaticScroll = false;
    let programmaticScrollTimer = null;
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
      const trackPaddingLeft = Number.parseFloat(trackStyle.paddingLeft || "0") || 0;
      const firstCardInset =
        Number.parseFloat(
          window.getComputedStyle(root).getPropertyValue("--video-rollup-first-card-inset") || "0"
        ) || 0;
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

    const clearAutoAdvance = () => {};
    const startAutoAdvance = () => {};

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

    // Render Dot Navigation
    if (dotsWrap) {
      dotsWrap.innerHTML = "";
      slides.forEach((_, index) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = `video-rollup-slider__dot${index === 0 ? " is-active" : ""}`;
        dot.setAttribute("aria-label", `Go to slide ${index + 1}`);
        dot.addEventListener("click", () => {
          scrollToSlideIndex(index);
        });
        dotsWrap.appendChild(dot);
      });
    }

    // High performance IntersectionObserver for native snap scroll tracking
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          slideVisibility.set(entry.target, entry.intersectionRatio || 0);
        });

        autoplayVisibleVideo();

        // Only update active dots/indexes during manual swipe/scroll (not during programmatic scrollTo animations)
        if (!isProgrammaticScroll) {
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
        }
      },
      { root: track, threshold: [0, 0.25, 0.5, 0.75, 1.0] }
    );

    slides.forEach((slide) => observer.observe(slide));

    // Next/Prev Buttons
    prev?.addEventListener("click", () => {
      scrollToSlideIndex(currentIndex - 1);
    });
    next?.addEventListener("click", () => {
      scrollToSlideIndex(currentIndex + 1);
    });

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
      let previousVolume = 0.6;
      let volumeOpenTimer = null;
      let isDraggingVolume = false;
      let isDraggingProgress = false;

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

        video.volume = safeVolume;
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
        isDraggingVolume = true;
        event.preventDefault();

        const rect = volumeShell.getBoundingClientRect();
        const travel = Math.max(rect.height - 32, 1);

        const updateFromClientY = (clientY) => {
          const offsetFromBottom = rect.bottom - clientY - 16;
          const nextVolume = Math.max(0, Math.min(1, offsetFromBottom / travel));
          applyVolume(nextVolume);
        };

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

      playButton?.addEventListener("click", () => {
        if (video.paused) {
          autoplayDisabled = false;
          clearAutoAdvance();
          pauseAllVideos(root, video);
          video.play().catch(() => {});
        } else {
          autoplayDisabled = true;
          video.pause();
          startAutoAdvance();
        }
        syncPlayingState();
      });

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
      controls?.addEventListener("mouseenter", openVolumeControls);
      controls?.addEventListener("mouseleave", () => {
        closeVolumeControls(140);
      });
      controls?.addEventListener("focusin", openVolumeControls);
      controls?.addEventListener("focusout", () => {
        closeVolumeControls(140);
      });

      volumeToggle?.addEventListener("click", () => {
        if (video.muted || video.volume === 0) {
          applyVolume(previousVolume || 0.6);
        } else {
          applyVolume(0);
        }

        if (!isCoarsePointer && !controls?.matches(":hover") && document.activeElement !== volumeToggle) {
          closeVolumeControls(0);
        }
      });

      progressTrack?.addEventListener("pointerdown", (event) => {
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

      video.addEventListener("play", () => {
        clearAutoAdvance();
        pauseAllVideos(root, video);
        syncPlayingState();
      });

      video.addEventListener("loadedmetadata", () => {
        updateDurationLabel(video, duration);
        updateProgress();

        if (!video.poster) {
          try {
            video.currentTime = 0.1;
          } catch (error) {
            // Ignore browsers that block seeking before playback.
          }
        }
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
      video.addEventListener("timeupdate", () => {
        updateDurationLabel(video, duration);
        updateProgress();
      });
    });

    setCurrentIndex(0);
    if (window.innerWidth < 768) {
      requestAnimationFrame(() => {
        scrollToSlideIndex(0, "auto");
      });
    } else {
      track.scrollTo({
        left: 0,
        behavior: "auto",
      });
    }
    startAutoAdvance();
    startAutoAdvance();
  };

  document.querySelectorAll(selectors.root).forEach(setupSlider);
})();
