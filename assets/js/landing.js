/* =====================================================================
   Govinda Education — Interaksi landing page
   Tanpa dependensi. Semua efek punya fallback agar konten tetap terlihat.
   ===================================================================== */
(function () {
    'use strict';

    var nav = document.getElementById('lp-nav');
    var toggle = document.querySelector('.lp-nav-toggle');
    var navLinks = document.getElementById('lp-nav-links');

    function syncNavState() {
        if (!nav) {
            return;
        }
        nav.classList.toggle('is-scrolled', window.scrollY > 16);
    }

    function setMenu(open) {
        if (!nav || !toggle) {
            return;
        }
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        var icon = toggle.querySelector('i');
        if (icon) {
            icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
        }
    }

    syncNavState();
    window.addEventListener('scroll', syncNavState, { passive: true });

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            setMenu(!nav.classList.contains('is-open'));
        });
    }

    if (navLinks) {
        navLinks.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setMenu(false);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setMenu(false);
        }
    });

    // FAQ: buka satu jawaban saja supaya halaman tidak terlalu panjang.
    var faqItems = Array.prototype.slice.call(document.querySelectorAll('.lp-faq-item'));
    faqItems.forEach(function (item) {
        item.addEventListener('toggle', function () {
            if (!item.open) {
                return;
            }
            faqItems.forEach(function (other) {
                if (other !== item) {
                    other.open = false;
                }
            });
        });
    });

    // Reveal saat elemen masuk viewport.
    var revealItems = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
    if ('IntersectionObserver' in window && revealItems.length > 0) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        revealItems.forEach(function (item) {
            revealObserver.observe(item);
        });
    } else {
        revealItems.forEach(function (item) {
            item.classList.add('is-visible');
        });
    }

    // Sorot menu sesuai bagian yang sedang dibaca.
    var sectionLinks = Array.prototype.slice.call(
        document.querySelectorAll('.lp-nav-link[href^="#"]')
    );
    var sections = sectionLinks.map(function (link) {
        return document.querySelector(link.getAttribute('href'));
    }).filter(Boolean);

    if ('IntersectionObserver' in window && sections.length > 0) {
        var navObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                sectionLinks.forEach(function (link) {
                    link.classList.toggle(
                        'is-active',
                        link.getAttribute('href') === '#' + entry.target.id
                    );
                });
            });
        }, { rootMargin: '-46% 0px -50% 0px', threshold: 0 });

        sections.forEach(function (section) {
            navObserver.observe(section);
        });
    }
})();
