<?php

namespace App\Providers;

use App\Services\ProviderCodeSynchronizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            app(ProviderCodeSynchronizer::class)->syncIfNeeded();
        } catch (Throwable $exception) {
            Log::warning('Prime Mac Games provider code auto-sync was skipped.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
