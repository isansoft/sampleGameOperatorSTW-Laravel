# Game Operator Task Tracker

Use this file to track project tasks, current status, and acceptance criteria.

Status values:

- `TODO` - not started
- `IN_PROGRESS` - actively being worked on
- `BLOCKED` - waiting on dependency or decision
- `DONE` - completed and verified

## Tasks

### GO-001 Live API Endpoint Monitor For Iframe Game

Status: `TODO`

Created: `2026-05-06`

Goal:
Show a floating live monitor on the player/operator dashboard while the game is
running in the iframe. The monitor should show which Prime Mac Games wallet API
endpoints are being called for the currently logged-in player while they play.

Background:
The game server calls the Game Operator wallet API endpoints during gameplay:

- `POST /api/player/authorize`
- `POST /api/balance/get`
- `POST /api/bet/place`
- `POST /api/bet/settle`
- `POST /api/bet/rollback`

The operator dashboard should display those calls live for the player account
that is currently logged in and playing.

Recommended approach:
Start with Server-Sent Events instead of a full WebSocket server.

Reason:
This feature only needs one-way live updates from the Laravel backend to the
browser. SSE is simpler to deploy in this sample project because it does not
need a separate WebSocket server, socket gateway, Redis pub/sub, or extra Nginx
upgrade handling. If we later need two-way realtime messaging, we can upgrade to
Laravel Reverb or another WebSocket server.

Implementation notes:

- Create an `api_request_logs` table to store safe wallet API activity.
- Log each signed provider wallet request after signature validation.
- Include safe fields only:
  - current player public ID when known
  - endpoint path
  - HTTP method
  - response status
  - wallet transaction ID when present
  - round ID / game ID when present
  - timestamp
  - short request hash
  - error code when request fails
- Do not log signing secrets.
- Do not log full signature headers in normal operation.
- Avoid storing sensitive raw request bodies in production mode.
- Add an authenticated stream endpoint, for example:
  - `GET /operator/api-logs/stream`
- The stream endpoint should only return logs for the logged-in player.
- Add a floating dashboard panel beside/over the iframe:
  - latest endpoint
  - timestamp
  - success/failure status
  - compact list of recent calls
  - pause/clear controls
- Keep the monitor optional and non-blocking; gameplay should continue if the
  stream disconnects.

Acceptance criteria:

- When the player launches Poker in the iframe, the dashboard shows a floating
  API monitor.
- When the game server calls any required wallet endpoint for that player, the
  monitor updates without page refresh.
- Logs are scoped to the logged-in player and do not expose other players.
- Signature secrets are never shown in UI or stored in normal API monitor logs.
- If the browser reconnects, the monitor can recover recent events.
- The feature applies to all five required wallet endpoints.
- Documentation explains how the monitor works and how to disable it if needed.

Open decision:
If the team specifically needs two-way realtime control later, replace or extend
SSE with Laravel Reverb/WebSockets. For the first version, SSE is the lower-risk
choice.
