# Importing activities

Dreeve builds everything it shows you (dashboard, charts, heatmap, gear stats) out of your activities.
There are two ways to import them. You can configure this with the `IMPORT_MODE` environment variable.

## Two import modes

| | `files` *(default)* | `stravaApi` |
|---|---|---|
| Where activities come from | `.fit` / `.tcx` / `.gpx` files you supply | The Strava API |
| Needs a Strava account | no | yes |
| Needs API keys | no | yes, a Strava API application |
| Rate limits | none | yes, Strava's |
| Segments & segment efforts | no | yes |
| Challenges & trophies | no | yes |

**`files` is the default.** It has no external dependencies: your data never leaves your machine, nothing can
rate-limit you, and nothing breaks when an API changes. What you give up is the Strava-only data: segments,
and challenges, none of which is present in an activity file.

> [!IMPORTANT]
> **Important** The two modes are **mutually exclusive**. Dreeve runs in one or the other

<!-- tabs:start -->

#### **Files mode**

```bash
> docker compose exec app bin/console app:cron:run-file-import
```

#### **Strava API mode**

```bash
> docker compose exec app bin/console app:cron:run-strava-import
```

<!-- tabs:end -->

> [!NOTE]
> In `files` import mode the **daemon** container runs the import automatically for you every 5 minutes.
> In `stravaApi` mode you can schedule it from the admin panel under **Settings > Daemon**.

