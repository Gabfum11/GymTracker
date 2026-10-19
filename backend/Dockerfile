# La "ricetta" del server dell'API: Render la usa per costruire il container.
# Prova in locale: docker build -t gymtracker-api backend
#                  docker run -p 8080:8080 -e PORT=8080 --env-file backend/.env gymtracker-api

# PHP 8.3 già pronto con il server web Apache
FROM php:8.3-apache

# l'estensione per parlare con PostgreSQL (non è inclusa nell'immagine base)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Apache: la cartella pubblica è public/, ogni indirizzo va a index.php, e l'header Authorization arriva a PHP
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf 'FallbackResource /index.php\nSetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1\n' > /etc/apache2/conf-enabled/gymtracker.conf

# prima solo le dipendenze (Docker le riusa dalla cache se composer.json non cambia), poi il codice
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
COPY . .

# Render dice su quale porta ascoltare con la variabile PORT. All'avvio: porta giusta, migrazioni, Apache
# ("exec": Apache diventa il processo principale e riceve il segnale di spegnimento da Render)
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
    && php bin/migrate.php \
    && exec apache2-foreground
