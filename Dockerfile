FROM php:8.2-apache

# PostgreSQL PDO driver (needed for Supabase / any Postgres backend)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Copy the application source
COPY . /var/www/html

# Serve from public/ (keeps config/, src/, storage/ outside the web root)
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite

EXPOSE 80
