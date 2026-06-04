/**
 * POWER GYM - Main JavaScript
 * main.js
 *
 * Modules:
 *  1. Navbar scroll + glassmorphism
 *  2. Hamburger / mobile nav toggle
 *  3. Smooth scroll for anchor links
 *  4. Intersection Observer for scroll animations
 *  5. Counter animation for stats
 *  6. Active nav link detection
 *  7. Scroll-to-top button
 */

'use strict';

/* ============================================================
   1. NAVBAR SCROLL EFFECT
   ============================================================ */
(function initNavbarScroll() {
  const navbar = document.querySelector('.navbar');
  if (!navbar) return;

  const SCROLL_THRESHOLD = 60;

  function onScroll() {
    if (window.scrollY > SCROLL_THRESHOLD) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  }

  // Throttle the scroll handler for performance
  let ticking = false;
  window.addEventListener('scroll', function () {
    if (!ticking) {
      requestAnimationFrame(function () {
        onScroll();
        ticking = false;
      });
      ticking = true;
    }
  }, { passive: true });

  // Run once on load in case page is already scrolled
  onScroll();
})();


/* ============================================================
   2. HAMBURGER MENU TOGGLE
   ============================================================ */
(function initHamburger() {
  const hamburger = document.querySelector('.hamburger');
  const mobileNav  = document.querySelector('.mobile-nav');
  const body       = document.body;

  if (!hamburger || !mobileNav) return;

  function openMenu() {
    hamburger.classList.add('active');
    mobileNav.classList.add('open');
    body.style.overflow = 'hidden';
    hamburger.setAttribute('aria-expanded', 'true');
    hamburger.setAttribute('aria-label', 'Chiudi menu');
  }

  function closeMenu() {
    hamburger.classList.remove('active');
    mobileNav.classList.remove('open');
    body.style.overflow = '';
    hamburger.setAttribute('aria-expanded', 'false');
    hamburger.setAttribute('aria-label', 'Apri menu');
  }

  function toggleMenu() {
    if (mobileNav.classList.contains('open')) {
      closeMenu();
    } else {
      openMenu();
    }
  }

  hamburger.addEventListener('click', toggleMenu);

  // Close on mobile nav link click
  const mobileLinks = mobileNav.querySelectorAll('a');
  mobileLinks.forEach(link => {
    link.addEventListener('click', closeMenu);
  });

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && mobileNav.classList.contains('open')) {
      closeMenu();
    }
  });

  // Close on outside click (click on overlay area)
  mobileNav.addEventListener('click', function (e) {
    if (e.target === mobileNav) {
      closeMenu();
    }
  });
})();


/* ============================================================
   3. SMOOTH SCROLL FOR ANCHOR LINKS
   ============================================================ */
(function initSmoothScroll() {
  const NAVBAR_OFFSET = 80; // account for fixed navbar height

  document.addEventListener('click', function (e) {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;

    const hash = link.getAttribute('href');
    if (hash === '#' || hash === '#!') return;

    const target = document.querySelector(hash);
    if (!target) return;

    e.preventDefault();

    const targetTop = target.getBoundingClientRect().top + window.scrollY - NAVBAR_OFFSET;

    window.scrollTo({
      top: targetTop,
      behavior: 'smooth'
    });

    // Update URL without page jump
    if (history.pushState) {
      history.pushState(null, null, hash);
    }
  });
})();


/* ============================================================
   4. INTERSECTION OBSERVER – SCROLL ANIMATIONS
   ============================================================ */
(function initScrollAnimations() {
  const animClasses = [
    '.anim-fade-up',
    '.anim-fade-in',
    '.anim-slide-left',
    '.anim-slide-right',
    '.anim-scale-in',
    '.stagger-children',
  ];

  const elements = document.querySelectorAll(animClasses.join(', '));
  if (!elements.length) return;

  const observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          // Once visible, no need to keep observing
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.1,        // trigger when 10% of element is in viewport
      rootMargin: '0px 0px -40px 0px' // slight offset from bottom
    }
  );

  elements.forEach(function (el) {
    observer.observe(el);
  });
})();


/* ============================================================
   5. COUNTER ANIMATION FOR STATS
   ============================================================ */
(function initCounters() {
  const statNumbers = document.querySelectorAll('.stat-number[data-target]');
  if (!statNumbers.length) return;

  /**
   * Animate a counter from 0 to its data-target value.
   * @param {HTMLElement} el
   */
  function animateCounter(el) {
    const target   = parseInt(el.getAttribute('data-target'), 10);
    const suffix   = el.getAttribute('data-suffix') || '';
    const prefix   = el.getAttribute('data-prefix') || '';
    const duration = 1800; // ms
    const frameRate = 1000 / 60; // 60fps
    const totalFrames = Math.round(duration / frameRate);
    let frame = 0;

    // Easing function: ease out quad
    function easeOutQuad(t) {
      return t * (2 - t);
    }

    const timer = setInterval(function () {
      frame++;
      const progress = easeOutQuad(frame / totalFrames);
      const current  = Math.round(progress * target);

      el.textContent = prefix + current + suffix;

      if (frame >= totalFrames) {
        clearInterval(timer);
        el.textContent = prefix + target + suffix;
      }
    }, frameRate);
  }

  const observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );

  statNumbers.forEach(function (el) {
    observer.observe(el);
  });
})();


/* ============================================================
   6. ACTIVE NAV LINK DETECTION
   ============================================================ */
(function initActiveNavLinks() {
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const navLinks    = document.querySelectorAll('.nav-link, .mobile-nav .nav-link');

  navLinks.forEach(function (link) {
    const href = link.getAttribute('href') || '';
    const linkPage = href.split('/').pop();

    if (
      linkPage === currentPath ||
      (currentPath === '' && linkPage === 'index.html') ||
      (currentPath === 'index.html' && linkPage === '' )
    ) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
})();


/* ============================================================
   7. SCROLL-TO-TOP BUTTON
   ============================================================ */
(function initScrollTop() {
  const btn = document.querySelector('.scroll-top');
  if (!btn) return;

  let ticking = false;

  window.addEventListener('scroll', function () {
    if (!ticking) {
      requestAnimationFrame(function () {
        if (window.scrollY > 400) {
          btn.classList.add('visible');
        } else {
          btn.classList.remove('visible');
        }
        ticking = false;
      });
      ticking = true;
    }
  }, { passive: true });

  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();


/* ============================================================
   8. COURSE FILTER (courses.html)
   ============================================================ */
(function initCourseFilter() {
  const filterBtns  = document.querySelectorAll('.filter-btn');
  const courseCards = document.querySelectorAll('.course-card-detailed');

  if (!filterBtns.length || !courseCards.length) return;

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      // Update active button
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.getAttribute('data-filter');

      courseCards.forEach(function (card) {
        const level = card.getAttribute('data-level');

        if (filter === 'all' || level === filter) {
          card.style.display = '';
          // Re-trigger animation
          card.classList.remove('is-visible');
          requestAnimationFrame(function () {
            card.classList.add('is-visible');
          });
        } else {
          card.style.display = 'none';
        }
      });
    });
  });
})();


/* ============================================================
   9. PASSWORD VISIBILITY TOGGLE (login.html)
   ============================================================ */
(function initPasswordToggle() {
  const toggleBtns = document.querySelectorAll('.password-toggle');

  toggleBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const wrapper   = btn.closest('.password-wrapper');
      const input     = wrapper ? wrapper.querySelector('input') : null;
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      // Toggle icon visibility
      const eyeOpen   = btn.querySelector('.eye-open');
      const eyeClosed = btn.querySelector('.eye-closed');
      if (eyeOpen)   eyeOpen.style.display   = isPassword ? 'none'  : '';
      if (eyeClosed) eyeClosed.style.display = isPassword ? ''      : 'none';

      btn.setAttribute('aria-label', isPassword ? 'Nascondi password' : 'Mostra password');
    });
  });
})();


/* ============================================================
   10. RIPPLE EFFECT ON BUTTONS
   ============================================================ */
(function initRipple() {
  const buttons = document.querySelectorAll('.btn-primary, .btn-outline-accent');

  buttons.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      const rect   = btn.getBoundingClientRect();
      const size   = Math.max(rect.width, rect.height);
      const x      = e.clientX - rect.left - size / 2;
      const y      = e.clientY - rect.top  - size / 2;

      const ripple = document.createElement('span');
      ripple.style.cssText = [
        'position:absolute',
        'border-radius:50%',
        'background:rgba(255,255,255,0.25)',
        'pointer-events:none',
        'width:'  + size + 'px',
        'height:' + size + 'px',
        'left:'   + x   + 'px',
        'top:'    + y   + 'px',
        'animation: ripple 0.6s ease forwards',
        'z-index:0',
      ].join(';');

      // Ensure btn has position relative (set via CSS, but just in case)
      if (getComputedStyle(btn).position === 'static') {
        btn.style.position = 'relative';
      }

      btn.appendChild(ripple);
      ripple.addEventListener('animationend', function () {
        ripple.remove();
      });
    });
  });
})();
