# Build the fit-tool Go binary (decodes Garmin .FIT files to JSON).
FROM --platform=$BUILDPLATFORM golang:1.25-alpine AS fit-tool
ARG TARGETARCH
WORKDIR /build
COPY docker/fit-tool/go.mod docker/fit-tool/go.sum ./
RUN go mod download
COPY docker/fit-tool/ ./
RUN CGO_ENABLED=0 GOOS=linux GOARCH=${TARGETARCH} go build -ldflags="-s -w" -o /out/fit-tool .

FROM php:8.6-cli

COPY docker/php-cli/php.ini "${PHP_INI_DIR}/conf.d/docker-php-config.ini"

RUN apt-get update && apt-get install -y \
    gnupg \
    g++ \
    procps \
    openssl \
    curl \
    git \
    unzip \
    zlib1g-dev \
    libzip-dev \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    libicu-dev  \
    libonig-dev \
    libxslt1-dev \
    acl \
    zstd

RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" zip gd exif mbstring bcmath pcntl intl

RUN curl -L -o "pie.phar" https://github.com/php/pie/releases/download/1.5.5/pie.phar

# Install zstd
RUN php pie.phar install kjdev/zstd

# Install Xdebug
RUN php pie.phar install xdebug/xdebug:^3.6@alpha
RUN docker-php-ext-enable xdebug
COPY docker/php-cli/xdebug.ini "${PHP_INI_DIR}/conf.d"
RUN rm pie.phar

# Download Caddy to be able to format config file.
RUN curl -fsSL --retry 5 --retry-all-errors -o "/tmp/caddy.tar.gz" https://github.com/caddyserver/caddy/releases/download/v2.10.2/caddy_2.10.2_linux_amd64.tar.gz \
 && tar -xzf /tmp/caddy.tar.gz -C /usr/bin caddy \
 && rm -f /tmp/caddy.tar.gz \
 && chmod +x /usr/bin/caddy

# Install node and npm.
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash
RUN apt-get install -y nodejs

# Install Shoutrrr.
ARG TARGETARCH
ENV TARGETARCH=${TARGETARCH}
ARG SHOUTRRR_VERSION="0.25.0"

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

# Add composer.
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
