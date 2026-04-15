FROM php:8.3-apache-bookworm

# 1. Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libaio1 \
    wget \
    && rm -rf /var/lib/apt/lists/*

# 2. Extensiones PHP estándar
RUN docker-php-ext-install mbstring exif pcntl bcmath gd

# 3-5. Oracle Instant Client desde ZIPs locales
WORKDIR /opt/oracle
COPY ./oracle-client/*.zip ./
RUN unzip -o "instantclient-basic-linux.x64-23.26.1.0.0.zip" \
    && unzip -o "instantclient-sdk-linux.x64-23.26.1.0.0.zip" \
    && mv instantclient_23_26 instantclient \
    && rm -f *.zip

# 6. Variable de entorno para librerías Oracle
ENV LD_LIBRARY_PATH=/opt/oracle/instantclient

# 7-8. Extensión oci8 vía PECL
RUN echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8 \
    && docker-php-ext-enable oci8

# 9. Extensión pdo_oci
RUN docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient,23.1 \
    && docker-php-ext-install pdo_oci

# 10-11. Apache: habilitar mod_rewrite y apuntar DocumentRoot a /public
RUN a2enmod rewrite \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/*.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf

# 12. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 13. Directorio de trabajo de la aplicación
WORKDIR /var/www/html
