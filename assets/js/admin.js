/**
 * Administration : menu latéral mobile et choix de l'origine des vidéos.
 * Les confirmations et compteurs sont gérés par core.js.
 */
(function () {
    'use strict';

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    /* ------------------------------------------------------------------
     * Menu latéral
     * ------------------------------------------------------------------ */

    var sidebar = $('[data-sidebar]');
    var overlay = $('[data-sidebar-overlay]');
    var openBtn = $('[data-sidebar-open]');
    var closeBtn = $('[data-sidebar-close]');

    function setSidebar(open) {
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('is-open', open);

        if (overlay) {
            overlay.hidden = !open;
        }

        document.body.classList.toggle('admin-nav-open', open);

        if (openBtn) {
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (open) {
            var firstLink = $('.admin-nav__link', sidebar);
            if (firstLink) {
                firstLink.focus();
            }
        } else if (openBtn) {
            openBtn.focus();
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            setSidebar(true);
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            setSidebar(false);
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            setSidebar(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            setSidebar(false);
        }
    });

    if (sidebar) {
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a') && window.innerWidth <= 900) {
                setSidebar(false);
            }
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900 && sidebar && sidebar.classList.contains('is-open')) {
            sidebar.classList.remove('is-open');
            if (overlay) {
                overlay.hidden = true;
            }
        }
    });

    /* ------------------------------------------------------------------
     * Vidéo locale ou lien externe
     * ------------------------------------------------------------------ */

    var sourceRadios = $$('[data-video-source]');
    var localField = $('[data-video-local]');
    var externalField = $('[data-video-external]');

    if (sourceRadios.length && localField && externalField) {
        var syncSource = function () {
            var external = sourceRadios.some(function (radio) {
                return radio.checked && radio.value === 'external';
            });

            localField.hidden = external;
            externalField.hidden = !external;

            var localInput = localField.querySelector('input[type="file"]');
            var externalInput = externalField.querySelector('input[type="url"]');

            if (localInput) {
                localInput.required = !external;
            }
            if (externalInput) {
                externalInput.required = external;
            }
        };

        sourceRadios.forEach(function (radio) {
            radio.addEventListener('change', syncSource);
        });

        syncSource();
    }
})();
