(function () {
    'use strict';

    var html = document.documentElement;
    var KEY  = 'vtx-theme';

    function currentEffective() {
        var saved = localStorage.getItem(KEY);
        if (saved) return saved;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function toggle() {
        var next = currentEffective() === 'dark' ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        html.setAttribute('data-theme', next);
    }

    var themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) themeBtn.addEventListener('click', toggle);

    // Mobile nav toggle
    var navToggle = document.querySelector('.nav-toggle');
    var nav       = document.querySelector('.site-nav');
    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target) && !navToggle.contains(e.target)) {
                nav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Smooth scroll for anchor links
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href^="#"]');
        if (!a) return;
        var id = a.getAttribute('href').slice(1);
        var el = id ? document.getElementById(id) : null;
        if (el) {
            e.preventDefault();
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}());
