# Strava authorization

During the authorization process with Strava, as well as when using the API, you may encounter various errors 
Below is a list of common issues and their solutions.

<!-- tabs:start -->

#### **Invalid permissions**

When you attempt to access Strava data without the necessary permissions, 
you may receive an error message indicating that the required permissions are missing.
This typically occurs when the access token does not have the required scopes for the requested data 
and usually means that you didn't finish the authorization process correctly. 

Be sure to [complete the authorization flow](getting-started/installation.md?id=obtaining-a-strava-refresh-token) and copy/paste the access token to your `.env` file correctly. 
When you have done so, restart your Docker container to apply the changes.

```json
{
  "message": "Authorization Error",
  "errors": [
    {
      "resource": "AccessToken",
      "field": "activity:read_permission",
      "code": "missing"
    }
  ]
}
```

#### **Invalid client_id**

When you encounter an error related to the `client_id`, 
it usually means that the client ID provided in your `.env` file is incorrect, 
or you did not recreate your Docker container after updating the `.env` file. 
Make sure that you have done both of these steps correctly.

```json
{
  "message": "Bad Request",
  "errors": [
    {
      "resource": "Application",
      "field": "client_id",
      "code": "missing"
    }
  ]
}
```

#### **Invalid redirect_uri**

If you receive an error regarding the `redirect_uri`, this probably means that you misconfigured 
the `Website` and `Authorization Callback Domain` in your Strava application settings. 
Ensure that the `Website` and `Authorization Callback Domain` match the URL/domain you are using to access your application.

```json
{
  "message": "Bad Request",
  "errors": [
    {
      "resource": "Application",
      "field": "redirect_uri",
      "code": "invalid"
    }
  ]
}
```

#### **Inactive application**

If Strava responds with a `403 Forbidden` and the error below, your refresh token is fine,
but the Strava API application itself no longer has access to the API.
This happens when the application is deactivated or when your Strava API subscription lapses.

To resolve this, either reactivate the application on [Strava's API settings page](https://www.strava.com/settings/api),
or switch to file import by setting `IMPORT_MODE=files` in your `.env` file and recreating your Docker container.

```json
{
  "message": "Forbidden",
  "errors": [
    {
      "resource": "Application",
      "field": "Status",
      "code": "Inactive"
    }
  ]
}
```

#### **500 Internal Server Error**

If you receive an error like the following:

```
Strava API threw error: Server Error: 'GET https://www.strava.com/api/v3/activities/[activityid]' restulted in a `500 Internal Server Error` response: 
{"message":"error"}
```

Try accessing the activity in a browser by copying the ID from the error message and navigating to: https://www.strava.com/activities/[activityid].
If the activity is accessible, then it's likely corrupted on Strava's end. You have two options:

1. **Update the activity**: for example, by changing the private note or title. Then try importing it again.
2. **Skip the activity from being imported**: add its ID to **Activities to skip during import** under
   [Settings → Import](/admin/settings.md#import).

<!-- tabs:end -->
