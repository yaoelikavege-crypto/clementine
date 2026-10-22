/**
 * Visionneuse de la galerie : images, vidéos locales, vidéos externes,
 * navigation clavier, balayage tactile, arrêt des vidéos à la fermeture.
 */
(function () {
    'use strict';

    var dataNode = document.getElementById('gallery-data');
    var dialog = document.querySelector('[data-lightbox]');

    if (!dataNode || !dialog) {
        return;
    }

    var items = [];

    try {
        items = JSON.parse(dataNode.textContent || '[]');
    } catch (error) {
        return;
    }

    if (!Array.isArray(items) || !items.length) {
        return;
    }

    var stage = dialog.querySelector('[data-lightbox-stage]');
    var titleEl = dialog.querySelector('[data-lightbox-title]');
    var descEl = dialog.querySelector('[data-lightbox-desc]');
    var counterEl = dialog.querySelector('[data-lightbox-counter]');
    var categoryEl = dialog.querySelector('[data-lightbox-category]');
    var dateEl = dialog.querySelector('[data-lightbox-date]');
    var closeBtn = dialog.querySelector('[data-lightbox-close]');
    var prevBtn = dialog.querySelector('[data-lightbox-prev]');
    var nextBtn = dialog.querySelector('[data-lightbox-next]');

    var current = 0;
    var lastTrigger = null;
    var supportsDialog = typeof dialog.showModal === 'function';

    function itemAt(index) {
        return items[(index + items.length) % items.length];
    }

    function setTag(el, value) {
        if (!el) {
            return;
        }

        if (value) {
            el.textContent = value;
            el.hidden = false;
        } else {
            el.textContent = '';
            el.hidden = true;
        }
    }

    function buildMedia(item) {
        stage.innerHTML = '';

        if (item.kind === 'image' && item.src) {
            var img = document.createElement('img');
            img.src = item.src;
            img.alt = item.title || 'Souvenir';
            img.decoding = 'async';
            stage.appendChild(img);
            return;
        }

        if (item.kind === 'video' && item.embed) {
            var iframe = document.createElement('iframe');
            iframe.src = item.embed;
            iframe.title = item.title || 'Vidéo souvenir';
            iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
            iframe.setAttribute('allowfullscreen', '');
            iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            iframe.setAttribute('loading', 'lazy');
            stage.appendChild(iframe);
            return;
        }

        if (item.kind === 'video' && item.src) {
            var video = document.createElement('video');
            video.src = item.src;
            video.controls = true;
            video.playsInline = true;
            video.preload = 'metadata';
            if (item.thumb) {
                video.poster = item.thumb;
            }
            stage.appendChild(video);
            return;
        }

        if (item.kind === 'video' && item.external) {
            var external = document.createElement('div');
            external.className = 'lightbox__external';

            var note = document.createElement('p');
            note.textContent = 'Ce fournisseur ne permet pas toujours la lecture intégrée. La vidéo reste accessible via son lien d\'origine.';
            external.appendChild(note);

            var link = document.createElement('a');
            link.className = 'btn btn--soft btn--sm';
            link.href = item.external;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Ouvrir la vidéo';
            external.appendChild(link);

            stage.appendChild(external);
            return;
        }

        var text = document.createElement('div');
        text.className = 'lightbox__text';
        text.textContent = item.description || item.title || 'Un souvenir.';
        stage.appendChild(text);
    }

    function render() {
        var item = itemAt(current);

        buildMedia(item);

        if (titleEl) {
            titleEl.textContent = item.title || 'Souvenir';
        }
        if (descEl) {
            descEl.textContent = item.description || '';
        }
        if (counterEl) {
            counterEl.textContent = (current + 1) + ' / ' + items.length;
        }

        setTag(categoryEl, item.category || '');
        setTag(dateEl, item.date || '');

        if (prevBtn) {
            prevBtn.disabled = items.length < 2;
        }
        if (nextBtn) {
            nextBtn.disabled = items.length < 2;
        }
    }

    function open(index, trigger) {
        current = index;
        lastTrigger = trigger || null;

        if (!supportsDialog) {
            var item = itemAt(current);
            var url = item.src || item.external || '';
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
            return;
        }

        render();
        dialog.showModal();
        document.body.classList.add('lightbox-open');

        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function close() {
        if (supportsDialog && dialog.open) {
            dialog.close();
        }
    }

    function go(delta) {
        current = (current + delta + items.length) % items.length;
        render();
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-gallery-open]');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        var index = parseInt(trigger.getAttribute('data-gallery-open'), 10);

        if (isFinite(index)) {
            open(index, trigger);
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            go(-1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            go(1);
        });
    }

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            close();
        }
    });

    dialog.addEventListener('close', function () {
        document.body.classList.remove('lightbox-open');
        stage.innerHTML = '';

        if (lastTrigger && typeof lastTrigger.focus === 'function') {
            lastTrigger.focus();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!dialog.open) {
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            go(1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            go(-1);
        }
    });

    var touchStartX = null;
    var touchStartY = null;

    stage.addEventListener('pointerdown', function (event) {
        touchStartX = event.clientX;
        touchStartY = event.clientY;
    });

    stage.addEventListener('pointerup', function (event) {
        if (touchStartX === null) {
            return;
        }

        var dx = event.clientX - touchStartX;
        var dy = event.clientY - touchStartY;

        if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.4) {
            go(dx < 0 ? 1 : -1);
        }

        touchStartX = null;
        touchStartY = null;
    });
})();
