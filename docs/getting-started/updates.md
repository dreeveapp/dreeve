# Updates

> [!NOTE]
> Coming from **Statistics for Strava v4**? That upgrade needs a few manual steps, follow
> [Migrating from v4 to v5](/getting-started/migrating-from-v4.md) instead of the instructions below.

When a new version of the app is released, pull the latest Docker image:

```bash
> docker compose pull # if available, pull a new image
> docker compose up -d # start new containers using the compose config and the newly pulled image
```

After that, run an import and build to regenerate the app with the new version:

```bash
# In files mode (the default)
> docker compose exec app bin/console app:cron:run-file-import --import --build

# In stravaApi mode
> docker compose exec app bin/console app:cron:run-strava-import --import --build
```

> [!WARNING]
> * **Backup before updates**: always back up your Docker volumes, in particular `storage/database`, before upgrading.
> * **Check the release notes**: check the [changelog](/changelog.md) to see whether there are any breaking changes.
