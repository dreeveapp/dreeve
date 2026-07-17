# Automation rules

Automation rules let you automatically fill in details on newly imported activities, straight from the
admin panel — no YAML required. Each rule is a list of **conditions** and a list of **actions**:

- **Tracked with device X → assign gear Y**
- **Tracked with device Y _and_ shorter than 10 km → mark as commute**

> [!NOTE]
> Automation rules are only available (and only run) when running in **file import mode**
> (`IMPORT_MODE=files`). They are managed under **Automation rules** in the admin panel.

---

## How it works

* Rules run **only against newly imported activities**. They fill in blanks — gear, commute flag,
  sport type, workout type, name and description — that the raw activity file doesn't already provide.
* Rules are evaluated **top to bottom**. The **first** rule whose conditions **all** match fires its
  actions, and evaluation stops. Drag rules to change their order.
* All conditions within a rule are combined with **AND**. A rule needs at least one condition and at
  least one action.
* Only **enabled** rules run. Existing, already-imported activities are never touched — saving a rule
  only affects future imports.

## Conditions

| Condition | Matches on |
|---|---|
| **Recording device** | The device the activity was recorded with. Type a device id or pick one from the list of devices seen on previous imports. |
| **Sport type** | The activity's sport type is (or is not) one of the selected types. |
| **Distance** | The activity distance (in kilometers) compared with a value. |
| **Weekday** | The activity started on one of the selected weekdays. |
| **Time of day** | The activity started before/after/at a given time of day. |
| **Starts near** | The activity's starting point lies within (or outside) a radius of a coordinate. |
| **Ends near** | The activity's finishing point lies within (or outside) a radius of a coordinate. |
| **Passes near** | Any point of the route comes near (or the whole route stays away from) a coordinate. |

Distances and radii are expressed in **kilometers**. Coordinates are decimal degrees
(e.g. latitude `51.0543`, longitude `3.7174`).

## Actions

| Action | Effect |
|---|---|
| **Assign gear** | Attaches the selected (non-retired) gear to the activity. |
| **Mark as commute** | Flags the activity as a commute. |
| **Set sport type** | Overrides the activity's sport type. |
| **Set workout type** | Sets the activity's workout type. |
| **Set name** | Sets the activity's name. |
| **Set description** | Sets the activity's description. |

## Example

To assign your commuter bike to short rides recorded with a specific device:

1. Go to **Automation rules** → **Add rule**.
2. Give it a name, e.g. _Commuter bike on short rides_.
3. Add conditions: **Recording device** _is_ `garmin-edge-130`, and **Distance** _less than_ `10`.
4. Add actions: **Assign gear** → your commuter bike, and **Mark as commute**.
5. Save. The next matching file import is tagged automatically.

## Testing rules

Not sure a rule will fire the way you expect? Use **Test rules** (top-right of the **Automation rules**
overview) to dry-run your rules against an **existing** activity — nothing is saved, and the activity is
never modified.

Enter an activity id and the page shows, for every rule:

* each condition with a **✓** (matched) or **✗** (did not match) against that activity, so you can see
  exactly *why* a rule did or didn't apply;
* the **winning** rule (the first enabled rule whose conditions all match) highlighted with an **Applies**
  badge;
* rules after the winner marked **Skipped**, mirroring the first-match-wins order used during import;
* each rule's actions with the value they would set (e.g. _Set name (Morning commute)_,
  _Assign gear (Gravel bike)_).

It's the quickest way to debug a rule that isn't behaving, without re-importing anything.
