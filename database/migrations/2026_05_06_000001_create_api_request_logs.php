<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_request_logs (
    id bigserial PRIMARY KEY,
    player_public_id varchar(80),
    endpoint_path varchar(160) NOT NULL,
    http_method varchar(12) NOT NULL,
    response_status integer NOT NULL,
    request_hash varchar(64),
    game_id integer,
    game_code varchar(40),
    round_id varchar(160),
    transaction_id varchar(180),
    error_code varchar(160),
    request_summary jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS ix_api_request_logs_player_id
    ON api_request_logs (player_public_id, id DESC);

CREATE INDEX IF NOT EXISTS ix_api_request_logs_created_at
    ON api_request_logs (created_at DESC);

CREATE OR REPLACE FUNCTION sp_api_request_log_create(
    p_player_public_id text,
    p_endpoint_path text,
    p_http_method text,
    p_response_status integer,
    p_request_hash text,
    p_game_id integer,
    p_game_code text,
    p_round_id text,
    p_transaction_id text,
    p_error_code text,
    p_request_summary jsonb
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_log api_request_logs%ROWTYPE;
BEGIN
    INSERT INTO api_request_logs (
        player_public_id,
        endpoint_path,
        http_method,
        response_status,
        request_hash,
        game_id,
        game_code,
        round_id,
        transaction_id,
        error_code,
        request_summary
    )
    VALUES (
        NULLIF(btrim(p_player_public_id), ''),
        btrim(p_endpoint_path),
        upper(btrim(p_http_method)),
        p_response_status,
        NULLIF(btrim(p_request_hash), ''),
        p_game_id,
        NULLIF(upper(btrim(p_game_code)), ''),
        NULLIF(btrim(p_round_id), ''),
        NULLIF(btrim(p_transaction_id), ''),
        NULLIF(btrim(p_error_code), ''),
        COALESCE(p_request_summary, '{}'::jsonb)
    )
    RETURNING * INTO v_log;

    RETURN jsonb_build_object(
        'id', v_log.id,
        'playerPublicId', v_log.player_public_id,
        'endpointPath', v_log.endpoint_path,
        'httpMethod', v_log.http_method,
        'responseStatus', v_log.response_status,
        'requestHash', v_log.request_hash,
        'requestHashShort', CASE
            WHEN v_log.request_hash IS NULL THEN NULL
            ELSE substr(v_log.request_hash, 1, 12)
        END,
        'gameId', v_log.game_id,
        'gameCode', v_log.game_code,
        'roundId', v_log.round_id,
        'transactionId', v_log.transaction_id,
        'errorCode', v_log.error_code,
        'requestSummary', v_log.request_summary,
        'createdAt', to_char(v_log.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.MS"Z"')
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_api_request_logs_for_player(
    p_player_public_id text,
    p_after_id bigint DEFAULT 0,
    p_limit integer DEFAULT 40
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_limit integer := least(greatest(COALESCE(p_limit, 40), 1), 100);
BEGIN
    IF COALESCE(p_after_id, 0) <= 0 THEN
        RETURN COALESCE((
            SELECT jsonb_agg(row_payload ORDER BY (row_payload->>'id')::bigint ASC)
            FROM (
                SELECT jsonb_build_object(
                    'id', id,
                    'playerPublicId', player_public_id,
                    'endpointPath', endpoint_path,
                    'httpMethod', http_method,
                    'responseStatus', response_status,
                    'requestHash', request_hash,
                    'requestHashShort', CASE
                        WHEN request_hash IS NULL THEN NULL
                        ELSE substr(request_hash, 1, 12)
                    END,
                    'gameId', game_id,
                    'gameCode', game_code,
                    'roundId', round_id,
                    'transactionId', transaction_id,
                    'errorCode', error_code,
                    'requestSummary', request_summary,
                    'createdAt', to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.MS"Z"')
                ) AS row_payload
                FROM api_request_logs
                WHERE player_public_id = p_player_public_id
                ORDER BY id DESC
                LIMIT v_limit
            ) recent
        ), '[]'::jsonb);
    END IF;

    RETURN COALESCE((
        SELECT jsonb_agg(row_payload ORDER BY (row_payload->>'id')::bigint ASC)
        FROM (
            SELECT jsonb_build_object(
                'id', id,
                'playerPublicId', player_public_id,
                'endpointPath', endpoint_path,
                'httpMethod', http_method,
                'responseStatus', response_status,
                'requestHash', request_hash,
                'requestHashShort', CASE
                    WHEN request_hash IS NULL THEN NULL
                    ELSE substr(request_hash, 1, 12)
                END,
                'gameId', game_id,
                'gameCode', game_code,
                'roundId', round_id,
                'transactionId', transaction_id,
                'errorCode', error_code,
                'requestSummary', request_summary,
                'createdAt', to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.MS"Z"')
            ) AS row_payload
            FROM api_request_logs
            WHERE player_public_id = p_player_public_id
                AND id > COALESCE(p_after_id, 0)
            ORDER BY id ASC
            LIMIT v_limit
        ) recent
    ), '[]'::jsonb);
END;
$$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP FUNCTION IF EXISTS sp_api_request_logs_for_player(text, bigint, integer);
DROP FUNCTION IF EXISTS sp_api_request_log_create(text, text, text, integer, text, integer, text, text, text, text, jsonb);
DROP TABLE IF EXISTS api_request_logs;
SQL);
    }
};
