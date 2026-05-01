# Sample Game Operator STW - Laravel

This project is a sample Laravel game operator integration for Prime Mac Games.
It is designed to be easy for other game operators to read, copy, and adapt into
their own systems.

The sample includes:

- A player-facing login and register page.
- A simple operator dashboard with the provider `PrimeMacGames`.
- A POKER game launch flow.
- PostgreSQL tables for players, wallets, launch tokens, and wallet transactions.
- PostgreSQL stored procedures for wallet-safe operations.
- The required server-to-server wallet API endpoints used by the game provider.
- Signature validation for provider callback requests.

Hosted sample domain:

```text
https://stwlaravel.primemacgames.com
```

Provider API base URL:

```text
https://api.primemacgames.com
```

## Quick Start

Use this section when you want to run the sample operator quickly on a new
developer machine.

### 1. Clone the project

```bash
git clone https://github.com/isansoft/sampleGameOperatorSTW-Laravel.git
cd sampleGameOperatorSTW-Laravel
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Create an operator account in the provider portal

Before configuring this Laravel sample, the game operator should create an
account in the Prime Mac Games provider portal:

```text
https://portal.primemacgames.com
```

Inside the Prime Mac Games portal dashboard, generate or copy:

- `operatorPublicId`
- signing secret

The `operatorPublicId` identifies the operator account. The signing secret is
used to validate signed wallet API calls from the game provider.

Keep the signing secret private. Do not commit it to GitHub, share it in chat,
or place it in sample docs.

### 4. Create the local environment file

```bash
cp .env.example .env
php artisan key:generate
```

Update these `.env` values:

```text
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=game_operator
DB_USERNAME=game_operator_user
DB_PASSWORD=your-local-password

PRIME_MAC_PROVIDER_BASE_URL=https://api.primemacgames.com
PRIME_MAC_PROVIDER_CODE_AUTO_SYNC=true
PRIME_MAC_PROVIDER_CODE_SYNCED=false
PRIME_MAC_OPERATOR_PUBLIC_ID=operator-public-id-from-provider-portal
PRIME_MAC_SIGNING_SECRET=signing-secret-from-provider-portal
```

`PRIME_MAC_PROVIDER_CODE` does not need to be guessed. The sample can fetch it
from the provider profile endpoint after `PRIME_MAC_OPERATOR_PUBLIC_ID` is set.

### 5. Create the PostgreSQL database

Example using `psql`:

```sql
CREATE DATABASE game_operator;
CREATE USER game_operator_user WITH PASSWORD 'your-local-password';
GRANT ALL PRIVILEGES ON DATABASE game_operator TO game_operator_user;
```

Depending on your PostgreSQL version, you may also need:

```sql
\c game_operator
GRANT ALL ON SCHEMA public TO game_operator_user;
```

### 6. Run migrations

```bash
php artisan migrate
```

This creates the player, wallet, launch token, provider config, nonce, and
wallet transaction tables. It also creates the stored procedures used by the
wallet APIs.

### 7. Sync providerCode

```bash
php artisan prime-mac:sync-provider-code --force
```

This calls:

```text
GET https://api.primemacgames.com/api/portal/operators/{operatorPublicId}
```

Then it saves `providerCode` to `.env` and `operator_provider_config`.

### 8. Start the Laravel server

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

Click `REGISTER` to create a test player. The sample gives new players a demo
wallet balance so they can launch POKER and test the required wallet APIs.

### 9. Confirm the required API routes

```bash
php artisan route:list --except-vendor
```

You should see:

```text
POST api/player/authorize
POST api/balance/get
POST api/bet/place
POST api/bet/settle
POST api/bet/rollback
```

### 10. Test with Postman

Set these Postman environment values:

```text
operator_wallet_base=http://127.0.0.1:8000
providerCode=value-from-provider-profile
signingSecret=your-provider-signing-secret
gameId=1
```

Create a player from the web UI first, launch the game once to generate a
`launchToken`, then use the signed wallet API requests.

To get test values for Postman:

```sql
SELECT
    p.player_public_id AS "playerId",
    t.launch_token AS "launchToken",
    t.game_id AS "gameId",
    t.game_code AS "gameCode",
    t.expires_at AS "expiresAt"
FROM player_launch_tokens t
JOIN operator_players p ON p.id = t.player_id
ORDER BY t.created_at DESC
LIMIT 1;
```

## How The Integration Works

There are two directions of communication.

First, the operator calls the provider API to get available games and launch URL
configuration.

Second, the provider calls the operator API while a player is in a game. These
provider-to-operator calls authorize the player, get wallet balance, place bets,
settle bets, and roll back failed or cancelled transactions.

```text
Player Browser
    |
    | login / register / click game
    v
Laravel Operator
    |
    | GET launch config
    v
Prime Mac Games Provider API
    |
    | redirect player to game URL
    v
Game Client

During play:

Prime Mac Games Provider
    |
    | signed POST wallet callbacks
    v
Laravel Operator API
    |
    | stored procedures
    v
PostgreSQL wallet database
```

## Important URLs

### Player Pages

```text
GET  https://stwlaravel.primemacgames.com/
GET  https://stwlaravel.primemacgames.com/operator
POST https://stwlaravel.primemacgames.com/register
POST https://stwlaravel.primemacgames.com/login
POST https://stwlaravel.primemacgames.com/logout
POST https://stwlaravel.primemacgames.com/operator/games/{gameId}/launch
```

### Required Provider Wallet API Endpoints

These five endpoints are the most important part of the sample. They are called
by the game provider, not directly by the player's browser.

```text
POST https://stwlaravel.primemacgames.com/api/player/authorize
POST https://stwlaravel.primemacgames.com/api/balance/get
POST https://stwlaravel.primemacgames.com/api/bet/place
POST https://stwlaravel.primemacgames.com/api/bet/settle
POST https://stwlaravel.primemacgames.com/api/bet/rollback
```

Routes are defined in:

```text
routes/api.php
```

The controller is:

```text
app/Http/Controllers/Api/ProviderWalletController.php
```

## Provider Configuration

The operator public ID is created by the provider. The operator system must not
generate it locally.

Each game operator should first create an account in the Prime Mac Games
provider portal:

```text
https://portal.primemacgames.com
```

From the provider portal dashboard, the operator can generate or copy the
`operatorPublicId` and signing secret. These values are then placed in the
operator system `.env` file.

Current sample operator public ID:

```text
3a6de854-339c-4668-ab69-cad5e168a231
```

Provider code:

```text
Prime Mac Games
```

The values are configured through `.env` and seeded into the database table
`operator_provider_config`.

Required `.env` values:

```text
PRIME_MAC_PROVIDER_BASE_URL=https://api.primemacgames.com
PRIME_MAC_PROVIDER_CODE="Prime Mac Games"
PRIME_MAC_PROVIDER_CODE_AUTO_SYNC=true
PRIME_MAC_PROVIDER_CODE_SYNCED=false
PRIME_MAC_OPERATOR_PUBLIC_ID=3a6de854-339c-4668-ab69-cad5e168a231
PRIME_MAC_SIGNING_SECRET=your-provider-signing-secret
PRIME_MAC_WALLET_SIGNATURE_DRIFT_MS=60000
```

Do not commit the real `.env` file. Only `.env.example` should be committed.

### How providerCode Is Discovered

The operator should not guess or generate `providerCode`. The provider assigns
it to the operator account. This sample gets it from:

```text
GET https://api.primemacgames.com/api/portal/operators/{operatorPublicId}
```

Example response:

```json
{
  "operatorPublicId": "3a6de854-339c-4668-ab69-cad5e168a231",
  "operatorName": "STW-Operator",
  "providerCode": "Prime Mac Games",
  "status": 1,
  "statusName": "Active"
}
```

On application startup, the sample checks `PRIME_MAC_PROVIDER_CODE_SYNCED`.
When it is `false`, the app:

1. Calls the provider profile endpoint using `PRIME_MAC_OPERATOR_PUBLIC_ID`.
2. Reads `providerCode` from the response.
3. Updates `operator_provider_config.provider_code`.
4. Writes `PRIME_MAC_PROVIDER_CODE` to `.env`.
5. Sets `PRIME_MAC_PROVIDER_CODE_SYNCED=true` in `.env`.

This keeps startup traffic low: after the first successful sync, the operator
uses the local saved provider code.

You can also run the sync manually:

```bash
php artisan prime-mac:sync-provider-code --force
```

Important files:

```text
app/Services/ProviderCodeSynchronizer.php
app/Services/ProviderApiClient.php
app/Services/OperatorStore.php
database/migrations/2026_05_01_000001_add_provider_code_sync_procedure.php
```

## Game Launch Flow

When a player clicks the POKER game:

1. The operator creates a short-lived launch token in its own database.
2. The operator calls the provider launch-config API.
3. The provider returns the current launch URL format.
4. The operator replaces `{launchToken}` and `{operatorPublicId}`.
5. The operator redirects the player to the final game URL.

Provider launch-config endpoint:

```text
GET https://api.primemacgames.com/api/portal/operators/{operatorPublicId}/games/{gameId}/launch-config
```

Current expected POKER launch URL pattern:

```text
https://livekit.poker.goscanqr.com/?launchToken={launchToken}&operatorPublicId={operatorPublicId}
```

Example final launch URL:

```text
https://livekit.poker.goscanqr.com/?launchToken=7dd1d6ee-1dad-4cf1-a560-4e087f2adb41&operatorPublicId=3a6de854-339c-4668-ab69-cad5e168a231
```

Important files:

```text
app/Http/Controllers/OperatorController.php
app/Services/ProviderApiClient.php
app/Services/OperatorStore.php
```

## Wallet API Security

Every provider wallet request must include these headers:

```text
X-Provider-Code
X-Timestamp
X-Nonce
X-Signature
```

`X-Provider-Code` must match:

```text
Prime Mac Games
```

The signing message format is:

```text
{timestamp}.{nonce}.{HTTP_METHOD}.{pathAndQuery}.{rawBody}
```

The signature is:

```text
Base64(HMACSHA256(signingSecret, signingMessage))
```

Example signing message:

```text
1714480000000.abc123nonce.POST./api/balance/get.{"playerId":"player-guid"}
```

The sample validates signatures in:

```text
app/Services/ProviderSignatureValidator.php
```

The validator checks:

- Provider code.
- Required signature headers.
- Timestamp drift.
- Nonce replay protection.
- HMAC signature.

Nonce values are stored in `provider_request_nonces`.

## Required API Endpoint Details

### POST /api/player/authorize

Purpose:

The provider exchanges a launch token for player information.

Request:

```json
{
  "launchToken": "7dd1d6ee-1dad-4cf1-a560-4e087f2adb41"
}
```

Successful response:

```json
{
  "playerId": "player-public-guid",
  "internalPlayerId": 1,
  "operatorPublicId": "3a6de854-339c-4668-ab69-cad5e168a231",
  "displayName": "Player Name",
  "gameId": 1,
  "currencyCode": "PHP",
  "languageCode": "en-PH",
  "countryCode": "PH",
  "balance": "1000.00"
}
```

Database function:

```text
sp_launch_token_authorize
```

### POST /api/balance/get

Purpose:

The provider requests the latest player wallet balance.

Request:

```json
{
  "playerId": "player-public-guid"
}
```

Successful response:

```json
{
  "playerId": "player-public-guid",
  "internalPlayerId": 1,
  "currencyCode": "PHP",
  "balance": "1000.00"
}
```

Database function:

```text
sp_balance_get
```

### POST /api/bet/place

Purpose:

The provider debits the player's wallet for a bet.

Request:

```json
{
  "playerId": "player-public-guid",
  "gameId": 1,
  "roundId": "round-001",
  "transactionId": "bet-001",
  "amount": 100,
  "currencyCode": "PHP"
}
```

Successful response:

```json
{
  "playerId": "player-public-guid",
  "internalPlayerId": 1,
  "currencyCode": "PHP",
  "balance": "900.00",
  "transactionId": "bet-001"
}
```

Rules:

- `amount` must be positive.
- The player must exist and be active.
- The player must have enough balance.
- `transactionId` must be idempotent.
- Same `transactionId` with the same payload returns the original result.
- Same `transactionId` with a different payload is rejected.

Database function:

```text
sp_wallet_bet_place
```

### POST /api/bet/settle

Purpose:

The provider credits a positive payout or winning amount to the player's wallet.

Request:

```json
{
  "playerId": "player-public-guid",
  "gameId": 1,
  "roundId": "round-001",
  "transactionId": "settle-001",
  "amount": 150,
  "currencyCode": "PHP",
  "playerActivity": {
    "result": "win"
  }
}
```

Successful response:

```json
{
  "playerId": "player-public-guid",
  "internalPlayerId": 1,
  "currencyCode": "PHP",
  "balance": "1050.00",
  "transactionId": "settle-001"
}
```

Rules:

- `amount` must be positive.
- The player must exist and be active.
- `transactionId` must be idempotent.
- Player activity is stored in `wallet_transactions.player_activity`.

Database function:

```text
sp_wallet_bet_settle
```

### POST /api/bet/rollback

Purpose:

The provider reverses an earlier bet or settle transaction.

Request:

```json
{
  "playerId": "player-public-guid",
  "gameId": 1,
  "roundId": "round-001",
  "transactionId": "rollback-001",
  "originalTransactionId": "bet-001",
  "amount": 100
}
```

Successful response:

```json
{
  "playerId": "player-public-guid",
  "internalPlayerId": 1,
  "currencyCode": "PHP",
  "balance": "1000.00",
  "transactionId": "rollback-001"
}
```

Rules:

- `originalTransactionId` must exist.
- The original transaction must be a bet debit or settle credit.
- A rollback for the same original transaction can only be processed once.
- The rollback amount must match the original transaction amount.
- If the original transaction was a debit, rollback credits the wallet back.
- If the original transaction was a credit, rollback debits the wallet back.

Database function:

```text
sp_wallet_bet_rollback
```

## Database Structure

This project uses PostgreSQL. The schema is created by:

```text
database/migrations/2026_04_30_151500_create_operator_core_schema.php
```

Stored procedures are created by:

```text
database/migrations/2026_04_30_151600_create_operator_stored_procedures.php
```

Provider config is seeded by:

```text
database/migrations/2026_04_30_151700_seed_operator_provider_config.php
```

### operator_provider_config

Stores the provider connection settings for this operator.

| Column | Type | Purpose |
| --- | --- | --- |
| id | smallint | Singleton row. Always `1`. |
| provider_name | varchar(120) | Human-readable provider name. |
| provider_code | varchar(120) | Code expected in `X-Provider-Code`. |
| provider_base_api_url | varchar(400) | Provider API base URL. |
| operator_public_id | uuid | Provider-created operator public ID. |
| signing_secret | text | Shared HMAC signing secret. |
| signature_drift_ms | integer | Allowed timestamp drift for signed requests. |
| created_at | timestamptz | Row creation time. |
| updated_at | timestamptz | Last update time. |

### operator_players

Stores player accounts for the sample operator.

| Column | Type | Purpose |
| --- | --- | --- |
| id | bigserial | Internal player ID. |
| player_public_id | uuid | Public player ID sent to provider. |
| username | varchar(80) | Login username. |
| display_name | varchar(120) | Name shown in UI and provider response. |
| password_hash | varchar(255) | Laravel password hash. |
| currency_code | char(3) | Player wallet currency, default `PHP`. |
| language_code | varchar(12) | Player language, default `en-PH`. |
| country_code | char(2) | Player country, default `PH`. |
| status | smallint | `1` active, `0` disabled, `2` suspended. |
| last_login_at | timestamptz | Last successful login time. |
| created_at | timestamptz | Row creation time. |
| updated_at | timestamptz | Last update time. |

Indexes and constraints:

- Unique `player_public_id`.
- Unique lowercase `username`.
- Status must be `0`, `1`, or `2`.

### player_wallets

Stores one wallet per player.

| Column | Type | Purpose |
| --- | --- | --- |
| id | bigserial | Wallet ID. |
| player_id | bigint | References `operator_players.id`. |
| currency_code | char(3) | Wallet currency. |
| balance | numeric(18,2) | Current wallet balance. |
| created_at | timestamptz | Row creation time. |
| updated_at | timestamptz | Last update time. |

Indexes and constraints:

- Unique `player_id`.
- Balance must be greater than or equal to zero.

### player_launch_tokens

Stores short-lived launch tokens created before redirecting a player to a game.

| Column | Type | Purpose |
| --- | --- | --- |
| launch_token | uuid | Token sent to the game launch URL. |
| player_id | bigint | References `operator_players.id`. |
| game_id | integer | Provider game ID. |
| game_code | varchar(50) | Provider game code, for example `POKER`. |
| expires_at | timestamptz | Token expiration time. |
| last_authorized_at | timestamptz | Last time provider authorized this token. |
| created_at | timestamptz | Row creation time. |

Indexes:

- `(player_id, created_at DESC)`.

### wallet_transactions

Stores all wallet movements and provider transaction IDs.

| Column | Type | Purpose |
| --- | --- | --- |
| id | bigserial | Internal transaction row ID. |
| player_id | bigint | References `operator_players.id`. |
| transaction_id | varchar(120) | Provider transaction ID or operator cash transaction ID. |
| original_transaction_id | varchar(120) | Original transaction for rollback rows. |
| transaction_type | varchar(24) | `debit`, `credit`, `rollback`, `cash_in`, or `cash_out`. |
| game_id | integer | Provider game ID. |
| round_id | varchar(120) | Provider round ID. |
| amount | numeric(18,2) | Positive amount moved. |
| currency_code | char(3) | Currency for this movement. |
| balance_before | numeric(18,2) | Wallet balance before movement. |
| balance_after | numeric(18,2) | Wallet balance after movement. |
| request_hash | varchar(128) | SHA-256 hash of the request body for idempotency. |
| payload | jsonb | Original provider request payload. |
| player_activity | jsonb | Optional settle activity/details. |
| created_at | timestamptz | Row creation time. |

Indexes and constraints:

- Unique `transaction_id`.
- Index on `(player_id, created_at DESC)`.
- Index on `original_transaction_id`.
- `amount` must be greater than zero.
- `transaction_type` must be one of the allowed values.

### provider_request_nonces

Stores request nonces to prevent replay attacks.

| Column | Type | Purpose |
| --- | --- | --- |
| id | bigserial | Internal nonce row ID. |
| provider_code | varchar(120) | Provider code from request header. |
| nonce | varchar(160) | Unique nonce from request header. |
| timestamp_ms | bigint | Request timestamp in milliseconds. |
| expires_at | timestamptz | Nonce expiration time. |
| created_at | timestamptz | Row creation time. |

Indexes and constraints:

- Unique `(provider_code, nonce)`.
- Index on `expires_at`.

## Stored Procedures

The sample intentionally uses stored procedures for wallet operations. This makes
balance changes atomic, easier to audit, and safer for concurrent requests.

| Procedure | Purpose |
| --- | --- |
| sp_provider_config_upsert | Creates or updates the singleton provider config row. |
| sp_provider_config_get | Reads provider config. |
| sp_provider_code_sync | Saves providerCode returned by the provider profile endpoint. |
| sp_player_register | Creates a player and starting wallet. |
| sp_player_login_lookup | Finds an active player by username for login. |
| sp_player_mark_login | Updates `last_login_at`. |
| sp_player_get | Reads player and wallet data for dashboard. |
| sp_launch_token_create | Creates a short-lived game launch token. |
| sp_launch_token_authorize | Exchanges launch token for player details. |
| sp_balance_get | Returns current wallet balance. |
| sp_provider_nonce_record | Stores nonce and rejects replayed nonce values. |
| sp_wallet_apply | Shared low-level debit/credit function. |
| sp_wallet_bet_place | Debits wallet for a bet. |
| sp_wallet_bet_settle | Credits wallet for a settlement/payout. |
| sp_wallet_bet_rollback | Reverses a previous debit or credit. |
| sp_wallet_adjust | Used by sample cash-in and cash-out buttons. |
| sp_wallet_recent_transactions | Shows recent wallet history on the dashboard. |

Application code calls these procedures through:

```text
app/Services/OperatorStore.php
```

For a real operator, `OperatorStore` is the main class to replace if you already
have your own player, wallet, and transaction system.

## Project File Guide

```text
app/Http/Controllers/OperatorController.php
```

Handles login, registration, dashboard, cash-in/cash-out, and game launch.

```text
app/Http/Controllers/Api/ProviderWalletController.php
```

Handles the five required provider wallet callbacks.

```text
app/Services/ProviderApiClient.php
```

Calls the provider API for operator profile, game list, provider health, and
launch URL configuration.

```text
app/Services/ProviderSignatureValidator.php
```

Validates signed provider wallet requests.

```text
app/Services/OperatorStore.php
```

The database boundary. It calls PostgreSQL stored procedures.

```text
routes/web.php
```

Browser routes for login, dashboard, wallet actions, and game launch.

```text
routes/api.php
```

Provider wallet callback routes.

```text
public/images
```

Logo and visual assets used by the login and dashboard pages.

## Local Setup

Requirements:

- PHP 8.2 or newer.
- Composer.
- PostgreSQL 14 or newer.
- PHP extensions required by Laravel and PostgreSQL, including `pdo_pgsql`.

Install dependencies:

```bash
composer install
```

Create environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` database settings:

```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=game_operator
DB_USERNAME=game_operator_user
DB_PASSWORD=your-password
```

Update `.env` provider settings:

```text
PRIME_MAC_PROVIDER_BASE_URL=https://api.primemacgames.com
PRIME_MAC_PROVIDER_CODE="Prime Mac Games"
PRIME_MAC_PROVIDER_CODE_AUTO_SYNC=true
PRIME_MAC_PROVIDER_CODE_SYNCED=false
PRIME_MAC_OPERATOR_PUBLIC_ID=3a6de854-339c-4668-ab69-cad5e168a231
PRIME_MAC_SIGNING_SECRET=your-provider-signing-secret
PRIME_MAC_WALLET_SIGNATURE_DRIFT_MS=60000
```

Run migrations:

```bash
php artisan migrate
```

Start the local server:

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## Deployment Notes

For a production-style Laravel deployment:

1. Point the web server document root to `public/`.
2. Set `APP_ENV=production`.
3. Set `APP_DEBUG=false`.
4. Set `APP_URL` to your HTTPS domain.
5. Configure PostgreSQL credentials.
6. Configure provider values in `.env`.
7. Run `composer install --no-dev --optimize-autoloader`.
8. Run `php artisan migrate --force`.
9. Run `php artisan optimize`.

For this sample deployment, the public domain is:

```text
https://stwlaravel.primemacgames.com
```

## Viewing Logs

Laravel application logs are written to:

```text
storage/logs/laravel.log
```

To follow logs in a terminal:

```bash
tail -f storage/logs/laravel.log
```

To show recent provider wallet endpoint activity, you can also inspect the
`wallet_transactions` table:

```sql
SELECT
    transaction_id,
    original_transaction_id,
    transaction_type,
    game_id,
    round_id,
    amount,
    currency_code,
    balance_before,
    balance_after,
    created_at
FROM wallet_transactions
ORDER BY created_at DESC
LIMIT 50;
```

## Testing The Routes

List the required API routes:

```bash
php artisan route:list --except-vendor
```

Check PHP syntax:

```bash
php -l app/Http/Controllers/OperatorController.php
php -l app/Http/Controllers/Api/ProviderWalletController.php
php -l app/Services/ProviderApiClient.php
php -l app/Services/ProviderSignatureValidator.php
php -l app/Services/OperatorStore.php
```

Test the provider launch URL builder:

```bash
php artisan tinker
```

Then run:

```php
$provider = app(App\Services\ProviderApiClient::class);
$config = $provider->launchConfig('3a6de854-339c-4668-ab69-cad5e168a231', 1);
$provider->buildLaunchUrl($config['launchUrlFormat'], '7dd1d6ee-1dad-4cf1-a560-4e087f2adb41', '3a6de854-339c-4668-ab69-cad5e168a231');
```

Expected output:

```text
https://livekit.poker.goscanqr.com/?launchToken=7dd1d6ee-1dad-4cf1-a560-4e087f2adb41&operatorPublicId=3a6de854-339c-4668-ab69-cad5e168a231
```

## Adapting This Sample For Another Operator

Keep these parts:

- The five required API routes.
- The request/response structure.
- Signature validation.
- Idempotency rules.
- Rollback rules.
- Audit trail for wallet transactions.

Replace these parts with your own production implementation:

- Player registration and login.
- Wallet storage.
- Cash-in and cash-out workflow.
- Transaction reporting.
- Fraud/risk checks.
- Admin tools.

The easiest place to connect an existing operator system is:

```text
app/Services/OperatorStore.php
```

As long as `OperatorStore` returns the same response shape to
`ProviderWalletController`, the provider API contract remains the same.

## Security Checklist

- Never commit `.env`.
- Never commit a real signing secret.
- Use HTTPS for the public operator domain.
- Validate provider signatures before touching wallet balances.
- Reject replayed nonces.
- Use idempotent transaction IDs.
- Keep complete wallet transaction history.
- Use database transactions or stored procedures for wallet updates.
- Monitor logs for failed signatures and failed wallet operations.
