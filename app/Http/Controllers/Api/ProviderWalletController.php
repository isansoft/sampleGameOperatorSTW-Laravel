<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OperatorStore;
use App\Services\ProviderSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/*
|--------------------------------------------------------------------------
| Provider wallet callback controller
|--------------------------------------------------------------------------
|
| The game provider calls these endpoints while a game is running. Keep this
| controller small: validate the provider signature, validate required fields,
| then delegate money movement to stored procedures through OperatorStore.
|
| Required endpoints:
| - POST /api/player/authorize
| - POST /api/balance/get
| - POST /api/bet/place
| - POST /api/bet/settle
| - POST /api/bet/rollback
|
*/
class ProviderWalletController extends Controller
{
    public function __construct(
        private readonly OperatorStore $store,
        private readonly ProviderSignatureValidator $signatureValidator
    ) {
    }

    public function authorizePlayer(Request $request): JsonResponse
    {
        // The provider exchanges a short-lived launchToken for player details.
        // The token is created by this operator before redirecting to the game.
        $signature = $this->signatureValidator->validate($request);
        if (($signature['ok'] ?? false) !== true) {
            return $this->withApiLog($request, null, $signature, $this->error($signature['error'] ?? 'unauthorized_signature', (int) ($signature['status'] ?? 401)));
        }

        $payload = $this->jsonPayload($request);
        if (!$payload || empty($payload['launchToken'])) {
            return $this->withApiLog($request, $payload, $signature, $this->error('malformed_request', 400));
        }

        $result = $this->store->authorizeLaunchToken((string) $payload['launchToken']);
        if (($result['ok'] ?? false) !== true) {
            return $this->withApiLog($request, $payload, $signature, $this->error($result['error'] ?? 'Invalid or expired launch token.', 400), $result);
        }

        unset($result['ok']);
        return $this->withApiLog($request, $payload, $signature, response()->json($result), $result);
    }

    public function balance(Request $request): JsonResponse
    {
        // The provider asks for the latest wallet balance before or during play.
        $signature = $this->signatureValidator->validate($request);
        if (($signature['ok'] ?? false) !== true) {
            return $this->withApiLog($request, null, $signature, $this->error($signature['error'] ?? 'unauthorized_signature', (int) ($signature['status'] ?? 401)));
        }

        $payload = $this->jsonPayload($request);
        if (!$payload || empty($payload['playerId'])) {
            return $this->withApiLog($request, $payload, $signature, $this->error('malformed_request', 400));
        }

        $result = $this->store->balance((string) $payload['playerId']);

        return $this->withApiLog($request, $payload, $signature, $this->storedResult($result), $result);
    }

    public function placeBet(Request $request): JsonResponse
    {
        // Debit the player's wallet. transactionId must be idempotent.
        // Same transactionId + same payload returns the original result.
        // Same transactionId + different payload is rejected.
        $signature = $this->signatureValidator->validate($request);
        if (($signature['ok'] ?? false) !== true) {
            return $this->withApiLog($request, null, $signature, $this->error($signature['error'] ?? 'unauthorized_signature', (int) ($signature['status'] ?? 401)));
        }

        $payload = $this->jsonPayload($request);
        if (!$this->hasWalletFields($payload, ['playerId', 'gameId', 'roundId', 'transactionId', 'amount', 'currencyCode'])) {
            return $this->withApiLog($request, $payload, $signature, $this->error('malformed_request', 400));
        }

        $result = $this->store->placeBet($payload, (string) $signature['requestHash']);

        return $this->withApiLog($request, $payload, $signature, $this->storedResult($result), $result);
    }

    public function settleBet(Request $request): JsonResponse
    {
        // Credit payout/winnings to the player's wallet. Amount is positive.
        $signature = $this->signatureValidator->validate($request);
        if (($signature['ok'] ?? false) !== true) {
            return $this->withApiLog($request, null, $signature, $this->error($signature['error'] ?? 'unauthorized_signature', (int) ($signature['status'] ?? 401)));
        }

        $payload = $this->jsonPayload($request);
        if (!$this->hasWalletFields($payload, ['playerId', 'gameId', 'roundId', 'transactionId', 'amount', 'currencyCode'])) {
            return $this->withApiLog($request, $payload, $signature, $this->error('malformed_request', 400));
        }

        $result = $this->store->settleBet($payload, (string) $signature['requestHash']);

        return $this->withApiLog($request, $payload, $signature, $this->storedResult($result), $result);
    }

    public function rollbackBet(Request $request): JsonResponse
    {
        // Reverse an earlier debit or credit by originalTransactionId.
        // A rollback has its own transactionId and is also idempotent.
        $signature = $this->signatureValidator->validate($request);
        if (($signature['ok'] ?? false) !== true) {
            return $this->withApiLog($request, null, $signature, $this->error($signature['error'] ?? 'unauthorized_signature', (int) ($signature['status'] ?? 401)));
        }

        $payload = $this->jsonPayload($request);
        if (!$this->hasWalletFields($payload, ['playerId', 'gameId', 'roundId', 'transactionId', 'originalTransactionId', 'amount'])) {
            return $this->withApiLog($request, $payload, $signature, $this->error('malformed_request', 400));
        }

        $result = $this->store->rollbackBet($payload, (string) $signature['requestHash']);

        return $this->withApiLog($request, $payload, $signature, $this->storedResult($result), $result);
    }

    private function storedResult(array $result): JsonResponse
    {
        if (($result['ok'] ?? false) !== true) {
            $status = match ($result['error'] ?? '') {
                'insufficient_funds' => 409,
                'duplicate_transaction_payload_mismatch',
                'rollback_already_processed',
                'rollback_amount_mismatch' => 409,
                'player_not_found',
                'transaction_not_found' => 404,
                default => 400,
            };

            return $this->error($result['error'] ?? 'malformed_request', $status);
        }

        unset($result['ok'], $result['idempotent']);
        return response()->json($result);
    }

    private function error(string $error, int $status): JsonResponse
    {
        return response()->json(['error' => $error], $status);
    }

    private function jsonPayload(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : null;
    }

    private function hasWalletFields(?array $payload, array $fields): bool
    {
        if (!$payload) {
            return false;
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                return false;
            }
        }

        return true;
    }

    private function withApiLog(Request $request, ?array $payload, array $signature, JsonResponse $response, ?array $result = null): JsonResponse
    {
        try {
            $this->store->logApiRequest($this->apiLogEntry($request, $payload, $signature, $response, $result));
        } catch (Throwable $exception) {
            Log::warning('Unable to write API monitor log.', [
                'endpointPath' => $this->pathAndQuery($request),
                'error' => $exception->getMessage(),
            ]);
        }

        return $response;
    }

    private function apiLogEntry(Request $request, ?array $payload, array $signature, JsonResponse $response, ?array $result): array
    {
        $errorCode = $result['error'] ?? null;
        if (!$errorCode && ($signature['ok'] ?? false) !== true) {
            $errorCode = $signature['error'] ?? 'unauthorized_signature';
        }
        if (!$errorCode && $response->getStatusCode() >= 400) {
            $errorCode = $this->responseErrorCode($response);
        }

        return [
            'playerPublicId' => $this->playerPublicId($payload, $result),
            'endpointPath' => $this->pathAndQuery($request),
            'httpMethod' => strtoupper($request->getMethod()),
            'responseStatus' => $response->getStatusCode(),
            'requestHash' => $signature['requestHash'] ?? hash('sha256', $request->getContent()),
            'gameId' => $payload['gameId'] ?? $result['gameId'] ?? null,
            'gameCode' => $payload['gameCode'] ?? $result['gameCode'] ?? null,
            'roundId' => $payload['roundId'] ?? $payload['matchId'] ?? null,
            'transactionId' => $payload['transactionId'] ?? $result['transactionId'] ?? null,
            'errorCode' => $errorCode,
            'requestSummary' => $this->requestSummary($payload),
        ];
    }

    private function playerPublicId(?array $payload, ?array $result): ?string
    {
        $playerId = $result['playerId'] ?? $payload['playerId'] ?? null;

        return is_string($playerId) && $playerId !== '' ? $playerId : null;
    }

    private function requestSummary(?array $payload): array
    {
        if (!$payload) {
            return [];
        }

        $summary = [];
        foreach (['playerId', 'gameId', 'gameCode', 'roundId', 'matchId', 'transactionId', 'originalTransactionId', 'amount', 'currencyCode'] as $key) {
            if (array_key_exists($key, $payload)) {
                $summary[$key] = $payload[$key];
            }
        }

        if (array_key_exists('launchToken', $payload)) {
            $summary['launchTokenPresent'] = $payload['launchToken'] !== '';
        }

        return $summary;
    }

    private function responseErrorCode(JsonResponse $response): ?string
    {
        $data = json_decode((string) $response->getContent(), true);

        return is_array($data) && isset($data['error']) ? (string) $data['error'] : null;
    }

    private function pathAndQuery(Request $request): string
    {
        $requestUri = $request->getRequestUri();

        if ($requestUri !== '') {
            return $requestUri;
        }

        $query = $request->getQueryString();

        return '/'.$request->path().($query ? '?'.$query : '');
    }
}
