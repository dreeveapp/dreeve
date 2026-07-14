#  Local setup

Run the following commands to setup the project on your local machine

```bash
> git clone git@github.com:your-name/your-fork.git
> make composer arg="install --no-scripts"
> make up
```

Everytime you make changes to the app, you need to build the html files again

```bash
> make console arg="app:cron:run-file-import --build"
```

(In `stravaApi` mode, use `app:cron:run-strava-import --build`.)