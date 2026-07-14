# Importing challenges and trophies

Strava does not allow to fetch a complete history of your completed challenges and trophies via the API.
There's a little workaround if you'd still like to import these:
* Navigate to https://www.strava.com/athletes/[YOUR_ATHLETE_ID]/trophy-case
* Open the page's source code and copy everything
* Make sure you save the source code to the file `./storage/files/strava-challenge-history.html`
* On the next import, all your challenges will be imported

![Trophy case source code](../assets/images/trophy-case-source-code.png)

> [!WARNING]
> **Warning** Make sure before you save the source code, your Strava account is set to be translated in English.
The app can only handle this export in **English** for now.
