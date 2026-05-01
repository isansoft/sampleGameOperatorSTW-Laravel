<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Game Operator Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/operator.css') }}?v=20260501-game-frame">
</head>
<body class="app-body">
    @php
        $balance = (float) ($player['balance'] ?? 0);
        $currencyCode = (string) ($player['currencyCode'] ?? 'PHP');
        $balanceLabel = $currencyCode.' '.number_format($balance, 2);
        $healthStatus = (string) ($providerHealth['status'] ?? 'unknown');
    @endphp

    <div class="operator-shell">
        <aside class="sidebar">
            <div class="language-control">
                <span class="flag-mark"></span>
                <span>EN | English</span>
                <span class="chevron"></span>
            </div>

            <div class="profile-block">
                <div class="avatar">{{ strtoupper(substr($player['displayName'] ?? $player['username'] ?? 'GO', 0, 2)) }}</div>
                <span>Welcome Back,</span>
                <strong>{{ $player['displayName'] }}</strong>
                <small>{{ $player['playerPublicId'] }}</small>
            </div>

            <div class="wallet-strip">
                <strong>{{ $balanceLabel }}</strong>
                <button type="button" aria-label="Refresh balance" onclick="window.location.reload()">R</button>
                <button type="button" aria-label="Open wallet">W</button>
            </div>

            <div class="wallet-adjustments" aria-label="Wallet actions">
                <form method="POST" action="{{ route('operator.wallet.cash-in') }}" class="wallet-adjust-form">
                    @csrf
                    <input name="amount" type="number" min="1" max="1000000" step="0.01" placeholder="Amount" required>
                    <button class="cash-button cash-in" type="submit">Cash-In</button>
                </form>

                <form method="POST" action="{{ route('operator.wallet.cash-out') }}" class="wallet-adjust-form">
                    @csrf
                    <input name="amount" type="number" min="1" max="1000000" step="0.01" placeholder="Amount" required>
                    <button class="cash-button cash-out" type="submit">Cash-Out</button>
                </form>
            </div>

            <div class="balance-note">Converted Balance: ${{ number_format($balance / 57, 2) }}</div>

            <nav class="side-nav" aria-label="Operator navigation">
                <div class="nav-label">GAME PROVIDERS</div>

                @foreach ($providers as $provider)
                    <button class="provider-button is-active" type="button" data-provider-toggle aria-expanded="true">
                        <span class="provider-logo-small logo-frame">
                            <img src="{{ asset('images/'.$provider['logo']) }}" alt="">
                        </span>
                        <span>{{ $provider['name'] }}</span>
                        <span class="status-dot"></span>
                    </button>

                    <div class="provider-games is-open" data-provider-games>
                        @foreach ($provider['games'] as $game)
                            <button class="side-game is-active" type="button" data-game-button="{{ $game['code'] }}">
                                <img src="{{ asset('images/'.$game['icon']) }}" alt="">
                                <span>{{ $game['name'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="nav-label account-label">ACCOUNT</div>
            <a class="plain-nav-link" href="#transactions">Live Games Bet History</a>
            <a class="plain-nav-link" href="#transactions">Wallet History</a>
        </aside>

        <section class="operator-main">
            <header class="topbar">
                <button class="icon-button" type="button" aria-label="Open menu">
                    <span></span>
                </button>
                <h1>Game Operator</h1>
                <div class="topbar-actions">
                    <span class="topbar-balance">{{ $balanceLabel }}</span>
                    <form method="POST" action="{{ route('operator.logout') }}">
                        @csrf
                        <button class="logout-button" type="submit">Logout</button>
                    </form>
                </div>
            </header>

            <main class="operator-content">
                @if (session('status'))
                    <div class="operator-alert operator-alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->has('wallet') || $errors->has('launch') || $errors->has('amount'))
                    <div class="operator-alert">
                        {{ $errors->first('wallet') ?: $errors->first('launch') ?: $errors->first('amount') }}
                    </div>
                @endif

                @if ($activeGame)
                    <section class="game-frame-panel" aria-label="{{ $activeGame['gameName'] ?? 'Game' }} game window">
                        <div class="game-frame-header">
                            <div>
                                <span class="section-kicker">Running</span>
                                <h2>{{ $activeGame['gameName'] ?? $activeGame['gameCode'] ?? 'Game' }}</h2>
                            </div>

                            <form method="POST" action="{{ route('operator.games.close') }}">
                                @csrf
                                <button class="secondary-button" type="submit">Back to Games</button>
                            </form>
                        </div>

                        <div class="game-frame-shell">
                            <iframe
                                class="game-frame"
                                src="{{ $activeGame['launchUrl'] }}"
                                title="{{ $activeGame['gameName'] ?? $activeGame['gameCode'] ?? 'Game' }}"
                                allow="camera *; microphone *; autoplay *; fullscreen *; display-capture *; clipboard-read *; clipboard-write *; encrypted-media *"
                                allowfullscreen
                                loading="eager"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </section>
                @else
                    @foreach ($providers as $provider)
                        <section class="provider-hero" data-provider-panel>
                            <div>
                                <span class="section-kicker">{{ $provider['status'] }}</span>
                                <h2>{{ $provider['name'] }}</h2>
                                <p>Provider API: api.primemacgames.com</p>
                                <dl class="provider-meta">
                                    <div>
                                        <dt>Operator ID</dt>
                                        <dd>{{ $operatorPublicId }}</dd>
                                    </div>
                                    <div>
                                        <dt>Health</dt>
                                        <dd>{{ strtoupper($healthStatus) }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="hero-logo-frame logo-frame">
                                <img src="{{ asset('images/'.$provider['logo']) }}" alt="{{ $provider['name'] }}">
                            </div>
                        </section>

                        <section class="games-panel">
                            <div class="section-heading">
                                <div>
                                    <span class="section-kicker">Games</span>
                                    <h2>Available Games</h2>
                                </div>
                                <span class="game-count">{{ count($provider['games']) }} active</span>
                            </div>

                            <div class="game-grid">
                                @foreach ($provider['games'] as $game)
                                    <article class="game-card is-selected" data-game-card="{{ $game['code'] }}">
                                        <div class="game-art">
                                            <img src="{{ asset('images/'.$game['icon']) }}" alt="">
                                        </div>
                                        <div class="game-copy">
                                            <span>{{ $game['status'] }}</span>
                                            <h3>{{ $game['name'] }}</h3>
                                            <p>{{ $game['currencyCode'] ?? $currencyCode }} wallet launch</p>
                                        </div>
                                        <form method="POST" action="{{ route('operator.games.launch', $game['gameId']) }}">
                                            @csrf
                                            <input type="hidden" name="game_code" value="{{ $game['code'] }}">
                                            <button class="manage-button" type="submit">Launch {{ $game['name'] }}</button>
                                        </form>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <section class="transactions-panel" id="transactions">
                        <div class="section-heading">
                            <div>
                                <span class="section-kicker">Wallet</span>
                                <h2>Recent Transactions</h2>
                            </div>
                        </div>

                        <div class="transaction-list">
                            @forelse ($transactions as $transaction)
                                <div class="transaction-row">
                                    <span>{{ strtoupper(str_replace('_', ' ', $transaction['transactionType'] ?? 'transaction')) }}</span>
                                    <strong>{{ $transaction['currencyCode'] ?? $currencyCode }} {{ number_format((float) ($transaction['amount'] ?? 0), 2) }}</strong>
                                    <small>{{ $transaction['transactionId'] ?? '' }}</small>
                                    <em>{{ $transaction['createdAt'] ?? '' }}</em>
                                </div>
                            @empty
                                <div class="empty-state">No wallet transactions yet.</div>
                            @endforelse
                        </div>
                    </section>
                @endif
            </main>
        </section>
    </div>

    <button class="support-fab app-support" type="button" aria-label="Open support">
        <span></span>
    </button>

    <script src="{{ asset('js/operator.js') }}?v=20260430-register-modal"></script>
</body>
</html>
