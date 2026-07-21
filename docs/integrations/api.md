# Configuration API

Dreeve exposes a small HTTP API for reading and updating configuration, so
settings that come from an external source — a smart scale, a health export, a
coaching spreadsheet — can be kept in sync without anyone opening the admin
panel.

The API is **disabled by default**. It only starts accepting requests once you
set `API_TOKEN`.

## Enabling the API

Generate a long random token and add it to your `.env`:

```bash
# At least 32 characters. Anything shorter is rejected on startup.
API_TOKEN=$(openssl rand -hex 32)
```

```bash
API_TOKEN=1f1d1b0a...  # your generated value
```

Then recreate the containers so the new value is picked up:

```bash
> docker compose up -d
```

> [!WARNING]
> The token grants full read/write access to your configuration. Treat it like a
> password: keep it out of version control, and prefer serving Dreeve over HTTPS
> if it is reachable outside your network.

`ADMIN_ALLOWED_IPS` applies to the API as well as the admin panel, so the same
setting restricts both routes into your configuration:

```bash
ADMIN_ALLOWED_IPS=192.168.1.10,192.168.1.11
```

Requests from any other address get a `404`, the same way the admin panel is
concealed.

## Authentication

Every request needs a bearer token:

```bash
> curl -H "Authorization: Bearer $API_TOKEN" \
       http://localhost:8080/api/v1/config
```

Missing, malformed or incorrect tokens get a `401` with a JSON body:

```json
{ "message": "Invalid API token." }
```

## Discovering what is available

`GET /api/v1/config` lists every resource this version exposes:

```json
{
  "resources": [
    { "name": "athlete/ftp-history/cycling",   "href": "/api/v1/config/athlete/ftp-history/cycling",   "methods": ["GET", "PUT"] },
    { "name": "athlete/ftp-history/running",   "href": "/api/v1/config/athlete/ftp-history/running",   "methods": ["GET", "PUT"] },
    { "name": "athlete/max-heart-rate",        "href": "/api/v1/config/athlete/max-heart-rate",        "methods": ["GET", "PUT"] },
    { "name": "athlete/resting-heart-rate",    "href": "/api/v1/config/athlete/resting-heart-rate",    "methods": ["GET", "PUT"] },
    { "name": "athlete/weight-history",        "href": "/api/v1/config/athlete/weight-history",        "methods": ["GET", "PUT"] },
    { "name": "zwift",                         "href": "/api/v1/config/zwift",                         "methods": ["GET", "PUT"] }
  ]
}
```

`GET` reads the current value; `PUT` replaces it and responds with the stored
state, so you can confirm what was persisted without a second request. Always
check `methods` rather than assuming: a resource may be read-only, in which case
`PUT` returns `405`.

`PUT` **replaces** the whole collection rather than appending to it — send the
full history every time.

## Weight history

```bash
> curl -H "Authorization: Bearer $API_TOKEN" \
       http://localhost:8080/api/v1/config/athlete/weight-history
```

```json
{
  "unit": "kg",
  "entries": [
    { "on": "2024-01-01", "weight": 70.5 },
    { "on": "2024-06-01", "weight": 69 }
  ]
}
```

Weights are stored as plain numbers and interpreted using the unit system from
**Settings → Appearance** — `kg` when metric, `lb` when imperial. The `unit`
field is returned so a client can tell which one is in effect.

To update:

```bash
> curl -X PUT \
       -H "Authorization: Bearer $API_TOKEN" \
       -H "Content-Type: application/json" \
       -d '{"unit": "kg", "entries": [{"on": "2024-01-01", "weight": 70.5}]}' \
       http://localhost:8080/api/v1/config/athlete/weight-history
```

`unit` is optional, but sending it is recommended: if it disagrees with the
configured unit system the request is rejected instead of silently writing
pounds into a kilogram history.

## FTP history

FTP is tracked separately per sport, so each has its own resource:

```bash
> curl -X PUT \
       -H "Authorization: Bearer $API_TOKEN" \
       -H "Content-Type: application/json" \
       -d '{"entries": [{"on": "2025-01-01", "ftp": 300}]}' \
       http://localhost:8080/api/v1/config/athlete/ftp-history/cycling
```

```json
{
  "sport": "cycling",
  "entries": [{ "on": "2025-01-01", "ftp": 300 }]
}
```

Updating one sport never affects the other. The sport is taken from the URL, so
a `sport` field in the body is ignored.

## Heart rate

Max heart rate is either a named formula or values you measured, discriminated
by `type`:

```bash
> curl -X PUT \
       -H "Authorization: Bearer $API_TOKEN" \
       -H "Content-Type: application/json" \
       -d '{"type": "formula", "formula": "fox"}' \
       http://localhost:8080/api/v1/config/athlete/max-heart-rate
```

Allowed formulas: `arena`, `astrand`, `fox`, `gellish`, `nes`, `tanaka`.

Measured values apply from their date onwards:

```json
{
  "type": "measured",
  "entries": [
    { "on": "2020-01-01", "bpm": 198 },
    { "on": "2025-01-10", "bpm": 193 }
  ]
}
```

Resting heart rate works the same way, with a third option for a single fixed
value:

```json
{ "type": "formula", "formula": "heuristicAgeBased" }
{ "type": "fixed",   "bpm": 52 }
{ "type": "measured", "entries": [{ "on": "2024-01-01", "bpm": 54 }] }
```

A date may appear only once per history, and every `bpm` must be a whole number
above zero.

## Zwift

```bash
> curl -X PUT \
       -H "Authorization: Bearer $API_TOKEN" \
       -H "Content-Type: application/json" \
       -d '{"level": 42, "racingScore": 495}' \
       http://localhost:8080/api/v1/config/zwift
```

Either field may be `null` to clear it.

## Rebuilds

Weight, FTP and heart rate feed derived metrics such as w/kg, heart rate zones
and stress level, so a successful update flags the app for a rebuild. The daemon container picks that
up within a few minutes and regenerates the affected pages. Nothing extra to
call.

## Errors

| Status | Meaning |
|--------|---------|
| `400`  | The body was not valid JSON, or a value was rejected. `message` says which entry and why. |
| `401`  | Missing, malformed or incorrect token, or `API_TOKEN` is not set. |
| `404`  | No such configuration resource, or your IP is not in `ADMIN_ALLOWED_IPS`. |
| `405`  | The resource is read-only. |

Validation is all-or-nothing: if any entry is rejected, nothing is written.

```json
{ "message": "Entry #2 is missing a valid numeric \"weight\"." }
```

## Adding a new resource

The endpoint list is not hardcoded in the controller. Implement
`App\Domain\Settings\Api\ConfigResource` and the class is picked up
automatically through the `app.api.config_resource` tag:

```php
final readonly class MyConfigResource implements ConfigResource
{
    public function getName(): string
    {
        return 'my/resource';
    }

    public function read(): array
    {
        // Current value, as returned by GET. Never return secrets: anything
        // here is visible to every client holding an API token.
    }
}
```

That gives you a read-only endpoint. To accept `PUT` as well, implement
`WritableConfigResource` instead:

```php
final readonly class MyConfigResource implements WritableConfigResource
{
    // getName() and read() as above.

    public function buildUpdateCommand(array $payload): Command
    {
        // Validate, then return a command without mutating anything. Throw
        // CouldNotDeserializeCommand::invalidPayload() to produce a 400.
    }
}
```

The command can be an existing one — `ZwiftConfigResource` returns
`UpdateSettings` rather than defining its own. Mark it `#[RequiresRebuild]` if
the setting feeds anything the app renders.

No routing or controller changes are required — the resource shows up in
`GET /api/v1/config` and becomes addressable at `/api/v1/config/my/resource`.
