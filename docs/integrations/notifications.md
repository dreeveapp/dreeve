# Notifications

Dreeve can push a notification to you when something needs your attention. It uses
[Shoutrrr](https://shoutrrr.nickfedor.com/), which speaks to a long list of services (ntfy, Gotify, Discord,
Telegram, Slack, email, and plenty more) through a single URL format.

## What triggers a notification

| Trigger                             | Notes                                                                                            |
|-------------------------------------|--------------------------------------------------------------------------------------------------|
| **A new build of the app just ran** | Your data has been imported and the app has been updated.                                        |
| **Gear maintenance is due**         | A maintenance task has reached its interval. |
| **A new app version is available**  | A newer Dreeve release has been published.                                                       |

## Setup

1. Work out the Shoutrrr **service URL** for your notification service. The
   [Shoutrrr services documentation](https://shoutrrr.nickfedor.com/services/overview/) has the format for
   each one. A few examples:

   ```
   ntfy://ntfy.sh/your-topic
   discord://token@id
   telegram://token@telegram?chats=@channel-1
   gotify://gotify.example.com/token
   ```

2. Add it under **Settings → Integrations**, in the notifications section. You can configure more than one, every notification goes to all of them.

3. Enable the notification jobs you want under **Settings → Daemon**.

## Testing it

You can test your Shoutrrr config by manually triggering a notification:

```bash
> docker compose exec app shoutrrr send -v --url="ntfy://ntfy.sh/your-topic" --message="Hello" --title="Test"
```

## ntfy.sh with an authentication token

If you are:

* Running a locally hosted NTFY server
* Requiring authentication to send notifications
* Using an authentication token

You may notice that the Shoutrrr documentation does not clearly explain how to format the notification URL in this scenario. Use the following format:

```
ntfy://:your-authentication-token@ntfy.example.com/your-topic
```

> [!IMPORTANT]
> **Important** Make sure to include the colon (:) before the authentication token. Omitting the colon will cause authentication to fail.
