<?php

use App\Http\Controllers\Api\ProviderWalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Required Prime Mac Games wallet endpoints
|--------------------------------------------------------------------------
|
| These five POST endpoints are called by the game provider, not by the
| player's browser. Every request must be signed with X-Signature,
| X-Timestamp, X-Nonce, and X-Provider-Code headers.
|
*/
Route::post('/player/authorize', [ProviderWalletController::class, 'authorizePlayer']);
Route::post('/balance/get', [ProviderWalletController::class, 'balance']);
Route::post('/bet/place', [ProviderWalletController::class, 'placeBet']);
Route::post('/bet/settle', [ProviderWalletController::class, 'settleBet']);
Route::post('/bet/rollback', [ProviderWalletController::class, 'rollbackBet']);
