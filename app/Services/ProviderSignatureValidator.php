<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Prime Mac Games request signature validation
|--------------------------------------------------------------------------
|
| Wallet callback requests are server-to-server calls from the provider. The
| operator must verify the provider code, timestamp, nonce, and HMAC signature
| before touching a player's wallet.
|
| Signing message:
| {timestamp}.{nonce}.{HTTP_METHOD}.{pathAndQuery}.{rawBody}
|
*/
class ProviderSignatureValidator
{
    public function __construct(private readonly OperatorStore $store)
    {
    }

    public function validate(Request $request): array
    {
        $config = $this->store->providerConfig(includeSecret: true);
        if (($config['ok'] ?? true) === false) {
            return $this->reject('provider_config_missing', 500);
        }

        $providerCode = (string) ($config['provider_code'] ?? config('services.prime_mac.provider_code'));
        $requestProviderCode = (string) $request->header('X-Provider-Code', '');
        $signature = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $method = strtoupper($request->getMethod());
        $pathAndQuery = $this->pathAndQuery($request);
        $rawBody = $request->getContent();
        $signingSecret = (string) ($config['signing_secret'] ?? '');

        $debugContext = [
            'method' => $method,
            'pathAndQuery' => $pathAndQuery,
            'receivedProviderCode' => $requestProviderCode,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'rawBody' => $rawBody,
            'receivedSignature' => $signature,
            'signingSecretConfigured' => $signingSecret !== '',
        ];

        if ($signingSecret === '') {
            return $this->reject('provider_config_missing', 500, $debugContext);
        }

        if (!hash_equals($providerCode, $requestProviderCode)) {
            return $this->reject('unauthorized_provider_code', 401, $debugContext);
        }

        if ($signature === '' || $timestamp === '' || $nonce === '') {
            return $this->reject('missing_signature_headers', 401, $debugContext);
        }

        if (!ctype_digit($timestamp)) {
            return $this->reject('timestamp_out_of_range', 401, $debugContext);
        }

        $timestampMs = (int) $timestamp;
        $nowMs = (int) floor(microtime(true) * 1000);
        $driftMs = (int) ($config['signature_drift_ms'] ?? 60000);
        if (abs($nowMs - $timestampMs) > $driftMs) {
            return $this->reject('timestamp_out_of_range', 401, $debugContext + [
                'nowMs' => $nowMs,
                'allowedDriftMs' => $driftMs,
            ]);
        }

        $message = implode('.', [
            $timestamp,
            $nonce,
            $method,
            $pathAndQuery,
            $rawBody,
        ]);

        $expected = base64_encode(hash_hmac('sha256', $message, $signingSecret, true));
        if (!hash_equals($expected, $signature)) {
            return $this->reject('unauthorized_signature', 401, $debugContext + [
                'recomputedSignature' => $expected,
            ]);
        }

        $nonceResult = $this->store->recordNonce($providerCode, $nonce, $timestampMs, 180);
        if (($nonceResult['ok'] ?? false) !== true) {
            return $this->reject($nonceResult['error'] ?? 'nonce_replayed', 401, $debugContext + [
                'recomputedSignature' => $expected,
            ]);
        }

        return ['ok' => true, 'requestHash' => hash('sha256', $rawBody)];
    }

    private function pathAndQuery(Request $request): string
    {
        // The provider signs only the request target, for example:
        // /api/balance/get?roundId=123
        // Normalize defensively in case a proxy ever passes an absolute URI.
        $requestUri = $request->getRequestUri();
        if (preg_match('#^https?://#i', $requestUri) === 1) {
            $parts = parse_url($requestUri);
            $path = (string) ($parts['path'] ?? '/');
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return $path.$query;
        }

        if ($requestUri !== '') {
            return $requestUri;
        }

        $query = $request->getQueryString();

        return '/'.$request->path().($query ? '?'.$query : '');
    }

    private function reject(string $error, int $status, array $context = []): array
    {
        if ((bool) config('services.prime_mac.signature_debug', false)) {
            Log::debug('Prime Mac wallet signature validation failed.', [
                'rejectReason' => $error,
                ...$context,
            ]);
        }

        return ['ok' => false, 'status' => $status, 'error' => $error];
    }
}
