<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION sp_provider_config_upsert(
    p_provider_name text,
    p_provider_code text,
    p_provider_base_api_url text,
    p_operator_public_id uuid,
    p_signing_secret text,
    p_signature_drift_ms integer
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_config operator_provider_config%ROWTYPE;
BEGIN
    INSERT INTO operator_provider_config (
        id,
        provider_name,
        provider_code,
        provider_base_api_url,
        operator_public_id,
        signing_secret,
        signature_drift_ms,
        updated_at
    )
    VALUES (
        1,
        p_provider_name,
        p_provider_code,
        rtrim(p_provider_base_api_url, '/'),
        p_operator_public_id,
        p_signing_secret,
        p_signature_drift_ms,
        now()
    )
    ON CONFLICT (id) DO UPDATE SET
        provider_name = EXCLUDED.provider_name,
        provider_code = EXCLUDED.provider_code,
        provider_base_api_url = EXCLUDED.provider_base_api_url,
        operator_public_id = EXCLUDED.operator_public_id,
        signing_secret = EXCLUDED.signing_secret,
        signature_drift_ms = EXCLUDED.signature_drift_ms,
        updated_at = now()
    RETURNING * INTO v_config;

    RETURN to_jsonb(v_config) - 'signing_secret';
END;
$$;

CREATE OR REPLACE FUNCTION sp_provider_config_get()
RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_config operator_provider_config%ROWTYPE;
BEGIN
    SELECT * INTO v_config FROM operator_provider_config WHERE id = 1;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'provider_config_missing');
    END IF;

    RETURN to_jsonb(v_config);
END;
$$;

CREATE OR REPLACE FUNCTION sp_player_register(
    p_username text,
    p_display_name text,
    p_password_hash text,
    p_currency_code text DEFAULT 'PHP',
    p_language_code text DEFAULT 'en-PH',
    p_country_code text DEFAULT 'PH',
    p_initial_balance numeric DEFAULT 1000
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_player operator_players%ROWTYPE;
    v_wallet player_wallets%ROWTYPE;
BEGIN
    IF p_username IS NULL OR length(btrim(p_username)) < 3 THEN
        RETURN jsonb_build_object('ok', false, 'error', 'username_min_length');
    END IF;

    IF EXISTS (SELECT 1 FROM operator_players WHERE lower(username) = lower(btrim(p_username))) THEN
        RETURN jsonb_build_object('ok', false, 'error', 'username_taken');
    END IF;

    INSERT INTO operator_players (
        username,
        display_name,
        password_hash,
        currency_code,
        language_code,
        country_code
    )
    VALUES (
        btrim(p_username),
        COALESCE(NULLIF(btrim(p_display_name), ''), btrim(p_username)),
        p_password_hash,
        upper(COALESCE(NULLIF(btrim(p_currency_code), ''), 'PHP'))::char(3),
        COALESCE(NULLIF(btrim(p_language_code), ''), 'en-PH'),
        upper(COALESCE(NULLIF(btrim(p_country_code), ''), 'PH'))::char(2)
    )
    RETURNING * INTO v_player;

    INSERT INTO player_wallets (player_id, currency_code, balance)
    VALUES (v_player.id, v_player.currency_code, COALESCE(p_initial_balance, 0))
    RETURNING * INTO v_wallet;

    RETURN jsonb_build_object(
        'ok', true,
        'player', jsonb_build_object(
            'id', v_player.id,
            'playerPublicId', v_player.player_public_id,
            'username', v_player.username,
            'displayName', v_player.display_name,
            'currencyCode', v_player.currency_code,
            'languageCode', v_player.language_code,
            'countryCode', v_player.country_code,
            'balance', v_wallet.balance
        )
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_player_login_lookup(p_username text)
RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_player operator_players%ROWTYPE;
    v_wallet player_wallets%ROWTYPE;
BEGIN
    SELECT * INTO v_player
    FROM operator_players
    WHERE lower(username) = lower(btrim(p_username))
    LIMIT 1;

    IF NOT FOUND OR v_player.status <> 1 THEN
        RETURN jsonb_build_object('ok', false, 'error', 'invalid_credentials');
    END IF;

    SELECT * INTO v_wallet FROM player_wallets WHERE player_id = v_player.id;

    RETURN jsonb_build_object(
        'ok', true,
        'player', jsonb_build_object(
            'id', v_player.id,
            'playerPublicId', v_player.player_public_id,
            'username', v_player.username,
            'displayName', v_player.display_name,
            'passwordHash', v_player.password_hash,
            'currencyCode', v_player.currency_code,
            'languageCode', v_player.language_code,
            'countryCode', v_player.country_code,
            'balance', v_wallet.balance
        )
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_player_mark_login(p_player_id bigint)
RETURNS jsonb
LANGUAGE plpgsql
AS $$
BEGIN
    UPDATE operator_players
    SET last_login_at = now(), updated_at = now()
    WHERE id = p_player_id;

    RETURN jsonb_build_object('ok', true);
END;
$$;

CREATE OR REPLACE FUNCTION sp_player_get(p_player_id bigint)
RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_player operator_players%ROWTYPE;
    v_wallet player_wallets%ROWTYPE;
BEGIN
    SELECT * INTO v_player FROM operator_players WHERE id = p_player_id;
    IF NOT FOUND OR v_player.status <> 1 THEN
        RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
    END IF;

    SELECT * INTO v_wallet FROM player_wallets WHERE player_id = v_player.id;

    RETURN jsonb_build_object(
        'ok', true,
        'player', jsonb_build_object(
            'id', v_player.id,
            'playerId', v_player.player_public_id::text,
            'playerPublicId', v_player.player_public_id::text,
            'internalPlayerId', v_player.id,
            'username', v_player.username,
            'displayName', v_player.display_name,
            'currencyCode', v_player.currency_code,
            'languageCode', v_player.language_code,
            'countryCode', v_player.country_code,
            'balance', v_wallet.balance
        )
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_launch_token_create(
    p_player_id bigint,
    p_game_id integer,
    p_game_code text,
    p_ttl_minutes integer DEFAULT 5
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_token player_launch_tokens%ROWTYPE;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM operator_players WHERE id = p_player_id AND status = 1) THEN
        RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
    END IF;

    INSERT INTO player_launch_tokens (player_id, game_id, game_code, expires_at)
    VALUES (p_player_id, p_game_id, upper(btrim(p_game_code)), now() + make_interval(mins => COALESCE(p_ttl_minutes, 5)))
    RETURNING * INTO v_token;

    RETURN jsonb_build_object(
        'ok', true,
        'launchToken', v_token.launch_token::text,
        'gameId', v_token.game_id,
        'gameCode', v_token.game_code,
        'expiresAt', v_token.expires_at
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_launch_token_authorize(p_launch_token uuid)
RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_token player_launch_tokens%ROWTYPE;
    v_player operator_players%ROWTYPE;
    v_wallet player_wallets%ROWTYPE;
    v_config operator_provider_config%ROWTYPE;
BEGIN
    SELECT * INTO v_token
    FROM player_launch_tokens
    WHERE launch_token = p_launch_token
    LIMIT 1;

    IF NOT FOUND OR v_token.expires_at < now() THEN
        RETURN jsonb_build_object('ok', false, 'error', 'invalid_or_expired_launch_token');
    END IF;

    SELECT * INTO v_player FROM operator_players WHERE id = v_token.player_id AND status = 1;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
    END IF;

    SELECT * INTO v_wallet FROM player_wallets WHERE player_id = v_player.id;
    SELECT * INTO v_config FROM operator_provider_config WHERE id = 1;

    UPDATE player_launch_tokens
    SET last_authorized_at = now()
    WHERE launch_token = p_launch_token;

    RETURN jsonb_build_object(
        'ok', true,
        'playerId', v_player.player_public_id::text,
        'internalPlayerId', v_player.id,
        'operatorPublicId', v_config.operator_public_id::text,
        'displayName', v_player.display_name,
        'gameId', v_token.game_id,
        'currencyCode', v_player.currency_code,
        'languageCode', v_player.language_code,
        'countryCode', v_player.country_code,
        'balance', v_wallet.balance
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_balance_get(p_player_public_id text)
RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_player operator_players%ROWTYPE;
    v_wallet player_wallets%ROWTYPE;
BEGIN
    SELECT * INTO v_player
    FROM operator_players
    WHERE player_public_id = p_player_public_id::uuid AND status = 1;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
    END IF;

    SELECT * INTO v_wallet FROM player_wallets WHERE player_id = v_player.id;

    RETURN jsonb_build_object(
        'ok', true,
        'playerId', v_player.player_public_id::text,
        'internalPlayerId', v_player.id,
        'currencyCode', v_wallet.currency_code,
        'balance', v_wallet.balance
    );
EXCEPTION WHEN invalid_text_representation THEN
    RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
END;
$$;

CREATE OR REPLACE FUNCTION sp_provider_nonce_record(
    p_provider_code text,
    p_nonce text,
    p_timestamp_ms bigint,
    p_expires_at timestamptz
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
BEGIN
    DELETE FROM provider_request_nonces WHERE expires_at < now();

    INSERT INTO provider_request_nonces (provider_code, nonce, timestamp_ms, expires_at)
    VALUES (p_provider_code, p_nonce, p_timestamp_ms, p_expires_at);

    RETURN jsonb_build_object('ok', true);
EXCEPTION WHEN unique_violation THEN
    RETURN jsonb_build_object('ok', false, 'error', 'nonce_replayed');
END;
$$;

CREATE OR REPLACE FUNCTION sp_wallet_apply(
    p_player_public_id text,
    p_game_id integer,
    p_round_id text,
    p_transaction_id text,
    p_amount numeric,
    p_currency_code text,
    p_direction text,
    p_transaction_type text,
    p_original_transaction_id text,
    p_payload jsonb,
    p_player_activity jsonb,
    p_request_hash text
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_player operator_players%ROWTYPE;
    v_wallet player_wallets%ROWTYPE;
    v_existing wallet_transactions%ROWTYPE;
    v_balance_before numeric(18,2);
    v_balance_after numeric(18,2);
BEGIN
    IF p_amount IS NULL OR p_amount <= 0 THEN
        RETURN jsonb_build_object('ok', false, 'error', 'invalid_amount');
    END IF;

    SELECT * INTO v_existing
    FROM wallet_transactions
    WHERE transaction_id = p_transaction_id;

    IF FOUND THEN
        IF v_existing.request_hash <> p_request_hash THEN
            RETURN jsonb_build_object('ok', false, 'error', 'duplicate_transaction_payload_mismatch');
        END IF;

        SELECT * INTO v_player FROM operator_players WHERE id = v_existing.player_id;

        RETURN jsonb_build_object(
            'ok', true,
            'idempotent', true,
            'playerId', v_player.player_public_id::text,
            'internalPlayerId', v_player.id,
            'currencyCode', v_existing.currency_code,
            'balance', v_existing.balance_after,
            'transactionId', v_existing.transaction_id
        );
    END IF;

    SELECT * INTO v_player
    FROM operator_players
    WHERE player_public_id = p_player_public_id::uuid AND status = 1;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
    END IF;

    SELECT * INTO v_wallet
    FROM player_wallets
    WHERE player_id = v_player.id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'wallet_not_found');
    END IF;

    IF upper(v_wallet.currency_code) <> upper(COALESCE(p_currency_code, v_wallet.currency_code)) THEN
        RETURN jsonb_build_object('ok', false, 'error', 'currency_mismatch');
    END IF;

    v_balance_before := v_wallet.balance;

    IF p_direction = 'debit' THEN
        IF v_wallet.balance < p_amount THEN
            RETURN jsonb_build_object('ok', false, 'error', 'insufficient_funds');
        END IF;
        v_balance_after := v_wallet.balance - p_amount;
    ELSIF p_direction = 'credit' THEN
        v_balance_after := v_wallet.balance + p_amount;
    ELSE
        RETURN jsonb_build_object('ok', false, 'error', 'invalid_wallet_direction');
    END IF;

    UPDATE player_wallets
    SET balance = v_balance_after, updated_at = now()
    WHERE id = v_wallet.id;

    INSERT INTO wallet_transactions (
        player_id,
        transaction_id,
        original_transaction_id,
        transaction_type,
        game_id,
        round_id,
        amount,
        currency_code,
        balance_before,
        balance_after,
        request_hash,
        payload,
        player_activity
    )
    VALUES (
        v_player.id,
        p_transaction_id,
        p_original_transaction_id,
        p_transaction_type,
        p_game_id,
        p_round_id,
        p_amount,
        v_wallet.currency_code,
        v_balance_before,
        v_balance_after,
        p_request_hash,
        COALESCE(p_payload, '{}'::jsonb),
        p_player_activity
    );

    RETURN jsonb_build_object(
        'ok', true,
        'playerId', v_player.player_public_id::text,
        'internalPlayerId', v_player.id,
        'currencyCode', v_wallet.currency_code,
        'balance', v_balance_after,
        'transactionId', p_transaction_id
    );
EXCEPTION WHEN invalid_text_representation THEN
    RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
END;
$$;

CREATE OR REPLACE FUNCTION sp_wallet_bet_place(
    p_player_public_id text,
    p_game_id integer,
    p_round_id text,
    p_transaction_id text,
    p_amount numeric,
    p_currency_code text,
    p_payload jsonb,
    p_request_hash text
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN sp_wallet_apply(
        p_player_public_id,
        p_game_id,
        p_round_id,
        p_transaction_id,
        p_amount,
        p_currency_code,
        'debit',
        'debit',
        NULL,
        p_payload,
        NULL,
        p_request_hash
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_wallet_bet_settle(
    p_player_public_id text,
    p_game_id integer,
    p_round_id text,
    p_transaction_id text,
    p_amount numeric,
    p_currency_code text,
    p_payload jsonb,
    p_player_activity jsonb,
    p_request_hash text
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN sp_wallet_apply(
        p_player_public_id,
        p_game_id,
        p_round_id,
        p_transaction_id,
        p_amount,
        p_currency_code,
        'credit',
        'credit',
        NULL,
        p_payload,
        p_player_activity,
        p_request_hash
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_wallet_bet_rollback(
    p_player_public_id text,
    p_game_id integer,
    p_round_id text,
    p_transaction_id text,
    p_original_transaction_id text,
    p_amount numeric,
    p_payload jsonb,
    p_request_hash text
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_original wallet_transactions%ROWTYPE;
    v_rollback wallet_transactions%ROWTYPE;
    v_direction text;
BEGIN
    SELECT * INTO v_rollback
    FROM wallet_transactions
    WHERE original_transaction_id = p_original_transaction_id
      AND transaction_type = 'rollback';

    IF FOUND AND v_rollback.transaction_id <> p_transaction_id THEN
        RETURN jsonb_build_object('ok', false, 'error', 'rollback_already_processed');
    END IF;

    SELECT * INTO v_original
    FROM wallet_transactions
    WHERE transaction_id = p_original_transaction_id
      AND transaction_type IN ('debit', 'credit');

    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'transaction_not_found');
    END IF;

    IF v_original.amount <> p_amount THEN
        RETURN jsonb_build_object('ok', false, 'error', 'rollback_amount_mismatch');
    END IF;

    v_direction := CASE WHEN v_original.transaction_type = 'debit' THEN 'credit' ELSE 'debit' END;

    RETURN sp_wallet_apply(
        p_player_public_id,
        COALESCE(p_game_id, v_original.game_id),
        COALESCE(p_round_id, v_original.round_id),
        p_transaction_id,
        p_amount,
        v_original.currency_code,
        v_direction,
        'rollback',
        p_original_transaction_id,
        p_payload,
        NULL,
        p_request_hash
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_wallet_adjust(
    p_player_id bigint,
    p_transaction_id text,
    p_direction text,
    p_amount numeric,
    p_note text
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    v_player operator_players%ROWTYPE;
BEGIN
    SELECT * INTO v_player FROM operator_players WHERE id = p_player_id AND status = 1;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('ok', false, 'error', 'player_not_found');
    END IF;

    RETURN sp_wallet_apply(
        v_player.player_public_id::text,
        NULL,
        NULL,
        p_transaction_id,
        p_amount,
        v_player.currency_code,
        p_direction,
        CASE WHEN p_direction = 'credit' THEN 'cash_in' ELSE 'cash_out' END,
        NULL,
        jsonb_build_object('note', p_note),
        NULL,
        encode(digest(p_transaction_id || ':' || p_direction || ':' || p_amount::text, 'sha256'), 'hex')
    );
END;
$$;

CREATE OR REPLACE FUNCTION sp_wallet_recent_transactions(
    p_player_id bigint,
    p_limit integer DEFAULT 20
) RETURNS jsonb
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN COALESCE((
        SELECT jsonb_agg(to_jsonb(t) ORDER BY t."createdAt" DESC)
        FROM (
            SELECT
                transaction_id AS "transactionId",
                original_transaction_id AS "originalTransactionId",
                transaction_type AS "transactionType",
                game_id AS "gameId",
                round_id AS "roundId",
                amount,
                currency_code AS "currencyCode",
                balance_before AS "balanceBefore",
                balance_after AS "balanceAfter",
                created_at AS "createdAt"
            FROM wallet_transactions
            WHERE player_id = p_player_id
            ORDER BY created_at DESC
            LIMIT LEAST(GREATEST(COALESCE(p_limit, 20), 1), 100)
        ) t
    ), '[]'::jsonb);
END;
$$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP FUNCTION IF EXISTS sp_wallet_recent_transactions(bigint, integer);
DROP FUNCTION IF EXISTS sp_wallet_adjust(bigint, text, text, numeric, text);
DROP FUNCTION IF EXISTS sp_wallet_bet_rollback(text, integer, text, text, text, numeric, jsonb, text);
DROP FUNCTION IF EXISTS sp_wallet_bet_settle(text, integer, text, text, numeric, text, jsonb, jsonb, text);
DROP FUNCTION IF EXISTS sp_wallet_bet_place(text, integer, text, text, numeric, text, jsonb, text);
DROP FUNCTION IF EXISTS sp_wallet_apply(text, integer, text, text, numeric, text, text, text, text, jsonb, jsonb, text);
DROP FUNCTION IF EXISTS sp_provider_nonce_record(text, text, bigint, timestamptz);
DROP FUNCTION IF EXISTS sp_balance_get(text);
DROP FUNCTION IF EXISTS sp_launch_token_authorize(uuid);
DROP FUNCTION IF EXISTS sp_launch_token_create(bigint, integer, text, integer);
DROP FUNCTION IF EXISTS sp_player_get(bigint);
DROP FUNCTION IF EXISTS sp_player_mark_login(bigint);
DROP FUNCTION IF EXISTS sp_player_login_lookup(text);
DROP FUNCTION IF EXISTS sp_player_register(text, text, text, text, text, text, numeric);
DROP FUNCTION IF EXISTS sp_provider_config_get();
DROP FUNCTION IF EXISTS sp_provider_config_upsert(text, text, text, uuid, text, integer);
SQL);
    }
};
