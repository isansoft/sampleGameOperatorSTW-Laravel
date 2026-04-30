<?php

namespace App\Http\Controllers;

use App\Services\OperatorStore;
use App\Services\ProviderApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OperatorController extends Controller
{
    public function login(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('player_id')) {
            return redirect()->route('operator.dashboard');
        }

        return view('operator.login');
    }

    public function register(Request $request, OperatorStore $store): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:3', 'max:80', 'alpha_dash'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors($validator)
                ->with('auth_mode', 'register');
        }

        $data = $validator->validated();
        $result = $store->registerPlayer(
            $data['username'],
            $data['display_name'] ?? $data['username'],
            Hash::make($data['password']),
            'PHP',
            'en-PH',
            'PH',
            1000
        );

        if (($result['ok'] ?? false) !== true) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['register' => $this->operatorErrorMessage($result['error'] ?? 'registration_failed')])
                ->with('auth_mode', 'register');
        }

        $player = $result['player'];
        $request->session()->regenerate();
        $request->session()->put('player_id', (int) $player['id']);

        return redirect()->route('operator.dashboard');
    }

    public function authenticate(Request $request, OperatorStore $store): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors($validator)
                ->with('auth_mode', 'login');
        }

        $credentials = $validator->validated();
        $result = $store->loginLookup($credentials['username']);
        $player = $result['player'] ?? null;
        if (($result['ok'] ?? false) !== true || !$player || !Hash::check($credentials['password'], $player['passwordHash'] ?? '')) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['login' => 'Invalid username or password.'])
                ->with('auth_mode', 'login');
        }

        $store->markLogin((int) $player['id']);
        $request->session()->regenerate();
        $request->session()->put('player_id', (int) $player['id']);

        return redirect()->route('operator.dashboard');
    }

    public function dashboard(Request $request, OperatorStore $store, ProviderApiClient $provider): View|RedirectResponse
    {
        $playerId = $request->session()->get('player_id');
        if (!$playerId) {
            return redirect()->route('operator.login');
        }

        $playerResult = $store->player((int) $playerId);
        if (($playerResult['ok'] ?? false) !== true) {
            $request->session()->forget('player_id');
            return redirect()->route('operator.login');
        }

        $player = $playerResult['player'];
        $config = $store->providerConfig();
        $operatorPublicId = (string) ($config['operator_public_id'] ?? config('services.prime_mac.operator_public_id'));
        $providerGames = $provider->games($operatorPublicId);
        $providerHealth = $provider->health($operatorPublicId);
        $transactions = $store->recentTransactions((int) $playerId);

        // Show only games that the provider says are active and enabled for
        // this operatorPublicId. The operator should not hard-code enabled games.
        $games = collect($providerGames)
            ->filter(fn (array $game) => (bool) ($game['isActive'] ?? false) && (bool) ($game['isEnabled'] ?? false))
            ->map(fn (array $game) => [
                'name' => strtoupper((string) ($game['gameCode'] ?? $game['name'] ?? 'GAME')),
                'code' => strtoupper((string) ($game['gameCode'] ?? 'GAME')),
                'status' => 'Ready',
                'icon' => 'game-poker.svg',
                'gameId' => (int) ($game['gameId'] ?? 1),
                'tables' => 0,
                'players' => 0,
                'minBet' => $game['minBet'] ?? null,
                'maxBet' => $game['maxBet'] ?? null,
                'currencyCode' => $game['currencyCode'] ?? $player['currencyCode'],
            ])
            ->values()
            ->all();

        if ($games === []) {
            $games = [[
                'name' => 'POKER',
                'code' => 'POKER',
                'status' => 'Ready',
                'icon' => 'game-poker.svg',
                'gameId' => 1,
                'tables' => 0,
                'players' => 0,
                'minBet' => 1,
                'maxBet' => 1000,
                'currencyCode' => $player['currencyCode'],
            ]];
        }

        $providers = [
            [
                'name' => 'PrimeMacGames',
                'status' => ($providerHealth['status'] ?? '') === 'ready' ? 'Connected' : 'Needs configuration',
                'logo' => 'primeMacGamesLogo.png',
                'games' => $games,
            ],
        ];

        return view('operator.dashboard', [
            'user' => [
                'name' => $player['displayName'],
                'role' => 'Player',
            ],
            'player' => $player,
            'providers' => $providers,
            'operatorPublicId' => $operatorPublicId,
            'providerHealth' => $providerHealth,
            'transactions' => $transactions,
        ]);
    }

    public function cashIn(Request $request, OperatorStore $store): RedirectResponse
    {
        return $this->adjustWallet($request, $store, 'credit', 'Cash-in');
    }

    public function cashOut(Request $request, OperatorStore $store): RedirectResponse
    {
        return $this->adjustWallet($request, $store, 'debit', 'Cash-out');
    }

    public function launchGame(int $gameId, Request $request, OperatorStore $store, ProviderApiClient $provider): RedirectResponse
    {
        $playerId = $request->session()->get('player_id');
        if (!$playerId) {
            return redirect()->route('operator.login');
        }

        $gameCode = strtoupper((string) $request->input('game_code', 'POKER'));

        // Step 1: create a short-lived launch token in the operator database.
        // The provider will later call POST /api/player/authorize with this
        // token to get the player id, balance, currency, and operatorPublicId.
        $tokenResult = $store->createLaunchToken((int) $playerId, $gameId, $gameCode, 5);
        if (($tokenResult['ok'] ?? false) !== true) {
            return back()->withErrors(['launch' => $this->operatorErrorMessage($tokenResult['error'] ?? 'launch_failed')]);
        }

        $config = $store->providerConfig();
        $operatorPublicId = (string) ($config['operator_public_id'] ?? config('services.prime_mac.operator_public_id'));

        // Step 2: ask the provider for the current launch URL format.
        // GET https://api.primemacgames.com/api/portal/operators/{operatorPublicId}/games/{gameId}/launch-config
        $launchConfig = $provider->launchConfig($operatorPublicId, $gameId);
        if (!isset($launchConfig['launchUrlFormat'])) {
            return back()->withErrors(['launch' => 'Provider launch configuration is not available.']);
        }

        // Step 3: replace {launchToken} and redirect the player to the game.
        return redirect()->away($provider->buildLaunchUrl(
            $launchConfig['launchUrlFormat'],
            (string) $tokenResult['launchToken'],
            $operatorPublicId
        ));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('player_id');
        $request->session()->regenerateToken();

        return redirect()->route('operator.login');
    }

    private function adjustWallet(Request $request, OperatorStore $store, string $direction, string $label): RedirectResponse
    {
        $playerId = $request->session()->get('player_id');
        if (!$playerId) {
            return redirect()->route('operator.login');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
        ]);

        $result = $store->adjustWallet((int) $playerId, $direction, (float) $data['amount'], $label);
        if (($result['ok'] ?? false) !== true) {
            return back()->withErrors(['wallet' => $this->operatorErrorMessage($result['error'] ?? 'wallet_adjust_failed')]);
        }

        return back()->with('status', "{$label} processed.");
    }

    private function operatorErrorMessage(string $error): string
    {
        return match ($error) {
            'username_taken' => 'That username is already registered.',
            'username_min_length' => 'Username must be at least 3 characters.',
            'insufficient_funds' => 'Insufficient wallet balance.',
            'provider_config_missing' => 'Provider configuration is missing.',
            default => Str::headline($error),
        };
    }
}
