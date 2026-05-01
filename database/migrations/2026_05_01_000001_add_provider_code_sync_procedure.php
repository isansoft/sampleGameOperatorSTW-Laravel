<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION sp_provider_code_sync(
    p_provider_name text,
    p_provider_code text
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_config operator_provider_config%ROWTYPE;
BEGIN
    IF p_provider_code IS NULL OR btrim(p_provider_code) = '' THEN
        RETURN jsonb_build_object('ok', false, 'error', 'provider_code_missing');
    END IF;

    UPDATE operator_provider_config
    SET
        provider_name = COALESCE(NULLIF(btrim(p_provider_name), ''), provider_name),
        provider_code = btrim(p_provider_code),
        updated_at = now()
    WHERE id = 1
    RETURNING * INTO v_config;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'provider_config_missing');
    END IF;

    RETURN (to_jsonb(v_config) - 'signing_secret') || jsonb_build_object('ok', true);
END;
$$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP FUNCTION IF EXISTS sp_provider_code_sync(text, text);
SQL);
    }
};
