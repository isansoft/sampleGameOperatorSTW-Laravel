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
})();
