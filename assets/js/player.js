/**
 * Lecteur audio commun.
 * Un seul élément <audio> par page : aucune superposition de pistes.
 * La lecture automatique est tentée ; si le navigateur la bloque,
 * un bouton « Activer la musique » apparaît.
 */
(function () {
    'use strict';

    var roots = Array.prototype.slice.call(document.querySelectorAll('[data-player]'));
    if (!roots.length) {
        return;
    }

    var STORAGE_VOLUME = 'clementine_volume';

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        var minutes = Math.floor(seconds / 60);
        var secs = Math.floor(seconds % 60);
        return minutes + ':' + (secs < 10 ? '0' + secs : secs);
    }

    function init(root) {
        var dataId = root.getAttribute('data-tracks-id');
        var dataNode = dataId ? document.getElementById(dataId) : null;
        var tracks = [];

        if (dataNode) {
            try {
                tracks = JSON.parse(dataNode.textContent || '[]');
            } catch (error) {
                tracks = [];
            }
        }

        if (!tracks.length) {
            return;
        }

        var audio = root.querySelector('[data-player-audio]') || new Audio();
        var titleEl = root.querySelector('[data-player-title]');
        var artistEl = root.querySelector('[data-player-artist]');
        var progressEl = root.querySelector('[data-player-progress]');
        var seekEl = root.querySelector('[data-player-seek]');
        var elapsedEl = root.querySelector('[data-player-elapsed]');
        var durationEl = root.querySelector('[data-player-duration]');
        var statusEl = root.querySelector('[data-player-status]');
        var volumeEl = root.querySelector('[data-player-volume]');
        var muteBtn = root.querySelector('[data-player-mute]');
        var toggles = Array.prototype.slice.call(root.querySelectorAll('[data-player-toggle]'));
        var prevBtn = root.querySelector('[data-player-prev]');
        var nextBtn = root.querySelector('[data-player-next]');

        var shell = root.closest('.player-shell');
        var unlock = shell ? shell.querySelector('[data-player-unlock]') : null;
        var startBtn = unlock ? unlock.querySelector('[data-player-start]') : null;

        var index = 0;
        var failures = 0;
        var muted = false;

        var storedVolume = parseFloat(window.Clementine ? window.Clementine.storageGet(STORAGE_VOLUME, '0.7') : '0.7');
        var volume = isFinite(storedVolume) ? clamp(storedVolume, 0, 1) : 0.7;

        audio.preload = 'none';
        audio.volume = volume;

        if (volumeEl) {
            volumeEl.value = String(Math.round(volume * 100));
        }

        function track() {
            return tracks[index] || tracks[0];
        }

        function setStatus(message) {
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        function showUnlock(show) {
            if (unlock) {
                unlock.hidden = !show;
            }
        }

        function updateMeta() {
            var current = track();

            if (titleEl) {
                titleEl.textContent = current.title;
            }
            if (artistEl) {
                artistEl.textContent = current.artist || 'Clémentine · 21 ans';
            }

            if ('mediaSession' in navigator && window.MediaMetadata) {
                try {
                    navigator.mediaSession.metadata = new window.MediaMetadata({
                        title: current.title,
                        artist: current.artist || 'Clémentine · 21 ans',
                        album: 'Pour Clémentine'
                    });
                } catch (error) {
                    /* API facultative */
                }
            }
        }

        function loadTrack(autoplay) {
            var current = track();
            failures = 0;
            audio.src = current.url;
            updateMeta();
            setProgress(0, 0);

            if (autoplay) {
                attemptPlay();
            }
        }

        function setProgress(current, duration) {
            var ratio = 0;

            if (isFinite(duration) && duration > 0) {
                ratio = clamp(current / duration, 0, 1);
            }

            if (progressEl) {
                progressEl.style.width = (ratio * 100).toFixed(2) + '%';
            }
            if (seekEl) {
                seekEl.setAttribute('aria-valuenow', String(Math.round(ratio * 100)));
            }
            if (elapsedEl) {
                elapsedEl.textContent = formatTime(current);
            }
            if (durationEl) {
                durationEl.textContent = isFinite(duration) && duration > 0 ? formatTime(duration) : '0:00';
            }
        }

        function setPlaying(isPlaying) {
            root.classList.toggle('is-playing', isPlaying);
            toggles.forEach(function (button) {
                button.setAttribute('aria-label', isPlaying ? 'Mettre la musique en pause' : 'Lire la musique');
            });
            setStatus(isPlaying ? 'Lecture en cours : ' + track().title : 'Musique en pause.');
        }

        function attemptPlay() {
            var promise = audio.play();

            if (promise && typeof promise.then === 'function') {
                promise.then(function () {
                    showUnlock(false);
                }).catch(function () {
                    showUnlock(true);
                    setStatus('Lecture automatique bloquée par le navigateur. Active la musique avec le bouton.');
                });
            }
        }

        function play() {
            if (!audio.src) {
                loadTrack(false);
            }
            attemptPlay();
        }

        function pause() {
            audio.pause();
        }

        function toggle() {
            if (audio.paused) {
                play();
            } else {
                pause();
            }
        }

        function go(delta) {
            if (tracks.length < 2) {
                return;
            }

            index = (index + delta + tracks.length) % tracks.length;
            loadTrack(true);
        }

        if (audio.parentNode !== root) {
            audio.setAttribute('data-player-audio', '');
            root.insertBefore(audio, root.firstChild);
        }

        toggles.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                toggle();
            });
        });

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

        if (startBtn) {
            startBtn.addEventListener('click', function () {
                play();
            });
        }

        if (volumeEl) {
            volumeEl.addEventListener('input', function () {
                volume = clamp(parseFloat(volumeEl.value) / 100, 0, 1);
                audio.volume = volume;
                audio.muted = volume === 0;
                muted = volume === 0;
                updateMuteButton();

                if (window.Clementine) {
                    window.Clementine.storageSet(STORAGE_VOLUME, String(volume));
                }
            });
        }

        function updateMuteButton() {
            if (!muteBtn) {
                return;
            }

            var isMuted = audio.muted || audio.volume === 0;
            muteBtn.setAttribute('aria-label', isMuted ? 'Réactiver le son' : 'Couper le son');
            muteBtn.setAttribute('title', isMuted ? 'Réactiver le son' : 'Couper le son');
            muteBtn.classList.toggle('is-muted', isMuted);
        }

        if (muteBtn) {
            muteBtn.addEventListener('click', function () {
                if (audio.muted || audio.volume === 0) {
                    audio.muted = false;
                    if (audio.volume === 0) {
                        volume = 0.6;
                        audio.volume = volume;
                        if (volumeEl) {
                            volumeEl.value = '60';
                        }
                    }
                } else {
                    audio.muted = true;
                }
                muted = audio.muted;
                updateMuteButton();
            });
        }

        if (seekEl) {
            var seekAt = function (event) {
                var rect = seekEl.getBoundingClientRect();
                var ratio = clamp((event.clientX - rect.left) / rect.width, 0, 1);

                if (isFinite(audio.duration) && audio.duration > 0) {
                    audio.currentTime = ratio * audio.duration;
                    setProgress(audio.currentTime, audio.duration);
                }
            };

            seekEl.addEventListener('click', seekAt);

            seekEl.addEventListener('keydown', function (event) {
                if (!isFinite(audio.duration) || audio.duration <= 0) {
                    return;
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    audio.currentTime = clamp(audio.currentTime + 5, 0, audio.duration);
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    audio.currentTime = clamp(audio.currentTime - 5, 0, audio.duration);
                }
            });
        }

        audio.addEventListener('play', function () {
            setPlaying(true);
            showUnlock(false);
            if (window.Clementine) {
                window.Clementine.storageSet('clementine_wants_music', '1');
            }
        });

        audio.addEventListener('pause', function () {
            setPlaying(false);
        });

        audio.addEventListener('timeupdate', function () {
            setProgress(audio.currentTime, audio.duration);
        });

        audio.addEventListener('loadedmetadata', function () {
            setProgress(audio.currentTime, audio.duration);
        });

        audio.addEventListener('ended', function () {
            if (tracks.length > 1) {
                index = (index + 1) % tracks.length;
                loadTrack(true);
            } else {
                audio.currentTime = 0;
                setProgress(0, audio.duration);
                setStatus('Fin de la piste.');
            }
        });

        audio.addEventListener('error', function () {
            failures++;

            if (failures >= tracks.length) {
                setStatus('Cette piste n\'est pas disponible pour le moment.');
                return;
            }

            setStatus('Piste indisponible, passage à la suivante…');
            index = (index + 1) % tracks.length;
            window.setTimeout(function () {
                loadTrack(true);
            }, 600);
        });

        document.addEventListener('clementine:play', function () {
            play();
        });

        updateMeta();
        updateMuteButton();
        loadTrack(false);

        // Tentative de lecture automatique (souvent bloquée sans interaction).
        window.setTimeout(function () {
            attemptPlay();
        }, 350);
    }

    roots.forEach(init);
})();
