(function () {
    const loginModal = document.querySelector('[data-login-modal]');
    const registerModal = document.querySelector('[data-register-modal]');
    const openLoginButtons = document.querySelectorAll('[data-open-login]');
    const closeLoginButtons = document.querySelectorAll('[data-close-login]');
    const openRegisterButtons = document.querySelectorAll('[data-open-register]');
    const closeRegisterButtons = document.querySelectorAll('[data-close-register]');

    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.hidden = false;
        const firstInput = modal.querySelector('input');
        if (firstInput) {
            firstInput.focus();
        }
    };

    const closeModal = (modal) => {
        if (modal) {
            modal.hidden = true;
        }
    };

    openLoginButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openModal(loginModal);
        });
    });

    closeLoginButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(loginModal);
        });
    });

    openRegisterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openModal(registerModal);
        });
    });

    closeRegisterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(registerModal);
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((passwordToggle) => {
        passwordToggle.addEventListener('click', () => {
            const field = passwordToggle.closest('.password-field');
            const passwordInput = field ? field.querySelector('[data-password-input]') : null;
            if (!passwordInput) {
                return;
            }

            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    document.querySelectorAll('[data-provider-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const games = button.nextElementSibling;
            const isOpen = games && games.classList.toggle('is-open');
            button.classList.toggle('is-active', Boolean(isOpen));
            button.setAttribute('aria-expanded', String(Boolean(isOpen)));
        });
    });

    document.querySelectorAll('[data-game-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const gameCode = button.getAttribute('data-game-button');

            document.querySelectorAll('[data-game-button]').forEach((item) => {
                item.classList.toggle('is-active', item === button);
            });

            document.querySelectorAll('[data-game-card]').forEach((card) => {
                card.classList.toggle('is-selected', card.getAttribute('data-game-card') === gameCode);
            });
        });
    });

    const apiMonitor = document.querySelector('[data-api-monitor]');
    if (apiMonitor) {
        const streamUrl = apiMonitor.getAttribute('data-stream-url');
        const list = apiMonitor.querySelector('[data-api-monitor-list]');
        const latest = apiMonitor.querySelector('[data-api-monitor-latest]');
        const status = apiMonitor.querySelector('[data-api-monitor-status]');
        const pauseButton = apiMonitor.querySelector('[data-api-monitor-pause]');
        const clearButton = apiMonitor.querySelector('[data-api-monitor-clear]');
        const dragHandle = apiMonitor.querySelector('[data-api-monitor-drag]');
        let logs = [];
        let lastId = 0;
        let paused = false;
        let eventSource = null;
        let reconnectTimer = null;
        let dragState = null;
        let hasCustomMonitorPosition = false;
        const monitorPositionKey = 'gameOperator.apiMonitor.position';

        const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

        const saveMonitorPosition = () => {
            if (!hasCustomMonitorPosition) {
                return;
            }

            const rect = apiMonitor.getBoundingClientRect();

            try {
                window.localStorage.setItem(monitorPositionKey, JSON.stringify({
                    left: Math.round(rect.left),
                    top: Math.round(rect.top),
                }));
            } catch (error) {
                // The monitor still works if browser storage is disabled.
            }
        };

        const setMonitorPosition = (left, top, persist = true) => {
            const rect = apiMonitor.getBoundingClientRect();
            const margin = 12;
            const maxLeft = Math.max(margin, window.innerWidth - rect.width - margin);
            const maxTop = Math.max(margin, window.innerHeight - rect.height - margin);

            apiMonitor.style.left = `${clamp(left, margin, maxLeft)}px`;
            apiMonitor.style.top = `${clamp(top, margin, maxTop)}px`;
            apiMonitor.style.right = 'auto';
            apiMonitor.style.bottom = 'auto';
            hasCustomMonitorPosition = true;

            if (persist) {
                saveMonitorPosition();
            }
        };

        const restoreMonitorPosition = () => {
            try {
                const saved = JSON.parse(window.localStorage.getItem(monitorPositionKey) || 'null');

                if (saved && Number.isFinite(saved.left) && Number.isFinite(saved.top)) {
                    setMonitorPosition(saved.left, saved.top, false);
                }
            } catch (error) {
                // Ignore corrupt or unavailable saved positions.
            }
        };

        const finishDrag = (event) => {
            if (!dragState) {
                return;
            }

            if (dragHandle && dragHandle.hasPointerCapture(event.pointerId)) {
                dragHandle.releasePointerCapture(event.pointerId);
            }

            dragState = null;
            apiMonitor.classList.remove('is-dragging');
            saveMonitorPosition();
        };

        const setStatus = (text, state) => {
            if (!status) {
                return;
            }

            status.textContent = text;
            status.setAttribute('data-state', state);
        };

        const parseInitialLogs = () => {
            try {
                const parsed = JSON.parse(apiMonitor.getAttribute('data-initial-logs') || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        };

        const endpointLabel = (log) => `${log.httpMethod || 'POST'} ${log.endpointPath || ''}`.trim();

        const formatTime = (value) => {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        };

        const render = () => {
            if (!list) {
                return;
            }

            list.innerHTML = '';
            logs.slice(0, 24).forEach((log) => {
                const item = document.createElement('li');
                const ok = Number(log.responseStatus || 0) < 400;
                item.className = ok ? 'is-ok' : 'is-error';

                const title = document.createElement('strong');
                title.textContent = endpointLabel(log);

                const meta = document.createElement('span');
                const bits = [
                    String(log.responseStatus || ''),
                    formatTime(log.createdAt),
                    log.transactionId ? `tx ${log.transactionId}` : null,
                    log.roundId ? `round ${log.roundId}` : null,
                    log.requestHashShort ? `hash ${log.requestHashShort}` : null,
                    log.errorCode ? `error ${log.errorCode}` : null,
                ].filter(Boolean);
                meta.textContent = bits.join(' | ');

                item.append(title, meta);
                list.prepend(item);
            });

            if (latest) {
                const newest = logs[logs.length - 1];
                latest.textContent = newest
                    ? `${endpointLabel(newest)} -> ${newest.responseStatus || ''}`
                    : 'No API calls yet.';
            }
        };

        const remember = (log) => {
            const id = Number(log.id || 0);
            if (id > 0) {
                lastId = Math.max(lastId, id);
            }

            if (logs.some((item) => Number(item.id || 0) === id && id > 0)) {
                return;
            }

            logs.push(log);
            logs = logs.slice(-24);

            if (!paused) {
                render();
            }
        };

        const connect = () => {
            if (!streamUrl || !window.EventSource) {
                setStatus('Unavailable', 'error');
                return;
            }

            const separator = streamUrl.includes('?') ? '&' : '?';
            eventSource = new EventSource(`${streamUrl}${separator}after_id=${encodeURIComponent(lastId)}`);

            eventSource.onopen = () => {
                setStatus(paused ? 'Paused' : 'Connected', paused ? 'paused' : 'ok');
            };

            eventSource.addEventListener('api-log', (event) => {
                try {
                    remember(JSON.parse(event.data));
                } catch (error) {
                    setStatus('Parse error', 'error');
                }
            });

            eventSource.onerror = () => {
                if (eventSource) {
                    eventSource.close();
                }

                setStatus('Reconnecting', 'warning');
                window.clearTimeout(reconnectTimer);
                reconnectTimer = window.setTimeout(connect, 2000);
            };
        };

        restoreMonitorPosition();

        if (dragHandle && window.PointerEvent) {
            dragHandle.addEventListener('pointerdown', (event) => {
                if (event.target.closest('button, a, input, select, textarea')) {
                    return;
                }

                const rect = apiMonitor.getBoundingClientRect();
                dragState = {
                    offsetX: event.clientX - rect.left,
                    offsetY: event.clientY - rect.top,
                };

                dragHandle.setPointerCapture(event.pointerId);
                apiMonitor.classList.add('is-dragging');
            });

            dragHandle.addEventListener('pointermove', (event) => {
                if (!dragState) {
                    return;
                }

                setMonitorPosition(event.clientX - dragState.offsetX, event.clientY - dragState.offsetY, false);
            });

            dragHandle.addEventListener('pointerup', finishDrag);
            dragHandle.addEventListener('pointercancel', finishDrag);
        }

        window.addEventListener('resize', () => {
            if (!hasCustomMonitorPosition) {
                return;
            }

            const rect = apiMonitor.getBoundingClientRect();
            setMonitorPosition(rect.left, rect.top);
        });

        parseInitialLogs().forEach(remember);
        render();
        connect();

        if (pauseButton) {
            pauseButton.addEventListener('click', () => {
                paused = !paused;
                pauseButton.textContent = paused ? 'Resume' : 'Pause';
                setStatus(paused ? 'Paused' : 'Connected', paused ? 'paused' : 'ok');
                if (!paused) {
                    render();
                }
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                logs = [];
                render();
            });
        }
    }
})();
