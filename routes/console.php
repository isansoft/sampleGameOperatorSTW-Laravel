<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('operator:about', function (): void {
    $this->info('Game Operator portal for PrimeMacGames.');
})->purpose('Show Game Operator project information');
