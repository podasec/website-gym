/**
 * POWER GYM - Testimonials Slider
 * slider.js
 *
 * Features:
 *  - Auto-play every 4 seconds
 *  - Dot navigation
 *  - Prev / Next arrow buttons
 *  - Touch / swipe support (mobile)
 *  - Pause on hover
 *  - Keyboard navigation (← →)
 *  - Accessible (aria attributes)
 */

'use strict';

(function initSlider() {
  /* -------------------------------------------------------
     Query required DOM elements
  ------------------------------------------------------- */
  const sliderWrapper = document.querySelector('.slider-wrapper');
  if (!sliderWrapper) return;

  const track  = sliderWrapper.querySelector('.slider-track');
  const slides = Array.from(sliderWrapper.querySelectorAll('.slide'));
  if (!slides.length) return;

  const dotsContainer = document.querySelector('.slider-dots');
  const prevBtn       = document.querySelector('.slider-btn.prev');
  const nextBtn       = document.querySelector('.slider-btn.next');

  const TOTAL      = slides.length;
  const AUTO_DELAY = 4000; // ms

  let currentIndex  = 0;
  let autoPlayTimer = null;
  let isHovered     = false;

  /* -------------------------------------------------------
     Create dot buttons
  ------------------------------------------------------- */
  if (dotsContainer) {
    slides.forEach(function (_, i) {
      const dot = document.createElement('button');
      dot.classList.add('slider-dot');
      dot.setAttribute('aria-label', 'Vai alla recensione ' + (i + 1));
      dot.setAttribute('type', 'button');
      if (i === 0) dot.classList.add('active');

      dot.addEventListener('click', function () {
        goTo(i);
        resetAutoPlay();
      });

      dotsContainer.appendChild(dot);
    });
  }

  function getDots() {
    return dotsContainer ? Array.from(dotsContainer.querySelectorAll('.slider-dot')) : [];
  }

  /* -------------------------------------------------------
     Core navigation
  ------------------------------------------------------- */
  function goTo(index) {
    // Clamp index with wrap-around
    currentIndex = ((index % TOTAL) + TOTAL) % TOTAL;

    // Move the track
    track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

    // Update aria on track
    track.setAttribute('aria-live', 'polite');

    // Update dots
    getDots().forEach(function (dot, i) {
      dot.classList.toggle('active', i === currentIndex);
      dot.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');
    });

    // Update slides aria-hidden
    slides.forEach(function (slide, i) {
      slide.setAttribute('aria-hidden', i !== currentIndex ? 'true' : 'false');
    });

    // Update prev/next button states
    updateArrows();
  }

  function goNext() {
    goTo(currentIndex + 1);
  }

  function goPrev() {
    goTo(currentIndex - 1);
  }

  function updateArrows() {
    // For infinite loop sliders we never truly disable arrows
    // but we update aria-disabled for accessibility
    if (prevBtn) prevBtn.setAttribute('aria-disabled', 'false');
    if (nextBtn) nextBtn.setAttribute('aria-disabled', 'false');
  }

  /* -------------------------------------------------------
     Arrow button listeners
  ------------------------------------------------------- */
  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      goPrev();
      resetAutoPlay();
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      goNext();
      resetAutoPlay();
    });
  }

  /* -------------------------------------------------------
     Auto-play
  ------------------------------------------------------- */
  function startAutoPlay() {
    if (autoPlayTimer) return;
    autoPlayTimer = setInterval(function () {
      if (!isHovered) {
        goNext();
      }
    }, AUTO_DELAY);
  }

  function stopAutoPlay() {
    clearInterval(autoPlayTimer);
    autoPlayTimer = null;
  }

  function resetAutoPlay() {
    stopAutoPlay();
    startAutoPlay();
  }

  /* -------------------------------------------------------
     Pause on hover
  ------------------------------------------------------- */
  sliderWrapper.addEventListener('mouseenter', function () {
    isHovered = true;
  });

  sliderWrapper.addEventListener('mouseleave', function () {
    isHovered = false;
  });

  /* -------------------------------------------------------
     Keyboard navigation
  ------------------------------------------------------- */
  sliderWrapper.setAttribute('tabindex', '0');
  sliderWrapper.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') {
      goPrev();
      resetAutoPlay();
    } else if (e.key === 'ArrowRight') {
      goNext();
      resetAutoPlay();
    }
  });

  /* -------------------------------------------------------
     Touch / Swipe support
  ------------------------------------------------------- */
  let touchStartX  = 0;
  let touchStartY  = 0;
  let touchEndX    = 0;
  let isDragging   = false;
  const SWIPE_THRESHOLD = 50; // px

  sliderWrapper.addEventListener('touchstart', function (e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    isDragging  = false;
  }, { passive: true });

  sliderWrapper.addEventListener('touchmove', function (e) {
    if (!isDragging) {
      const dx = Math.abs(e.touches[0].clientX - touchStartX);
      const dy = Math.abs(e.touches[0].clientY - touchStartY);
      // Only hijack horizontal swipes
      if (dx > dy && dx > 10) {
        isDragging = true;
      }
    }
    touchEndX = e.touches[0].clientX;
  }, { passive: true });

  sliderWrapper.addEventListener('touchend', function () {
    if (!isDragging) return;

    const delta = touchStartX - touchEndX;

    if (Math.abs(delta) > SWIPE_THRESHOLD) {
      if (delta > 0) {
        goNext();
      } else {
        goPrev();
      }
      resetAutoPlay();
    }

    isDragging = false;
  });

  /* -------------------------------------------------------
     Mouse drag support (desktop)
  ------------------------------------------------------- */
  let mouseStartX = 0;
  let mouseEndX   = 0;
  let isMouseDown = false;

  sliderWrapper.addEventListener('mousedown', function (e) {
    mouseStartX = e.clientX;
    isMouseDown = true;
    sliderWrapper.style.cursor = 'grabbing';
  });

  document.addEventListener('mousemove', function (e) {
    if (!isMouseDown) return;
    mouseEndX = e.clientX;
  });

  document.addEventListener('mouseup', function () {
    if (!isMouseDown) return;
    isMouseDown = false;
    sliderWrapper.style.cursor = '';

    const delta = mouseStartX - mouseEndX;
    if (Math.abs(delta) > SWIPE_THRESHOLD) {
      if (delta > 0) {
        goNext();
      } else {
        goPrev();
      }
      resetAutoPlay();
    }
  });

  // Prevent link clicks while dragging
  sliderWrapper.addEventListener('click', function (e) {
    if (Math.abs(mouseStartX - mouseEndX) > 5) {
      e.preventDefault();
    }
  });

  /* -------------------------------------------------------
     Visibility API: pause when tab is hidden
  ------------------------------------------------------- */
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stopAutoPlay();
    } else {
      startAutoPlay();
    }
  });

  /* -------------------------------------------------------
     Init
  ------------------------------------------------------- */
  // Set initial aria attributes
  slides.forEach(function (slide, i) {
    slide.setAttribute('role', 'group');
    slide.setAttribute('aria-roledescription', 'slide');
    slide.setAttribute('aria-label', 'Recensione ' + (i + 1) + ' di ' + TOTAL);
    slide.setAttribute('aria-hidden', i !== 0 ? 'true' : 'false');
  });

  if (track) {
    track.setAttribute('role', 'list');
    track.setAttribute('aria-live', 'off');
  }

  // Go to first slide to set initial state
  goTo(0);

  // Start autoplay
  startAutoPlay();

  // Expose API in case other scripts need to control slider
  window.PowerGymSlider = {
    goTo:     goTo,
    goNext:   goNext,
    goPrev:   goPrev,
    stop:     stopAutoPlay,
    start:    startAutoPlay,
    current:  function () { return currentIndex; },
  };
})();
