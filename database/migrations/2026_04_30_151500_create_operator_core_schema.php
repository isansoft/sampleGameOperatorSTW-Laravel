<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS operator_provider_config (
    id smallint PRIMARY KEY DEFAULT 1,
    provider_name varchar(120) NOT NULL DEFAULT 'Prime Mac Games',
    provider_code varchar(120) NOT NULL DEFAULT 'Prime Mac Games',
    provider_base_api_url varchar(400) NOT NULL DEFAULT 'https://api.primemacgames.com',
    operator_public_id uuid NOT NULL,
    signing_secret text NOT NULL,
    signature_drift_ms integer NOT NULL DEFAULT 60000,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT operator_provider_config_singleton CHECK (id = 1)
);

CREATE TABLE IF NOT EXISTS operator_players (
    id bigserial PRIMARY KEY,
    player_public_id uuid NOT NULL DEFAULT gen_random_uuid(),
    username varchar(80) NOT NULL,
    display_name varchar(120) NOT NULL,
    password_hash varchar(255) NOT NULL,
    currency_code char(3) NOT NULL DEFAULT 'PHP',
    language_code varchar(12) NOT NULL DEFAULT 'en-PH',
    country_code char(2) NOT NULL DEFAULT 'PH',
    status smallint NOT NULL DEFAULT 1,
    last_login_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ux_operator_players_public_id UNIQUE (player_public_id),
    CONSTRAINT ck_operator_players_status CHECK (status IN (0, 1, 2))
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_operator_players_username
    ON operator_players (lower(username));

CREATE TABLE IF NOT EXISTS player_wallets (
    id bigserial PRIMARY KEY,
    player_id bigint NOT NULL REFERENCES operator_players(id),
    currency_code char(3) NOT NULL DEFAULT 'PHP',
    balance numeric(18,2) NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ux_player_wallets_player UNIQUE (player_id),
    CONSTRAINT ck_player_wallets_balance CHECK (balance >= 0)
);

CREATE TABLE IF NOT EXISTS player_launch_tokens (
    launch_token uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    player_id bigint NOT NULL REFERENCES operator_players(id),
    game_id integer NOT NULL,
    game_code varchar(50) NOT NULL,
    expires_at timestamptz NOT NULL,
    last_authorized_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS ix_player_launch_tokens_player
    ON player_launch_tokens (player_id, created_at DESC);

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id bigserial PRIMARY KEY,
    player_id bigint NOT NULL REFERENCES operator_players(id),
    transaction_id varchar(120) NOT NULL,
    original_transaction_id varchar(120) NULL,
    transaction_type varchar(24) NOT NULL,
    game_id integer NULL,
    round_id varchar(120) NULL,
    amount numeric(18,2) NOT NULL,
    currency_code char(3) NOT NULL DEFAULT 'PHP',
    balance_before numeric(18,2) NOT NULL,
    balance_after numeric(18,2) NOT NULL,
    request_hash varchar(128) NOT NULL,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    player_activity jsonb NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ux_wallet_transactions_transaction_id UNIQUE (transaction_id),
    CONSTRAINT ck_wallet_transactions_type CHECK (transaction_type IN ('debit', 'credit', 'rollback', 'cash_in', 'cash_out')),
    CONSTRAINT ck_wallet_transactions_amount CHECK (amount > 0)
);

CREATE INDEX IF NOT EXISTS ix_wallet_transactions_player_created
    ON wallet_transactions (player_id, created_at DESC);

CREATE INDEX IF NOT EXISTS ix_wallet_transactions_original
    ON wallet_transactions (original_transaction_id);

CREATE TABLE IF NOT EXISTS provider_request_nonces (
    id bigserial PRIMARY KEY,
    provider_code varchar(120) NOT NULL,
    nonce varchar(160) NOT NULL,
    timestamp_ms bigint NOT NULL,
    expires_at timestamptz NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT ux_provider_request_nonces UNIQUE (provider_code, nonce)
);

CREATE INDEX IF NOT EXISTS ix_provider_request_nonces_expires
    ON provider_request_nonces (expires_at);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS provider_request_nonces;
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS player_launch_tokens;
DROP TABLE IF EXISTS player_wallets;
DROP TABLE IF EXISTS operator_players;
DROP TABLE IF EXISTS operator_provider_config;
SQL);
    }
};
