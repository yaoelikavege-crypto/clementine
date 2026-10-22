/**
 * Scène finale : bougie interactive et pluie d'étincelles.
 * Respecte prefers-reduced-motion (message affiché sans animation lourde).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-finale]');
    var candle = root ? root.querySelector('[data-candle]') : null;
    var message = root ? root.querySelector('[data-finale-message]') : null;
    var replay = root ? root.querySelector('[data-finale-replay]') : null;
    var hint = root ? root.querySelector('[data-finale-hint]') : null;
    var blowNote = root ? root.querySelector('[data-finale-blow]') : null;
    var canvas = root ? root.querySelector('[data-finale-sparks]') : null;

    if (!root || !candle) {
        return;
    }

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var originalHint = hint ? hint.textContent.trim() : '';
    var blown = false;
    var animation = null;
    var particles = [];

    function spawnSparks() {
        if (reduceMotion || !canvas || !canvas.getContext) {
            return;
        }

        var stage = canvas.parentElement;
        var rect = canvas.getBoundingClientRect();
        var candleRect = candle.getBoundingClientRect();

        var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = Math.floor(rect.width * dpr);
        canvas.height = Math.floor(rect.height * dpr);

        var ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        var originX = candleRect.left - rect.left + candleRect.width / 2;
        var originY = candleRect.top - rect.top + 40;

        var colors = ['#FF6FB5', '#C9B8FF', '#7957FF', '#FFD98A', '#FFFFFF'];
        particles = [];

        for (var i = 0; i < 120; i++) {
            var angle = (Math.PI * 2 * i) / 120 + Math.random() * 0.4;
            var speed = Math.random() * 3.6 + 1.2;

            particles.push({
                x: originX,
                y: originY,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed - Math.random() * 2.2,
                life: 1,
                decay: Math.random() * 0.012 + 0.006,
                size: Math.random() * 2.6 + 0.8,
                color: colors[Math.floor(Math.random() * colors.length)]
            });
        }

        var start = performance.now();

        function frame(now) {
            var elapsed = now - start;
            ctx.clearRect(0, 0, rect.width, rect.height);

            particles.forEach(function (particle) {
                particle.x += particle.vx;
                particle.y += particle.vy;
                particle.vy += 0.045;
                particle.vx *= 0.992;
                particle.life -= particle.decay;

                if (particle.life <= 0) {
                    return;
                }

                ctx.globalAlpha = Math.max(0, particle.life);
                ctx.fillStyle = particle.color;
                ctx.beginPath();
                ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                ctx.fill();
            });

            ctx.globalAlpha = 1;

            if (elapsed < 4200 && particles.some(function (p) { return p.life > 0; })) {
                animation = window.requestAnimationFrame(frame);
            } else {
                ctx.clearRect(0, 0, rect.width, rect.height);
                animation = null;
            }
        }

        if (animation) {
            window.cancelAnimationFrame(animation);
        }

        animation = window.requestAnimationFrame(frame);
    }

    function blow() {
        if (blown) {
            return;
        }

        blown = true;
        root.classList.add('is-blown');
        candle.setAttribute('aria-label', 'Bougie soufflée');
        candle.setAttribute('aria-disabled', 'true');

        if (message) {
            message.hidden = false;
        }

        if (hint) {
            hint.hidden = true;
        }

        if (blowNote) {
            blowNote.hidden = false;
            blowNote.textContent = 'Ton vœu s\'envole…';
        }

        if (replay) {
            replay.hidden = false;
        }

        spawnSparks();

        // Lance la playlist finale si un lecteur est présent.
        document.dispatchEvent(new CustomEvent('clementine:play'));
    }

    function reset() {
        blown = false;
        root.classList.remove('is-blown');
        candle.removeAttribute('aria-disabled');
        candle.setAttribute('aria-label', 'Souffler la bougie d\'anniversaire');

        if (message) {
            message.hidden = true;
        }

        if (replay) {
            replay.hidden = true;
        }

        if (hint) {
            hint.hidden = false;
            hint.textContent = originalHint;
        }

        if (blowNote) {
            blowNote.hidden = true;
        }

        if (canvas) {
            var ctx = canvas.getContext ? canvas.getContext('2d') : null;
            if (ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        candle.focus();
    }

    candle.addEventListener('click', blow);

    candle.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            blow();
        }
    });

    if (replay) {
        replay.addEventListener('click', reset);
    }
})();
