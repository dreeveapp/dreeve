# [v5.0.0](https://github.com/dreeveapp/dreeve/releases/tag/v5.0.0) - 2026-07-19

It has taken a while, but `v5.0.0` is finally here.
This is the biggest release since the project began as Statistics for Strava.

> [!WARNING]
> **Back up first.** Make a copy of `storage/database` **and** of your `config` directory before you upgrade to this new version.
> The migration guide will walk you through the upgrade process:: https://docs.dreeve.app/#/getting-started/migrating-from-v4

Here are the three biggest changes:

## 1. Welcome to `Dreeve`

The project has been rebranded to `Dreeve`

A `dreve` is the West-Flemish word (the birthplace of Dreeve's maintainer) for a country road lined with trees on both sides.
the kind of road every cyclist and runner loves.

## 2. No more Strava dependency

You no longer need Strava to use Dreeve.

The app allows you to upload raw FIT, TCX and GPX files.
If you already use Strava, importing your activities from Strava is still fully supported.

## 3. The admin panel

Configuration is now much simpler.

All settings have been moved from YAML configuration files into a built-in admin panel, 
making Dreeve easier to configure and maintain.


For detailed upgrade instructions, see the migration guide: https://docs.dreeve.app/#/getting-started/migrating-from-v4

## Technical details
* ISSUE #2174: Enable sqlite journal_mode=WAL by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2176
* ISSUE #2177: Fix a bug where build crashes when an activity has no name by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2178
* ISSUE #2179: Improve DuplicateActivityScanner to better detect duplicates by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2180
* ISSUE #2173: Provide user authentication by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2175
* ISSUE #2181: Allow to upload activity files via the admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2182
* ISSUE #2183: Add an overview of all file imports to the admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2184
* ISSUE #2187: Drop pre-calculated distanceInMeters on the Gear entity by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2188
* ISSUE #2189: Persist gear purchase prices to database by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2190
* Bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/dreeveapp/dreeve/pull/2192
* ISSUE #2186: Allow to manage gear via the admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2191
* ISSUE #2193: Allow to manage activities via the admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2194
* ISSUE #2195: Allow to enable demo mode based on user auth instead of IP by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2196
* ISSUE #2197: Remove all occurrences of Statistics for Strava by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2198
* ISSUE #2199: Allow to manage devices via the admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2201
* ISSUE #2197: Remove all occurrences of Statistics for Strava by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2203
* ISSUE #2200: Move gear maintenance config to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2202
* ISSUE #2208: Show a (toast) succes message after submitting a form by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2209
* ISSUE #2205: Show an indicator in the admin panel that changes still need to be processed by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2210
* fix(i18n): translate remaining Italian locale strings by @franzudev in https://github.com/dreeveapp/dreeve/pull/2213
* fix(notification): avoid unsupported Shoutrrr params for Telegram by @franzudev in https://github.com/dreeveapp/dreeve/pull/2214
* German translation by @effectpears in https://github.com/dreeveapp/dreeve/pull/2207
* ISSUE #2211: Move dashboard configuration to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2212
* ISSUE #2215: Move appUrl config to .env file by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2216
* ISSUE #2217: Move "general" config from YAML to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2218
* ISSUE #2221: Use a CachedSettingsRepository when building static HTML files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2222
* ISSUE #2219: Move "appearance" config from YAML to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2220
* ISSUE #2219: Render sportTypesSortingOrder on appearance settings form by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2223
* ISSUE #2225: Move 'import' config from YAML to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2226
* ISSUE #2227: Move 'metrics' config from YAML to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2228
* ISSUE #2230: Import fails on long activities: route WKT exceeds MAX_ARG_STRLEN in geosop call ('Failed to run geosop') by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2235
* ISSUE #2233: When migrating settings to db, merge yaml files into one consolidated settings file by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2236
* ISSUE #2229: Move 'integrations' config from YAML to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2232
* ISSUE #2237: Move 'Daemon' config from YAML to admin panel by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2238
* ISSUE #2239: Clear cron locks when containers are restarted by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2240
* ISSUE #2241: specific name for GPX download files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2242
* ISSUE #2224: Move translatables from admin panel to separate translation file by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2245
* ISSUE #2243: Improve onboarding flow by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2246
* ISSUE #2251: Harden database migrations and concurrency of containers by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2252
* ISSUE #2243: Onboarding flow - bis by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2250
* Update Brazilian Portuguese translation files by @gustavobim in https://github.com/dreeveapp/dreeve/pull/2254
* #2255 Improvement of German Translation by @effectpears in https://github.com/dreeveapp/dreeve/pull/2256
* ISSUE #2248: Rewrite documentation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2257

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.8...v5.0.0

# [v4.8.8](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.8) - 2026-06-14

## Improvements
* Moved database migrations away from the codebase to the Docker entrypoint
* Redesigned onboarding flow
* Updated German translations
* Updated Portuguese translations

## Bug fixes
* Fixed a bug where filters on the photos page was not persisted between page loads

## Technical details
* ISSUE #2142: Introduce an isolated service to detect duplicate activities before importing by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2143
* ISSUE #2141: Move the app away from Strava branding by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2144
* ISSUE #2133: Add new Activity file import pipeline by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2145
* ISSUE #2146: Add periodic file imports to the daemon container by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2147
* ISSUE #2152: Rename database, but fallback to existing strava.db file by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2153
* ISSUE #2151: Only render link to Strava activity if activity got imported through Strava API by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2154
* ISSUE #2156: Filters on the photos page are not persisted to localStorage by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2157
* ISSUE #2149: Remove references to kudo's and comments by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2158
* ISSUE #2160: Improve import flow and get rid of unnecessary cron complexity by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2162
* ISSUE #2161: Improve file parser tests by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2163
* ISSUE #2155: Move database migrations to docker entrypoint by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2159
* ISSUE #2164: Import athlete from file config by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2165
* ISSUE #2150: Restyle onboarding flow by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2166
* Update German translations in intl-icu file by @effectpears in https://github.com/dreeveapp/dreeve/pull/2170
* Update messages+intl-icu.pt_BR.yaml by @gustavobim in https://github.com/dreeveapp/dreeve/pull/2168

## New Contributors
* @gustavobim made their first contribution in https://github.com/dreeveapp/dreeve/pull/2168

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.7...v4.8.8

# [v4.8.7](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.7) - 2026-06-08

## Bug fixes
* Fixed an issue where the import crashes for manually added activities

## Technical details
* ISSUE #2128: Add config option 'import mode' in preparation of multi-source imports by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2130
* ISSUE #2129: Add new 'FileImport' entity to keep track of all file imports by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2131
* ISSUE #2133: Add new Activity file import pipeline by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2134
* ISSUE #2132: Import crashes on activities with null external_id by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2137
* Fix CI/CD by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2138

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.6...v4.8.7

# [v4.8.6](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.6) - 2026-06-04

## New features
* Allow to configure all maps instead of the heatmap only

> [!WARNING]  
> We slightly altered the configuration of the heatmap to take all maps into account.
> 
> Configuration before:
> ```yaml
>appearance:
>  heatmap:
>    polylineColor: '#fc6719'
>    tileLayerUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
>    enableGreyScale: true
>    initialCenter: null
>    initialZoom: null
> ```
> 
> Configuration now:
> ```yaml
>appearance:
>  maps:
>    polylineColor: '#fc6719'
>    tileLayerUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
>    enableGreyScale: true
>    heatmap:
>      initialCenter: null
>      initialZoom: null
> ```

These changes are reflected in the documentation: https://docs.dreeve.app/#/configuration/main-configuration

## Improvements
* Allow for activity descriptions to be expanded when clicking them

## Bug fixes
* Fix buggy behaviour when using the browser's back button

## Technical details
* ISSUE #2113: Fix buggy behaviour when using the browser's back button by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2118
* Added note to the docs informing users about Strava API policy by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2120
* ISSUE #2114: Leaflet map settings by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2119
* ISSUE #2114: Allow for descriptions to be expanded by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2122
* ISSUE #2123: Move manifest.json to build folder by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2124
* ISSUE #2125: Add ImportSource and ExternalReferenceId on Activity entity in preparation of multi-source imports by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2126

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.5...v4.8.6

# [v4.8.5](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.5) - 2026-06-01

## New features
* Added activity elapsed time to the activity detail page

## Improvements
* Improved application security by tightening file access rules
* Improved accessibility of the mobile navigation menu

## Bug fixes
* Fixed and refined Caddy server directives, with additional test coverage to prevent future regressions

## Technical details
* ISSUE #2104: Harden security by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2105
* ISSUE #2106: Add button state to the mobile menu trigger by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2108
* ISSUE #2107: Fix Caddy directives and add proper tests by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2109
* ISSUE #2110: Activity elapsed time by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2111

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.4...v4.8.5

# [v4.8.4](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.4) - 2026-05-29

> [!WARNING]  
> This release fixes an issue where log files stored under `storage/files/logs` could be accessed through the web server on publicly exposed SfS instances.
> While the logs did not contain sensitive user data, they were never intended to be publicly accessible. Access to these files is now properly blocked.
> Security and privacy are taken very seriously, and we apologize for not catching this earlier.

> [!NOTE]  
> We revisited the way training load is calculated and discovered that the current implementation was incorrect in certain cases.
> As a result, users with many heart rate-based activities may notice significant changes in their training load numbers after updating.

## Technical details
* ISSUE #2093: Cadence distribution chart bars do not connect by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2097
* Added anonymous analytics for docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2098
* ISSUE #2033: Normalize daily TSS and daily TRIMP by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2100
* ISSUE #2101: Allow demo to serve anonymized image by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2102

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.3...v4.8.4

# [v4.8.3](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.3) - 2026-05-28

## Bug fixes
* Fixed rendering of previous milestones in the milestone dashboard widget
* Convert all measurements to proper unit system

## Technical details
* ISSUE #2082: Convert all measurements to proper unit system by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2083
* ISSUE #2085: Milestone dashboard widget does not render previous milestones correctly by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2086
* ISSUE #2087: Bundle flowbite in app.min.js by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2088
* ISSUE #2089: New Shoutrrr release - v0.15.1 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2090
* ISSUE #2094: Reference to new domain dreeve.app by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2095

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.2...v4.8.3

# [v4.8.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.2) - 2026-05-26 

## Improvements
* A lot of small UX improvements throughout the app
* Use table header labels instead of icons

## Bug fixes
* Properly format GAP pace for running activities

## Technical details
* ISSUE #2062: Daytime dashboard widget disable text wrap by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2063
* ISSUE #2064: Yearly stats dashboard widget disable text wrap by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2065
* ISSUE #2068: Use table header labels instead of icons by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2069
* fix: format GAP like pace by @cgtobi in https://github.com/dreeveapp/dreeve/pull/2071

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.1...v4.8.2

# [v4.8.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.1) - 2026-05-23

## Bug fixes
* Fixed ChunkLoadError: Loading chunk failed on instances served behind Cloudflare, where dynamically loaded JS chunks were being requested from the wrong
  URL due to interference from the Cloudflare Insights beacon.

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.8.0...v4.8.1

# [v4.8.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.8.0) - 2026-05-23

## Improvements
* Added support for the PhysicalTherapy sport type
* Redesigned the activity detail page
* Redesigned the sidebar navigation
* Fixed dark mode styling issues throughout the app

## Bug fixes
* Fixed an issue where the dropdown active state was not updating correctly when filtering.

## Technical details
* ISSUE #2047: Fix dropdown active state when filtering by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2049
* ISSUE #2050: Add support for sport type PhysicalTherapy by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2052
* ISSUE #2048: Redesign of the activity detail page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2051
* ISSUE #2050: Follow up fixes for activity redesign by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2054
* ISSUE #2053: Remove average heart rate from splits by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2055
* ISSUE #2050: Followup fixes for activity redesign by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2056
* ISSUE #2057: Small UX improvements throughout the app by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2058
* ISSUE #2057: Fix gear maintenance dark mode UI by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2059

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.12...v4.8.0

# [v4.7.12](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.12) - 2026-05-14

## What's Changed
* Fix date column wrapping with non-default date formats by @czdanika in https://github.com/dreeveapp/dreeve/pull/2039
* Reverted ISSUE #2033: Normalize daily TSS and daily TRIMP by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2034

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.11...v4.7.12

# [v4.7.11](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.11) - 2026-05-13

## New Features
* Added kilojoules (kJ) to the activity detail page

## Improvements
* Updated the German (de_DE) ICU translation file
* Added documentation for installing via HelmForge on Kubernetes
* Added a new "Current year" rewind option
* Normalized daily TSS and TRIMP calculations

## Bug fixes
* Fixed the dropdown menu not closing when clicking a link
* Fixed GAP calculation issues caused by repeated identical GPS coordinates

## Technical details
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/2026
* docs: add HelmForge Kubernetes install guide by @mberlofa in https://github.com/dreeveapp/dreeve/pull/2024
* feat: Add YTD rewind option by @paquiro in https://github.com/dreeveapp/dreeve/pull/2029
* ISSUE #2033: Normalize daily TSS and daily TRIMP by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2034
* ISSUE #2027: Show kilojoules on activity detail page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2036
* ISSUE #2035: We need to close the dropdown menu when clicking a link by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2037
* fix GAP for repeated moving coordinates by @cgtobi in https://github.com/dreeveapp/dreeve/pull/2031

## New Contributors
* @mberlofa made their first contribution in https://github.com/dreeveapp/dreeve/pull/2024
* @paquiro made their first contribution in https://github.com/dreeveapp/dreeve/pull/2029

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.10...v4.7.11

# [v4.7.10](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.10) - 2026-05-07

## Improvements
* Use non-breaking space as thousands separator to prevent line wrapping
* Allow to set Zwift level over 100
* Improve split GAP calculation and values

## Technical details
* fix: use non-breaking space as thousands separator to prevent line wrapping by @vrkrebs in https://github.com/dreeveapp/dreeve/pull/2015
* ISSUE #2016: Allow to set Zwift level over 100 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2017
* ISSUE #2018: Use docker compose CMD instead of ENTRYPOINT by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2019
* fix: stabilize split GAP values and add GAP recalculation command by @cgtobi in https://github.com/dreeveapp/dreeve/pull/1997

## New Contributors
* @vrkrebs made their first contribution in https://github.com/dreeveapp/dreeve/pull/2015

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.9...v4.7.10

# [v4.7.9](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.9) - 2026-05-04

## New features
* SFS Docker image is now also published on the GitHub Container Registry.
* Added a Hungarian (hu_HU) translation.
* Added an option to configure the heatmap to load with a fixed viewport.

```yaml
appearance:
  heatmap:
    # Optional, sets the initial center point of the heatmap as [latitude, longitude].
    # When set together with initialZoom, the map will use this fixed viewport instead of auto-fitting to your most active area.
    # 🔥 PRO tip: You can easily get the latitude and longitude by navigating to a location in Google Maps. The coordinates are shown in the URL.
    # Leave empty to auto-fit.
    initialCenter: null
    # initialCenter: [51.05, 3.72]
    # Optional, sets the initial zoom level of the heatmap (1-18, where 1 is fully zoomed out and 18 is street-level).
    # Must be used together with initialCenter.
    initialZoom: null
    # initialZoom: 12
```

## Improvements
* Added winter sports to the Yearly stats chart.
* Added pre-configured quick links to the date filter options.

## Bug fixes
* Fixed the incorrect “Best Efforts YTD” label.
* Fixed an issue where the label of the rightmost data point in weekly stats was partially cut off.
* Fixed pace rounding in the Segments charts.

## Technical details
* ISSUE #1996: Update docs with Github container registry reference by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1998
* Add Hungarian (hu_HU) translation by @czdanika in https://github.com/dreeveapp/dreeve/pull/2002
* ISSUE #2001: Fixed best efforts YTD label by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2003
* ISSUE #1999: Weekly stats, data label of rightmost data point is partially cutoff by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2004
* ISSUE #2000: Pace rounding in Segments charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2005
* ISSUE #2006: Add winter sports to Yearly stats chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2007
* ISSUE #2008: Add pre-configured links to the date filters by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2010
* ISSUE #2009: Allow to configure heatmap to load on fixed view port by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/2011

## New Contributors
* @czdanika made their first contribution in https://github.com/dreeveapp/dreeve/pull/2002

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.8...v4.7.9

# [v4.7.8](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.8) - 2026-04-28

## Bug fixes
* ISSUE #1993: An exception has been thrown during the rendering of a template by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1994

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.7...v4.7.8

# [v4.7.7](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.7) - 2026-04-27

## New features
* Introduced Grade Adjusted Pace (GAP) for running activities
* Training goal widget allows goals to be configured based on a specific date range

```yaml
'config': {
  'goals': {
    'weekly': [
      { label: 'Cycling', enabled: true, type: 'distance', unit: 'km', goal: 200,  restrictToDateRange: {from: '2026-01-01', to: '2026-03-31'} }
    ],
  },
}
```

https://docs.dreeve.app/#/configuration/dashboard-widgets?id=traininggoals

## Bug fixes
* Fixed cadence accuracy for running and walking activities by correctly doubling the cadence value where needed.

## Technical details
* ISSUE #1974: Cadence needs to be doubled for runs and walks by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1978
* feat(translations): more fr by @christian-vdz in https://github.com/dreeveapp/dreeve/pull/1972
* ISSUE #1976: Provide GitHub Container Packages as alternative to dock… by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1977
* ISSUE #1979: Calculate and display Grade Adjusted Pace (GAP) for running activities by @cgtobi https://github.com/dreeveapp/dreeve/pull/1981
* ISSUE #1984: Move activity split average heart rate calculation to CalculateActivityMetrics pipeline by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1989
* ISSUE #1988: Training goal based on date range by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1991

## New Contributors
* @cgtobi made their first contribution in https://github.com/dreeveapp/dreeve/pull/1981

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.6...v4.7.7

# [v4.7.6](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.6) - 2026-04-13

## New features
* Added a new Cadence Distribution chart to better visualize cadence data
* Added configuration support to exclude specific activities from Peak Power Outputs calculations.

```yaml
metrics:
  # An array of activity ids to exclude from peak power outputs. 
  # This allows you to blacklist specific activity IDs from the peak power calculation.
  # ["123456789", "987654321"]
  excludeActivitiesFromPeakPowerOutputs: []
```

## Improvements
* Added French translations (thanks to @christian-vdz).
* Added zoom/slider controls to the FTP dashboard widget for easier navigation.
* Improved Athlete Weight History tooltip formatting for clearer and more consistent display.

## Bug fixes
* Fixed activity sorting issues where ordering by distance and pace could be slightly incorrect.
* Fixed challenge imports by removing duplicate imported challenges.

## Technical details
* feat(translations): new fr by @christian-vdz in https://github.com/dreeveapp/dreeve/pull/1951
* ISSUE #1960: Sorting Activities by Distance and Pace is slightly off by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1961
* ISSUE #1963: Remove duplicate imported challenges by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1964
* ISSUE #1958: Zoom/Slider for FTP dashboard widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1965
* ISSUE #1957: Allow excluding specific activities from Peak Power Outputs via config by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1966
* ISSUE #1959: Distribution Chart - Cadence by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1967
* ISSUE #1968: AthleteWeightHistoryChart tooltip formatting by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1970

## New Contributors
* @christian-vdz made their first contribution in https://github.com/dreeveapp/dreeve/pull/1951

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.5...v4.7.6

# [v4.7.5](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.5) - 2026-04-01

## Improvements
* Updated Italian translations: Improved and corrected several Italian UI translations.
* Enhanced Power Output chart: The power output chart has been improved for better readability and accuracy.

## Bug fixes
* Fixed crash related to missing indices: Resolved an issue that caused errors like "Couldn't read the indices [Names][XK]".
* Fixed milestone duration formatting: Previous milestone durations are now displayed in the correct format.
* Fixed speed distribution graph for imperial unit system.

## Technical details
* ISSUE #1931: Fix milestones format previous durations by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1932
* ISSUE #1930: Speed Distribution is not hitting X-Axis properly by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1933
* chore(i18n): improve Italian translations by @franzudev in https://github.com/dreeveapp/dreeve/pull/1934
* ISSUE #1937: Improve power output chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1938
* ISSUE #1944: Couldn't read the indices [Names][XK] by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1945

## New Contributors
* @franzudev made their first contribution in https://github.com/dreeveapp/dreeve/pull/1934

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.4...v4.7.5

# [v4.7.4](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.4) - 2026-03-23

## New features
* Added a new milestone: your first activity in a new country is now tracked.

## Improvements
* Redact AI API keys and improve the documentation.
* Improved how time is displayed for Personal Best milestones.
* You can now open an activity’s detail page by clicking its image on the Photos page.
* Updated German translations.
* Fixed a layout issue so recent milestones now align properly, even when other widgets in the same row are taller.

## Bug fixes
* Fixed an issue in the Workout Assistant by switching to relative URLs.

## Technical details
* Docs: FAQ improvement by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1907
* ISSUE #1909: Use relative URLs while using the workout assistant by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1910
* ISSUE #1908: Add AI api key to redactions + improve AI docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1911
* ISSUE #1916: Add first activity in a new country as a new milestone by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1917
* ISSUE #1915: Milestones - Personal Bests: time notation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1918
* ISSUE #1914: Open activity detail when clicking an image on the Photos page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1919
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/1920
* ISSUE #1923: Make Most recent milestones same height when another widget on same row is taller by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1924

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.3...v4.7.4

# [v4.7.3](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.3) - 2026-03-17

## Improvements
* Added extra filters to the segment overview page
* Introduced relative cost per distance unit for gear and recording devices

## Bug fixes
* Fixed a division by zero issue in weekday statistics

## Technical details
* ISSUE #1897: Extra filters on the segment overview page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1898
* ISSUE #1899: Relative cost per km by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1900
* ISSUE #1901: Filter segment page for a specific period on last effort… by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1902
* ISSUE #1904: Division by zero in WeekdayStats.php by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1905

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.2...v4.7.3

# [v4.7.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.2) - 2026-03-14

## New features
* Average gradient on segments page

## Improvements
* Multi-select filters for heatmaps
* Updated Shoutrrr to v0.14.0
* German translation updates

## Bug fixes
* Fixed pace distribution for imperial units

## Technical details
* ISSUE #1867: Add average gradient on segments page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1874
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/1883
* ISSUE #1884: Update Shoutrrr to v0.14.0 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1885
* ISSUE #1887: Sunset challenge import from public profile by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1888
* ISSUE #1891: Fix pace distribution issue for imerial system by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1894
* ISSUE #1893: Allow heatmap activities / filters to be multi-select by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1895

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.1...v4.7.2

# [v4.7.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.1) - 2026-03-09

## New features
* Added a new dashboard widget showing your most recent milestones.

## Improvements
* Improved the descriptions for gear milestones.
* Improved the mobile layout for milestones.
* Improved milestone comparisons.
* Updated the German translation file.

## Technical details
* ISSUE #1852: Improve gear milestone descriptions by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1853
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/1858
* ISSUE #1854: Dashboard widget for most recent milestones by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1859
* ISSUE #1861: Improve milestones mobile layout by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1862
* ISSUE #1863: Switch var annotations in favour of asserts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1864
* ISSUE #1865: Improve milestone comparisons by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1868
* ISSUE #1865: Supported providers link broken by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1871

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.7.0...v4.7.1

# [v4.7.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.7.0) - 2026-03-05

## New features
* Added a timeline view of your key achievements and milestones over time.

## Bug fixes
* Fixed a calculation bug that caused the yearly statistics graph to incorrectly drop downward.

## Technical details
* ISSUE #1837: Milestones timeline by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1839
* ISSUE #1848: Yearly Stats graph calculation error - went downward by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1850

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.8...v4.7.0

# [v4.6.8](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.8) - 2026-03-03

## New features
* Added [statistics for recording devices](https://docs.dreeve.app/#/configuration/main-configuration), giving you more insight into which devices you use for your activities.

```yaml
gear:
  recordingDevices:
    - gearId: garmin-forerunner-265
      purchasePrice:
        amountInCents: 29950
        currency: EUR
```

## Improvements
* Made submenu items consistent across the entire app for a more uniform user experience.
* Reorganized the src/Infrastructure directory to improve project structure and maintainability.

## Technical details
* ISSUE #1826: Rearrange src/Infrastructure by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1827
* ISSUE #1833: Submenu items should be consistent throughout the app by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1834
* ISSUE #1835: Recording devices stats by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1836
* ISSUE #1841: Move rewind svg icons from twig files to SvgsTwigExtension by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1842
* ISSUE #1819: Update neuron-core/neuron-ai to v3 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1820

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.7...v4.6.8

# [v4.6.7](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.7) - 2026-02-26

## New features
* You can now view Eddington metrics in both metric and imperial units, regardless of your default unit system.

## Bug fixes
* Fixed a small visual issue where Y-axis labels were slightly clipped on charts.
* Resolved a bug in the Athlete Weight History chart where the unit system wasn’t applied correctly.
* Fixed an alignment issue with elevation data in the "Most Recent Activities" widget.
* Corrected inverted colors in the Segment Effort vs Heart Rate chart.
* Fixed an issue where the pace tooltip displayed incorrect values in the Combined Stream Profile charts.

## Technical details
* ISSUE #1803: Allow to view Eddington metrics for both unit systems by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1813
* ISSUE #1808: Fix slight clipping of Y-Labels by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1814
* ISSUE #1815: Fix AthleteWeightHistoryChart unit system bug by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1817
* ISSUE #1816: widget:mostRecentActivities - fix elevation alignment by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1818
* ISSUE #1821: Segment effort vs heart rate chart inverted colors by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1822
* ISSUE #1823: Fix pace tooltip in CombinedStreamProfileCharts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1824

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.6...V4.6.7

# [v4.6.6](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.6) - 2026-02-24

## What's Changed
* ISSUE #1763: Add SegmentEffort vs heart rate scatter chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1788
* ISSUE #1778: Make database migration more memory efficient by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1794
* ISSUE #1795: Add new sports types to docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1798
* ISSUE #1797: Add config to activityGrid to arrange metricsDisplayOrder by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1799
* ISSUE #1801: Fix pace Y axis label on activity metric charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1804
* ISSUE #1802: Add option to redact sensitive info to app:debug:environment console command by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1805

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.5...v4.6.6

# [v4.6.5](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.5) - 2026-02-21

> [!NOTE]  
> This release introduces another data compression, reducing the disk space used by your database.
> Always create a backup of your database before performing an upgrade.

## What's Changed
* ISSUE #1760: Add distance and elevation range filters to the activity overview by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1764
* ISSUE #1761: Add support for new Strava sport types by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1768
* ISSUE #1773: Improve gear maintenance docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1774
* ISSUE #1766: Move calculated stream values to separate ActivityStreamMetric entity by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1777
* ISSUE #1766: If available, use coordinate stream to display leaflet polylines by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1780
* ISSUE #1778: Compress ActivityStream data to reduce DB size by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1781

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.4...v4.6.5

# [v4.6.4](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.4) - 2026-02-16

This release fixes a critical issue in the Daemon that was introduced in `v4.6.3`

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.3...v4.6.4

# [v4.6.3](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.3) - 2026-02-15

> [!NOTE]  
> This release primarily focuses on data compression, reducing the disk space used by your build directory and database.

## What's Changed
* ISSUE #1742: Fix duplicate rendering of gear maintenance rows by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1743
* ISSUE #1746: Re-arrange javascript files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1747
* ISSUE #1745: Introduce compressed API calls to reduce file sizes by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1748
* ISSUE #1745: Introduce compressed API calls to render charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1749
* ISSUE #1745: compress gpx files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1753
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/1752
* ISSUE #1754: Reduce Docker image size by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1755

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.2...v4.6.3

# [v4.6.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.2) - 2026-02-11

Another day, another release improving the activity metric charts.

## What's Changed
* ISSUE #1735 & #1736: Activity metrics chart improvements by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1740

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.1...v4.6.2

# [v4.6.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.1) - 2026-02-10

> [!NOTE]  
> This release needs to recalculate the metrics chart on the activity detail pages.
> Your next import may take a bit longer, but subsequent imports will run at normal speed.

### Revamped metric charts on activity detail pages

* Charts are now zoomable
* Charts now use the full raw data instead of simplified data

## What's Changed
* ISSUE #1724: Dark mode: prevent 'flash' on page load by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1725
* Adding French translations by @BenjaminDiet in https://github.com/dreeveapp/dreeve/pull/1727
* ISSUE #1728: Add apple-touch-icon for iOS home screen support by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1729
* ISSUE #1730: Fix mis-aligned cursor training load analysis by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1731
* ISSUE #1716: Rework activity profile charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1720
* ISSUE #1716: Fix caching issue while importing activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1720

## New Contributors
* @BenjaminDiet made their first contribution in https://github.com/dreeveapp/dreeve/pull/1727

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.6.0...v4.6.1

# [v4.6.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.6.0) - 2026-02-07

## Dark mode is here!

No more getting flash-banged by the UI at night.
Because your eyes didn't deserve to burn like they were staring into the sun.

## New "profile" dropdown

We’ve moved the following links into a new dropdown in the top-right corner, accessible by clicking your profile picture:

* Workout assistant
* Badges
* Strava profile

## What's Changed
* Update German translations by @luusl in https://github.com/dreeveapp/dreeve/pull/1712
* ISSUE #1713: Add crossorigin=use-credentials to manifest link by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1717
* ISSUE #1714: Dark mode by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1715
* ISSUE #1714: Always show profile drop down by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1719

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.5.0...v4.6.0

# [v4.5.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.5.0) - 2026-02-03

> [!WARNING]  
> Before upgrading to this release, you'll need to upgrade to `v4.4.2` first. 
> Skipping this step will result in an error.

## What's Changed
* ISSUE #1696: Refactor enrichers to builders to ensure immutable objects by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1697
* ISSUE #1698: Squash existing database migrations to one file by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1699
* feat(training-load): add a forecast tab for training load recovery by @hutchinsp01 in https://github.com/dreeveapp/dreeve/pull/1702
* ISSUE #1705: New Shoutrrr release available: v0.13.2 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1706
* ISSUE #1707: Save the filters of the activities and segments overviews between page refreshes by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1708

## New Contributors
* @hutchinsp01 made their first contribution in https://github.com/dreeveapp/dreeve/pull/1702

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.4.2...v4.5.0

# [v4.4.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.4.2) - 2026-01-30

## What's Changed
* ISSUE #1688: Revisit how intensity in the Athlete profile chart is calculated by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1689
* ISSUE #1691: Pace data smoothing for activity charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1692
* ISSUE #1682: Best effort periods by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1693

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.4.1...v4.4.2

# [v4.4.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.4.1) - 2026-01-26

## What's Changed
* ISSUE #1659: Introduce ActivityIdRepository by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1662
* ISSUE #1675: Postpone webhooks process when other process is importing data by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1676
* ISSUE #1677: Webhook fails due to Invalid RESTING_HEART_RATE_FORMULA by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1679
* Respect app basePath when resolving default route by @luusl in https://github.com/dreeveapp/dreeve/pull/1678

## New Contributors
* @luusl made their first contribution in https://github.com/dreeveapp/dreeve/pull/1678

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.4.0...v4.4.1

# [v4.4.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.4.0) - 2026-01-19

To properly calculate activity intensities, we introduced a new config option:

```yaml
general:
  athlete:
    # The formula used to calculate your resting heart rate. The default is heuristicAgeBased.
    # Allowed values: a fixed number, heuristicAgeBased or you can set a fixed number for any given date range.  
    restingHeartRateFormula: 'heuristicAgeBased'
    # restingHeartRateFormula: 50
    # restingHeartRateFormula:
    #    "2020-01-01": 53
    #    "2025-01-10": 55
```

## What's Changed
* ISSUE #1642: Activity heatmap -> activities link does not use subpath by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1643
* ISSUE #1644: Improve hover states of context buttons by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1645
* ISSUE #1646: Improve y-axis scaling of segment effort history chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1647
* ISSUE 1648: Improve Eddington page layout by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1650
* Strava webhook documentation by @lennon101 in https://github.com/dreeveapp/dreeve/pull/1652
* ISSUE #1651: Fix new app version notification by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1655
* ISSUE #1656: Fix ActivityGrid widget tabs consistency by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1657
* ISSUE #1641: ChallengeConsistency allow empty sportTypesToInclude by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1658
* ISSUE #1660: Fix GPX file check by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1661
* ISSUE 1653: Rework activity intensity algorithm by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1654
* Add OpenRouter documentation by @jasonkuperberg in https://github.com/dreeveapp/dreeve/pull/1668
* Chat design and functionality improvements by @jasonkuperberg in https://github.com/dreeveapp/dreeve/pull/1665
* ISSUE #1670: Fix Undefined array key in BuildRewindHtmlCommandHandlephp by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1672
* ISSUE #1669: Gear maintenance - every x days used by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1673

## New Contributors
* @jasonkuperberg made their first contribution in https://github.com/dreeveapp/dreeve/pull/1668

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.6...v4.4.0

# [v4.3.6](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.6) - 2026-01-11

## What's Changed
* ISSUE #1610: Radar chart indicating athlete profile by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1622
* ISSUE #1631: Streaks add start dates and optional subtitle by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1636
* ISSUE #1638: Adjustable Limit / Width of Eddington Chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1639

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.5...v4.3.7

# [v4.3.5](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.5) - 2026-01-09

## What's Changed
* ISSUE #1625: Create tool to fix corrupted data by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1626
* ISSUE #1627: Download GPX from activity pop-up by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1628
* ISSUE #1624: trainingGoals widget support new types numberOfActivities and calories by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1630
* ISSUE #1632: Weekly stats X-Axis scaling fix by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1633

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.4...v4.3.5

# [v4.3.4](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.4) - 2026-01-05

## What's Changed
* ISSUE #1579: Add grok, huggingFace, openAILike and openAIResponses as AI providers by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1613
* ISSUE #1617: Streaks widget not displaying streaks correctly by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1618
* ISSUE #1608: Segment effort history chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1615

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.3...v4.3.4

# [v4.3.3](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.3) - 2026-01-02

## What's Changed
* ISSUE #1599: Rewind active vs rest days always takes end of year into account by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1600
* ISSUE #1587: Current streaks dashboard widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1590
* ISSUE #1605: Improve webhooks documentation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1606

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.2...v4.3.3

# [v4.3.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.2) - 2025-12-29

## What's Changed
* ISSUE #1597: Fix weekly stats and goals do not line up in the last week of the year by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1598

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.1...v4.3.2

# [v4.3.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.1) - 2025-12-28

## What's Changed
* ISSUE #1577: Improve webhook documentation by @cailloux in https://github.com/dreeveapp/dreeve/pull/1578
* Change and add some Italian translations by @maramazza in https://github.com/dreeveapp/dreeve/pull/1581
* ISSUE #1582: Improve docker entrypoint feedback by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1583
* ISSUE #1561: Move activityStream import to activityImportPipeline by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1566
* ISSUE #1584: Add progress bar to CLI output by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1585
* ISSUE #1591 #1592: Rewind bugfix to images and improvement to activity grid by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1593
* ISSUE #1594: Cap activity title length in segment overview by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1595

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.3.0...v4.3.1

# [v4.3.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.3.0) - 2025-12-22

### 🥳 Strava Webhook support is here!

Dreeve now supports Strava webhooks to automatically import and build your data when new activities are uploaded. This eliminates the need to manually run import commands or set up cron jobs.

Learn how to enable and configure webhooks in the documentation: https://docs.dreeve.app/#/configuration/strava-webhooks

## What's Changed
* ISSUE #1551: Only import activities that are affected by incoming webhooks by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1560
* ISSUE #1564: Remove doctrine production config by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1565
* ISSUE #1570: Do not crash import when call to open-meteo.com fails by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1572

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.6...v4.3.0

# [v4.2.6](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.6) - 2025-12-17

## What's Changed
* ISSUE #1530: Allow individual activities to be imported by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1538
* ISSUE #1553: Import incorrectly flags missing Strava gear when custom gear is used by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1554
* ISSUE #1552: Simplify activity deletion by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1556
* ISSUE #1557: Fix A LineString must be composed of at least 2 points by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1558

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.5...v4.2.6

# [v4.2.5](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.5) - 2025-12-16

## What's Changed
* ISSUE #1524: RouteGeographyAnalyzer to determine which countries a route passes through by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1526
* ISSUE #1529: Rework how gears are imported from strava by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1532
* ISSUE #1528: Improve Shoutrrr documentation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1535
* ISSUE #1536: Gear maintenance docs improvement by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1537
* ISSUE #1534: Set up image as non root by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1541
* ISSUE #1542: Improve CalculateCombinedStreams feedback in CLI by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1547
* ISSUE #1540: Bugfix difference between CTL and ATL by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1549

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.4...v4.2.5

# [v4.2.4](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.4) - 2025-12-09

## What's Changed
* ISSUE #1520: Bug when trying to start Strava auth flow by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1521

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.3...v4.2.4

# [v4.2.3](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.3) - 2025-12-09

## What's Changed
* Update messages+intl-icu.de_DE.yaml by @daydreamer77 in https://github.com/dreeveapp/dreeve/pull/1510
* ISSUE #1513: Add Shoutrrr notifications to troubleshooting section by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1514
* ISSUE #1515: Mutex crashes when migrations have never run by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1518

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.2...v4.2.3

# [v4.2.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.2) - 2025-12-08

## What's Changed
* ISSUE #1496: Heatmap show full name on hover activity name by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1497
* ISSUE #1498: Fix double quotes in heatmap route names by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1500
* ISSUE #1499: Fix BC bug for FtpHistory by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1501
* ISSUE #1504: Replace Mutex lock names with enums by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1505
* ISSUE #1503: Fix build time indication in notifications by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1506

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.1...v4.2.2

# [v4.2.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.1) - 2025-12-06

## What's Changed
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/1481
* ISSUE #1482: Upgrade to Symfony 8 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1483
* ISSUE #1484: Show route info on heatmap by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1485
* ISSUE #1473: Update to stable xdebug version by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1487
* ISSUE #1486: Add 50k, 100k, and 100miles to running best efforts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1489
* ISSUE #1488: Add km/time/distance left to the training goals widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1490
* ISSUE #1493: New Shoutrrr release available: v0.13.0 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1494

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.2.0...v4.2.1

# [v4.2.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.2.0) - 2025-12-01

> [!WARNING]  
> The `weeklyGoals` widget has been removed and replaced by the `trainingGoals` widget.
> If you currently use `weeklyGoals`, update your configuration accordingly:
> https://docs.dreeve.app/#/configuration/dashboard-widgets?id=traininggoals

## What's Changed
* ISSUE #1421: Add running to peak power output and FTP history by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1467
* ISSUE #1428: Monthly, Yearly, Lifetime goals by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1468
* ISSUE #1470: Divide by Zero error in PowerDistributionChart.php by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1468

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.1.0...v4.2.0

# [v4.1.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.1.0) - 2025-11-28

🔥 Two new features worth mentioning this release:

* To keep your configuration clean and maintainable, you can split it across multiple files: https://docs.dreeve.app/#/configuration/main-configuration?id=splitting-your-configuration-into-multiple-files
* An improved notification system. Under the hood we're using *Shoutrrr*, so any service supported by Shoutrrr, will be supported by SFS (https://shoutrrr.nickfedor.com/dev/services/overview/)

> [!NOTE]  
> The old ntfy.sh config will keep working, the feature is backwards compatible.
> Read the docs how to configure your notifications: https://docs.dreeve.app/#/configuration/main-configuration

## What's Changed
* ISSUE #1448: Implement Shoutrrr to allow other notification providers by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1457
* ISSUE #1455: Improve console intro output by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1456
* ISSUE #1458: Allow to split up config by using multiple config files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1460
* ISSUE #1461: Bumped Shoutrrr version to v0.12.1 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1464
* ISSUE #1462: Show power zones in power distribution chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1465

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.6...v4.1.0

# [v4.0.6](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.6) - 2025-11-25

## What's Changed
* ISSUE #1434: Weights in Imperial (pounds) are rounding to 14 digits by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1436
* ISSUE #1443: French translations by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1444
* ISSUE #1438: Upgrade to php8.5 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1439
* ISSUE #1440: Set a max width on gear maintenance components column by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1445
* Update Chinese translations by @c0j0s in https://github.com/dreeveapp/dreeve/pull/1446
* ISSUE #1442: Gear component purchase price by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1447
* Update messages+intl-icu.de_DE.yaml by @effectpears in https://github.com/dreeveapp/dreeve/pull/1450
* Update documentation pages to Docsify v5 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1452

## New Contributors
* @effectpears made their first contribution in https://github.com/dreeveapp/dreeve/pull/1450

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.5...v4.0.6

# [v4.0.5](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.5) - 2025-11-18

## What's Changed
* ISSUE #1407: Distribution charts are not calculated for new activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1415
* Remove unit symbol from WeeklyStatsChart label formatter by @lennon101 in https://github.com/dreeveapp/dreeve/pull/1423
* ISSUE #1425: Use Symfony's built in EventStreamResponse by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1426

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.4...v4.0.5

# [v4.0.4](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.4) - 2025-11-16

> [!WARNING]  
> If you are using the internal cron to manage your app imports and builds,
> make sure you configured the correct volumes on the Daemon container.
> The docs were missing a volume and have been updated:
> https://docs.dreeve.app/#/getting-started/installation?id=docker-composeyml

## What's Changed
* ISSUE #1410: Build crashes when VelocityDistributionChart has no valid data by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1411
* ISSUE #1409: Improve gear docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1412
* ISSUE #1408: Add zoom to athlete weight history widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1413

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.3...v4.0.4

# [v4.0.3](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.3) - 2025-11-15

## What's Changed
* ISSUE #1391: Pace distribution bug fix by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1395
* ISSUE #1394: Athlete weight history dashboard widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1396
* ISSUE #1398: Fix gear color inconsistency across charts by @wzharith in https://github.com/dreeveapp/dreeve/pull/1399
* ISSUE #1400: Heatmap doesn't load when using a subpath by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1402
* ISSUE #1401: Database migrations are not run in Daemon by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1403

## New Contributors
* @wzharith made their first contribution in https://github.com/dreeveapp/dreeve/pull/1399

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.2...v4.0.3

# [v4.0.2](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.2) - 2025-11-14

## What's Changed
* ISSUE #1352: Add Pace Distribution Plot In Activity Details Page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1377
* Ensure docker-compose in documentation is valid by @bastantoine in https://github.com/dreeveapp/dreeve/pull/1387
* ISSUE #1385: Add an option to hide retired gear from maintenance page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1388
* ISSUE #1381: Add ability to sort power on best 5s, best 10s etc by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1389

## New Contributors
* @bastantoine made their first contribution in https://github.com/dreeveapp/dreeve/pull/1387

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.1...v4.0.2

# [v4.0.1](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.1) - 2025-11-13

## What's Changed
* Update daemon container configuration in installation.md by @jparnellx in https://github.com/dreeveapp/dreeve/pull/1380
* ISSUE #1379: Consider cron expression * * * * * as a misconfiguration by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1382
* ISSUE #1379: Disable Daemon debug mode in Docker image by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1382

## New Contributors
* @jparnellx made their first contribution in https://github.com/dreeveapp/dreeve/pull/1380

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v4.0.0...v4.0.1

# [v4.0.0](https://github.com/dreeveapp/dreeve/releases/tag/v4.0.0) - 2025-11-12

> [!WARNING]  
> New major release, several breaking changes!
>
> 1. The config option `general.ntfyUrl` has been moved to `integrations.notifications.ntfyUrl`
> 2. The config option `stravaGear` has been moved to `gear.stravaGear`
> 3. The separate config file `custom-gear.yaml` has been removed.
>    Its configuration has been merged into the main config file under `gear.customGear`
> 
> Simply copy/paste your existing values into their new locations.
> https://docs.dreeve.app/#/configuration/main-configuration

🔥This new release adds several new features and improvements. 
The highlight is the addition of an internal scheduler, allowing you to define and run recurring background tasks directly within the app.

If you prefer, you can still use external tools to trigger the import and build scripts.
However, to use the internal scheduler, you’ll need to configure two things:

* An extra container in your `docker-compose.yml` file: https://docs.dreeve.app/#/getting-started/installation?id=docker-composeyml
* The recurring tasks in your main config file under `daemin.cron`: https://docs.dreeve.app/#/configuration/main-configuration

## What's Changed
* syntax fix by @oregonpillow in https://github.com/dreeveapp/dreeve/pull/1362
* ISSUE #1282: Gear maintenance notifications by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1361
* ISSUE #1336: Move custom gear config to main config file by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1363
* ISSUE #1366: Fix Custom Gear Cost Calculation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1367
* ISSUE #1368: Distance breakdown for walks by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1374
* ISSUE #1371: Do not crash import when nominatim fails us by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1375

## New Contributors
* @oregonpillow made their first contribution in https://github.com/dreeveapp/dreeve/pull/1362

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.9.0...v4.0.0

# [v3.9.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.9.0) - 2025-11-06

🔥 This release includes a complete rewrite of the data import process, making it significantly faster, especially for partial imports.

## What's Changed
* ISSUE #1302: Add new version of Zwift map of New York by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1332
* ISSUE #1338: Fix tense inconsistency and improve readability in intro summary widget by @lennon101 in https://github.com/dreeveapp/dreeve/pull/1339
* ISSUE #1330: Use Strava API rate limit headers to provide a faster data import by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1334
* ISSUE #1335: Add popover with total hours everywhere we display human readable time notation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1342
* ISSUE #1337: Do not round the time in weekly by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1343
* ISSUE #1341: Better support for MyWhoosh by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1344
* ISSUE #1345: Cache static assets by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1346
* ISSUE #1347: Reduce file size of Zwift maps by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1348
* ISSUE #1351: Add Strava activity link to segments efforts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1354
* ISSUE #1355: Move tabs tailwind classes to separate component by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1356
* ISSUE #1357: Do not crash import when OpenWeather API fails us by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1358

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.8.4...v3.9.0

# [v3.8.4](https://github.com/dreeveapp/dreeve/releases/tag/v3.8.4) - 2025-11-02

🔥 This release introduces a new dashboard widget __weeklyGoals__.

> [!NOTE]  
> This widget is disabled in the default dashboard layout. If you want to use this widget you need to configure a custom dashboard layout.

Read more in the documentation: https://docs.dreeve.app/#/configuration/dashboard-widgets?id=weeklygoals

## What's Changed
* ISSUE #1326: Do not use gear that has no activities referencing it by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1327
* ISSUE #1300: Weekly Goal Widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1321

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.8.3...v3.8.4

# [v3.8.3](https://github.com/dreeveapp/dreeve/releases/tag/v3.8.3) - 2025-10-31

🔥 This release introduces a new config option __metricsDisplayOrder__ for the following widgets:

* `weeklyStats`
* `monthlyStats`
* `yearlyStats`

This option lets you customize the order of the metric buttons __Distance__, __Time__, and __Elevation__ in each widget to match your preferred display order.

Read more in the documentation: https://docs.dreeve.app/#/configuration/dashboard-widgets

## What's Changed
* Minor change for Ofelia docs example config by @Luen in https://github.com/dreeveapp/dreeve/pull/1314
* ISSUE #1316: Dashboard chart widgets should have equal height by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1317
* ISSUE #1311: Allow to configure metricsDisplayOrder on widgets by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1318
* Fix cron job schedule for 7 PM by @lennon101 in https://github.com/dreeveapp/dreeve/pull/1320
* ISSUE #1322: Fix wrong unit for average speed in gear state by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1323

## New Contributors
* @Luen made their first contribution in https://github.com/dreeveapp/dreeve/pull/1314
* @lennon101 made their first contribution in https://github.com/dreeveapp/dreeve/pull/1320

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.8.2...v3.8.3

# [v3.8.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.8.2) - 2025-10-29

> [!WARNING]  
> The config option `metrics.consistencyChallenges` has been moved to the config of the `challengeConsistency` dashboard widget.
> If you do not have custom challenges configured, you don't have to do anything

🔥 You can now set the purchase price of your (custom) gear to track its relative cost per hour and per activity. Read more in the documentation:

* https://docs.dreeve.app/#/configuration/main-configuration
* https://docs.dreeve.app/#/configuration/custom-gear

## What's Changed
* updated german translations by @Export33 in https://github.com/dreeveapp/dreeve/pull/1294
* ISSUE #1266: Gear cost-per-use and cost-per-hour statistics by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1291
* ISSUE #1299: Fix Tool get_activity_streams has been attempted too man… by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1301
* Refine Swedish translations by @strobit in https://github.com/dreeveapp/dreeve/pull/1298
* ISSUE #1303: thin horizontal scrollbars by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1304
* ISSUE #1306: Move consistency challenge config to widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1307

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.8.1...v3.8.2

# [v3.8.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.8.1) - 2025-10-26

## What's Changed
* ISSUE #1279: Re-introduce photos.hidePhotosForSportTypes by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1280
* ISSUE #1279: Show activity details on image hover by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1283
* ISSUE #1281: Add Caddy rule to cache /files/* by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1285
* ISSUE #1284: Improve agent chat tools by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1286
* ISSUE #1289: Better MigrationRunner by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1290
* ISSUE #1287: Fix tool max calls for AI integration by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1292

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.8.0...v3.8.1

# [v3.8.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.8.0) - 2025-10-23

🔥 This release introduces a complete overhaul of the “Photos” page.

> [!WARNING]  
> The config option `photos.hidePhotosForSportTypes` has been removed. Use the new `defaultEnabledFilters` option instead:
> ```yaml
>   photos:
>      # Optional, a list of filters that are enabled by default. For example, you can use this to automatically hide all photos from virtual activities.
>      defaultEnabledFilters: {}
> ```

Read more in the documentation: https://docs.dreeve.app/#/configuration/main-configuration

## What's Changed
* ISSUE #1264: Fix NeuronAI bug by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1265
* ISSUE #1268: Redesign photos page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1270
* ISSUE #1268: Re-organize js files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1271
* ISSUE #1269: Add start/end of segment on map by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1276
* ISSUE #1274: Make sure the sport type list in the docs aligns with the list defined in the app by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1277
* ISSUE #1275: Update "Since I began working out ..." template phrasing by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1277

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.7.4...v3.8.0

# [v3.7.4](https://github.com/dreeveapp/dreeve/releases/tag/v3.7.4) - 2025-10-21

> [!WARNING]  
> This release includes updates to the existing `monthlyStats` dashboard widget.
> The `context` configuration option has been removed, as the widget now displays all data at once. You may need to remove duplicate instances of this widget from your dashboard layout configuration.
>
> The same change applies to the `gearStats` dashboard widget.

## What's Changed
* ISSUE #1243: Try to fix FrankenPHP crash on Synology by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1244
* ISSUE #1249: Improve tabs on mobile layout by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1250
* ISSUE #1240 & #1242: Weekday/Daytime stats per activity type by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1253
* ISSUE #1232: Heart rate zones per activity type by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1254
* ISSUE #1231: Allow for charts to be rendered in nested tabs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1255
* ISSUE #1231: Support nested tabs for the monthly stats widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1258
* ISSUE #1231: Convert yearlyDistances widget to yearlyStats widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1259
* ISSUE #1260: Only render tabs for sportTypes that have images by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1261

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.7.3...v3.7.4

# [v3.7.3](https://github.com/dreeveapp/dreeve/releases/tag/v3.7.3) - 2025-10-17

## What's Changed
* ISSUE #1220: Add clickable data points in the MONTHLY stats plot by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1222
* ISSUE #1221: Total Hours Spent Per Gear Pie Chart restrict on sport type by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1223
* ISSUE #1224: Upgrade Echarts from v5 to v6 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1225
* ISSUE #1227: Layout improvements for xl viewport by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1228
* ISSUE #1230: Weekly Stats X-Axis Scaling by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1233
* ISSUE #1235: More filters on heatmap page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1236

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.7.2...v3.7.3

# [v3.7.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.7.2) - 2025-10-15

## What's Changed
* ISSUE #1203: Fix date filter timezone issues by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1210
* ISSUE #1204: Add translation to swedish by @strobit in https://github.com/dreeveapp/dreeve/pull/1205
* ISSUE #1209: Add clickable data points in the weekly stats plot by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1211
* ISSUE #1215: Enable zoom with mouse wheel on activity page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1216

## New Contributors
* @strobit made their first contribution in https://github.com/dreeveapp/dreeve/pull/1205

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.7.1...v3.7.2

# [v3.7.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.7.1) - 2025-10-14

## What's Changed
* ISSUE #1196: Cadence on running activities shows stride per minute instead of steps per minute by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1197
* ISSUE #1201: Add AI support for azureOpenAI by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1202
* ISSUE #1206: Use new css file for auth pages by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1207

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.7.0...v3.7.1

# [v3.7.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.7.0) - 2025-10-13

🔥Main new features in this release:

* More clickable links throughout the app that redirect to a pre-filtered list of activities
* A new dashboard widget "Zwift Stats"

## What's Changed
* ISSUE #1167: Fix bug when checking new version in sidebar by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1168
* ISSUE #1169: Preload non required CSS files by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1170
* ISSUE #1171: Allow to click on gear name by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1172
* ISSUE #1176: Rework public directory structure by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1177
* ISSUE #1178: Add Strava API rate limits at end of import CLI command by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1179
* ISSUE #1182: Add date to best efforts list by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1183
* ISSUE #1187: Add power and cadence to combined activity charts for running by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1188
* ISSUE #1184: Add a Zwift stats dashboard widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1189
* ISSUE #1192: Add guard to make sure that mis-configuration does not delete all imported activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1193

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.6.3...v3.7.0

# [v3.6.3](https://github.com/dreeveapp/dreeve/releases/tag/v3.6.3) - 2025-10-09

🔥 This release introduces a new feature that lets you click on any day in the dashboard’s activity heatmap to quickly view the activities completed on that date.

## What's Changed
* ISSUE #1151: ntfyUrl documenation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1157
* ISSUE #1158: Add documentation related to map visibility by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1159
* ISSUE #1160: Add rector to CI by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1163
* ISSUE #1162: Allow dataTables to be pre-filtered based on localStorage by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1164

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.6.2...v3.6.3

# [v3.6.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.6.2) - 2025-10-05

## What's Changed
* Minor bug fix in device filter by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1152
* ISSUE #1154: Bug fix when activity is not set by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1155

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.6.1...v3.6.2

# [v3.6.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.6.1) - 2025-10-05

## What's Changed
* ISSUE #1140: Improve version update workflow by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1141
* ISSUE #1143: Do not render Best Efforts menu item when there are none by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1144
* ISSUE #1142: Add Device filter on activities overview page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1145
* ISSUE #1147: Add normalized power to activity detail page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1148

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.6.0...v3.6.1

# [v3.6.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.6.0) - 2025-10-03

> [!WARNING]  
> This release comes with a new Docker-based architecture.  Before upgrading, review and update the required `.env` values for your setup:  
> https://docs.dreeve.app/#/getting-started/installation?id=env

```.env
# !! IMPORTANT If you want to serve Dreeve from a custom domain (not localhost), 
# uncomment the following lines and configure them accordingly:

# The domain where Dreeve will be available.
# PROXY_HOST=https://your-domain.com
# The port on which the app will be served.
# PROXY_PORT=8080
```

## What's Changed
* ISSUE #1112: Replace PHP-FPM and Nginx with FrankenPHP and Caddy by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1113
* ISSUE #1131: Improve custom gear docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1132
* ISSUE #1121: Gear maintenance reset counters mode by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1133
* ISSUE #1129: Improve Distance Breakdown for short distance athletes by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1134
* ISSUE #1135: Add time to xAxis on activity combined charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1137
* ISSUE #1130: Add bar chart to activity laps by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1138

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.5.0...v3.6.0

# [v3.5.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.5.0) - 2025-09-29

## What's Changed
* ISSUE #1117: Improve performance of RamerDouglasPeucker algorithm by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1119
* ISSUE #1118: Improve prerequisites documentation by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1120
* ISSUE #1097: Show position on leaflet map when hovering charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1123
* ISSUE #1122: Add enableLastXYearsByDefault config option to yearlyDistances widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1124

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.4.3...v3.5.0

# [v3.4.3](https://github.com/dreeveapp/dreeve/releases/tag/v3.4.3) - 2025-09-26

## What's Changed
* ISSUE #1114: Fix backwards compatibility bug in MostRecentActivitiesWidget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1115

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.4.2...v3.4.3

# [v3.4.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.4.2) - 2025-09-24

## What's Changed
* Fix Italian Translation by @maramazza in https://github.com/dreeveapp/dreeve/pull/1095
* Add flexibility for number of activities to display by @GrabBug in https://github.com/dreeveapp/dreeve/pull/1102
* ISSUE #1101: Do not render challenges dashboard widget when empty by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1103
* ISSUE #1098: show mouseover over newest activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1104
* ISSUE #1106 #1096: Improve combined activity charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1107
* ISSUE #1100: Use consistent colors in rewind charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1108
* ISSUE #1100: Store theme in database by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1109
* EddingtonHistoryChart axis label formatting by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1110

## New Contributors
* @maramazza made their first contribution in https://github.com/dreeveapp/dreeve/pull/1095
* @GrabBug made their first contribution in https://github.com/dreeveapp/dreeve/pull/1102

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.4.1...v3.4.2

# [v3.4.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.4.1) - 2025-09-20

## What's Changed
* ISSUE #1050: Fix bug while calculating activity intensity by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1079
* ISSUE #1080: Show chat icon on mobile version by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1081
* ISSUE #1084: Add eMTB to best effort stats by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1085
* fix: docs typo by @zipWhale in https://github.com/dreeveapp/dreeve/pull/1092
* ISSUE #1090: Fix unknown `workout_type` values by @romainveillard in https://github.com/dreeveapp/dreeve/pull/1093

## New Contributors
* @zipWhale made their first contribution in https://github.com/dreeveapp/dreeve/pull/1092
* @romainveillard made their first contribution in https://github.com/dreeveapp/dreeve/pull/1093

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.4.0...v3.4.1

# [v3.4.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.4.0) - 2025-09-15

## What's Changed
* German translations by @Export33 in https://github.com/dreeveapp/dreeve/pull/1071
* ISSUE #1057: BestEffort history by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1073
* ISSUE #1072: Fix translations by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1077

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.3.1...v3.4.0

# [v3.3.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.3.1) - 2025-09-11

## What's Changed
* ISSUE #1063: Format activity grid values by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1065
* ISSUE #1051: No decimals for distances greater than 100 by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1066
* ISSUE #1067: Disable Symfony logging by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1068


**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.3.0...v3.3.1

# [v3.3.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.3.0) - 2025-09-08

## What's Changed
* ISSUE-1054: Remove 'minZoom' property from leaflet options in segment… by @ausernamedtom in https://github.com/dreeveapp/dreeve/pull/1055
* ISSUE #1023: Add activity grids by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1049
* ISSUE #1056: Add elevation gain on weekly stats chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1059
* ISSUE #1058: Fix bug in AIAgentChatConsoleCommand by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1060
* ISSUE #1057: Move best efforts away from dashboard to dedicated page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1061

## New Contributors
* @ausernamedtom made their first contribution in https://github.com/dreeveapp/dreeve/pull/1055

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.9...v3.3.0

# [v3.2.9](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.9) - 2025-09-03

## What's Changed
* Adjustment of the german translation to Strava. by @Export33 in https://github.com/dreeveapp/dreeve/pull/1036
* ISSUE #1029: Time Window for the Training Load Analysis by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1035
* ISSUE #1039: Allow to hide photos per sportType by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1040
* german translations new variables by @Export33 in https://github.com/dreeveapp/dreeve/pull/1045
* ISSUE #1023: Rename ActivityIntensityWidget to ActivityGridWidget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1046
* ISSUE #1051: Round ride distance to 2 numbers by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1052

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.8...v3.2.9

# [v3.2.8](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.8) - 2025-08-27

## What's Changed
* Fix javascript imports by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1028
* ISSUE #1031: Fix overflow-x-scroll on tab elements by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1032
* ISSUE #1030: render pace for running segments by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1033

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.7...v3.2.8

# [v3.2.7](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.7) - 2025-08-21

## What's Changed
* ISSUE #1016: More translatables by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1017
* Improved German translations by @Export33 in https://github.com/dreeveapp/dreeve/pull/1018
* ISSUE #1021: Fix heatmap date filter by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1024
* ISSUE #1022: Add default date to date filters by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1025

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.6...v3.2.7

# [v3.2.6](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.6) - 2025-08-20

## What's Changed
* ISSUE #1006: Yearly statistics: add more delta's comparing previous years by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1007
* ISSUE #1008: Remove maintenance tags from activity title in rewind by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1009
* updated german translations by @Export33 in https://github.com/dreeveapp/dreeve/pull/1011
* ISSUE #1012: Monthly elevation chart by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1013

## New Contributors
* @Export33 made their first contribution in https://github.com/dreeveapp/dreeve/pull/1011

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.5...v3.2.6

# [v3.2.5](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.5) - 2025-08-18

## What's Changed
* ISSUE #998: Refactor Gear maintenance calculator by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/999
* ISSUE #1000: Refactor APP namespaces by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1001
* ISSUE #1002: Add date to best efforts table by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/1003

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.4...v3.2.5

# [v3.2.4](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.4) - 2025-08-14

## What's Changed
* ISSUE #989: Mark 404 segments as imported to avoid importing them again by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/990
* ISSUE #992: German translations by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/993
* ISSUE #995: Show gear maintenance history by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/996

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.3...v3.2.4

# [v3.2.3](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.3) - 2025-08-13

## What's Changed
* ISSUE #980: Fix duplicate countries in segment country filter by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/981
* ISSUE #982: Fix bug with sticky columns in month overview by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/983
* ISSUE #984: Continue segment import when Strava API makes booboo by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/985

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.2...v3.2.3

# [v3.2.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.2) - 2025-08-12

## What's Changed
* ISSUE #973: Add gear to run activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/977
* ISSUE #974: Rework monthly overview by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/978

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.1...v3.2.2

# [v3.2.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.1) - 2025-08-10

This release introduces 2 new dashboard widgets:

#### gearStats

This widget displays your hours spent per gear.

* __includeRetiredGear__: flag indicating if the widget needs to include retired gear.

```yml
{ 'widget': 'gearStats', 'width': 50, 'enabled': true, 'config': { 'includeRetiredGear': true } }
```

#### mostRecentChallengesCompleted

This widget displays your most recent challenges.

* __numberOfChallengesToDisplay__: the number of challenges to display.

```yml
{ 'widget': 'mostRecentChallengesCompleted', 'width': 50, 'enabled': true, 'config': { 'numberOfChallengesToDisplay': 5 } }
```

## What's Changed
* ISSUE #955: Add average heart rate to segment efforts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/963
* ISSUE #957: Make sure tab IDs are unique for all dashboard widgets by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/964
* ISSUE #966: Gear widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/968
* ISSUE #966: Challenges widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/969
* ISSUE #970: Better CLI debug output by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/971

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.2.0...v3.2.1

# [v3.2.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.2.0) - 2025-08-06

This release allows you to import segment details from Strava to be able to display maps on segment detail pages:

```yml
import:
  # Setting this to true will import segment details. This means each segment will need an extra Strava API call to fetch the segment details.
  # This is required to be able to display a map of the segment.
  # Setting this to true will increase the import time significantly if you have a lot of segments.
  # Each segment only needs to be imported once, so this will not affect the import time for subsequent imports.
  optInToSegmentDetailImport: false
```

## What's Changed
* ISSUE #945: Allow to opt-in to import segment details by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/952
* ISSUE #945: Render segment leaflet map by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/958
* ISSUE #959: Add support for Kosovo as a country by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/960
* ISSUE #953: Add ALL tab to photos by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/961

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.1.3...v3.2.0

# [v3.1.3](https://github.com/dreeveapp/dreeve/releases/tag/v3.1.3) - 2025-08-01

Another day, another release, another new dashboard widget. This time it's the Eddington widget.

```yml
appearance:
  dashboard:
    layout:
      - { 'widget': 'eddington', 'width': 50, 'enabled': true }
```

Check https://docs.dreeve.app/#/configuration/dashboard-widgets?id=eddington for more information.

## What's Changed
* ISSUE #942: Better error messages in chat UI by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/947
* ISSUE #946: Introduce EddingtonCalculator by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/948
* ISSUE #946: Expose eddington as a dashboard widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/949

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.1.2...v3.1.3

# [v3.1.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.1.2) - 2025-07-31

This release introduces a new dashboard widget for monthly stats.

```yml
appearance:
  dashboard:
    # The width is a percentage of the available space, so 33 means the widget will take up one third of the available space.
    # The allowed values for width are 33, 50, 66, and 100.
    # The order of the widgets in the list determines their order on the dashboard.
    layout:
      - { 'widget': 'monthlyStats', 'width': 100, 'enabled': true, 'config': { 'context': 'distance', enableLastXYearsByDefault: 10 } }
```

## What's Changed
* ISSUE #939: Improve rewind compare links by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/940
* ISSUE #937: Expose monthly stats as a dashboard widget by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/941
* ISSUE #916: Escape activity titles when rendering lightGallery by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/917

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.1.1...v3.1.2

# [v3.1.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.1.1) - 2025-07-30

🤖 This release introduces extra configuration options for the AI agent. You can now configure pre-defined chat commands.

Check https://docs.dreeve.app/#/configuration/ai-integration?id=pre-defining-chat-commands for more information.

```yaml
integrations:
  ai:
    # Other AI configuration options
    agent:
      commands:
        - command: 'analyse-last-workout'
          message: 'You are my bike trainer. Please analyze my most recent ride with regard to aspects such as heart rate, power (if available). Please give me an assessment of my performance level and possible improvements for future training sessions.'
        - command: 'compare-last-two-weeks'
          message:  'You are my bike trainer. Please compare my workouts and performance of the last 7 days with the 7 days before and give a short assessment.'
```

## What's Changed
* ISSUE #927: Better support for swimming activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/929
* ISSUE #930: No rewind available when there's only one year worth of data by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/931
* ISSUE #906: Allow to configure commands for AI chat agent by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/936

## New Contributors
* @cailloux made their first contribution in https://github.com/dreeveapp/dreeve/pull/935

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.1.0...v3.1.1

# [v3.1.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.1.0) - 2025-07-29

🔥 This release allows you to configure dashboard widgets. You can now choose which widgets you want to see on your dashboard and in which order they are displayed.
Check https://docs.dreeve.app/#/configuration/main-configuration?id=dashboard-layout for more information.

## What's Changed
* ISSUE #902: Fix weekly streaks in rewind by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/907
* ISSUE #903: Allow to configure multiple tile layers on heatmap by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/908
* ISSUE #911: Fix UI issue rewind feature on mobile by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/913
* ISSUE #912: Allow to configure dashboard widgets by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/923

## New Contributors
* @kenetickreator made their first contribution in https://github.com/dreeveapp/dreeve/pull/909

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.0.2...v3.1.0

# [v3.0.2](https://github.com/dreeveapp/dreeve/releases/tag/v3.0.2) - 2025-07-24

This release introduces configuration options for the heatmap

```yaml
  heatmap:
    # Specifies the color of polylines drawn on the heatmap. Accepts any valid CSS color.
    # (e.g. "red", "#FF0000", "rgb(255,0,0)")
    polylineColor: '#fc6719'
    # Specifies the type of map to use. Must be a valid tile layer URL.
    # For example, a satellite layer: https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}
    tileLayerUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
    # Enables or disables grayscale styling on the heatmap.
    enableGreyScale: true
```

## What's Changed
* ISSUE #887: Add a proper chat history by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/891
* ISSUE #893: Add gear to segment effort overviews by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/897
* ISSUE #895: Add tooltips to consistency challenges by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/898
* ISSUE #885: Allow to configure heatmap by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/896

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.0.1...v3.0.2

# [v3.0.1](https://github.com/dreeveapp/dreeve/releases/tag/v3.0.1) - 2025-07-23

## What's Changed
* ISSUE #883: Get rid of Trivia and add All time option to Rewind by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/884
* ISSUE #887: Better error handling for AI agent by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/888

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v3.0.0...v3.0.1

# [v3.0.0](https://github.com/dreeveapp/dreeve/releases/tag/v3.0.0) - 2025-07-22

* 🚀 Our AI agent "Mark" is now available via the UI as well. You can enable this by setting `enableUI: true` in your config.yaml file
* ⚠️ Native scheduling has been removed from the SFS image. [Check the docs](https://docs.dreeve.app/#/getting-started/scheduling) for alternatives.

## What's Changed
* ISSUE #870: German translations by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/871
* ISSUE #835: Better activity description formatting by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/872
* PR #874: Little Eddington bug fix by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/874
* ISSUE #875: Remove profiler by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/876
* ISSUE #846: AI chat agent UI by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/847
* ISSUE #651: Move scheduling out of the SFS container by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/878
* ISSUE #879: Add Calories as an option to Challenge consistency custom… by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/880

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.4.5...v3.0.0

# [v2.4.5](https://github.com/dreeveapp/dreeve/releases/tag/v2.4.5) - 2025-07-16

## What's Changed
* ISSUE #861: Update starred segments while importing from Strava by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/862
* ISSUE #860: Add last effort date to segment overview by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/863
* PR #865: Fix photo slideshow bug by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/865
* PR #866: Re-work polarized training layout by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/866

## New Contributors
* @jamesfricker made their first contribution in https://github.com/dreeveapp/dreeve/pull/855

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.4.4...v2.4.5

# [v2.4.4](https://github.com/dreeveapp/dreeve/releases/tag/v2.4.4) - 2025-07-14

## What's Changed
* ISSUE #852: Fix yearly stats by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/854
* ISSUE #857: Add country filter to segments page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/858

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.4.3...v2.4.4

# [v2.4.3](https://github.com/dreeveapp/dreeve/releases/tag/v2.4.3) - 2025-07-10

## What's Changed
* ISSUE #833: Improve NeuronAI tool descriptions by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/839
* ISSUE #834: Monthly overview in graph by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/842
* ISSUE #848: Improve activity detail combined profile charts by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/849

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.4.2...v2.4.3

# [v2.4.2](https://github.com/dreeveapp/dreeve/releases/tag/v2.4.2) - 2025-07-08

## What's Changed
* ISSUE #822: Fix portrait images on activity detail pages by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/823
* ISSUE #824: Escape quotes in JSON encoded strings by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/825
* ISSUE #827: Fix activity best efforts when Strava data is wrong by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/828
* ISSUE #764: Add polarised training/ time in zones by @oddish3 in https://github.com/dreeveapp/dreeve/pull/826
* ISSUE #831: Improve activity template performance by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/832

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.4.1...v2.4.2

# [v2.4.1](https://github.com/dreeveapp/dreeve/releases/tag/v2.4.1) - 2025-07-04

## What's Changed
* ISSUE #814: Re-arrange components on activity detail page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/815
* ISSUE #813: Better template structure by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/816
* ISSUE-818: Performance improvements by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/820

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.4.0...v2.4.1

# [v2.4.0](https://github.com/dreeveapp/dreeve/releases/tag/v2.4.0) - 2025-07-01

🚀 Dreeve v2.4.0 is here!
The most noteworthy feature is the virtual workout assistant: https://docs.dreeve.app/#/configuration/ai-integration

## What's Changed
* ISSUE #806: Fix filter date range bug by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/807
* ISSUE #789: Show segment effort history by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/808
* ISSUE #794: Show PB on activity detail page by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/805
* ISSUE #810: Docs - Proxmox by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/812
* ISSUE #150: give the required AI implementation a try by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/358

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.6...v2.4.0

# [v2.3.6](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.6) - 2025-06-30

⚠️We moved the sorting of sportTypes away from the import process by introducing a new setting:

```yaml
appearance:
  # With this list you can decide the order the sport types will be rendered in. For example in the tabs on the dashboard.
  # You don't have to include all sport types. Sport types not included in this list will be rendered by the app default.
  # A full list of allowed options is available on https://docs.dreeve.app/#/configuration/main-configuration?id=supported-sport-types 
  sportTypesSortingOrder: []  
```

## What's Changed
* ISSUE #795: Reverse months when rendering ConsistencyChallenges by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/797
* ISSUE #793: Add tooltips to column icons by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/798
* ISSUE #796: Fix elevation chart when elevation is below sea level by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/799
* ISSUE #792: Move sorting of sport types away from importing them by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/800

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.5...v2.3.6

# [v2.3.5](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.5) - 2025-06-27

Configure your own consistency challenges: https://docs.dreeve.app/#/configuration/main-configuration?id=consistency-challenges

## What's Changed
* ISSUE #786: Allow to configure consistency challenges by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/790

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.4...v2.3.5

# [v2.3.4](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.4) - 2025-06-21

## What's Changed
* ISSUE #778: Re-draw charts when side navbar is collapsed by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/779
* ISSUE #780: Update sportType when importing activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/781
* ISSUE #782: Fix bug while combining filters on the heatmap by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/784

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.3...v2.3.4

# [v2.3.3](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.3) - 2025-06-20

## What's Changed
* ISSUE #763: PB user badges are wrong for running activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/766
* ISSUE #767: update rounding in best effort accordion table by @oddish3 in https://github.com/dreeveapp/dreeve/pull/768
* Italian and Portuguese translations by @milleruk in https://github.com/dreeveapp/dreeve/pull/773
* ISSUE #765: Remove best efforts for activities that get deleted by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/774
* ISSUE #771: Fix rendering of map for every old Zwift activities by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/775

## New Contributors
* @kyearb made their first contribution in https://github.com/dreeveapp/dreeve/pull/769
* @milleruk made their first contribution in https://github.com/dreeveapp/dreeve/pull/773

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.2...v2.3.3

# [v2.3.2](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.2) - 2025-06-18

This release introduces more user badges and the ability to use custom date formats:

```yaml
appearance:
  dateFormat:
    short: 'd-m-y' # This renders to 01-01-25
    normal: 'd-m-Y' # This renders to 01-01-2025
```

## What's Changed
* ISSUE #752: Badge that includes Best Efforts times by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/757
* ISSUE #760: Allow for custom date formats by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/761

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.1...v2.3.2

# [v2.3.1](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.1) - 2025-06-17

## What's Changed
* ISSUE #748: Indicate navbar collapsed state with icons by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/749
* ISSUE #740: Activity laps by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/750
* ISSUE #744: Allow to configure an app subtitle by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/753
* ISSUE #755: ISSUE #755: Fix division by zero bug by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/758

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.3.0...v2.3.1

# [v2.3.0](https://github.com/dreeveapp/dreeve/releases/tag/v2.3.0) - 2025-06-16

🚀 New: Fine-tune your heart rate zones with advanced configuration options!
📖 Learn how to set them up: [Configuration Guide](https://docs.dreeve.app/#/configuration/main-configuration)

## What's Changed
* ISSUE #677: Show activity where PB was set by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/720
* ISSUE #680: Allow custom heart rate zones by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/737


**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.2.1...v2.3.0

# [v2.2.1](https://github.com/dreeveapp/dreeve/releases/tag/v2.2.1) - 2025-06-13

## What's Changed
* ISSUE #728: Add dividers to side navbar by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/729
* ISSUE #730: Render maintenance is due indicator on gear submenu by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/731
* ISSUE #734: Error when import : Warning: Undefined array key address by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/735

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.2.0...v2.2.1

# [v2.2.0](https://github.com/dreeveapp/dreeve/releases/tag/v2.2.0) - 2025-06-12

🔥 Be the master of your Eddington. 
Read up on how to configure your Eddington numbers: https://docs.dreeve.app/#/configuration/main-configuration

## What's Changed
* ISSUE #692: Fix bug in Safari while rendering gear maintenance by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/704
* ISSUE #707: Fixed weekday label in calendar view by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/708
* ISSUE #709: Replace all sidenav icons by outlined ones by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/710
* ISSUE #711: Consistency in icons by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/712
* ISSUE #658: Allow users to configure Eddington by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/714

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.1.4...v2.2.0

# [v2.1.4](https://github.com/dreeveapp/dreeve/releases/tag/v2.1.4) - 2025-06-11

## What's Changed
* ISSUE #702: Eddington spacing in navbar is too large by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/703

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.1.3...v2.1.4

# [v2.1.3](https://github.com/dreeveapp/dreeve/releases/tag/v2.1.3) - 2025-06-11

## What's Changed
* ISSUE #665: Logo variant for the docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/667
* ISSUE #679: Improve oauth flow by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/681
* ISSUE #684: Improve import feedback in CLI + deprecate schedule in docs by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/691
* ISSUE #693: Convert DistanceOverTimePerGearChart to imperial if needed by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/695
* ISSUE #696: Move Gear submenu to tabs in preparation of collapsed sidebar by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/697
* ISSUE #698: Collapsible sidenav by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/699

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.1.2...v2.1.3

# [v2.1.2](https://github.com/dreeveapp/dreeve/releases/tag/v2.1.2) - 2025-06-08

## What's Changed
* ISSUE #641: Elevation below sea level is not shown on the plot by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/642
* ISSUE #646: Setup docsify by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/647
* ISSUE #657: Remove references to old .env config by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/660
* ISSUE #654: Improve the documentation and add Strava authorization troubleshooting by @robiningelbrecht in https://github.com/dreeveapp/dreeve/pull/661

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.1.1...v2.1.2

# [v2.1.1](https://github.com/dreeveapp/dreeve/releases/tag/v2.1.1) - 2025-06-03

## What's Changed
* Amélioration des traductions françaises by [@Snoopfr](https://github.com/Snoopfr) in [#638](https://github.com/dreeveapp/dreeve/pull/638)
* ISSUE [#639](https://github.com/dreeveapp/dreeve/issues/639): When using "gear" in the custum gear tag, toUnprefixedString() will strip it away by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#640](https://github.com/dreeveapp/dreeve/pull/640)

## New Contributors
* [@Snoopfr](https://github.com/Snoopfr) made their first contribution in [#638](https://github.com/dreeveapp/dreeve/pull/638)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.1.0...v2.1.1

# [v2.1.0](https://github.com/dreeveapp/dreeve/releases/tag/v2.1.0) - 2025-06-03

🔥 Dreeve now allows you to manage custom gear. This is useful for gear that Strava doesn't allow you to track. For example:

* Skateboards
* Peddleboards
* Snowboards
* Kayaks
* Kites
...

https://github.com/dreeveapp/dreeve/wiki/Custom-Gear

## What's Changed
* ISSUE [#558](https://github.com/dreeveapp/dreeve/issues/558): Custom gear by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#597](https://github.com/dreeveapp/dreeve/pull/597)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.0.3...v2.1.0

# [v2.0.3](https://github.com/dreeveapp/dreeve/releases/tag/v2.0.3) - 2025-06-01

## What's Changed
* ISSUE [#617](https://github.com/dreeveapp/dreeve/issues/617): Always run test suite with a random seed by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#618](https://github.com/dreeveapp/dreeve/pull/618)
* ISSUE [#619](https://github.com/dreeveapp/dreeve/issues/619): Dynamic chart rounding by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#620](https://github.com/dreeveapp/dreeve/pull/620)
* ISSUE [#621](https://github.com/dreeveapp/dreeve/issues/621): Install and configure blackfire by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#622](https://github.com/dreeveapp/dreeve/pull/622)
* ISSUE [#624](https://github.com/dreeveapp/dreeve/issues/624): Upgrade to Symfony 7.3 by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#626](https://github.com/dreeveapp/dreeve/pull/626)
* ISSUE [#629](https://github.com/dreeveapp/dreeve/issues/629): Add Eddington for walks by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#632](https://github.com/dreeveapp/dreeve/pull/632)
* ISSUE [#630](https://github.com/dreeveapp/dreeve/issues/630): max_heart_rate_formula can't be specified as array in con… by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#633](https://github.com/dreeveapp/dreeve/pull/633)
* ISSUE [#627](https://github.com/dreeveapp/dreeve/issues/627): Move config.yaml to camelCase but keep it backward compatible with snake_case by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#634](https://github.com/dreeveapp/dreeve/pull/634)
* ISSUE-627: Bump APP version by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#635](https://github.com/dreeveapp/dreeve/pull/635)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.0.2...v2.0.3

# [v2.0.2](https://github.com/dreeveapp/dreeve/releases/tag/v2.0.2) - 2025-05-27

Did a big woopsie in `v2.0.1`. This release fixes the booboo

## What's Changed
* ISSUE [#615](https://github.com/dreeveapp/dreeve/issues/615): Fix fatal error due to faulty AppExpressionLanguageProvider by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#616](https://github.com/dreeveapp/dreeve/pull/616)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.0.1...v2.0.2

# [v2.0.1](https://github.com/dreeveapp/dreeve/releases/tag/v2.0.1) - 2025-05-27

⚠️ Your next import will probably take a while. We improved the charts on the activity detail pages, but we need to re-calculate a lot of data to do so. Just sit back, wait and relax. We got you

## What's Changed
* ISSUE [#594](https://github.com/dreeveapp/dreeve/issues/594): Better expressions in DI by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#596](https://github.com/dreeveapp/dreeve/pull/596)
* ISSUE [#594](https://github.com/dreeveapp/dreeve/issues/594): Fix debug console command by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#598](https://github.com/dreeveapp/dreeve/pull/598)
* ISSUE [#599](https://github.com/dreeveapp/dreeve/issues/599): Proper 403 page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#601](https://github.com/dreeveapp/dreeve/pull/601)
* ISSUE [#603](https://github.com/dreeveapp/dreeve/issues/603): Better broken image handling by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#604](https://github.com/dreeveapp/dreeve/pull/604)
* ISSUE [#608](https://github.com/dreeveapp/dreeve/issues/608): Simplify expression language provider by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#609](https://github.com/dreeveapp/dreeve/pull/609)
* ISSUE [#610](https://github.com/dreeveapp/dreeve/issues/610): Make sure gear-info modal is built even when the maintenance feature is not enabled by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#611](https://github.com/dreeveapp/dreeve/pull/611)
* ISSUE-612: better height graph defaults by [@oddish3](https://github.com/oddish3) in [#613](https://github.com/dreeveapp/dreeve/pull/613)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v2.0.0...v2.0.1

# [v2.0.0](https://github.com/dreeveapp/dreeve/releases/tag/v2.0.0) - 2025-05-24

🚨 v2.0.0 Migration Notice: Breaking Change

Version `v2.0.0` introduces a breaking change: most configuration values have moved from `.env` to a new `config.yaml` file. This requires manual action on your part.

https://github.com/dreeveapp/dreeve/wiki/%F0%9F%9A%A8-v2.0.0-Migration-Notice:-Breaking-Change

## What's Changed
* Update README; new user how-to by [@SorenKyhl](https://github.com/SorenKyhl) in [#590](https://github.com/dreeveapp/dreeve/pull/590)
* ISSUE [#589](https://github.com/dreeveapp/dreeve/issues/589): Use timzone to render dates by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#593](https://github.com/dreeveapp/dreeve/pull/593)
* ISSUE [#587](https://github.com/dreeveapp/dreeve/issues/587): Allow to prepend base path to relative URIs by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#588](https://github.com/dreeveapp/dreeve/pull/588)
* ISSUE-586: include history in dashboard command handler by [@oddish3](https://github.com/oddish3) in [#591](https://github.com/dreeveapp/dreeve/pull/591)
* ISSUE [#594](https://github.com/dreeveapp/dreeve/issues/594): Move config to YAML file by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#595](https://github.com/dreeveapp/dreeve/pull/595)

## New Contributors
* [@SorenKyhl](https://github.com/SorenKyhl) made their first contribution in [#590](https://github.com/dreeveapp/dreeve/pull/590)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.6...v2.0.0

# [v1.3.6](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.6) - 2025-05-20

## What's Changed
* ISSUE [#578](https://github.com/dreeveapp/dreeve/issues/578): Better error handling for invalid ATHLETE_BIRTHDAY by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#579](https://github.com/dreeveapp/dreeve/pull/579)
* ISSUE [#580](https://github.com/dreeveapp/dreeve/issues/580): Escape activity titles when rendering badges by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#581](https://github.com/dreeveapp/dreeve/pull/581)
* ISSUE [#582](https://github.com/dreeveapp/dreeve/issues/582): Download images when the activity image count has changed by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#585](https://github.com/dreeveapp/dreeve/pull/585)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.5...v1.3.6

# [v1.3.5](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.5) - 2025-05-15

## What's Changed
* ISSUE [#565](https://github.com/dreeveapp/dreeve/issues/565): Fix mobile issues with tooltips on dashboard by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#566](https://github.com/dreeveapp/dreeve/pull/566)
* ISSUE [#567](https://github.com/dreeveapp/dreeve/issues/567): Properly handle referencing of urls by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#568](https://github.com/dreeveapp/dreeve/pull/568)
* changes to TSB popover by [@oddish3](https://github.com/oddish3) in [#570](https://github.com/dreeveapp/dreeve/pull/570)
* ISSUE [#572](https://github.com/dreeveapp/dreeve/issues/572): Heart rate dustribution bars should not be clickable by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#574](https://github.com/dreeveapp/dreeve/pull/574)
* ISSUE [#573](https://github.com/dreeveapp/dreeve/issues/573): Update workout type during activity import by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#577](https://github.com/dreeveapp/dreeve/pull/577)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.4...v1.3.5

# [v1.3.4](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.4) - 2025-05-13

## What's Changed
* Minor layout fixes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#554](https://github.com/dreeveapp/dreeve/pull/554)
* ISSUE [#555](https://github.com/dreeveapp/dreeve/issues/555): Add a modal showing the imported gear ids to configure maintenance tasks by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#556](https://github.com/dreeveapp/dreeve/pull/556)
* Use AutoconfigureTag by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#562](https://github.com/dreeveapp/dreeve/pull/562)
* ISSUE [#561](https://github.com/dreeveapp/dreeve/issues/561): Fix bug where AVG hear rate was not displayed corrextly in distribution chart by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#563](https://github.com/dreeveapp/dreeve/pull/563)
* ISSUE-512: adding training load metrics by [@oddish3](https://github.com/oddish3) in [#513](https://github.com/dreeveapp/dreeve/pull/513)
* ISSUE [#557](https://github.com/dreeveapp/dreeve/issues/557): Consistensy in dashboard margins by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#564](https://github.com/dreeveapp/dreeve/pull/564)

## New Contributors
* [@oddish3](https://github.com/oddish3) made their first contribution in [#513](https://github.com/dreeveapp/dreeve/pull/513)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.3...v1.3.4

# [v1.3.3](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.3) - 2025-05-06

## What's Changed
* ISSUE-549: Maintenance due indicator improvement by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#550](https://github.com/dreeveapp/dreeve/pull/550)
* ISSUE-548: Import segment climb category to determine KOM by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#551](https://github.com/dreeveapp/dreeve/pull/551)
* ISSUE-552: Make sure that updating ATHLETE_WEIGHT_HISTORY is not blocked by Strava API rate limits by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#553](https://github.com/dreeveapp/dreeve/pull/553)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.2...v1.3.3

# [v1.3.2](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.2) - 2025-05-02

## What's Changed
* ISSUE-489: Calculate zwift progress based on xp by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#540](https://github.com/dreeveapp/dreeve/pull/540)
* ISSUE-539: Replace stats repo with QueryBus by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#541](https://github.com/dreeveapp/dreeve/pull/541)
* ISSUE-542: Do not show rewind compare button on small screen sizes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#543](https://github.com/dreeveapp/dreeve/pull/543)
* Split up controllers by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#545](https://github.com/dreeveapp/dreeve/pull/545)
* Strava branding guide rules by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#546](https://github.com/dreeveapp/dreeve/pull/546)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.1...v1.3.2

# [v1.3.1](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.1) - 2025-04-28

Rewind compare 🎉

https://github.com/user-attachments/assets/0deedcb0-0571-4b4e-9ad6-952edb8dacfc

## What's Changed
* ISSUE-527: Fix router crash by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#529](https://github.com/dreeveapp/dreeve/pull/529)
* Docker Compose example update by [@101br03k](https://github.com/101br03k) in [#533](https://github.com/dreeveapp/dreeve/pull/533)
* ISSUE-534: More KOMs by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#535](https://github.com/dreeveapp/dreeve/pull/535)
* ISSUE-530: Rewind sport type colors by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#536](https://github.com/dreeveapp/dreeve/pull/536)
* ISSUE-526: Rewind compare by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#532](https://github.com/dreeveapp/dreeve/pull/532)
* ISSUE-537: Do not waste space with silly notifications by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#538](https://github.com/dreeveapp/dreeve/pull/538)

## New Contributors
* [@101br03k](https://github.com/101br03k) made their first contribution in [#533](https://github.com/dreeveapp/dreeve/pull/533)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.3.0...v1.3.1

# [v1.3.0](https://github.com/dreeveapp/dreeve/releases/tag/v1.3.0) - 2025-04-25

**Big new feature 🎉!**

Rewind is here!

https://github.com/user-attachments/assets/be4df936-1bfe-4f87-8c59-1f218897cf98

## What's Changed
* ISSUE-507: Add Brazilian Portuguese localization by [@davisenra](https://github.com/davisenra) in [#516](https://github.com/dreeveapp/dreeve/pull/516)
* ISSUE-517: Remove maintenance tasks tags from activity titles by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#518](https://github.com/dreeveapp/dreeve/pull/518)
* ISSUE-515: Remove app.php in favour of default Symfony routing by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#519](https://github.com/dreeveapp/dreeve/pull/519)
* ISSUE-520: Introduce a QueryBus by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#521](https://github.com/dreeveapp/dreeve/pull/521)
* ISSUE-444: Strava rewind by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#504](https://github.com/dreeveapp/dreeve/pull/504)
* Remove test db from git by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#524](https://github.com/dreeveapp/dreeve/pull/524)
* ISSUE-523: Move Strava oauth to the UI by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#525](https://github.com/dreeveapp/dreeve/pull/525)

## New Contributors
* [@davisenra](https://github.com/davisenra) made their first contribution in [#516](https://github.com/dreeveapp/dreeve/pull/516)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.2.4...v1.3.0

# [v1.2.4](https://github.com/dreeveapp/dreeve/releases/tag/v1.2.4) - 2025-04-20

## What's Changed
* ISSUE-503: Visiual indicator when maintenance task is due by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#506](https://github.com/dreeveapp/dreeve/pull/506)
* ISSUE-508: Gear maintenance normalize gear ids by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#509](https://github.com/dreeveapp/dreeve/pull/509)
* ISSUE-510: When a component is attached to multiple gears, we need to… by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#511](https://github.com/dreeveapp/dreeve/pull/511)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.2.3...v1.2.4

# [v1.2.3](https://github.com/dreeveapp/dreeve/releases/tag/v1.2.3) - 2025-04-16

## What's Changed
* Gear maintanance mobile layout by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#498](https://github.com/dreeveapp/dreeve/pull/498)
* ISSUE-499: Move Gear maintenance to it's own dedicated page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#500](https://github.com/dreeveapp/dreeve/pull/500)
* ISSUE-501: Filter activities with no gear by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#502](https://github.com/dreeveapp/dreeve/pull/502)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.2.2...v1.2.3

# [v1.2.2](https://github.com/dreeveapp/dreeve/releases/tag/v1.2.2) - 2025-04-15

Last one for today, I promise 👼 

## What's Changed
* ISSUE-496: gear maintenance fix bug when last tagged activity is most recent one by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#497](https://github.com/dreeveapp/dreeve/pull/497)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.2.1...v1.2.2

# [v1.2.1](https://github.com/dreeveapp/dreeve/releases/tag/v1.2.1) - 2025-04-15

## What's Changed
* Fix gear maintenance image bug by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#494](https://github.com/dreeveapp/dreeve/pull/494)
* Minor gear maintenance layout fixes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#495](https://github.com/dreeveapp/dreeve/pull/495)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.2.0...v1.2.1

# [v1.2.0](https://github.com/dreeveapp/dreeve/releases/tag/v1.2.0) - 2025-04-15

## Big new feature 🎉!

**New feature: Gear (component) maintenance tracking!**. Learn how it works and how to enable it on https://github.com/dreeveapp/dreeve/wiki/Gear-maintenance

## What's Changed
* ISSUE-491: Add missing french translations by [@Ahmosys](https://github.com/Ahmosys) in [#492](https://github.com/dreeveapp/dreeve/pull/492)
* ISSUE-412: Gear maintenance validate and process by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#488](https://github.com/dreeveapp/dreeve/pull/488)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.1.4...v1.2.0

# [v1.1.4](https://github.com/dreeveapp/dreeve/releases/tag/v1.1.4) - 2025-04-10

## What's Changed
* ISSUE-485: Fix activity pace graph for unreasonable slow speeds by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#486](https://github.com/dreeveapp/dreeve/pull/486)
* Update Simplified Chinese translations by [@c0j0s](https://github.com/c0j0s) in [#487](https://github.com/dreeveapp/dreeve/pull/487)
* Bumped APP vrsion by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#490](https://github.com/dreeveapp/dreeve/pull/490)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.1.3...v1.1.4

# [v1.1.3](https://github.com/dreeveapp/dreeve/releases/tag/v1.1.3) - 2025-04-06

## What's Changed
* ISSUE-481: Activity best effort chart shows wrong data when multiple activities result in the same best average by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#482](https://github.com/dreeveapp/dreeve/pull/482)
* ISSUE-483: Fix rendering of charts in tabs in combo with modals by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#484](https://github.com/dreeveapp/dreeve/pull/484)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.1.2...v1.1.3

# [v1.1.2](https://github.com/dreeveapp/dreeve/releases/tag/v1.1.2) - 2025-04-04

## What's Changed
* Update discord link in README.md by [@valueduser](https://github.com/valueduser) in [#474](https://github.com/dreeveapp/dreeve/pull/474)
* ISSUE-477: Tweak Ramer-Douglas-Peucker by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#479](https://github.com/dreeveapp/dreeve/pull/479)
* ISSUE-476: Add the pace unit to the tooltips by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#480](https://github.com/dreeveapp/dreeve/pull/480)

## New Contributors
* [@valueduser](https://github.com/valueduser) made their first contribution in [#474](https://github.com/dreeveapp/dreeve/pull/474)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.1.1...v1.1.2

# [v1.1.1](https://github.com/dreeveapp/dreeve/releases/tag/v1.1.1) - 2025-04-02

## What's Changed
* ISSUE-471: Fixed bug for importing challenges from trophy case by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#472](https://github.com/dreeveapp/dreeve/pull/472)
* ISSUE-470: Update isCommute during activity import by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#473](https://github.com/dreeveapp/dreeve/pull/473)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.1.0...v1.1.1

# [v1.1.0](https://github.com/dreeveapp/dreeve/releases/tag/v1.1.0) - 2025-04-01

ℹ️ This release introduces "activity profile charts". The first time after updating, the data import might take some time as it needs to calculate combined activity streams for each of your activities. 

## What's Changed
* ISSUE-432: Add elevation profile by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#460](https://github.com/dreeveapp/dreeve/pull/460)
* ISSUE-465: Boost gear stats performance by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#468](https://github.com/dreeveapp/dreeve/pull/468)
* ISSUE-467: Filter on workout type by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#469](https://github.com/dreeveapp/dreeve/pull/469)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.0.1...v1.1.0

# [v1.0.1](https://github.com/dreeveapp/dreeve/releases/tag/v1.0.1) - 2025-03-29

## What's Changed
* ISSUE-461: Fix Discord invite link by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#462](https://github.com/dreeveapp/dreeve/pull/462)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v1.0.0...v1.0.1

# [v1.0.0](https://github.com/dreeveapp/dreeve/releases/tag/v1.0.0) - 2025-03-28

Finally, the first stable release 🎉! There will be no breaking changes within the same major versions from now on.
Expect a lot of cool new features coming soon!

## What's Changed
* ISSUE 456: Carbon emission saved comparison by [@robiningelbrecht](https://github.com/robiningelbrecht) in [#459](https://github.com/dreeveapp/dreeve/pull/459)

**Full Changelog**: https://github.com/dreeveapp/dreeve/compare/v0.4.32...v1.0.0

# [v0.4.32](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.32) - 2025-03-27

## What's Changed
* ISSUE-434: Activity best efforts by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#445](https://github.com/robiningelbrecht/strava-statistics/pull/445)
* ISSUE-449: Add gear filter on activities overview by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#454](https://github.com/robiningelbrecht/strava-statistics/pull/454)
* ISSUE-440: Heatmap does not load when there are no states in reverse geocoded data by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#441](https://github.com/robiningelbrecht/strava-statistics/pull/441)
* Gear chart improvements by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#442](https://github.com/robiningelbrecht/strava-statistics/pull/442)
* ISSUE-451: Add STAND_UP_PADDLING sport type to heat map by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#452](https://github.com/robiningelbrecht/strava-statistics/pull/452)
* ISSUE-447: Fix flag tooltip on heatmap by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#453](https://github.com/robiningelbrecht/strava-statistics/pull/453)
* ISSUE-450: Ignore power data from E-bikes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#455](https://github.com/robiningelbrecht/strava-statistics/pull/455)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.31...v0.4.32

# [v0.4.31](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.31) - 2025-03-22

## What's Changed
* ISSUE-422: Show percentage of countries you worked out in by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#430](https://github.com/robiningelbrecht/strava-statistics/pull/430)
* ISSUE-426: Add country filter on activity overview page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#435](https://github.com/robiningelbrecht/strava-statistics/pull/435)
* ISSUE-431: Added commute filter on activities overview page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#437](https://github.com/robiningelbrecht/strava-statistics/pull/437)
* ISSUE-436: Add scrollwheel zoom in/out in heatmap by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#438](https://github.com/robiningelbrecht/strava-statistics/pull/438)
* ISSUE-428: Allow to only import activities after a certain date by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#439](https://github.com/robiningelbrecht/strava-statistics/pull/439)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.30...v0.4.31

# [v0.4.30](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.30) - 2025-03-21

## What's Changed
* Added link to discord server by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#413](https://github.com/robiningelbrecht/strava-statistics/pull/413)
* Update some pt_PT translations and add missing translations to templates that were missing them by [@jcnmsg](https://github.com/jcnmsg) in [robiningelbrecht/strava-statistics#415](https://github.com/robiningelbrecht/strava-statistics/pull/415)
* ISSUE-414: Fix nginx config 404 bug in very rare cases by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#418](https://github.com/robiningelbrecht/strava-statistics/pull/418)
* Add Symfony profiler by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#419](https://github.com/robiningelbrecht/strava-statistics/pull/419)
* ISSUE-420: Allow to set MAX_HEART_RATE_FORMULA by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#424](https://github.com/robiningelbrecht/strava-statistics/pull/424)
* ISSUE-422: Allow to zoom yearly distance chart by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#425](https://github.com/robiningelbrecht/strava-statistics/pull/425)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.29...v0.4.30

# [v0.4.29](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.29) - 2025-03-18

## What's Changed
* ISSUE-390: Fixed a bug in updating images by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#401](https://github.com/robiningelbrecht/strava-statistics/pull/401)
* ISSUE-402: Slideshow / gallery on photos page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#403](https://github.com/robiningelbrecht/strava-statistics/pull/403)
* ISSUE-405: Allow to build app after importing first activity by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#407](https://github.com/robiningelbrecht/strava-statistics/pull/407)
* ISSUE-408: Better error handling while using FallbackEnvVarProcessor by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#409](https://github.com/robiningelbrecht/strava-statistics/pull/409)
* ISSUE-410: Do not decode contents while downloading images by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#411](https://github.com/robiningelbrecht/strava-statistics/pull/411)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.28...v0.4.29

# [v0.4.28](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.28) - 2025-03-15

## What's Changed
* ISSUE-393: Improve heart rate chart by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#394](https://github.com/robiningelbrecht/strava-statistics/pull/394)
* ISSUE-390: Import images while updating activities during import by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#395](https://github.com/robiningelbrecht/strava-statistics/pull/395)
* Weekly stats chart label by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#396](https://github.com/robiningelbrecht/strava-statistics/pull/396)
* ISSUE-397: Fix manifest boo boo by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#398](https://github.com/robiningelbrecht/strava-statistics/pull/398)
* ISSUE-399: Ignore heart rates that are fubar by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#400](https://github.com/robiningelbrecht/strava-statistics/pull/400)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.27...v0.4.28

# [v0.4.27](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.27) - 2025-03-14

## What's Changed
* ISSUE-384: Better and more logging by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#385](https://github.com/robiningelbrecht/strava-statistics/pull/385)
* ISSUE-386: Rename ENV variables by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#388](https://github.com/robiningelbrecht/strava-statistics/pull/388)
* ISSUE-391: Sanitize gear names by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#392](https://github.com/robiningelbrecht/strava-statistics/pull/392)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.26...v0.4.27

# [v0.4.26](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.26) - 2025-03-11

## What's Changed
* ISSUE-380: Calculation for "Since I began working out" bug fix by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#381](https://github.com/robiningelbrecht/strava-statistics/pull/381)
* ISSUE-382: Improve weeklyDistanceTimeChart by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#383](https://github.com/robiningelbrecht/strava-statistics/pull/383)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.25...v0.4.26

# [v0.4.25](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.25) - 2025-03-10

The app is now available in 🇩🇪 German and 🇵🇹 Portuguese! 

## What's Changed
* German translation messages+intl-icu.de_DE.yaml by [@daydreamer77](https://github.com/daydreamer77) in [robiningelbrecht/strava-statistics#368](https://github.com/robiningelbrecht/strava-statistics/pull/368)
* ISSE-369: Enable de_DE localisation by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#370](https://github.com/robiningelbrecht/strava-statistics/pull/370)
* ISSUE-369: ExtractTranslationsConsoleCommand by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#374](https://github.com/robiningelbrecht/strava-statistics/pull/374)
* Add European Portuguese translations by [@jcnmsg](https://github.com/jcnmsg) in [robiningelbrecht/strava-statistics#373](https://github.com/robiningelbrecht/strava-statistics/pull/373)
* ISSUE-373: Enable Portuguese translations by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#375](https://github.com/robiningelbrecht/strava-statistics/pull/375)
* ISSUE-363: Fix bug in leaflet map render by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#376](https://github.com/robiningelbrecht/strava-statistics/pull/376)

## New Contributors
* [@daydreamer77](https://github.com/daydreamer77) made their first contribution in [robiningelbrecht/strava-statistics#368](https://github.com/robiningelbrecht/strava-statistics/pull/368)
* [@jcnmsg](https://github.com/jcnmsg) made their first contribution in [robiningelbrecht/strava-statistics#373](https://github.com/robiningelbrecht/strava-statistics/pull/373)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.24...v0.4.25

# [v0.4.24](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.24) - 2025-03-07

## What's Changed
* Rebranding left overs by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#349](https://github.com/robiningelbrecht/strava-statistics/pull/349)
* Fix badge cache control by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#350](https://github.com/robiningelbrecht/strava-statistics/pull/350)
* ISSUE-353: Attempt at BuildGpxFilesCommandHandler using less resources by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#354](https://github.com/robiningelbrecht/strava-statistics/pull/354)
* ISSUE-352: Activity visibilities to import by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#355](https://github.com/robiningelbrecht/strava-statistics/pull/355)
* ISSUE-356: Fix badge color on challenges page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#357](https://github.com/robiningelbrecht/strava-statistics/pull/357)
* ISSUE-351: More translatables by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#361](https://github.com/robiningelbrecht/strava-statistics/pull/361)
* ISSUE 351: More translation fixes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#362](https://github.com/robiningelbrecht/strava-statistics/pull/362)
* ISSUE-364: Escape special chars when buildoing GPX files by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#365](https://github.com/robiningelbrecht/strava-statistics/pull/365)
* ISSUE-351: Fix typos and missing French translations by [@Ahmosys](https://github.com/Ahmosys) in [robiningelbrecht/strava-statistics#359](https://github.com/robiningelbrecht/strava-statistics/pull/359)
* Bump app versionb to v0.4.24 by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#367](https://github.com/robiningelbrecht/strava-statistics/pull/367)

## New Contributors
* [@Ahmosys](https://github.com/Ahmosys) made their first contribution in [robiningelbrecht/strava-statistics#359](https://github.com/robiningelbrecht/strava-statistics/pull/359)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.23...v0.4.24

# [v0.4.23](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.23) - 2025-03-04

## What's Changed
* ISSUE-342: Scale Y-axis for weekly distance/time based on selected time range by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#343](https://github.com/robiningelbrecht/strava-statistics/pull/343)
* ISSUE-335: Allow to set own profile picture by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#345](https://github.com/robiningelbrecht/strava-statistics/pull/345)
* ISSUE-323: Zwift badge by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#347](https://github.com/robiningelbrecht/strava-statistics/pull/347)
* ISSUE-346: Show pace for running activities in overviews by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#348](https://github.com/robiningelbrecht/strava-statistics/pull/348)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.22...v0.4.23

# [v0.4.22](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.22) - 2025-03-03

## What's Changed
* Update messages+intl-icu.fr_FR.yaml by [@llaumgui](https://github.com/llaumgui) in [robiningelbrecht/strava-statistics#326](https://github.com/robiningelbrecht/strava-statistics/pull/326)
* ISSUE-319: Better testing by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#330](https://github.com/robiningelbrecht/strava-statistics/pull/330)
* ISSUE-328: Better error handling and readme by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#331](https://github.com/robiningelbrecht/strava-statistics/pull/331)
* ISSUE-327: Skip import of challenges that are un-importable by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#333](https://github.com/robiningelbrecht/strava-statistics/pull/333)
* ISSUE-320: Use the png version of the logo by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#338](https://github.com/robiningelbrecht/strava-statistics/pull/338)
* ISSUE-329: Rebrand to Dreeve by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#339](https://github.com/robiningelbrecht/strava-statistics/pull/339)
* ISSUE-336: Support Windsurf by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#340](https://github.com/robiningelbrecht/strava-statistics/pull/340)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.21...v0.4.22

# [v0.4.21](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.21) - 2025-03-02

## What's Changed
* ISSUE-315: Properly use FlySystem and have separate filesystems in place by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#316](https://github.com/robiningelbrecht/strava-statistics/pull/316)
* feat: add simplified chinese translation by [@c0j0s](https://github.com/c0j0s) in [robiningelbrecht/strava-statistics#318](https://github.com/robiningelbrecht/strava-statistics/pull/318)
* ISSUE-322: Fix bug where we override streamsAreImported during each i… by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#324](https://github.com/robiningelbrecht/strava-statistics/pull/324)
* ISSUE-320: Fix reference to logo in Ntfy notification by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#325](https://github.com/robiningelbrecht/strava-statistics/pull/325)

## New Contributors
* [@c0j0s](https://github.com/c0j0s) made their first contribution in [robiningelbrecht/strava-statistics#318](https://github.com/robiningelbrecht/strava-statistics/pull/318)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.20...v0.4.21

# [v0.4.20](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.20) - 2025-02-28

⚠️ This release will import a lot of new data. Depending on the amount of activities you have, this might take a few days.

## What's Changed
* ISSUE-290: Generate GPX files in preparation of Dawarich integration by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#304](https://github.com/robiningelbrecht/strava-statistics/pull/304)
* ISSUE-307: Re-arrange sport and activity types by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#311](https://github.com/robiningelbrecht/strava-statistics/pull/311)
* ISSUE-308: Use PHP attributes to tag domain commands with commandTypes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#312](https://github.com/robiningelbrecht/strava-statistics/pull/312)
* ISSUE-305: Update activity polyline by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#313](https://github.com/robiningelbrecht/strava-statistics/pull/313)
* ISSUE-290: Only create non-existing gpx files by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#314](https://github.com/robiningelbrecht/strava-statistics/pull/314)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.19...v0.4.20

# [v0.4.19](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.19) - 2025-02-25

## What's Changed
* ISSUE-305: Update more activity properties during import by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#306](https://github.com/robiningelbrecht/strava-statistics/pull/306)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.18...v0.4.19

# [v0.4.18](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.18) - 2025-02-24

## What's Changed
* Fix port documentation by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#299](https://github.com/robiningelbrecht/strava-statistics/pull/299)
* New updated version of Watopia map by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#300](https://github.com/robiningelbrecht/strava-statistics/pull/300)
* ISSUE-294: Make sure Trivia is cached by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#301](https://github.com/robiningelbrecht/strava-statistics/pull/301)
* ISSUE-302: Trivia total carbon saved by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#303](https://github.com/robiningelbrecht/strava-statistics/pull/303)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.17...v0.4.18

# [v0.4.17](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.17) - 2025-02-23

## What's Changed
* ISSUE-285: Split up "BuildApp" domain command by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#286](https://github.com/robiningelbrecht/strava-statistics/pull/286)
* ISSUE-266: Peak power outputs  by [@robiningelbrecht](https://github.com/robiningelbrecht), [@f0ns](https://github.com/f0ns) in [robiningelbrecht/strava-statistics#295](https://github.com/robiningelbrecht/strava-statistics/pull/295)
* ISSUE-294: Trivia rework by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#297](https://github.com/robiningelbrecht/strava-statistics/pull/297)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.16...v0.4.17

# [v0.4.16](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.16) - 2025-02-19

## What's Changed
* ISSUE-284: Fixed menu items there were too long by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#288](https://github.com/robiningelbrecht/strava-statistics/pull/288)
* ISSUE-287: Fix bug where heatmap would not load if state contained quotes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#289](https://github.com/robiningelbrecht/strava-statistics/pull/289)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.15...v0.4.16

# [v0.4.15](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.15) - 2025-02-18

## What's Changed
* ISSUE-282: Skating support by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#283](https://github.com/robiningelbrecht/strava-statistics/pull/283)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.14...v0.4.15

# [v0.4.14](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.14) - 2025-02-15

## What's Changed
* ISSUE-262: Heatmap filters by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#277](https://github.com/robiningelbrecht/strava-statistics/pull/277)
* ISSUE-279: Upgrade to Tailwind v4 by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#280](https://github.com/robiningelbrecht/strava-statistics/pull/280)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.13...v0.4.14

# [v0.4.13](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.13) - 2025-02-12

🎉 Special thanks to [@FoxxMD](https://github.com/FoxxMD) for helping out and configuring the docker image

## What's Changed
* ISSUE-232: Improve test coverage by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#261](https://github.com/robiningelbrecht/strava-statistics/pull/261)
* ISSUE-263: Split up BuildApp domain command by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#265](https://github.com/robiningelbrecht/strava-statistics/pull/265)
* ISSUE-246: Add modals to router and history state by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#270](https://github.com/robiningelbrecht/strava-statistics/pull/270)
* ISSUE-269: Disable cache for badge.svg by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#271](https://github.com/robiningelbrecht/strava-statistics/pull/271)
* ISSUE-272: Make sure error pages are available before building app by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#273](https://github.com/robiningelbrecht/strava-statistics/pull/273)
* ISSUE-274: Tell fpm what the owner/group of the socket should be by [@FoxxMD](https://github.com/FoxxMD) in [robiningelbrecht/strava-statistics#275](https://github.com/robiningelbrecht/strava-statistics/pull/275)
* ISSUE-267: Distance precision per activity type by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#276](https://github.com/robiningelbrecht/strava-statistics/pull/276)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.12...v0.4.13

# [v0.4.12](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.12) - 2025-02-09

## What's Changed
* fix: Fix bad permissions on /run by [@FoxxMD](https://github.com/FoxxMD) in [robiningelbrecht/strava-statistics#247](https://github.com/robiningelbrecht/strava-statistics/pull/247)
* ISSUE-234: Render user badge by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#251](https://github.com/robiningelbrecht/strava-statistics/pull/251)
* ISSUE-234: Render modal with badge example by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#252](https://github.com/robiningelbrecht/strava-statistics/pull/252)
* ISSUE-245: Hide clear link when no filters are applied by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#253](https://github.com/robiningelbrecht/strava-statistics/pull/253)
* ISSUE-234: Fix activity titles with a lot of uppercase chars by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#255](https://github.com/robiningelbrecht/strava-statistics/pull/255)
* ISSUE-254: Fix activity description import by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#256](https://github.com/robiningelbrecht/strava-statistics/pull/256)
* ISSUE-249: Invalidate browser cache by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#257](https://github.com/robiningelbrecht/strava-statistics/pull/257)
* ISSUE-249: Added asset versioning strategy by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#258](https://github.com/robiningelbrecht/strava-statistics/pull/258)
* ISSUE-196: List rank of segments for activity by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#259](https://github.com/robiningelbrecht/strava-statistics/pull/259)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.11...v0.4.12

# [v0.4.11](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.11) - 2025-02-06

Sorry for all the releases 🦧

## What's Changed
* ISSUE-243: Fix PWA and gear names by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#244](https://github.com/robiningelbrecht/strava-statistics/pull/244)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.10...v0.4.11

# [v0.4.10](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.10) - 2025-02-06

## What's Changed
* New logo, final... maybe by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#242](https://github.com/robiningelbrecht/strava-statistics/pull/242)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.9...v0.4.10

# [v0.4.9](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.9) - 2025-02-06

## What's Changed
* New logo... again by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#231](https://github.com/robiningelbrecht/strava-statistics/pull/231)
* ISSUE-219: Average heart rate for activity splits by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#233](https://github.com/robiningelbrecht/strava-statistics/pull/233)
* ISSUE-237: Fix activity pace for imperial nuts :) by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#238](https://github.com/robiningelbrecht/strava-statistics/pull/238)
* ISSUE-197: Date range filter by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#235](https://github.com/robiningelbrecht/strava-statistics/pull/235)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.8...v0.4.9

# [v0.4.8](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.8) - 2025-01-31

## What's Changed
* ISSUE-213: Show totals at top of activities table by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#226](https://github.com/robiningelbrecht/strava-statistics/pull/226)
* ISSUE-224: Add debounce to overview search by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#227](https://github.com/robiningelbrecht/strava-statistics/pull/227)
* ISSUE-225: Fix activity split pace for imperial nuts :) by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#228](https://github.com/robiningelbrecht/strava-statistics/pull/228)
* v0.4.8 Bumped APP version by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#229](https://github.com/robiningelbrecht/strava-statistics/pull/229)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.7...v0.4.8

# [v0.4.7](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.7) - 2025-01-31

## What's Changed
* Better pwa logos by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#212](https://github.com/robiningelbrecht/strava-statistics/pull/212)
* ISSUE-214: Fix gear names by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#215](https://github.com/robiningelbrecht/strava-statistics/pull/215)
* ISSUE-218: Avg pace for running activity by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#220](https://github.com/robiningelbrecht/strava-statistics/pull/220)
* ISSUE-217: Added environment debug console command by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#221](https://github.com/robiningelbrecht/strava-statistics/pull/221)
* V0.4.7 by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#222](https://github.com/robiningelbrecht/strava-statistics/pull/222) by [@FoxxMD](https://github.com/FoxxMD) 

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.6...v0.4.7

# [v0.4.6](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.6) - 2025-01-28

## What's Changed
* ISSUE-194: Code coverage report by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#205](https://github.com/robiningelbrecht/strava-statistics/pull/205)
* ISSUE-204: Added manifest to support PWA by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#208](https://github.com/robiningelbrecht/strava-statistics/pull/208)
* ISSUE-206: Proper error pages by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#209](https://github.com/robiningelbrecht/strava-statistics/pull/209)
* ISSUE-204: Fix manifest logos by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#210](https://github.com/robiningelbrecht/strava-statistics/pull/210)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.5...v0.4.6

# [v0.4.5](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.5) - 2025-01-28

## What's Changed
* ISSUE-131-132: Render running stats by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#201](https://github.com/robiningelbrecht/strava-statistics/pull/201)
* ISSUE-202: Fix overview search and sort by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#203](https://github.com/robiningelbrecht/strava-statistics/pull/203)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.4...v0.4.5

# [v0.4.4](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.4) - 2025-01-26

## What's Changed
* Cleanup snapshots by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#195](https://github.com/robiningelbrecht/strava-statistics/pull/195)
* ISSUE-132: Activity splits for run stats by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#193](https://github.com/robiningelbrecht/strava-statistics/pull/193)
* ISSUE-132: Calculate relative pace speed by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#199](https://github.com/robiningelbrecht/strava-statistics/pull/199)
* ISSUE-198: Fix translation bug in setup.html.twig by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#200](https://github.com/robiningelbrecht/strava-statistics/pull/200)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.3...v0.4.4

# [v0.4.3](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.3) - 2025-01-24

## What's Changed
* More fr_FR translations by [@llaumgui](https://github.com/llaumgui) in [robiningelbrecht/strava-statistics#189](https://github.com/robiningelbrecht/strava-statistics/pull/189)
* ISSUE-186: Add a scheduler by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#190](https://github.com/robiningelbrecht/strava-statistics/pull/190)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.2...v0.4.3

# [v0.4.2](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.2) - 2025-01-24

## What's Changed
* ISSUE-187: Fix bug where eddington is rendered for non existing activ… by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#188](https://github.com/robiningelbrecht/strava-statistics/pull/188)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.1...v0.4.2

# [v0.4.1](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.1) - 2025-01-23

## What's Changed
* Translate messages+intl-icu.fr_FR.yaml by [@llaumgui](https://github.com/llaumgui) in [robiningelbrecht/strava-statistics#184](https://github.com/robiningelbrecht/strava-statistics/pull/184)
* ISSUE-174: more translations by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#185](https://github.com/robiningelbrecht/strava-statistics/pull/185)

## New Contributors
* [@llaumgui](https://github.com/llaumgui) made their first contribution in [robiningelbrecht/strava-statistics#184](https://github.com/robiningelbrecht/strava-statistics/pull/184)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.4.0...v0.4.1

# [v0.4.0](https://github.com/dreeveapp/dreeve/releases/tag/v0.4.0) - 2025-01-23

> [!WARNING]
Make sure to backup your database before upgrading. This release has a working migrate path from `v0.3.x` to `v0.4.0`, but since there was a huge backend overhaul, it's better to be safe than sorry right? 😎

This release adds support for localisations and translations. Expect more new cool features soon.

## What's Changed
* ISSUE-161: Do not allow to build HTML files if there are still migrat… by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#165](https://github.com/robiningelbrecht/strava-statistics/pull/165)
* ISSUE-168: Improve error messages by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#169](https://github.com/robiningelbrecht/strava-statistics/pull/169)
* Move measurement namespace to infrastructure by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#170](https://github.com/robiningelbrecht/strava-statistics/pull/170)
* Re-order templates by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#171](https://github.com/robiningelbrecht/strava-statistics/pull/171)
* ISSUE-131: Add running specific activity templates by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#172](https://github.com/robiningelbrecht/strava-statistics/pull/172)
* ISSUE-166: Try to normalize database and improve performance by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#176](https://github.com/robiningelbrecht/strava-statistics/pull/176)
* ISSUE-173: Metric imperial for weather by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#177](https://github.com/robiningelbrecht/strava-statistics/pull/177)
* ISSUE-174: Add locales and translations by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#179](https://github.com/robiningelbrecht/strava-statistics/pull/179)
* ISSUE-174: Translatables for challenge consistencies by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#181](https://github.com/robiningelbrecht/strava-statistics/pull/181)
* ISSUE-174: Added nl_BE translations by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#183](https://github.com/robiningelbrecht/strava-statistics/pull/183)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.9...v0.4.0

# [v0.3.9](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.9) - 2025-01-15

## What's Changed
* ISSUE-163: Fix bug introduced in v0.3.7 where average heart rate of 0 was considered NULL by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#164](https://github.com/robiningelbrecht/strava-statistics/pull/164)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.8...v0.3.9

# [v0.3.8](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.8) - 2025-01-14

## What's Changed
* ISSUE-158: Add proper label to the SportType and ActivityType enums tech debt by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#160](https://github.com/robiningelbrecht/strava-statistics/pull/160)
* ISSUE-154: Add support for multiple date and time formats by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#162](https://github.com/robiningelbrecht/strava-statistics/pull/162)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.7...v0.3.8

# [v0.3.7](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.7) - 2025-01-14

## What's Changed
* ISSUE-155: Added water sport to yearly stats by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#156](https://github.com/robiningelbrecht/strava-statistics/pull/156)
* ISSUE-157: Major performance improvements while building HTML files by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#159](https://github.com/robiningelbrecht/strava-statistics/pull/159)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.6...v0.3.7

# [v0.3.6](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.6) - 2025-01-13

## What's Changed
* ISSUE-148: Try to use less resources while calculating stream avarages by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#152](https://github.com/robiningelbrecht/strava-statistics/pull/152)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.5...v0.3.6

# [v0.3.5](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.5) - 2025-01-12

## What's Changed
* ISSUE-143: PHPstan type coverage by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#144](https://github.com/robiningelbrecht/strava-statistics/pull/144)
* ISSUE-146: Fix month label by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#147](https://github.com/robiningelbrecht/strava-statistics/pull/147)
* ISSUE-104: Add support for ntfy.sh by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#145](https://github.com/robiningelbrecht/strava-statistics/pull/145)
* ISSUE-148: Disable query log to save memory by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#149](https://github.com/robiningelbrecht/strava-statistics/pull/149)
* ISSUE-140: Allow to set a maximum number of activities to import per run by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#142](https://github.com/robiningelbrecht/strava-statistics/pull/142)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.4...v0.3.5

# [v0.3.4](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.4) - 2025-01-11

## What's Changed
* ISSUE-130: UI fixes for monthly overview by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#139](https://github.com/robiningelbrecht/strava-statistics/pull/139)
* ISSUE-137: Allow to skip ativities from being imported by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#141](https://github.com/robiningelbrecht/strava-statistics/pull/141)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.3...v0.3.4

# [v0.3.3](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.3) - 2025-01-10

## What's Changed
* ISSUE-134: Eddington history chart, show the current eddington number by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#137](https://github.com/robiningelbrecht/strava-statistics/pull/137)
* ISSUE-135: Fix various bugs when activities have no distances by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#138](https://github.com/robiningelbrecht/strava-statistics/pull/138)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.2...v0.3.3

# [v0.3.2](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.2) - 2025-01-09

## What's Changed
* ISSUE-124: Do not timeout migration process by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#125](https://github.com/robiningelbrecht/strava-statistics/pull/125)
* ISSUE-117: Improve app.php by using autoloading and twig template by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#126](https://github.com/robiningelbrecht/strava-statistics/pull/126)
* ISSUE-114: Allow to set a custom order for sportTypes and activityTypes by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#118](https://github.com/robiningelbrecht/strava-statistics/pull/118)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.1...v0.3.2

# [v0.3.1](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.1) - 2025-01-09

## What's Changed
* ISSUE-120: Better CLI output by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#121](https://github.com/robiningelbrecht/strava-statistics/pull/121)
* ISSUE-119: Fix bug when there's no peak power data by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#122](https://github.com/robiningelbrecht/strava-statistics/pull/122)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.3.0...v0.3.1

# [v0.3.0](https://github.com/dreeveapp/dreeve/releases/tag/v0.3.0) - 2025-01-08

# Breaking change!

⚠️ This release introduces a breaking change, please read https://github.com/robiningelbrecht/strava-statistics/wiki/Migrate-from-v0.2.x-to-v0.3.x before upgrading

## What's Changed
* Re-configure Tailwind by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#102](https://github.com/robiningelbrecht/strava-statistics/pull/102)
* Update vendor packages by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#105](https://github.com/robiningelbrecht/strava-statistics/pull/105)
* ISSUE-43: Add filterables to activities and segments by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#108](https://github.com/robiningelbrecht/strava-statistics/pull/108)
* ISSUE-109: Render athlete name in head title by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#111](https://github.com/robiningelbrecht/strava-statistics/pull/111)
* ISSUE-88: Distance breakdown stats for all activity types by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#113](https://github.com/robiningelbrecht/strava-statistics/pull/113)
* ISSUE-110: Project needed data to Segment table while importing by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#112](https://github.com/robiningelbrecht/strava-statistics/pull/112)
* ISSUE-103: Yearly stats per activity type by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#116](https://github.com/robiningelbrecht/strava-statistics/pull/116)
* ISSUE-62: Import all activity types by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#82](https://github.com/robiningelbrecht/strava-statistics/pull/82)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.13...v0.3.0

# [v0.2.13](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.13) - 2025-01-06

## What's Changed
* ISSUE-98: Fix weekly distance chart first week of january by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#99](https://github.com/robiningelbrecht/strava-statistics/pull/99)
* ISSUE-100: UI bug fix introduced by [#93](https://github.com/dreeveapp/dreeve/issues/93) by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#101](https://github.com/robiningelbrecht/strava-statistics/pull/101)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.12...v0.2.13

# [v0.2.12](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.12) - 2025-01-06

## What's Changed
* ISSUE-91: Slow down import to avoid hitting API rate limits by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#94](https://github.com/robiningelbrecht/strava-statistics/pull/94)
* ISSUE-93: Abide to Stravas branding rules by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#96](https://github.com/robiningelbrecht/strava-statistics/pull/96)
* ISSUE-93: Fix mobile UI issues by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#97](https://github.com/robiningelbrecht/strava-statistics/pull/97)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.11...v0.2.12

# [v0.2.11](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.11) - 2025-01-05

## What's Changed
* ISSUE-84: Fix bug where 404 streams caused app to hit Strava API limits by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#92](https://github.com/robiningelbrecht/strava-statistics/pull/92)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.10...v0.2.11

# [v0.2.10](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.10) - 2025-01-05

## What's Changed
* ISSUE-86: Fix week number 53 on the end of the year by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#87](https://github.com/robiningelbrecht/strava-statistics/pull/87)
* ISSUE-84: Add logging when using Strava API by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#90](https://github.com/robiningelbrecht/strava-statistics/pull/90)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.9...v0.2.10

# [v0.2.9](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.9) - 2025-01-04

## What's Changed
* ISSUE-79: Fix bug when in DistanceBreakdown when there are no activities by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#80](https://github.com/robiningelbrecht/strava-statistics/pull/80)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.8...v0.2.9

# [v0.2.8](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.8) - 2025-01-04

## What's Changed
* ISSUE-62: Intro PR to start importing all activity types by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#78](https://github.com/robiningelbrecht/strava-statistics/pull/78)
* ISSUE-83: Fix DATABASE_URL in .env by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#85](https://github.com/robiningelbrecht/strava-statistics/pull/85)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.7...v0.2.8

# [v0.2.7](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.7) - 2025-01-02

## What's Changed
* ISSUE-70: Only include sports which there are activities for in the m… by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#71](https://github.com/robiningelbrecht/strava-statistics/pull/71)
* ISSUE-70-bis: Introduced UI bug by merging ISSUE-70 by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#72](https://github.com/robiningelbrecht/strava-statistics/pull/72)
* ISSUE-73: Fix references to metric values in imperial system by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#74](https://github.com/robiningelbrecht/strava-statistics/pull/74)
* ISSUE-76: Re-worked Strava API rate limits by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#77](https://github.com/robiningelbrecht/strava-statistics/pull/77)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.6...v0.2.7

# [v0.2.6](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.6) - 2025-01-01

## What's Changed
* ISSUE-60: Moved activity intensity to separate service by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#66](https://github.com/robiningelbrecht/strava-statistics/pull/66)
* ISSUE-26: Delete segment and segmentEfforts on activity delete by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#68](https://github.com/robiningelbrecht/strava-statistics/pull/68)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.5...v0.2.6

# [v0.2.5](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.5) - 2024-12-31

## What's Changed
* ISSUE-41: Better version UX by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#55](https://github.com/robiningelbrecht/strava-statistics/pull/55)
* ISSUE-42: Add a count badge next to the month on the challenges page by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#56](https://github.com/robiningelbrecht/strava-statistics/pull/56)
* ISSUE-46: Remove references to dark mode by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#58](https://github.com/robiningelbrecht/strava-statistics/pull/58)
* ISSUE-63-64: Better error messages by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#65](https://github.com/robiningelbrecht/strava-statistics/pull/65)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.4...v0.2.5

# [v0.2.4](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.4) - 2024-12-30

## What's Changed
* ISSUE-52: Run migrations before checking import status DEUH by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#54](https://github.com/robiningelbrecht/strava-statistics/pull/54)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.3...v0.2.4

# [v0.2.3](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.3) - 2024-12-30

## What's Changed
* ISSUE-37: Fix docker volume permissions by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#53](https://github.com/robiningelbrecht/strava-statistics/pull/53)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.2...v0.2.3

# [v0.2.2](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.2) - 2024-12-30

## What's Changed
* ISSUE-50: Mark import as completed bug by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#51](https://github.com/robiningelbrecht/strava-statistics/pull/51)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.1...v0.2.2

# [v0.2.1](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.1) - 2024-12-30

## What's Changed
* ISSUE-47: Bug determening when to calculate eddington by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#48](https://github.com/robiningelbrecht/strava-statistics/pull/48)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.2.0...v0.2.1

# [v0.2.0](https://github.com/dreeveapp/dreeve/releases/tag/v0.2.0) - 2024-12-30

## What's Changed
* ISSUE-3: Support running activities by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#40](https://github.com/robiningelbrecht/strava-statistics/pull/40)

This release introduces support for runs.
Change your `.env` file and include what type of activities you want to import:

```
ACTIVITY_TYPES_TO_IMPORT='["Ride", "VirtualRide", "Run"]'
```

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.14...v0.2.0

# [v0.1.14](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.14) - 2024-12-29

## What's Changed
* ISSUE-44: Fix bug when importing challenges from trophy case by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#45](https://github.com/robiningelbrecht/strava-statistics/pull/45)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.13...v0.1.14

# [v0.1.13](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.13) - 2024-12-27

## What's Changed
* ISSUE-38: Better error reporting by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#39](https://github.com/robiningelbrecht/strava-statistics/pull/39)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.12...v0.1.13

# [v0.1.12](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.12) - 2024-12-26

## What's Changed
* ISSUE-30: Add power over time chart by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#35](https://github.com/robiningelbrecht/strava-statistics/pull/35)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.11...v0.1.12

# [v0.1.11](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.11) - 2024-12-24

## What's Changed
* ISSUE-15: Got rid of alpe du zwift by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#31](https://github.com/robiningelbrecht/strava-statistics/pull/31)
* ISSUE-33: Mark KOM segments, make them searchable by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#34](https://github.com/robiningelbrecht/strava-statistics/pull/34)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.10...v0.1.11

# [v0.1.10](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.10) - 2024-12-22

## What's Changed
* ISSUE-28: Added app version to UI by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#29](https://github.com/robiningelbrecht/strava-statistics/pull/29)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.9...v0.1.10

# [v0.1.9](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.9) - 2024-12-22

## What's Changed
* ISSUE-24: Get rid of highest heart rates by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#27](https://github.com/robiningelbrecht/strava-statistics/pull/27)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.8...v0.1.9

# [v0.1.8](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.8) - 2024-12-22

## What's Changed
* ISSUE-16: PHP 8.4 upgrade by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#17](https://github.com/robiningelbrecht/strava-statistics/pull/17)
* ISSUE-1: Measurement system by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#22](https://github.com/robiningelbrecht/strava-statistics/pull/22)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.7...v0.1.8

# [v0.1.7](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.7) - 2024-12-20

## What's Changed
* ISSUE-5: Input athlete weight by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#13](https://github.com/robiningelbrecht/strava-statistics/pull/13)
* ISSUE-12: Add ARM64 support by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#14](https://github.com/robiningelbrecht/strava-statistics/pull/14)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.6...v0.1.7

# [v0.1.6](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.6) - 2024-12-17

## What's Changed
* ISSUE-2: Athlete weight division by zero by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#10](https://github.com/robiningelbrecht/strava-statistics/pull/10)
* ISSUE-9: Improved error handling by [@robiningelbrecht](https://github.com/robiningelbrecht) in [robiningelbrecht/strava-statistics#11](https://github.com/robiningelbrecht/strava-statistics/pull/11)

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.5...v0.1.6

# [v0.1.5](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.5) - 2024-12-16

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.4...v0.1.5

# [v0.1.4](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.4) - 2024-12-16

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.3...v0.1.4

# [v0.1.3](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.3) - 2024-12-16

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.2...v0.1.3

# [v0.1.2](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.2) - 2024-12-16

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.1...v0.1.2

# [v0.1.1](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.1) - 2024-12-16

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/compare/v0.1.0...v0.1.1

# [v0.1.0](https://github.com/dreeveapp/dreeve/releases/tag/v0.1.0) - 2024-12-15

**Full Changelog**: https://github.com/robiningelbrecht/strava-statistics/commits/v0.1.0