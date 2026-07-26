import Lenis from 'lenis';

/**
 * Smooth ("liquid") scrolling for the marketing site.
 *
 * Lenis eases the NATIVE scroll position rather than transforming a
 * container, so the fixed navbar, the progress bar and the flight-check
 * modal keep working - a transform-based scroller (ASScroll, older
 * locomotive) would reposition all of them, because a transformed ancestor
 * becomes the containing block for position:fixed descendants.
 *
 * Native scrolling is left alone for touch devices (they already have
 * momentum) and for anyone who asked for reduced motion.
 */
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const touch = window.matchMedia('(hover: none) and (pointer: coarse)').matches;

if (!reduced && !touch) {
    // CSS smooth scrolling animates every programmatic scroll on its own
    // timeline and would fight Lenis frame for frame.
    document.documentElement.style.scrollBehavior = 'auto';

    const lenis = new Lenis({
        duration: 1.05,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), // expo-out
        smoothWheel: true,
        syncTouch: false,
        wheelMultiplier: 1,
    });

    function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
    }

    requestAnimationFrame(raf);

    // In-page anchors ride the same easing instead of jumping.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href*="#"]');
        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        if (url.pathname !== window.location.pathname || url.hash.length < 2) {
            return;
        }

        const target = document.querySelector(url.hash);
        if (!target) {
            return;
        }

        event.preventDefault();
        lenis.scrollTo(target, { offset: -80 });
        history.pushState(null, '', url.hash);
    });

    // Panels that scroll on their own (the flight-check modal) opt out via
    // data-lenis-prevent; pause the page entirely while a modal is open.
    const body = document.body;
    new MutationObserver(() => {
        body.style.overflow === 'hidden' ? lenis.stop() : lenis.start();
    }).observe(body, { attributes: true, attributeFilter: ['style'] });

    window.lenis = lenis;
}
