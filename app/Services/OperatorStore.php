<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Operator database boundary
|--------------------------------------------------------------------------
|
| This sample project keeps business logic in PostgreSQL stored procedures.
| Controllers call these methods instead of running ad hoc SQL. Operators can
| replace this class with their own database layer as long as the API responses
| stay compatible with the provider contract.
|
*/
class OperatorStore
{
    public function providerConfig(bool $includeSecret = false): array
    {
        $result = $this->callJson('SELECT sp_provider_config_get() AS result');

        if (!$includeSecret) {
            unset($result['signing_secret']);
        }

        return $result;
    }

    public function syncProviderCode(string $providerCode, ?string $providerName = null): array
    {
        // The providerCode belongs to the provider-created operator account.
        // This updates the stored config after reading it from the provider
        // profile endpoint: GET /api/portal/operators/{operatorPublicId}.
        return $this->callJson(
            'SELECT sp_provider_code_sync(?, ?) AS result',
            [$providerName, $providerCode]
        );
    }

    public function registerPlayer(
        string $username,
        string $displayName,
        string $passwordHash,
        string $currencyCode = 'PHP',
        string $languageCode = 'en-PH',
        string $countryCode = 'PH',
        float $initialBalance = 1000
    ): array {
        return $this->callJson(
            'SELECT sp_player_register(?, ?, ?, ?, ?, ?, ?) AS result',
            [$username, $displayName, $passwordHash, $currencyCode, $languageCode, $countryCode, $initialBalance]
        );
    }

    public function loginLookup(string $username): array
    {
        return $this->callJson('SELECT sp_player_login_lookup(?) AS result', [$username]);
    }

    public function markLogin(int $playerId): void
    {
        $this->callJson('SELECT sp_player_mark_login(?) AS result', [$playerId]);
    }

    public function player(int $playerId): array
    {
        return $this->callJson('SELECT sp_player_get(?) AS result', [$playerId]);
    }

    public function createLaunchToken(int $playerId, int $gameId, string $gameCode, int $ttlMinutes = 5): array
    {
        // The launch token is sent to the game client. The provider sends it
        // back to POST /api/player/authorize to identify the player securely.
        return $this->callJson(
            'SELECT sp_launch_token_create(?, ?, ?, ?) AS result',
            [$playerId, $gameId, $gameCode, $ttlMinutes]
        );
    }

    public function authorizeLaunchToken(string $launchToken): array
    {
        return $this->callJson('SELECT sp_launch_token_authorize(?::uuid) AS result', [$launchToken]);
    }

    public function balance(string $playerId): array
    {
        return $this->callJson('SELECT sp_balance_get(?) AS result', [$playerId]);
    }

    public function recordNonce(string $providerCode, string $nonce, int $timestampMs, int $ttlSeconds = 180): array
    {
        return $this->callJson(
            "SELECT sp_provider_nonce_record(?, ?, ?, now() + (? || ' seconds')::interval) AS result",
            [$providerCode, $nonce, $timestampMs, $ttlSeconds]
        );
    }

    public function placeBet(array $payload, string $requestHash): array
    {
        // Stored procedure handles: player lookup, row lock, balance debit,
        // insufficient-funds check, transactionId idempotency, and audit row.
        return $this->callJson(
            'SELECT sp_wallet_bet_place(?, ?, ?, ?, ?, ?, ?::jsonb, ?) AS result',
            [
                $payload['playerId'] ?? '',
                (int) ($payload['gameId'] ?? 0),
                $payload['roundId'] ?? null,
                $payload['transactionId'] ?? '',
                (float) ($payload['amount'] ?? 0),
                $payload['currencyCode'] ?? 'PHP',
                json_encode($payload, JSON_THROW_ON_ERROR),
                $requestHash,
            ]
        );
    }

    public function settleBet(array $payload, string $requestHash): array
    {
        // Credit positive payout amount. Do not send negative settle amounts.
        return $this->callJson(
            'SELECT sp_wallet_bet_settle(?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?) AS result',
            [
                $payload['playerId'] ?? '',
                (int) ($payload['gameId'] ?? 0),
                $payload['roundId'] ?? null,
                $payload['transactionId'] ?? '',
                (float) ($payload['amount'] ?? 0),
                $payload['currencyCode'] ?? 'PHP',
                json_encode($payload, JSON_THROW_ON_ERROR),
                json_encode($payload['playerActivity'] ?? null, JSON_THROW_ON_ERROR),
                $requestHash,
            ]
        );
    }

    public function rollbackBet(array $payload, string $requestHash): array
    {
        // Reverses the original transaction. Debit originals are credited back;
        // credit originals are debited back.
        return $this->callJson(
            'SELECT sp_wallet_bet_rollback(?, ?, ?, ?, ?, ?, ?::jsonb, ?) AS result',
            [
                $payload['playerId'] ?? '',
                (int) ($payload['gameId'] ?? 0),
                $payload['roundId'] ?? null,
                $payload['transactionId'] ?? '',
                $payload['originalTransactionId'] ?? '',
                (float) ($payload['amount'] ?? 0),
                json_encode($payload, JSON_THROW_ON_ERROR),
                $requestHash,
            ]
        );
    }

    public function adjustWallet(int $playerId, string $direction, float $amount, string $note): array
    {
        return $this->callJson(
            'SELECT sp_wallet_adjust(?, ?, ?, ?, ?) AS result',
            [$playerId, 'ADJ-'.strtoupper(bin2hex(random_bytes(8))), $direction, $amount, $note]
        );
    }

    public function recentTransactions(int $playerId, int $limit = 20): array
    {
        $result = $this->callJson('SELECT sp_wallet_recent_transactions(?, ?) AS result', [$playerId, $limit]);

        return array_values($result);
    }

    private function callJson(string $sql, array $bindings = []): array
    {
        $row = DB::selectOne($sql, $bindings);
        $payload = $row?->result ?? '{}';

        if (is_array($payload)) {
            return $payload;
        }

        return json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
