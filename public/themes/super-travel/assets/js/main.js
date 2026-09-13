/**
 * Super Travel theme front-end behaviour. Zero dependencies, on purpose —
 * keeps the ultra-lightweight budget the CMS is built around.
 *
 * 1. Scroll-triggered reveal-up for every .st-reveal element.
 * 2. Mobile nav toggle for the fixed glassmorphism header.
 * 3. Solid background for the transparent-over-hero nav once scrolled.
 */
(function () {
    'use strict';

    function initReveal() {
        var items = document.querySelectorAll('.st-reveal');

        if (! items.length) {
            return;
        }

        if (! ('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        items.forEach(function (el) { observer.observe(el); });
    }

    function initMobileNav() {
        var toggle = document.getElementById('st-nav-toggle');
        var menu   = document.getElementById('st-nav-menu');

        if (! toggle || ! menu) {
            return;
        }

        toggle.addEventListener('click', function () {
            var isOpen = menu.classList.toggle('is-open');
            toggle.classList.toggle('is-active', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('is-open');
                toggle.classList.remove('is-active');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                menu.classList.remove('is-open');
                toggle.classList.remove('is-active');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initNavScroll() {
        var nav = document.getElementById('st-nav');

        // Only the homepage hero renders the nav transparent; everywhere
        // else it's already solid and needs no scroll listener.
        if (! nav || ! nav.classList.contains('st-nav--transparent')) {
            return;
        }

        function update() {
            nav.classList.toggle('is-scrolled', window.scrollY > 40);
        }

        update();
        window.addEventListener('scroll', update, { passive: true });
    }

    // Shared by every homepage carousel (pricing, why-choose-us,
    // testimonials) — same slide/pagination/nav shape, just a different
    // root selector, so one Swiper instance per selector found.
    function initSwiper(selector, overrides) {
        var el = document.querySelector(selector);

        if (! el || typeof Swiper === 'undefined') {
            return;
        }

        var options = {
            slidesPerView: 1.1,
            spaceBetween: 20,
            grabCursor: true,
            pagination: { el: selector + ' .swiper-pagination', clickable: true },
            navigation: {
                nextEl: selector + ' .swiper-button-next',
                prevEl: selector + ' .swiper-button-prev',
            },
            breakpoints: {
                640: { slidesPerView: 1.4 },
                900: { slidesPerView: 2.2 },
                1200: { slidesPerView: 3 },
            },
        };

        new Swiper(el, Object.assign(options, overrides || {}));
    }

    function initCarousels() {
        initSwiper('.st-why__swiper');
        initSwiper('.st-pricing__swiper');
        initSwiper('.st-testimonials__swiper');

        // Page gallery cell (.lcms-bento__cell--gallery): a plain 2-up/1-up
        // split rather than the "peek at the next card" ratios above —
        // a gallery reads better showing whole photos than partial ones.
        initSwiper('.lcms-page-gallery', {
            slidesPerView: 1,
            breakpoints: { 900: { slidesPerView: 2 } },
        });
    }

    /**
     * Hero highlight cards: a vertical, one-card-at-a-time swipe-up stack
     * on phones only (small square cards so the hero photo stays visible);
     * desktop keeps the plain stacked list and never gets a Swiper
     * instance at all (see the min-width:901px reset in style.css, which
     * covers Swiper's base CSS independently of whether this ran).
     */
    function initHeroHighlights() {
        var el = document.querySelector('.st-hero__highlights-swiper');

        if (! el || typeof Swiper === 'undefined' || ! window.matchMedia('(max-width: 900px)').matches) {
            return;
        }

        new Swiper(el, {
            direction: 'vertical',
            slidesPerView: 1,
            spaceBetween: 0,
            grabCursor: true,
            mousewheel: true,
            // Lives beside the card, not inside it — .st-hero__highlights-
            // swiper is Swiper's own clipped (overflow:hidden) slide
            // viewport, so a pagination dot column meant to stay visible
            // next to it has to be a sibling Swiper is told to control
            // rather than a child that would get clipped with the slides.
            pagination: { el: '.st-hero__highlights-dots', clickable: true },
        });
    }

    // Floating "back to top" button (layouts/footer.php): fades in once the
    // visitor has scrolled a bit, scrolls smoothly to the top on click. The
    // floating WhatsApp button next to it needs no JS — it's a plain link.
    function initBackToTop() {
        var button = document.getElementById('st-back-to-top');

        if (! button) {
            return;
        }

        function update() {
            button.classList.toggle('is-visible', window.scrollY > 400);
        }

        update();
        window.addEventListener('scroll', update, { passive: true });

        button.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /**
     * Share row (single.php): the Facebook/WhatsApp/Threads/X/Email
     * buttons are plain links needing no JS at all. Only two need it —
     * [data-share-copy] (copy link) and [data-share-native] (the "+"
     * button, native OS share sheet via the Web Share API, which can
     * hand the article to literally any installed app — falls back to
     * copying the link when the API isn't available, e.g. desktop).
     */
    function initShareButtons() {
        var tooltipTimer = null;

        function flashCopied(button) {
            button.classList.add('is-copied');
            clearTimeout(tooltipTimer);
            tooltipTimer = setTimeout(function () {
                button.classList.remove('is-copied');
            }, 2000);
        }

        function copyToClipboard(url, button) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    flashCopied(button);
                });
                return;
            }

            // Fallback for browsers without the async Clipboard API.
            var textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                flashCopied(button);
            } catch (e) {
                /* Nothing more we can do here — fail quietly. */
            }

            document.body.removeChild(textarea);
        }

        document.querySelectorAll('[data-share-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                copyToClipboard(button.dataset.shareCopy, button);
            });
        });

        document.querySelectorAll('[data-share-native]').forEach(function (button) {
            button.addEventListener('click', function () {
                var url   = button.dataset.shareNative;
                var title = button.dataset.shareTitle || document.title;

                if (navigator.share) {
                    // A user-cancelled share sheet rejects too — that's not
                    // an error, so it's caught and ignored rather than
                    // falling through to the clipboard fallback.
                    navigator.share({ title: title, url: url }).catch(function () {});
                    return;
                }

                copyToClipboard(url, button);
            });
        });
    }

    /**
     * Booking form (Cómo Reservar — SeoContentSeeder's bookingForm()):
     * no server-side booking system by design, so submitting just builds
     * a WhatsApp message from the fields and opens it there. The message
     * leads with the site name so it's easy to tell apart from other
     * incoming WhatsApp chats, then the booking details in the same shape
     * as every other pre-filled WhatsApp CTA on the site.
     */
    function initBookingForm() {
        var form = document.getElementById('st-booking-form');

        if (! form) {
            return;
        }

        var dateField = form.querySelector('[name="fecha"]');
        if (dateField) {
            // Set at load time, not baked into the seeded HTML, so it's
            // always "today" for whoever is visiting, not whenever this
            // page's content was last saved.
            dateField.min = new Date().toISOString().slice(0, 10);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var data = new FormData(form);
            var lines = [
                'Snorkel Penida - Nueva solicitud de reserva',
                '',
                'Nombre: ' + (data.get('nombre') || ''),
                'Personas: ' + (data.get('personas') || ''),
                'Fecha: ' + (data.get('fecha') || ''),
                'Hora preferida: ' + (data.get('hora') || ''),
                'Tipo de experiencia: ' + (data.get('experiencia') || '')
            ];

            var mensaje = (data.get('mensaje') || '').trim();
            if (mensaje) {
                lines.push('Mensaje: ' + mensaje);
            }

            lines.push('', '¿Podrían confirmar disponibilidad y precio?');

            var number = form.dataset.waNumber || '';
            var text   = encodeURIComponent(lines.join('\n'));

            window.open('https://wa.me/' + number + '?text=' + text, '_blank', 'noopener');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initReveal();
            initMobileNav();
            initNavScroll();
            initCarousels();
            initHeroHighlights();
            initBackToTop();
            initShareButtons();
            initBookingForm();
        });
    } else {
        initReveal();
        initMobileNav();
        initNavScroll();
        initCarousels();
        initHeroHighlights();
        initBackToTop();
        initShareButtons();
        initBookingForm();
    }
})();
