FROM php:8.2-fpm

ENV DEBIAN_FRONTEND=noninteractive

# Instalar extensiones de MySQL, Python 3 con pyotp para el sistema 2FA y Suricata para el IDS
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pyotp \
    suricata \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli && docker-php-ext-enable mysqli
