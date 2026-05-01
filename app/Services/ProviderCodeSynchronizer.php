<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/*
|--------------------------------------------------------------------------
| Provider code synchronizer
|--------------------------------------------------------------------------
|
| Operators should not invent providerCode values. The providerCode is returned
| by the provider profile endpoint for the provider-created operatorPublicId.
|
| This class reads that endpoint once, saves PRIME_MAC_PROVIDER_CODE in .env,
| and updates operator_provider_config so wallet callbacks validate the exact
| X-Provider-Code sent by the game server.
|
*/
class ProviderCodeSynchronizer
{
    private const AUTO_SYNC_ENV = 'PRIME_MAC_PROVIDER_CODE_AUTO_SYNC';
    private const PROVIDER_CODE_ENV = 'PRIME_MAC_PROVIDER_CODE';
    private const SYNCED_ENV = 'PRIME_MAC_PROVIDER_CODE_SYNCED';

    public function __construct(
        private readonly ProviderApiClient $provider,
        private readonly OperatorStore $store
    ) {
    }

    public function syncIfNeeded(): array
    {
        if (!$this->envBoolean(self::AUTO_SYNC_ENV, true)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'auto_sync_disabled'];
        }

        $operatorPublicId = $this->operatorPublicId();
        if ($operatorPublicId === '') {
            return ['ok' => false, 'error' => 'operator_public_id_missing'];
        }

        if ($this->alreadySynced($operatorPublicId)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'already_synced'];
        }

        return $this->sync(force: false, writeEnv: true, operatorPublicId: $operatorPublicId);
    }

    public function sync(bool $force = false, bool $writeEnv = true, ?string $operatorPublicId = null): array
    {
        $operatorPublicId = $operatorPublicId !== null ? trim($operatorPublicId) : $this->operatorPublicId();

        if (!$force && $this->alreadySynced($operatorPublicId)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'already_synced'];
        }

        if ($operatorPublicId === '') {
            return ['ok' => false, 'error' => 'operator_public_id_missing'];
        }

        $profile = $this->provider->operatorProfile($operatorPublicId);
        if (($profile['ok'] ?? true) === false) {
            return [
                'ok' => false,
                'error' => 'operator_profile_unavailable',
                'providerError' => $profile,
            ];
        }

        $providerCode = trim((string) ($profile['providerCode'] ?? ''));
        if ($providerCode === '') {
            return ['ok' => false, 'error' => 'provider_code_missing'];
        }

        $operatorName = isset($profile['operatorName']) ? (string) $profile['operatorName'] : null;
        $stored = $this->store->syncProviderCode($providerCode, $operatorName);
        if (($stored['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => $stored['error'] ?? 'provider_code_store_failed',
            ];
        }

        Config::set('services.prime_mac.provider_code', $providerCode);

        $envUpdated = false;
        if ($writeEnv) {
            $envUpdated = $this->writeEnvValues([
                self::PROVIDER_CODE_ENV => $providerCode,
                self::SYNCED_ENV => 'true',
            ]);
        }

        $this->writeSyncMarker($operatorPublicId, $operatorName, $providerCode, $envUpdated);

        Log::info('Prime Mac Games provider code synced.', [
            'operatorPublicId' => $operatorPublicId,
            'providerCode' => $providerCode,
            'envUpdated' => $envUpdated,
        ]);

        return [
            'ok' => true,
            'operatorPublicId' => $operatorPublicId,
            'operatorName' => $operatorName,
            'providerCode' => $providerCode,
            'envUpdated' => $envUpdated,
        ];
    }

    private function alreadySynced(string $operatorPublicId): bool
    {
        if ($this->envBoolean(self::SYNCED_ENV, false) && $this->envValue(self::PROVIDER_CODE_ENV) !== '') {
            return true;
        }

        $marker = $this->readSyncMarker();

        return ($marker['operatorPublicId'] ?? '') === $operatorPublicId
            && trim((string) ($marker['providerCode'] ?? '')) !== '';
    }

    private function operatorPublicId(): string
    {
        try {
            $config = $this->store->providerConfig();
        } catch (Throwable) {
            $config = [];
        }

        return trim((string) ($config['operator_public_id'] ?? config('services.prime_mac.operator_public_id', '')));
    }

    private function readSyncMarker(): array
    {
        $path = storage_path('app/prime-mac-provider-code.json');
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $marker = json_decode($contents, true);

        return is_array($marker) ? $marker : [];
    }

    private function writeSyncMarker(string $operatorPublicId, ?string $operatorName, string $providerCode, bool $envUpdated): void
    {
        $path = storage_path('app/prime-mac-provider-code.json');
        $payload = [
            'operatorPublicId' => $operatorPublicId,
            'operatorName' => $operatorName,
            'providerCode' => $providerCode,
            'envUpdated' => $envUpdated,
            'syncedAt' => now()->toIso8601String(),
        ];

        try {
            file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
        } catch (Throwable $exception) {
            Log::warning('Provider code sync marker could not be written.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function envBoolean(string $key, bool $default): bool
    {
        $value = $this->envValue($key);
        if ($value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function envValue(string $key): string
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return '';
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (!str_starts_with($line, $key.'=')) {
                continue;
            }

            $value = substr($line, strlen($key) + 1);

            return trim($value, " \t\n\r\0\x0B\"'");
        }

        return '';
    }

    private function writeEnvValues(array $values): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath) || !is_writable($envPath)) {
            Log::warning('Provider code was synced to the database, but .env is not writable.');

            return false;
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            return false;
        }

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->formatEnvValue((string) $value);
            if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $contents) ?? $contents;
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        return file_put_contents($envPath, $contents, LOCK_EX) !== false;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value !== '' && preg_match('/^[A-Za-z0-9_:\-.\/]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
