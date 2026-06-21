/* ================================================================
   PAX Higher Institute — Front Web JavaScript
   Alpine.js + AOS + Swiper + Custom modules
   ================================================================ */

/* === Alpine.js — Reactive UI framework === */
import Alpine from 'alpinejs';

/* === AOS — Animate on Scroll === */
import AOS from 'aos';
import 'aos/dist/aos.css';

/* === Swiper — Touch slider === */
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, EffectFade, Thumbs } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import 'swiper/css/effect-fade';

/* ================================================================
   MAKE GLOBALS AVAILABLE
   ================================================================ */
window.Alpine = Alpine;
window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination, Autoplay, EffectFade, Thumbs };

/* ================================================================
   ALPINE STORES / DATA
   ================================================================ */

// --- Mobile Navigation Store ---
Alpine.store('nav', {
    open: false,
    searchOpen: false,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    toggleSearch() { this.searchOpen = !this.searchOpen; },
    closeSearch() { this.searchOpen = false; },
});

// --- Sticky Header ---
Alpine.data('stickyHeader', () => ({
    scrolled: false,
    init() {
        this.checkScroll();
        window.addEventListener('scroll', () => this.checkScroll(), { passive: true });
    },
    checkScroll() {
        this.scrolled = window.scrollY > 80;
    },
}));

// --- Counter Animation ---
Alpine.data('counter', (target = 0, duration = 2000) => ({
    current: 0,
    hasAnimated: false,
    init() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.hasAnimated) {
                    this.hasAnimated = true;
                    this.animateTo(target, duration);
                }
            });
        }, { threshold: 0.3 });
        observer.observe(this.$el);
    },
    animateTo(target, duration) {
        const start = performance.now();
        const step = (timestamp) => {
            const progress = Math.min((timestamp - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            this.current = Math.floor(eased * target);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    },
}));

// --- Live Search ---
Alpine.data('liveSearch', () => ({
    query: '',
    results: [],
    loading: false,
    showResults: false,
    debounceTimer: null,
    
    search() {
        clearTimeout(this.debounceTimer);
        if (this.query.length < 2) {
            this.results = [];
            this.showResults = false;
            return;
        }
        this.loading = true;
        this.showResults = true;
        this.debounceTimer = setTimeout(async () => {
            try {
                const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';
                const res = await fetch(`${baseUrl}/api/search?q=${encodeURIComponent(this.query)}`);
                if (res.ok) {
                    this.results = await res.json();
                }
            } catch (e) {
                this.results = [];
            }
            this.loading = false;
        }, 350);
    },
    
    close() {
        setTimeout(() => { this.showResults = false; }, 200);
    },
}));

/* ================================================================
   INITIALIZE
   ================================================================ */
document.addEventListener('DOMContentLoaded', () => {
    // AOS
    AOS.init({
        duration: 700,
        easing: 'ease-out-cubic',
        once: true,
        offset: 60,
        disable: window.innerWidth < 768 ? 'phone' : false,
    });

    // Refresh AOS on dynamic content
    document.addEventListener('aos:refresh', () => AOS.refresh());
});

// Start Alpine
Alpine.start();

/* ================================================================
   HELPER: Smooth scroll to anchor
   ================================================================ */
document.addEventListener('click', (e) => {
    const target = e.target.closest('a[href^="#"]');
    if (!target) return;
    const el = document.querySelector(target.getAttribute('href'));
    if (el) {
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
