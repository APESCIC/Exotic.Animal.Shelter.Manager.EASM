(function () {
    'use strict';

    var THEME_KEY = 'easm-theme';
    var root = document.documentElement;

    function storedTheme() {
        try {
            return localStorage.getItem(THEME_KEY);
        } catch (e) {
            return null;
        }
    }

    function systemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            var dark = theme === 'dark';
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.textContent = dark ? 'Light mode' : 'Dark mode';
        });
    }

    function initTheme() {
        applyTheme(storedTheme() || systemTheme());

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                try {
                    localStorage.setItem(THEME_KEY, next);
                } catch (e) {
                    // Private mode: the toggle still works for this page.
                }
                applyTheme(next);
            });
        });

        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (!storedTheme()) {
                    applyTheme(systemTheme());
                }
            });
        }
    }

    function initNavDrawer() {
        var body = document.body;
        var toggles = document.querySelectorAll('[data-nav-toggle]');

        function setOpen(open) {
            body.classList.toggle('nav-open', open);
            toggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                setOpen(!body.classList.contains('nav-open'));
            });
        });

        document.querySelectorAll('[data-nav-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && body.classList.contains('nav-open')) {
                setOpen(false);
                if (toggles[0]) {
                    toggles[0].focus();
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initNavDrawer();
    });
})();
