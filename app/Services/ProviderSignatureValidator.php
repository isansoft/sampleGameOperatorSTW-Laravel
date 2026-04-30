<?php

namespace App\Services;

use Illuminate\Http\Request;

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
            return ['ok' => false, 'status' => 500, 'error' => 'provider_config_missing'];
        }

        $providerCode = (string) ($config['provider_code'] ?? config('services.prime_mac.provider_code'));
        $requestProviderCode = (string) $request->header('X-Provider-Code', '');
        if (!hash_equals($providerCode, $requestProviderCode)) {
            return ['ok' => false, 'status' => 401, 'error' => 'unauthorized_provider_code'];
        }

        $signature = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');

        if ($signature === '' || $timestamp === '' || $nonce === '') {
            return ['ok' => false, 'status' => 401, 'error' => 'missing_signature_headers'];
        }

        if (!ctype_digit($timestamp)) {
            return ['ok' => false, 'status' => 401, 'error' => 'timestamp_out_of_range'];
        }

        $timestampMs = (int) $timestamp;
        $nowMs = (int) floor(microtime(true) * 1000);
        $driftMs = (int) ($config['signature_drift_ms'] ?? 60000);
        if (abs($nowMs - $timestampMs) > $driftMs) {
            return ['ok' => false, 'status' => 401, 'error' => 'timestamp_out_of_range'];
        }

        $nonceResult = $this->store->recordNonce($providerCode, $nonce, $timestampMs, 180);
        if (($nonceResult['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => 401, 'error' => $nonceResult['error'] ?? 'nonce_replayed'];
        }

        $rawBody = $request->getContent();
        // getRequestUri() includes the path and query string, which must match
        // exactly what the provider used when creating X-Signature.
        $message = implode('.', [
            $timestamp,
            $nonce,
            strtoupper($request->method()),
            $request->getRequestUri(),
            $rawBody,
        ]);

        $expected = base64_encode(hash_hmac('sha256', $message, (string) $config['signing_secret'], true));
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'status' => 401, 'error' => 'unauthorized_signature'];
        }

        return ['ok' => true, 'requestHash' => hash('sha256', $rawBody)];
    }
}
