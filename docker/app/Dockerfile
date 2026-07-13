# Build the fit-tool Go binary (decodes Garmin .FIT files to JSON).
FROM --platform=$BUILDPLATFORM golang:1.25-alpine AS fit-tool
ARG TARGETARCH
WORKDIR /build
COPY docker/fit-tool/go.mod docker/fit-tool/go.sum ./
RUN go mod download
COPY docker/fit-tool/ ./
RUN CGO_ENABLED=0 GOOS=linux GOARCH=${TARGETARCH} go build -ldflags="-s -w" -o /out/fit-tool .

FROM dunglas/frankenphp:1-php8.5-alpine

WORKDIR /var/www

# Run in production by default for all self-hosted instances.
ENV APP_ENV=prod
ENV APP_DEBUG=0

# hadolint ignore=DL3018
RUN apk add --no-cache \
  bash \
  curl \
  file \
  tzdata \
  geos

RUN set -eux; \
	install-php-extensions \
        bcmath \
        ctype \
        curl \
        dom \
        fileinfo \
        gd \
        intl \
        mbstring \
        opcache \
        pdo \
        pdo_sqlite \
        phar \
        session \
        simplexml \
        tokenizer \
        xml \
        xmlreader \
        xmlwriter \
        pcntl \
        zstd;

COPY docker/app/config/php.ini ${PHP_INI_DIR}/php.ini
COPY docker/app/config/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/app/config/snippets/ /etc/frankenphp/snippets/
COPY docker/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Add application
COPY . /var/www/
RUN touch /var/www/.env
RUN rm -Rf docker
RUN mkdir -p /var/www/var/cache/dev
RUN mkdir -p /var/www/var/log

# Install Shoutrrr.
ARG TARGETARCH
ENV TARGETARCH=${TARGETARCH}
ARG SHOUTRRR_VERSION="0.16.1"

RUN if [ "${TARGETARCH}" = "arm64" ]; then \
      curl -fL -o shoutrrr.tar.gz "https://github.com/nicholas-fedor/shoutrrr/releases/download/v$SHOUTRRR_VERSION/shoutrrr_linux_arm64v8_${SHOUTRRR_VERSION}.tar.gz"; \
    elif [ "${TARGETARCH}" = "amd64" ]; then \
      curl -fL -o shoutrrr.tar.gz "https://github.com/nicholas-fedor/shoutrrr/releases/download/v$SHOUTRRR_VERSION/shoutrrr_linux_amd64_${SHOUTRRR_VERSION}.tar.gz"; \
    fi
RUN tar -xzf shoutrrr.tar.gz \
    && chmod +x shoutrrr \
    && mv shoutrrr /usr/bin/shoutrrr && rm shoutrrr.tar.gz

# Install fit-tool (Garmin .FIT decoder).
COPY --from=fit-tool /out/fit-tool /usr/bin/fit-tool

ENTRYPOINT ["docker-entrypoint.sh"]
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]