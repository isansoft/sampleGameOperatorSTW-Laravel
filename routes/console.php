<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('operator:about', function (): void {
    $this->info('Game Operator portal for PrimeMacGames.');
})->purpose('Show Game Operator project information');

Artisan::command('prime-mac:sync-provider-code {--force : Fetch even when .env says the code was already synced} {--no-env : Update only the database, not the .env file}', function (): int {
    $result = app(\App\Services\ProviderCodeSynchronizer::class)->sync(
        force: (bool) $this->option('force'),
        writeEnv: !(bool) $this->option('no-env')
    );

    if (($result['ok'] ?? false) !== true) {
        $this->error('Provider code sync failed: '.($result['error'] ?? 'unknown_error'));

        return 1;
    }

    if (($result['skipped'] ?? false) === true) {
        $this->info('Provider code sync skipped: '.$result['reason']);

        return 0;
    }

    $this->info('Provider code synced.');
    $this->line('Operator Public ID: '.$result['operatorPublicId']);
    $this->line('Operator Name: '.($result['operatorName'] ?? ''));
    $this->line('Provider Code: '.$result['providerCode']);
    $this->line('Updated .env: '.(($result['envUpdated'] ?? false) ? 'yes' : 'no'));

    return 0;
})->purpose('Fetch providerCode from the provider profile endpoint and save it locally');
