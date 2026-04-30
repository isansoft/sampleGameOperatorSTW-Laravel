<?php

use App\Http\Controllers\OperatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OperatorController::class, 'login'])->name('operator.login');
Route::post('/register', [OperatorController::class, 'register'])->name('operator.register.submit');
Route::post('/login', [OperatorController::class, 'authenticate'])->name('operator.login.submit');
Route::post('/logout', [OperatorController::class, 'logout'])->name('operator.logout');
Route::get('/operator', [OperatorController::class, 'dashboard'])->name('operator.dashboard');
Route::post('/operator/wallet/cash-in', [OperatorController::class, 'cashIn'])->name('operator.wallet.cash-in');
Route::post('/operator/wallet/cash-out', [OperatorController::class, 'cashOut'])->name('operator.wallet.cash-out');
Route::post('/operator/games/{gameId}/launch', [OperatorController::class, 'launchGame'])->name('operator.games.launch');
Route::redirect('/dashboard', '/operator');
