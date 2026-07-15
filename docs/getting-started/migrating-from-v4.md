# Migrating from v4 to v5

v5 introduces a complete rebranding from "Statistics for Strava" to **Dreeve**. Next to the rename, two things changed that
affect every existing installation:

* **Configuration moved out of `config.yaml` and into the admin panel.** Your settings now live in the database
  and are edited from the browser.
* **Strava is no longer required.** The new default import mode is `files`, where you supply `.fit`, `.tcx` or
  `.gpx` files yourself. Importing from Strava is still fully supported.

> [!WARNING]
> **Back up first.** Make a copy of `storage/database` **and** of your `config` directory before you start.

> [!NOTE]
> Your activities, gear and history are kept. Nothing has to be re-imported. Images and gear purchase prices are the
> exception: they need to be re-uploaded and re-entered, see [step 7](#_7-re-upload-your-images-and-re-enter-your-purchase-prices).

### 1. Stop your containers

```bash
> docker compose stop
```

### 2. Keep your config directory mounted

On its first start, v5 reads your `config.yaml` and `gear-maintenance.yaml` and writes every setting into the
database.

So for now, **do not delete `config.yaml` and do not remove the `./config` volume**. You clean those up in
[step 6](#_6-clean-up-the-old-configuration), after the migration has run.

### 3. Update docker-compose.yml

The Docker image was renamed. Point both containers at the new one:

```yml
services:
  app:
    image: robiningelbrecht/dreeve:latest
    # image: ghcr.io/dreeveapp/dreeve:latest
    volumes:
      # Keep this one for now, it is removed in step 5.
      - ./config:/var/www/config/app
      - ./build:/var/www/build
      - ./storage/database:/var/www/storage/database
      - ./storage/files:/var/www/storage/files
      # Only needed when you import files. Drop your .fit/.tcx/.gpx files in here.
      - ./watch:/var/www/watch
    # ...
```

Container names, the network name and the daemon container are unchanged apart from the image. A full example
lives on the [installation](/getting-started/installation.md) page.

> [!NOTE]
> In v4, the daemon container was optional. This is no longer the case.

### 4. Add the new environment variables

Add these to your `.env`:

```bash
# Existing Strava users want "stravaApi" here, it keeps your current import behaviour.
# The default is "files".
IMPORT_MODE=stravaApi

# The URL you reach the app on. Include the port if you use one.
APP_URL=http://localhost:8080

# Used to sign the admin session cookie. Set it to any long random string.
APP_SECRET=change-me-to-a-long-random-string

# Admin panel credentials. Leave the hash empty for now, you generate it in step 5.
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=''
```

Your `STRAVA_CLIENT_ID`, `STRAVA_CLIENT_SECRET` and `STRAVA_REFRESH_TOKEN` stay exactly as they are, and
`PUID`, `PGID`, `PROXY_HOST` and `PROXY_PORT` are unchanged too.

### 5. Pull the new image and migrate

```bash
> docker compose pull
> docker compose up -d
```

The app container runs the database migrations on startup. This is where your YAML configuration is copied into
the database, so watch the logs and make sure it finishes without errors:

```bash
> docker compose logs -f app
```

Now generate your admin password hash, put it in `.env`, and recreate the containers so they pick it up:

```bash
> docker compose exec app bin/console security:hash-password
> docker compose up -d
```

> [!CAUTION]
> **Double every `$` in the hash** when you paste it into `.env`, otherwise Docker Compose eats it and you get a
> password that can never match. See [Admin password](/getting-started/installation.md#admin-password).

Log in at `/admin` and verify that your settings were migrated correctly.

### 6. Clean up the old configuration

Once you have confirmed your settings are in the admin panel, the YAML files have done their job. Remove the
config volume from **both** containers in `docker-compose.yml`:

```yml
    volumes:
      - ./config:/var/www/config/app   # <-- delete this line
```

Then recreate the containers and delete your local `config` directory. From here on, `config.yaml` is ignored:
everything is edited in the admin panel.

```bash
> docker compose up -d
```

### 7. Re-upload your images and re-enter your purchase prices

Two things are **not** carried over by the migration and have to be set up again in the admin panel:

* **Images.** Every image you referenced from YAML (`imgSrc` on gear maintenance components and on gear) is
  dropped.  Upload the images again in the admin panel.
* **Purchase prices.** The prices you configured under `gear` in `config.yaml` (on gear, custom gear and
  recording devices) are not migrated. Enter them again in the admin panel, on the gear or recording device
  settings pages.

### 8. Run an import and build

```bash
# In stravaApi mode
> docker compose exec app bin/console app:cron:run-strava-import --import --build

# In files mode
> docker compose exec app bin/console app:cron:run-file-import --import --build
```

## Where did my configuration go?

| v4                                            | v5                                    |
|-----------------------------------------------|---------------------------------------|
| `config.yaml` → `general`, `appearance`, `metrics`, `zwift`, `import` | Admin panel → Settings                |
| `config.yaml` → `dashboard`                   | Admin panel → Dashboard               |
| `config.yaml` → `integrations` (AI, notifications) | Admin panel → Settings → Integrations |
| `config.yaml` → `daemon` (cron schedules)     | Admin panel → Settings → Daemon       |
| `gear-maintenance.yaml`                       | Admin panel → Gear → Gear maintenance       |
| Custom gear in YAML                           | Admin panel → Gear                    |
| `config.yaml` → `gear` (purchase prices)      | Admin panel → Gear, **not migrated**  |
| `imgSrc` images in YAML                       | Admin panel, re-uploaded, **not migrated** |

## Other things worth knowing

* **The database file was renamed** from `strava.db` to `dreeve.db`. An existing `strava.db` keeps working, you
  do not have to rename anything.
* **The docs moved** to [docs.dreeve.app](https://docs.dreeve.app), and the repository to
  [dreeveapp/dreeve](https://github.com/dreeveapp/dreeve).
* **Strava is now optional, not central.** If you would rather stop dealing with API keys and rate limits, you
  can switch `IMPORT_MODE` to `files` later on. Activities already imported from Strava stay where they are.
