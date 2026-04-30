<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Read-only Prime Mac Games portal API client
|--------------------------------------------------------------------------
|
| The operator uses this client to read provider configuration, including the
| game launch URL. Wallet callbacks go the other direction and are handled by
| ProviderWalletController.
|
*/
class ProviderApiClient
{
    public function operatorProfile(string $operatorPublicId): array
    {
        return $this->get("/api/portal/operators/{$operatorPublicId}");
    }

    public function games(string $operatorPublicId): array
    {
        $response = $this->get("/api/portal/operators/{$operatorPublicId}/games");

        return $response['games'] ?? [];
    }

    public function launchConfig(string $operatorPublicId, int $gameId): array
    {
        // This is the endpoint operators call to get the current launch URL.
        // Example path:
        // /api/portal/operators/{operatorPublicId}/games/{gameId}/launch-config
        return $this->get("/api/portal/operators/{$operatorPublicId}/games/{$gameId}/launch-config");
    }

    public function health(string $operatorPublicId): array
    {
        return $this->get("/api/portal/operators/{$operatorPublicId}/health");
    }

    public function buildLaunchUrl(string $launchUrlFormat, string $launchToken, ?string $operatorPublicId = null): string
    {
        // The provider may return a template containing placeholders. The
        // operator only creates the launchToken; the operatorPublicId comes
        // from the provider-created operator account.
        $replacements = [
            '{launchToken}' => rawurlencode($launchToken),
        ];

        if ($operatorPublicId !== null) {
            $replacements['{operatorPublicId}'] = rawurlencode($operatorPublicId);
        }

        return Str::replace(array_keys($replacements), array_values($replacements), $launchUrlFormat);
    }

    private function get(string $path): array
    {
        $baseUrl = rtrim((string) config('services.prime_mac.base_url'), '/');
        $response = Http::acceptJson()
            ->timeout(12)
            ->retry(2, 200)
            ->get($baseUrl.$path);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'error' => 'provider_api_error',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        }

        return $response->json() ?? [];
    }
}
