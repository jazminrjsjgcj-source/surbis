# Imagen de desarrollo.
#
# Deliberadamente sobre Debian y no sobre Alpine: en la Fase 5 hay biblioteca
# multimedia con variantes de imagen, y gd/intl/imagick dan menos sorpresas
# aqui. El tamano de imagen no es un problema en desarrollo.
FROM php:8.4-fpm

# Instalador de extensiones. Evita la lista de paquetes -dev de cada
# extension, que es donde se acumulan los errores dificiles de leer.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pdo_pgsql \
        redis \
        intl \
        zip \
        bcmath \
        gd \
        exif \
        pcntl \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/99-encuestas.ini

# En Linux, los archivos que crea el contenedor pertenecen al usuario del
# contenedor. Sin esto, `composer create-project` deja el proyecto entero
# perteneciendo a root y el editor del host no puede escribirlo.
#
# /var/www es el directorio personal de www-data y pertenece a root, asi que
# cualquier herramienta que quiera escribir configuracion ahi falla. Ha pasado
# ya dos veces:
#
#   .composer   la cache de Composer. No fallaba: avisaba y volvia a
#               descargar el indice entero de Packagist en cada instalacion.
#   .config     psysh, que usa Tinker. Tampoco falla: imprime un aviso y no
#               ejecuta nada, asi que parece que el comando no devolvio
#               resultado.
#
# Las dos veces el sintoma fue un aviso donde deberia haber un error.
ARG UID=1000
ARG GID=1000
RUN groupmod --gid "${GID}" www-data \
    && usermod --uid "${UID}" --gid "${GID}" www-data \
    && mkdir -p /var/www/html /var/www/.composer /var/www/.config \
    && chown -R www-data:www-data \
        /var/www/html \
        /var/www/.composer \
        /var/www/.config \
        /usr/local/etc/php

# safe.directory: git se niega a operar sobre un repositorio cuyo propietario
# difiere del usuario que lo ejecuta. El .git lo crea el host y el contenedor
# corre como www-data, asi que siempre difieren.
#
# --system y no --global: --global escribe en $HOME del usuario que ejecuta el
# RUN, que aqui es root (/root/.gitconfig), y www-data tiene otro HOME, asi que
# nunca lo leeria. --system escribe en /etc/gitconfig, que leen todos.
RUN git config --system --add safe.directory /var/www/html

USER www-data
WORKDIR /var/www/html
