# Logs

In addition to the default Docker logs, Dreeve maintains its own application logs.
There are several types of logs, each stored in `storage/files/logs` with a 5-day rotation policy. 
These logs can help diagnose and troubleshoot issues.

## Strava API logs

These logs provide a detailed record of every API call made to Strava, including information about your rate limit usage.

### Example

```log
[2025-06-11T19:00:02.127479+00:00] strava-api.INFO: POST - oauth/token - x-ratelimit-limit:  - x-ratelimit-usage:  - x-readratelimit-limit:  - x-readratelimit-usage:  [] []
[2025-06-11T19:00:12.491022+00:00] strava-api.INFO: GET - api/v3/athlete - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 1,1 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 1,1 [] []
[2025-06-11T19:00:32.260245+00:00] strava-api.INFO: GET - api/v3/athlete/activities - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 2,2 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 2,2 [] []
[2025-06-11T19:00:54.976425+00:00] strava-api.INFO: GET - api/v3/athlete/activities - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 3,3 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 3,3 [] []
[2025-06-11T19:01:18.899883+00:00] strava-api.INFO: GET - api/v3/athlete/activities - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 4,4 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 4,4 [] []
[2025-06-11T19:01:43.526294+00:00] strava-api.INFO: GET - api/v3/athlete/activities - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 5,5 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 5,5 [] []
[2025-06-11T19:01:59.056937+00:00] strava-api.INFO: GET - api/v3/athlete/activities - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 6,6 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 6,6 [] []
[2025-06-11T19:02:09.288633+00:00] strava-api.INFO: GET - api/v3/athlete/activities - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 7,7 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 7,7 [] []
[2025-06-11T19:02:19.488767+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 8,8 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 8,8 [] []
[2025-06-11T19:02:29.843466+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 9,9 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 9,9 [] []
[2025-06-11T19:02:40.075241+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 10,10 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 10,10 [] []
[2025-06-11T19:02:50.243120+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 11,11 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 11,11 [] []
[2025-06-11T19:03:00.440590+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 12,12 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 12,12 [] []
[2025-06-11T19:03:11.066157+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 13,13 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 13,13 [] []
[2025-06-11T19:03:21.247486+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 14,14 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 14,14 [] []
[2025-06-11T19:03:32.692451+00:00] strava-api.INFO: GET - api/v3/gear/bxyxyxyxy - x-ratelimit-limit: 200,2000 - x-ratelimit-usage: 15,15 - x-readratelimit-limit: 100,1000 - x-readratelimit-usage: 15,15 [] []
[2025-06-11T19:03:49.575508+00:00] strava-api.INFO: GET - athletes/123456789 - x-ratelimit-limit:  - x-ratelimit-usage:  - x-readratelimit-limit:  - x-readratelimit-usage:  [] []
```

## CLI output logs

These logs capture all output from the CLI, providing a history of your imports and build processes.

### Example

```log
[2025-06-04T05:50:00.646195+00:00] console-output.INFO: Configuring locale... [] []
[2025-06-04T05:50:00.714520+00:00] console-output.INFO: Building Manifest... [] []
[2025-06-04T05:50:00.716234+00:00] console-output.INFO: Building App... [] []
[2025-06-04T05:50:00.716378+00:00] console-output.INFO:   => Building index [] []
[2025-06-04T05:50:02.059219+00:00] console-output.INFO:   => Building dashboard [] []
[2025-06-04T05:50:10.016846+00:00] console-output.INFO:   => Building activities [] []
[2025-06-04T05:50:19.658260+00:00] console-output.INFO:   => Building gpx files [] []
[2025-06-04T05:50:19.857651+00:00] console-output.INFO:   => Building monthly-stats [] []
[2025-06-04T05:50:21.074592+00:00] console-output.INFO:   => Building gear-stats [] []
[2025-06-04T05:50:21.140954+00:00] console-output.INFO:   => Building gear-maintenance [] []
[2025-06-04T05:50:21.182099+00:00] console-output.INFO:   => Building eddington [] []
[2025-06-04T05:50:21.277291+00:00] console-output.INFO:   => Building segments [] []
[2025-06-04T05:50:31.956830+00:00] console-output.INFO:   => Building heatmap [] []
[2025-06-04T05:50:31.987428+00:00] console-output.INFO:   => Building rewind [] []
[2025-06-04T05:50:32.139279+00:00] console-output.INFO:   => Building challenges [] []
[2025-06-04T05:50:32.188401+00:00] console-output.INFO:   => Building photos [] []
[2025-06-04T05:50:32.359225+00:00] console-output.INFO:   => Building badges [] []
[2025-06-04T05:50:32.385393+00:00] console-output.INFO: <info>Time: 31.739s, Memory: 206.50 MB, Peak Memory: 212.50 MB</info> [] []
```

## Daemon logs

These logs capture all output from the Daemon running recurring background tasks.

```log
[2025-11-10T09:28:55.650390+00:00] daemon.INFO:   [] []
[2025-11-10T09:28:55.654875+00:00] daemon.INFO: <fg=black;bg=green>                                                                                                                        </> [] []
[2025-11-10T09:28:55.655126+00:00] daemon.INFO: <fg=black;bg=green> Dreeve v3.9.0 | DAEMON                                                                                  </> [] []
[2025-11-10T09:28:55.655243+00:00] daemon.INFO: <fg=black;bg=green>                                                                                                                        </> [] []
[2025-11-10T09:28:55.655363+00:00] daemon.INFO: <fg=black;bg=green> Started on 10-11-2025 10:28:55                                                                                         </> [] []
[2025-11-10T09:28:55.655504+00:00] daemon.INFO: <fg=black;bg=green>                                                                                                                        </> [] []
[2025-11-10T09:28:55.655637+00:00] daemon.INFO:   [] []
[2025-11-10T09:28:55.655723+00:00] daemon.INFO: <info>No cron items configured, shutting down cron...</info> [] []
```


## Strava webhook logs

These logs capture all incoming notifications from Strava.

```log
[2025-11-10T09:28:55.650390+00:00] webhooks.INFO: Received Strava webhook validation request
[2025-11-10T09:28:55.654875+00:00] webhooks.ERROR: Invalid verify token received                                                                                                                       </> [] []
[2025-11-10T09:28:55.650390+00:00] webhooks.INFO: Received Strava webhook validation request
[2025-11-10T09:28:55.654875+00:00] webhooks.INFO: Validated Strava webhook request 
[2025-11-10T09:28:55.654875+00:00] webhooks.INFO: Received Strava webhook event
```