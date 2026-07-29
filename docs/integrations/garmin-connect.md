# Garmin Connect

Dreeve imports anything dropped into its `watch/` folder. Getting your Garmin activities *into* that
folder is what the **Garmin connector** does: it periodically lists new activities, downloads the
original `.fit` files and drops them in. No manual exports.

```
Garmin Connect → connector → watch/ → Dreeve imports it
```

The connector is a **separate container** from its own repository,
[dreeveapp/dreeve-garmin-connector](https://github.com/dreeveapp/dreeve-garmin-connector). It is not
part of Dreeve itself, it only writes files into the folder you
already mount.

> [!IMPORTANT]
> **Important** This uses Garmin's unofficial API. Garmin does not document or support it and has
> changed the authentication flow before, so expect occasional breakage. Read
> [An unofficial API](/integrations/garmin-connect.md#an-unofficial-api)
> before you rely on it.

## Setup

### 1. Add the container

Add this alongside the `app` and `daemon` services in your
[docker-compose.yml](/getting-started/installation.md#docker-composeyml):

```yml
  # Pulls activities out of Garmin Connect into the watch folder.
  garmin-connector:
    image: ghcr.io/dreeveapp/dreeve-garmin-connector:latest
    container_name: dreeve-garmin-connector
    restart: unless-stopped
    volumes:
      # The same ./watch folder the app and daemon mount.
      - ./watch:/watch
      - ./garmin/state:/state
      - ./garmin/tokens:/tokens
    env_file: ./.env
```

### 2. Configure it

Add to your `.env`:

```bash
GARMIN_EMAIL=you@example.com
GARMIN_PASSWORD=your-garmin-password
# How far back to reach on the very first run. A date (2026-01-01), a relative
# offset (-30d, 720h) or 'now'.
SINCE=-30d
```

`SINCE` only matters for the first run.

### 3. Log in

```bash
> docker compose run --rm garmin-connector login
```

This is the only command that ever uses your password.

On success your session is stored in `./garmin/tokens` and refreshes itself from then on. You can
remove `GARMIN_PASSWORD` from your `.env` afterward; nothing else needs it.

### 4. Start it

```bash
> docker compose up -d
> docker compose logs -f garmin-connector
```

A cycle runs immediately, then one every hour. Files land in the watch folder as
`<activityId>.fit`, and the daemon container will import them, exactly as if you had
dropped them there yourself.

## Why the first sync is slow

A first run against a large history is probably hundreds of downloads, and asking for all of them at
once is the most reliable way to get your Garmin account rate-limited. So a cycle downloads at most
`MAX_DOWNLOADS_PER_CYCLE` activities (25 by default) and picks up where it left off next time.

At the default hourly interval that is around **600 activities a day**, so a long backfill takes days
on purpose. Fetch the status by running:

```bash
> docker compose exec garmin-connector dreeve-garmin-connector status
```

| Key | Meaning                                                                                                             |
|---|---------------------------------------------------------------------------------------------------------------------|
| `healthy` | `false` once authentication is broken, or when three `POLL_INTERVAL`s have passed without a completed cycle.        |
| `cycles` | Cycles attempted since that start, successful or not.                                                               |
| `lastSuccessfulSync` | End of the last cycle that completed. `null` until the first one does.                                              |
| `nextRunAt` | When the next cycle is due, jitter and backoff included.                                                            |
| `backoffSeconds` | How long the connector is currently backing off after a rate limit. `0` when all is well.                           |
| `authentication` | `ok`, or the error Garmin returned. Anything else means the session is dead and `login` has to be run again.        |
| `lastError` | The last failure message, cleared by the next successful cycle.                                                     |
| `lastCycle` | What the last cycle did. `null` before the first one.                                                               |
| `lastCycle.listed` | Activities Garmin returned for the window                                                                           |
| `lastCycle.delivered` | Files written to the watch folder this cycle. Capped by `MAX_DOWNLOADS_PER_CYCLE`.                                  |
| `lastCycle.failed` | Downloads that went wrong and will be retried.                                                                      |
| `lastCycle.skipped` | Activities older than `SINCE`, so deliberately not fetched.                                                         |
| `lastCycle.withoutFile` | Activities Garmin has no file for.                                                                                  |
| `lastCycle.backlog` | Same as the top-level `backlog`.                                                                                    |
| `backlog` | Activities still owed a download, whatever cycle they were listed in. This is the number to watch during a backfill. |
| `activities` | The whole ledger counted by [status](#when-something-looks-wrong).           |

## Configuration

| Variable | Default        | What it does |
|---|----------------|---|
| `GARMIN_EMAIL` | -              | **Required.** Also accepts `GARMIN_EMAIL_FILE` for Docker secrets. |
| `GARMIN_PASSWORD` | -              | Only needed to log in. Also accepts `GARMIN_PASSWORD_FILE`. |
| `GARMIN_IS_CN` | `false`        | Use Garmin China. |
| `GARMINTOKENS` | `/tokens`      | Where the session is stored. Mount it as a volume. |
| `WATCH_DIR` | `/watch`       | Dreeve's watch folder. |
| `STATE_DIR` | `/state`       | Where the ledger is stored. Mount it as a volume. |
| `SINCE` | -              | **Required on the first run.** A date (`2026-01-01`), an ISO instant, a relative offset (`-30d`, `720h`) or `now`. Resolved once, then remembered. |
| `POLL_INTERVAL` | `3600`         | Seconds between cycles. |
| `POLL_JITTER_PCT` | `10`           | Randomises the interval by ±this much, so every deployment of this image does not hit Garmin on the same second. |
| `LOOKBACK_DAYS` | `7`            | Re-lists the last few days each cycle, catching watches that synced late and activities edited afterwards. |
| `MAX_DOWNLOADS_PER_CYCLE` | `25`           | The per-cycle cap. See above. |
| `DOWNLOAD_DELAY_SECONDS` | `2`            | Pause between downloads. |
| `FALLBACK_FORMAT` | `tcx`          | `tcx`, `gpx` or `none`, for activities that have no `.fit` file. |
| `ON_CONFLICT` | `skip`         | `skip` or `overwrite`, when the file is already in the watch folder. |
| `MAX_ATTEMPTS` | `5`            | How often an activity may fail before it is left alone. |
| `MAX_BACKOFF_SECONDS` | `21600`        | Cap on the backoff after a rate limit (6 hours). |
| `MAX_CYCLES` | `0`            | `0` runs forever; anything else runs that many cycles and exits. |
| `LOG_LEVEL` | `info`         | `debug`, `info`, `warning`, `error` or `critical`. |
| `LOG_FORMAT` | `text`         | `text` or `json`. |
| `PUID` / `PGID` | -              | Own the delivered files as this user. Set them to the same values Dreeve runs as. |
| `UMASK` / `TZ` | -              | As usual. |

> [!IMPORTANT]
> **Important** Just like Dreeve, the `.env` file is read when the container is **created**.
> After changing it, run `docker compose up -d --force-recreate`.

## Commands

```bash
# Log in. Once, interactively.
> docker compose run --rm garmin-connector login

# Run a single cycle and exit.
> docker compose run --rm garmin-connector sync-once

# Show what it would fetch, without downloading anything.
> docker compose run --rm garmin-connector sync-once --dry-run

# Ask a running connector what it is doing.
> docker compose exec garmin-connector dreeve-garmin-connector status
```

## An unofficial API

The connector is built on [cyberjunky/python-garminconnect](https://github.com/cyberjunky/python-garminconnect), which talks to
the same endpoints Garmin's own mobile app does. Garmin neither documents nor supports this, and
using it is at your own risk.

**When Garmin changes something**, the symptom is a burst of authentication errors in the logs.
Check for a newer version of the Docker image first.