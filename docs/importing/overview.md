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

## An import is always followed by a build

**Dreeve's frontend is pre-rendered static HTML.** Importing an activity writes it to the database, but it does
*not* make it show up in the app. The pages are only regenerated when a **build** runs.

```bash
# Import new activities, then rebuild the app
# In files mode (the default)
> docker compose exec app bin/console app:cron:run-file-import --import --build

# In stravaApi mode
> docker compose exec app bin/console app:cron:run-strava-import --import --build
```

> [!NOTE]
> The same applies after changing settings in the admin panel: a build has to run before you see the effect.
> The **daemon** container does this automatically for you every 5 minutes if you run the app in `file` import mode.

