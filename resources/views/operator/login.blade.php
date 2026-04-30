<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Game Operator Login</title>
    <link rel="stylesheet" href="{{ asset('css/operator.css') }}?v=20260430-stw-assets">
</head>
<body class="auth-body">
    @php
        $authMode = session('auth_mode');
        if (!$authMode && $errors->any()) {
            $authMode = old('display_name') !== null || $errors->has('register') || $errors->has('password_confirmation') ? 'register' : 'login';
        }
    @endphp

    <main class="auth-shell">
        <section class="auth-panel" aria-label="Operator access">
            <div class="auth-brand">
                <div class="auth-logo-frame logo-frame">
                    <img src="{{ asset('images/stw-logo.webp') }}" alt="Sabong Traditional Worldwide logo">
                </div>
                <p>Create an account or login.</p>
            </div>

            <div class="auth-actions">
                <button class="action-button action-login" type="button" data-open-login>LOGIN</button>
                <button class="action-button action-register" type="button" data-open-register>REGISTER</button>
                <a class="action-button action-download" href="#">DOWNLOAD APP</a>
            </div>
        </section>

        <div class="auth-divider" aria-hidden="true"></div>

        <section class="auth-showcase" aria-label="Sabong Traditional Worldwide">
            <div class="showcase-logo-frame">
                <img src="{{ asset('images/login-banner.webp') }}" alt="Sabong Traditional Worldwide">
            </div>
        </section>
    </main>

    <footer class="legal-notice">
        <a href="#">Legal Compliance &amp; Regulatory Notice</a>
        <p>This platform is geo-restricted and operates under licensed gaming regulations.</p>
    </footer>

    <button class="support-fab" type="button" aria-label="Open support">
        <span></span>
    </button>

    <div class="login-modal" data-login-modal @if ($authMode !== 'login') hidden @endif>
        <div class="modal-backdrop" data-close-login></div>
        <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="login-title">
            <button class="modal-close" type="button" data-close-login aria-label="Close login">&times;</button>
            <h1 id="login-title">User Login</h1>

            @if ($authMode === 'login' && ($errors->has('login') || $errors->has('username') || $errors->has('password')))
                <div class="form-alert">
                    {{ $errors->first('login') ?: $errors->first('username') ?: $errors->first('password') }}
                </div>
            @endif

            <form method="POST" action="{{ route('operator.login.submit') }}" class="login-form">
                @csrf

                <label class="field">
                    <span class="field-label">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M12 12c2.8 0 5-2.2 5-5s-2.2-5-5-5-5 2.2-5 5 2.2 5 5 5Zm0 2c-4.4 0-8 2.2-8 5v1c0 .6.4 1 1 1h14c.6 0 1-.4 1-1v-1c0-2.8-3.6-5-8-5Z"/>
                        </svg>
                        Username
                    </span>
                    <input name="username" type="text" value="{{ $authMode === 'login' ? old('username') : '' }}" placeholder="Username" autocomplete="username" required>
                </label>

                <label class="field">
                    <span class="field-label">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M17 9V7A5 5 0 0 0 7 7v2H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1ZM9 9V7a3 3 0 0 1 6 0v2H9Zm4 7.7V18h-2v-1.3a2 2 0 1 1 2 0Z"/>
                        </svg>
                        Password
                    </span>
                    <span class="password-field">
                        <input name="password" type="password" placeholder="Password" autocomplete="current-password" required data-password-input>
                        <button type="button" data-password-toggle aria-label="Show password">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M12 5c5.2 0 8.8 4.2 10 7-1.2 2.8-4.8 7-10 7S3.2 14.8 2 12c1.2-2.8 4.8-7 10-7Zm0 2C8.5 7 5.7 9.4 4.3 12c1.4 2.6 4.2 5 7.7 5s6.3-2.4 7.7-5C18.3 9.4 15.5 7 12 7Zm0 2.5A2.5 2.5 0 1 1 12 14a2.5 2.5 0 0 1 0-5Z"/>
                            </svg>
                        </button>
                    </span>
                </label>

                <button class="action-button action-login login-submit" type="submit">LOGIN</button>
                <a class="forgot-link" href="#">Forgot Password?</a>
            </form>

            <a class="action-button action-download modal-download" href="#">DOWNLOAD APP</a>

            <div class="modal-legal">
                <p>By accessing this platform, you agree to our</p>
                <a href="#">Legal Compliance &amp; Regulatory Notice</a>
                <p>This platform is geo-restricted and operates under licensed gaming regulations.</p>
            </div>
        </section>
    </div>

    <div class="register-modal" data-register-modal @if ($authMode !== 'register') hidden @endif>
        <div class="modal-backdrop" data-close-register></div>
        <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="register-title">
            <button class="modal-close" type="button" data-close-register aria-label="Close register">&times;</button>
            <h1 id="register-title">Create Account</h1>

            @if ($authMode === 'register' && $errors->any())
                <div class="form-alert">
                    {{ $errors->first('register') ?: $errors->first('username') ?: $errors->first('display_name') ?: $errors->first('password') ?: $errors->first('password_confirmation') }}
                </div>
            @endif

            <form method="POST" action="{{ route('operator.register.submit') }}" class="login-form">
                @csrf

                <label class="field">
                    <span class="field-label">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M12 12c2.8 0 5-2.2 5-5s-2.2-5-5-5-5 2.2-5 5 2.2 5 5 5Zm0 2c-4.4 0-8 2.2-8 5v1c0 .6.4 1 1 1h14c.6 0 1-.4 1-1v-1c0-2.8-3.6-5-8-5Z"/>
                        </svg>
                        Username
                    </span>
                    <input name="username" type="text" value="{{ $authMode === 'register' ? old('username') : '' }}" placeholder="Username" autocomplete="username" required>
                </label>

                <label class="field">
                    <span class="field-label">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M12 12c2.8 0 5-2.2 5-5s-2.2-5-5-5-5 2.2-5 5 2.2 5 5 5Zm0 2c-4.4 0-8 2.2-8 5v1c0 .6.4 1 1 1h14c.6 0 1-.4 1-1v-1c0-2.8-3.6-5-8-5Z"/>
                        </svg>
                        Display Name
                    </span>
                    <input name="display_name" type="text" value="{{ $authMode === 'register' ? old('display_name') : '' }}" placeholder="Display Name" autocomplete="name">
                </label>

                <label class="field">
                    <span class="field-label">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M17 9V7A5 5 0 0 0 7 7v2H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1ZM9 9V7a3 3 0 0 1 6 0v2H9Zm4 7.7V18h-2v-1.3a2 2 0 1 1 2 0Z"/>
                        </svg>
                        Password
                    </span>
                    <span class="password-field">
                        <input name="password" type="password" placeholder="Password" autocomplete="new-password" required data-password-input>
                        <button type="button" data-password-toggle aria-label="Show password">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M12 5c5.2 0 8.8 4.2 10 7-1.2 2.8-4.8 7-10 7S3.2 14.8 2 12c1.2-2.8 4.8-7 10-7Zm0 2C8.5 7 5.7 9.4 4.3 12c1.4 2.6 4.2 5 7.7 5s6.3-2.4 7.7-5C18.3 9.4 15.5 7 12 7Zm0 2.5A2.5 2.5 0 1 1 12 14a2.5 2.5 0 0 1 0-5Z"/>
                            </svg>
                        </button>
                    </span>
                </label>

                <label class="field">
                    <span class="field-label">
                        <svg aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M17 9V7A5 5 0 0 0 7 7v2H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1ZM9 9V7a3 3 0 0 1 6 0v2H9Zm4 7.7V18h-2v-1.3a2 2 0 1 1 2 0Z"/>
                        </svg>
                        Confirm Password
                    </span>
                    <span class="password-field">
                        <input name="password_confirmation" type="password" placeholder="Confirm Password" autocomplete="new-password" required data-password-input>
                        <button type="button" data-password-toggle aria-label="Show password">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M12 5c5.2 0 8.8 4.2 10 7-1.2 2.8-4.8 7-10 7S3.2 14.8 2 12c1.2-2.8 4.8-7 10-7Zm0 2C8.5 7 5.7 9.4 4.3 12c1.4 2.6 4.2 5 7.7 5s6.3-2.4 7.7-5C18.3 9.4 15.5 7 12 7Zm0 2.5A2.5 2.5 0 1 1 12 14a2.5 2.5 0 0 1 0-5Z"/>
                            </svg>
                        </button>
                    </span>
                </label>

                <button class="action-button action-register login-submit" type="submit">REGISTER</button>
            </form>

            <div class="modal-legal">
                <p>By creating an account, you agree to our</p>
                <a href="#">Legal Compliance &amp; Regulatory Notice</a>
                <p>This platform is geo-restricted and operates under licensed gaming regulations.</p>
            </div>
        </section>
    </div>

    <script src="{{ asset('js/operator.js') }}?v=20260430-register-modal"></script>
</body>
</html>
