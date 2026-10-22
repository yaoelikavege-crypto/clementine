/**
 * Compte à rebours synchronisé sur l'heure du serveur.
 * - La date cible est fournie par le serveur (fuseau Africa/Lome).
 * - L'écart d'horloge du téléphone est corrigé.
 * - Au passage à zéro, l'interface se met à jour sans action manuelle.
 */
(function () {
    'use strict';

    var dataNode = document.getElementById('countdown-config');
    if (!dataNode) {
        return;
    }

    var config;
    try {
        config = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    if (!config.revealAt || config.revealed) {
        return;
    }

    var revealAt = Number(config.revealAt);
    var serverNow = Number(config.serverNow) || Date.now();
    var offset = serverNow - Date.now(); // heure serveur = Date.now() + offset
    var pollTimer = null;
    var reloading = false;

    var valueNodes = Array.prototype.slice.call(document.querySelectorAll('[data-countdown-value]'));
    var stage = document.querySelector('[data-reveal-stage]');
    var preBlock = document.querySelector('.js-pre-reveal');
    var postBlock = document.querySelector('.js-post-reveal');

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function remaining() {
        return Math.max(0, Math.floor((revealAt - (Date.now() + offset)) / 1000));
    }

    function render(seconds) {
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;

        var values = { days: days, hours: hours, minutes: minutes, seconds: secs };

        valueNodes.forEach(function (node) {
            var key = node.getAttribute('data-countdown-value');
            if (Object.prototype.hasOwnProperty.call(values, key)) {
                var text = pad(values[key]);
                if (node.textContent !== text) {
                    node.textContent = text;
                }
            }
        });
    }

    function announceReveal() {
        var countdown = document.querySelector('[data-countdown]');
        if (countdown) {
            countdown.classList.add('is-celebrating');
        }

        if (preBlock) {
            preBlock.hidden = true;
        }

        if (postBlock) {
            postBlock.hidden = false;
        }

        if (stage) {
            stage.classList.add('is-revealed');
        }

        // On vérifie côté serveur avant de recharger : c'est lui qui fait foi.
        pollServer(0);
    }

    function pollServer(attempt) {
        if (reloading) {
            return;
        }

        if (!config.timeApi || !window.fetch) {
            scheduleReload(4000);
            return;
        }

        window.fetch(config.timeApi, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('time');
                }
                return response.json();
            })
            .then(function (data) {
                if (data && data.revealed) {
                    reloading = true;
                    window.clearTimeout(pollTimer);
                    pollTimer = window.setTimeout(function () {
                        window.location.reload();
                    }, 700);
                } else {
                    retryPoll(3000);
                }
            })
            .catch(function () {
                retryPoll(attempt < 4 ? 2500 : 8000, attempt + 1);
            });
    }

    function retryPoll(delay, attempt) {
        if (reloading) {
            return;
        }

        window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(function () {
            pollServer(typeof attempt === 'number' ? attempt : 0);
        }, delay);
    }

    var hasAnnounced = false;

    function tick() {
        var seconds = remaining();

        render(seconds);

        if (seconds <= 0 && !hasAnnounced) {
            hasAnnounced = true;
            announceReveal();
        }
    }

    tick();
    window.setInterval(tick, 1000);

    // Resynchronisation douce avec le serveur toutes les minutes.
    if (config.timeApi && window.fetch) {
        window.setInterval(function () {
            window.fetch(config.timeApi, { cache: 'no-store', credentials: 'same-origin' })
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (data) {
                    if (!data) {
                        return;
                    }

                    if (data.revealed && !hasAnnounced) {
                        hasAnnounced = true;
                        announceReveal();
                    }

                    if (typeof data.now_ms === 'number') {
                        offset = data.now_ms - Date.now();
                    }

                    if (typeof data.reveal_at === 'number') {
                        revealAt = data.reveal_at * 1000;
                    }

                    tick();
                })
                .catch(function () {
                    /* hors ligne : on continue avec l'écart connu */
                });
        }, 60000);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            tick();
        }
    });
})();
