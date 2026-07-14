compose=docker compose

dc:
	@${compose} -f docker-compose.yml $(cmd)

dcr:
	@make dc cmd="run --rm -e PHP_IDE_CONFIG=serverName=SFS php-cli $(cmd)"

stop:
	@make dc cmd="stop"

up:
	@make dc cmd="up -d"

build-containers:
	@make dc cmd="up -d --build"
	@make dc cmd="--profile on-demand up -d --build"

down:
	@make dc cmd="down"

console:
	@make dcr cmd="bin/console $(arg)"

console-blackfire:
	@make dcr cmd="blackfire run bin/console $(arg)"

console-profiler:
	# We need to use the app container here, otherwise the profiler can't access the files through web requests.
	docker compose exec app bin/console --profile -v $(arg)

composer:
	@make dcr cmd="composer $(arg)"

download-database:
	@make stop
	rm -Rf storage/database/dreeve.*
	scp $(user)@$(server):/home/docker/stacks/dreeve/storage/database/dreeve.db ./storage/database/dreeve.db
	@make dc cmd="up -d --build"

download-assets:
	scp -r $(user)@$(server):/home/docker/stacks/dreeve/storage/files ./storage/

# Database migration helpers.
migrate-diff:
	@make console arg="doctrine:migrations:diff"

# Translation helpers.
translation-extract:
	@make console arg="app:translations:extract"

translation-extract-with-remove:
	@make console arg="app:translations:extract --removeObsoleteTranslatables"

translation-debug:
	@make console arg="debug:translation en_US"

# Code quality tools.
phpunit:
	@make dcr cmd="vendor/bin/phpunit --order-by=random $(arg)"

paratest:
	@make dcr cmd="vendor/bin/paratest --order-by=random --processes=auto $(arg)"

phpunit-html-coverage:
	@make paratest arg="--coverage-html var/coverage"

phpstan:
	@make dcr cmd="vendor/bin/phpstan --memory-limit=1G $(arg)"

csfix:
	@make dcr cmd="vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php"
	@make dcr cmd="caddy fmt --overwrite --config=/var/www/docker/app/config/Caddyfile"

delete-snapshots:
	find . -name __snapshots__ -type d -prune -exec rm -rf {} \;

rector:
	@make dcr cmd="vendor/bin/rector"
	@make dcr cmd="vendor/bin/rector --config rector-tests.php"

# Helpers to build the app.
app-build-assets:
	@make dcr cmd="npx @tailwindcss/cli -i public/css/tailwind.css -o public/css/dist/tailwind.min.css --minify"
	@make dcr cmd="npx @tailwindcss/cli -i public/css/tailwind.css -o public/css/tailwind.output.css"
	@make dcr cmd="node_modules/.bin/webpack --config webpack.config.js"

app-serve-docs:
	docsify serve docs

# Ollama models
ollama-run-llama32:
	docker compose exec ollama ollama pull llama3.2

# Helpers for forks and PRs
fork-fetch-remote:
	git remote add $(remote-name) $(fork-url)
	git fetch $(remote-name) $(fork-branch-name)
	git checkout -b $(remote-name)  $(remote-name)/$(fork-branch-name)

fork-remove:
	git remote remove $(remote-name)