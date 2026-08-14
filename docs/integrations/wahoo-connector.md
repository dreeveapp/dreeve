# Wahoo Connector

Dreeve imports anything dropped into its `watch/` folder. Getting your Wahoo workouts *into* that
folder is what the **Wahoo connector** does: it periodically fetches workout activities from the Wahoo Fitness Cloud API, downloads the original `.fit` files, and drops them in. No manual exports.

```
Wahoo Cloud → connector → watch/ → Dreeve imports it
```

The connector is a **separate container** from its own repository,
[dreeveapp/dreeve-wahoo-connector](https://github.com/dreeveapp/dreeve-wahoo-connector). It is not
part of Dreeve itself, it only writes files into the folder you
already mount.

> [!IMPORTANT]
> **Important** Wahoo Cloud requires an OAuth 2.0 application. You will need to create a free developer application on Wahoo's portal to obtain a **Client ID** and **Client Secret** before setting up the connector. Read [Register a Wahoo client](/integrations/wahoo-connector.md#1-register-a-wahoo-client) first.

## Setup

### 1. Register a Wahoo client

1. Sign in to the [Wahoo Developer Portal](https://developers.wahooligan.com/applications).
2. Click **Create Application**.
3. Fill in the required fields:
   - **App Name**: `dreeve-wahoo-connector` (or your preferred name)
   - **Redirect URI**: `https://<this-host>:8085/callback` *(e.g. `https://192.168.1.100:8085/callback` or `http://localhost:8085/callback`)*
   - **Webhook URI**: Leave blank
4. Submit the application and copy your **Client ID** and **Client Secret**.

### 2. Add the container

Add this alongside the `app` and `daemon` services in your
[docker-compose.yml](/getting-started/installation.md#docker-composeyml):

```yml
  # Pulls workouts out of Wahoo Cloud into the watch folder.
  wahoo-connector:
    image: ghcr.io/dreeveapp/dreeve-wahoo-connector:latest
    container_name: dreeve-wahoo-connector
    restart: unless-stopped
    ports:
      - '8085:8085'
    env_file: ./.env
    volumes:
      # The same ./watch folder the app and daemon mount.
      - ./watch:/data/downloads
      - ./wahoo/config:/data/config
```

### 3. Configure it

Add to your `.env`:

```bash
WAHOO_CLIENT_ID=your-client-id
WAHOO_CLIENT_SECRET=your-client-secret
# Where this connector is reachable in a browser for OAuth callbacks.
WAHOO_REDIRECT_URI=https://<this-host>:8085/callback
# Initial sync window: 1_day, 1_week, 1_month, 1_year, or all_time
SYNC_TIME_WINDOW=1_week
# Cron expression for automated background downloads (default: daily at 02:00 UTC)
SYNC_CRON=0 2 * * *
```

### 4. Authorize it

Start the container:

```bash
> docker compose up -d wahoo-connector
```

Then open **`https://<this-host>:8085`** in your browser, accept the self-signed SSL certificate if prompted, and click **Connect Wahoo Account**.

After approving access on Wahoo's site, you will be redirected back to the connector dashboard. An initial sync cycle runs automatically upon successful authorization.

Tokens are saved persistently to the `./wahoo/config` volume and refresh automatically.

### 5. Start it

```bash
> docker compose up -d
> docker compose logs -f wahoo-connector
```

Background sync runs according to `SYNC_CRON` (or on demand via the web dashboard). Downloaded workouts land in the watch folder as `<date>_workout_<id>.fit`, and the daemon container will import them, exactly as if you had dropped them there yourself.

## How sync works

- **Atomic file delivery**: Files are written as temporary files (`.tmp`) and atomically renamed to `.fit` upon completion, preventing Dreeve from parsing partial files.
- **Smart deduplication**: Workouts are queried starting from newest first. Syncing stops early when encountering already downloaded activities. Download history is recorded persistently (`sync_history.json`), ensuring sync remains fast and avoids re-downloads even after Dreeve processes and removes `.fit` files from the watch folder.
- **Dynamic rate limiting**: Monitors Wahoo API `X-RateLimit-Remaining` HTTP response headers in real time to avoid `429 Too Many Requests` errors.

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `WAHOO_CLIENT_ID` | - | **Required.** Client ID from [Wahoo Developer Portal](https://developers.wahooligan.com/applications). |
| `WAHOO_CLIENT_SECRET` | - | **Required.** Client Secret from Wahoo Developer Portal. |
| `WAHOO_REDIRECT_URI` | `https://localhost:8085/callback` | OAuth redirect URI matching Wahoo App settings. |
| `SYNC_TIME_WINDOW` | `1_week` | Timeframe for sync cycles (`1_day`, `1_week`, `1_month`, `1_year`, `all_time`). |
| `SYNC_CRON` | `0 2 * * *` | 5-field Cron expression for scheduled background downloads. |
| `PORT` | `8085` | Port for the web dashboard & OAuth callback server. |
| `DATA_DIR` | `/data` | Internal container base path for configuration and downloaded files. |
| `STATE_DIR` | `/data/config` | Directory path to store authentication tokens and sync history. |
| `WATCH_DIR` | `/data/downloads` | Directory path to deliver downloaded `.fit` files (Dreeve watch folder). |
| `LOG_LEVEL` | `INFO` | Logging verbosity level (`DEBUG`, `INFO`, `WARNING`, `ERROR`). |
| `VERIFY_FILES_ON_DISK` | `false` | If `true`, requires `.fit` files to remain on disk during deduplication checks. |

> [!IMPORTANT]
> **Important** Just like Dreeve, the `.env` file is read when the container is **created**.
> After changing it, run `docker compose up -d --force-recreate`.

## Commands & Endpoints

```bash
# Start or update the container
> docker compose up -d wahoo-connector

# View status via HTTP API
> curl https://localhost:8085/api/status

# Trigger an immediate manual sync cycle
> curl -X POST https://localhost:8085/api/sync
```
