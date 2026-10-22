/**
 * Clémentine · 21 ans — script commun
 * Navigation, préférences d'animation, confirmations, compteurs, étoiles.
 * Aucune dépendance externe.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function storageGet(key, fallback) {
        try {
            var value = window.localStorage.getItem(key);
            return value === null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function storageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            /* stockage indisponible : on ignore */
        }
    }

    window.Clementine = {
        reduceMotion: reduceMotion,
        $: $,
        $$: $$,
        storageGet: storageGet,
        storageSet: storageSet
    };

    /* ------------------------------------------------------------------
     * En-tête : ombre au défilement + menu mobile
     * ------------------------------------------------------------------ */

    var header = $('[data-header]');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 12);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    var navToggle = $('[data-nav-toggle]');
    var nav = $('#site-nav');

    if (navToggle && nav) {
        var setNav = function (open) {
            nav.classList.toggle('is-open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
        };

        navToggle.addEventListener('click', function () {
            setNav(navToggle.getAttribute('aria-expanded') !== 'true');
        });

        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setNav(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
                setNav(false);
                navToggle.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (!nav.contains(event.target) && !navToggle.contains(event.target)) {
                setNav(false);
            }
        });
    }

    /* ------------------------------------------------------------------
     * Affichage / masquage des mots de passe
     * ------------------------------------------------------------------ */

    $$('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var wrapper = button.closest('.field__password');
            var input = wrapper ? $('input', wrapper) : null;
            if (!input) {
                return;
            }

            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.classList.toggle('is-on', show);
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    /* ------------------------------------------------------------------
     * Confirmation des actions destructrices (<dialog>)
     * ------------------------------------------------------------------ */

    var confirmDialog = null;

    function buildConfirmDialog() {
        if (confirmDialog) {
            return confirmDialog;
        }

        confirmDialog = document.createElement('dialog');
        confirmDialog.className = 'confirm-dialog';
        confirmDialog.setAttribute('aria-labelledby', 'confirm-title');
        confirmDialog.innerHTML =
            '<form method="dialog" class="confirm-dialog__inner">' +
                '<span class="confirm-dialog__icon" aria-hidden="true">!</span>' +
                '<h2 id="confirm-title">Confirmation</h2>' +
                '<p data-confirm-message></p>' +
                '<div class="confirm-dialog__actions">' +
                    '<button class="btn btn--ghost btn--sm" value="cancel" type="submit">Annuler</button>' +
                    '<button class="btn btn--danger btn--sm" value="confirm" type="submit" data-confirm-ok>Confirmer</button>' +
                '</div>' +
            '</form>';

        document.body.appendChild(confirmDialog);
        return confirmDialog;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        var message = form.getAttribute('data-confirm') || 'Confirmer cette action ?';

        if (typeof HTMLDialogElement === 'undefined' || !document.createElement('dialog').showModal) {
            if (window.confirm(message)) {
                form.dataset.confirmed = '1';
                form.submit();
            }
            return;
        }

        var dialog = buildConfirmDialog();
        $('[data-confirm-message]', dialog).textContent = message;

        var onClose = function () {
            dialog.removeEventListener('close', onClose);

            if (dialog.returnValue === 'confirm') {
                form.dataset.confirmed = '1';
                form.submit();
            }
        };

        dialog.returnValue = '';
        dialog.addEventListener('close', onClose);
        dialog.showModal();

        var okButton = $('[data-confirm-ok]', dialog);
        if (okButton) {
            okButton.focus();
        }
    });

    /* ------------------------------------------------------------------
     * Compteurs de caractères
     * ------------------------------------------------------------------ */

    $$('[data-char-count]').forEach(function (input) {
        var key = input.getAttribute('data-char-count');
        var target = document.querySelector('[data-char-target="' + key + '"]');

        if (!target) {
            return;
        }

        var update = function () {
            target.textContent = String(input.value.length);
        };

        input.addEventListener('input', update);
        update();
    });

    /* ------------------------------------------------------------------
     * Champ fichier : nom du fichier sélectionné
     * ------------------------------------------------------------------ */

    $$('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var hint = input.parentElement ? input.parentElement.querySelector('.field__hint') : null;
            if (hint && input.files && input.files[0]) {
                hint.textContent = input.files[0].name + ' — ' + (input.files[0].size / 1048576).toFixed(1) + ' Mo';
            }
        });
    });

    /* ------------------------------------------------------------------
     * Ciel étoilé (canvas léger)
     * ------------------------------------------------------------------ */

    function initStarfield() {
        var canvas = $('[data-starfield]');

        if (!canvas || !canvas.getContext) {
            return;
        }

        var ctx = canvas.getContext('2d');
        var dpr = Math.min(window.devicePixelRatio || 1, 1.6);
        var stars = [];
        var animation = null;
        var running = false;

        var isSmall = window.innerWidth < 720;
        var count = reduceMotion ? 0 : (isSmall ? 42 : 88);

        function resize() {
            var rect = canvas.getBoundingClientRect();
            var width = Math.max(1, Math.floor(rect.width));
            var height = Math.max(1, Math.floor(rect.height));

            canvas.width = Math.floor(width * dpr);
            canvas.height = Math.floor(height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            stars = [];
            for (var i = 0; i < count; i++) {
                stars.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    r: Math.random() * 1.4 + 0.35,
                    a: Math.random() * 0.6 + 0.2,
                    speed: Math.random() * 0.14 + 0.03,
                    phase: Math.random() * Math.PI * 2,
                    hue: Math.random() > 0.82 ? 'pink' : 'white'
                });
            }

            if (reduceMotion) {
                drawStatic(width, height);
            }
        }

        function drawStatic(width, height) {
            ctx.clearRect(0, 0, width, height);
            stars.forEach(function (star) {
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.r, 0, Math.PI * 2);
                ctx.fillStyle = star.hue === 'pink' ? 'rgba(255,111,181,' + star.a + ')' : 'rgba(255,255,255,' + star.a + ')';
                ctx.fill();
            });
        }

        function frame(time) {
            var rect = canvas.getBoundingClientRect();
            var width = rect.width;
            var height = rect.height;

            ctx.clearRect(0, 0, width, height);

            stars.forEach(function (star) {
                star.y -= star.speed;
                if (star.y < -4) {
                    star.y = height + 4;
                    star.x = Math.random() * width;
                }

                var twinkle = 0.55 + Math.sin(time / 1600 + star.phase) * 0.35;
                var alpha = Math.max(0.08, star.a * twinkle);

                ctx.beginPath();
                ctx.arc(star.x, star.y, star.r, 0, Math.PI * 2);
                ctx.fillStyle = star.hue === 'pink'
                    ? 'rgba(255,111,181,' + alpha + ')'
                    : 'rgba(255,255,255,' + alpha + ')';
                ctx.fill();
            });

            animation = window.requestAnimationFrame(frame);
        }

        function start() {
            if (running || reduceMotion || !count) {
                return;
            }
            running = true;
            animation = window.requestAnimationFrame(frame);
        }

        function stop() {
            running = false;
            if (animation) {
                window.cancelAnimationFrame(animation);
                animation = null;
            }
        }

        resize();
        start();

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(resize, 220);
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });
    }

    initStarfield();
})();
