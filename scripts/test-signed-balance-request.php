#!/usr/bin/env php
<?php

$root = dirname(__DIR__);
$envPath = $root.'/.env';
$env = is_file($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];
$options = getopt('', [
    'base-url::',
    'path::',
    'player-id::',
    'provider-code::',
]);

$baseUrl = rtrim((string) ($options['base-url'] ?? $env['APP_URL'] ?? 'https://stwlaravel.primemacgames.com'), '/');
$pathAndQuery = (string) ($options['path'] ?? '/api/balance/get');
$playerId = (string) ($options['player-id'] ?? '16c530e7-f5d2-4ca3-bb08-3a275197e5af');
$providerCode = (string) ($options['provider-code'] ?? $env['PRIME_MAC_PROVIDER_CODE'] ?? 'Prime Mac Games');
$signingSecret = (string) ($env['PRIME_MAC_SIGNING_SECRET'] ?? '');

if ($signingSecret === '') {
    fwrite(STDERR, "PRIME_MAC_SIGNING_SECRET is not configured.\n");
    exit(1);
}

if (!str_starts_with($pathAndQuery, '/')) {
    fwrite(STDERR, "--path must start with '/'.\n");
    exit(1);
}

$rawBody = '{"playerId":"'.$playerId.'"}';
$timestamp = (string) floor(microtime(true) * 1000);
$nonce = bin2hex(random_bytes(16));
$method = 'POST';
$signingMessage = $timestamp.'.'.$nonce.'.'.$method.'.'.$pathAndQuery.'.'.$rawBody;
$signature = base64_encode(hash_hmac('sha256', $signingMessage, $signingSecret, true));
$url = $baseUrl.$pathAndQuery;

$context = stream_context_create([
    'http' => [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 20,
        'header' => implode("\r\n", [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Timestamp: '.$timestamp,
            'X-Nonce: '.$nonce,
            'X-Provider-Code: '.$providerCode,
            'X-Signature: '.$signature,
        ]),
        'content' => $rawBody,
    ],
]);

$responseBody = file_get_contents($url, false, $context);
$responseHeaders = $http_response_header ?? [];
$status = 0;
foreach ($responseHeaders as $header) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
        $status = (int) $matches[1];
    }
}

$responseText = $responseBody === false ? '' : $responseBody;
$failedSignature = $status === 401 && str_contains($responseText, 'unauthorized_signature');

echo "Signed balance request test\n";
echo "Endpoint: {$url}\n";
echo "Method: {$method}\n";
echo "Path used for signing: {$pathAndQuery}\n";
echo "Provider code: {$providerCode}\n";
echo "Signing secret: configured, last4=".substr($signingSecret, -4).", length=".strlen($signingSecret)."\n";
echo "Raw body: {$rawBody}\n";
echo "HTTP status: {$status}\n";
echo "Response: {$responseText}\n";
echo 'Signature validation: '.($failedSignature ? 'FAILED' : 'PASSED')."\n";

exit($failedSignature ? 1 : 0);
