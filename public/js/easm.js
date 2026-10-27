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

    function isTypingTarget(el) {
        if (!el) {
            return false;
        }
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }

    function initPalette() {
        var dialog = document.querySelector('[data-palette]');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        var input = dialog.querySelector('[data-palette-input]');
        var results = dialog.querySelector('[data-palette-results]');
        var status = dialog.querySelector('[data-palette-status]');
        var searchUrl = dialog.getAttribute('data-search-url');
        var resultsUrl = dialog.getAttribute('data-results-url');
        var minLength = 2;
        var options = [];
        var activeIndex = -1;
        var timer = null;
        var controller = null;
        var returnFocus = null;
        var isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

        if (isMac) {
            document.querySelectorAll('.palette-hint kbd:first-child').forEach(function (kbd) {
                kbd.textContent = '⌘';
            });
        }

        function setStatus(text) {
            status.textContent = text;
        }

        function setActive(index) {
            options.forEach(function (option, i) {
                var selected = i === index;
                option.setAttribute('aria-selected', selected ? 'true' : 'false');
                option.classList.toggle('is-active', selected);
            });
            activeIndex = index;
            if (index >= 0 && options[index]) {
                input.setAttribute('aria-activedescendant', options[index].id);
                options[index].scrollIntoView({ block: 'nearest' });
            } else {
                input.setAttribute('aria-activedescendant', '');
            }
        }

        function render(groups) {
            results.textContent = '';
            options = [];

            groups.forEach(function (group) {
                var wrapper = document.createElement('div');
                wrapper.setAttribute('role', 'group');
                wrapper.className = 'palette-group';
                var heading = document.createElement('div');
                heading.className = 'palette-group-label';
                heading.id = 'palette-group-' + group.key;
                heading.setAttribute('role', 'presentation');
                heading.textContent = group.label;
                wrapper.setAttribute('aria-labelledby', heading.id);
                wrapper.appendChild(heading);

                group.items.forEach(function (item) {
                    var option = document.createElement('div');
                    option.id = 'palette-option-' + options.length;
                    option.className = 'palette-option';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.setAttribute('data-url', item.url);

                    var title = document.createElement('span');
                    title.className = 'palette-option-title';
                    title.textContent = item.title;
                    option.appendChild(title);

                    if (item.subtitle) {
                        var subtitle = document.createElement('span');
                        subtitle.className = 'palette-option-subtitle';
                        subtitle.textContent = item.subtitle;
                        option.appendChild(subtitle);
                    }

                    option.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        window.location.href = item.url;
                    });

                    wrapper.appendChild(option);
                    options.push(option);
                });

                results.appendChild(wrapper);
            });

            input.setAttribute('aria-expanded', options.length > 0 ? 'true' : 'false');
            setActive(options.length > 0 ? 0 : -1);
        }

        function runSearch() {
            var q = input.value.trim();

            if (controller) {
                controller.abort();
            }

            if (q.length < minLength) {
                render([]);
                setStatus('Type at least ' + minLength + ' characters.');
                return;
            }

            controller = new AbortController();
            setStatus('Searching…');

            fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    render(data.groups || []);
                    setStatus(options.length === 0
                        ? 'No matches. Press Enter for the full results page.'
                        : options.length + (options.length === 1 ? ' result' : ' results') + '.');
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        render([]);
                        setStatus('Search is unavailable. Press Enter for the full results page.');
                    }
                });
        }

        function open() {
            if (dialog.open) {
                input.focus();
                input.select();
                return;
            }
            returnFocus = document.activeElement;
            dialog.showModal();
            input.focus();
            input.select();
            if (input.value.trim().length >= minLength) {
                runSearch();
            }
        }

        function close() {
            if (dialog.open) {
                dialog.close();
            }
        }

        dialog.addEventListener('close', function () {
            if (controller) {
                controller.abort();
            }
            if (returnFocus && typeof returnFocus.focus === 'function') {
                returnFocus.focus();
            }
            returnFocus = null;
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                close();
            }
        });

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(runSearch, 150);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (options.length > 0) {
                    setActive((activeIndex + 1) % options.length);
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (options.length > 0) {
                    setActive((activeIndex - 1 + options.length) % options.length);
                }
            } else if (event.key === 'Home' && options.length > 0) {
                event.preventDefault();
                setActive(0);
            } else if (event.key === 'End' && options.length > 0) {
                event.preventDefault();
                setActive(options.length - 1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (activeIndex >= 0 && options[activeIndex]) {
                    window.location.href = options[activeIndex].getAttribute('data-url');
                } else if (input.value.trim() !== '') {
                    window.location.href = resultsUrl + '?q=' + encodeURIComponent(input.value.trim());
                }
            }
        });

        document.querySelectorAll('[data-palette-open]').forEach(function (button) {
            button.addEventListener('click', open);
        });

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && (event.key === 'k' || event.key === 'K')) {
                event.preventDefault();
                if (dialog.open) {
                    close();
                } else {
                    open();
                }
                return;
            }

            if (event.key === '/' && !dialog.open && !isTypingTarget(event.target)) {
                var topbar = document.querySelector('[data-search-input]');
                if (topbar) {
                    event.preventDefault();
                    topbar.focus();
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initNavDrawer();
        initPalette();
    });
})();
