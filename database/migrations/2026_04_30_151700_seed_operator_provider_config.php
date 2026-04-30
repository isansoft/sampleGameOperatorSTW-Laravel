<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::selectOne(
            'SELECT sp_provider_config_upsert(?, ?, ?, ?::uuid, ?, ?) AS result',
            [
                config('services.prime_mac.provider_code', 'Prime Mac Games'),
                config('services.prime_mac.provider_code', 'Prime Mac Games'),
                config('services.prime_mac.base_url', 'https://api.primemacgames.com'),
                config('services.prime_mac.operator_public_id'),
                config('services.prime_mac.signing_secret'),
                config('services.prime_mac.signature_drift_ms', 60000),
            ]
        );
    }

    public function down(): void
    {
        DB::table('operator_provider_config')->where('id', 1)->delete();
    }
};
