#  Local setup

Run the following commands to setup the project on your local machine

```bash
> git clone git@github.com:your-name/your-fork.git
> make composer arg="install --no-scripts"
> make build-countries-asset
> make up
```

The `app` container bakes `src/`, `templates/` and `public/` into its image, so rebuild it after you change
any of them:

```bash
> make build-containers
```

Pages are rendered on request and cached under the current app version, so drop the render cache to see your
changes:

```bash
> make clear-cache
```