# =============================================================================
# Image de production — CakePHP 5 + Nginx + PHP-FPM + Python (OR-Tools / Prophet)
# =============================================================================
FROM php:8.3-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    APP_HOME=/var/www/html \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PYTHONUNBUFFERED=1 \
    PATH="/opt/venv/bin:${PATH}"

# ---------------------------------------------------------------------------
# Paquets système : Nginx, Supervisor, Python, extensions PHP, outils build
# ---------------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        python3 \
        python3-pip \
        python3-venv \
        python3-dev \
        build-essential \
        g++ \
        cmake \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        mbstring \
        pdo_mysql \
        zip \
        opcache \
        bcmath \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------------------
# Configuration Nginx / PHP / Supervisor
# ---------------------------------------------------------------------------
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/planning
RUN ln -s /etc/nginx/sites-available/planning /etc/nginx/sites-enabled/planning

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/log/supervisor /var/log/nginx

# ---------------------------------------------------------------------------
# Dépendances PHP (couche cache Composer)
# ---------------------------------------------------------------------------
WORKDIR ${APP_HOME}

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ---------------------------------------------------------------------------
# Dépendances Python (venv isolé)
# ---------------------------------------------------------------------------
COPY solver-python/requirements.txt /tmp/requirements.txt
RUN python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --upgrade pip \
    && /opt/venv/bin/pip install --no-cache-dir -r /tmp/requirements.txt \
    && rm /tmp/requirements.txt

# ---------------------------------------------------------------------------
# Code applicatif
# ---------------------------------------------------------------------------
COPY . ${APP_HOME}

# Ré-exécute Composer pour scripts / autoload complet avec le code présent
RUN composer dump-autoload --optimize --no-dev \
    && if [ ! -f config/app_local.php ]; then \
         cp config/app_local.example.php config/app_local.php; \
       fi \
    && mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions logs \
    && chown -R www-data:www-data ${APP_HOME} \
    && chmod -R 775 tmp logs

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
