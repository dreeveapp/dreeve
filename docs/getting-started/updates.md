# Updates

> [!NOTE]
> Coming from **Statistics for Strava v4**? That upgrade needs a few manual steps, follow
> [Migrating from v4 to v5](/getting-started/migrating-from-v4.md) instead of the instructions below.

When a new version of the app is released, pull the latest Docker image:

```bash
> docker compose pull # if available, pull a new image
> docker compose up -d # start new containers using the compose config and the newly pulled image
```

> [!WARNING]
> * **Backup before updates**: always back up your Docker volumes, in particular `storage/database`, before upgrading.
> * **Check the release notes**: check the [changelog](/changelog.md) to see whether there are any breaking changes.
