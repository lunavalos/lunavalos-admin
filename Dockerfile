FROM php:8.4-apache

# Cambiar el document root a la carpeta public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Habilitar mod_rewrite para Laravel
RUN a2enmod rewrite

# Instalar dependencias del sistema y Node.js para assets
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libsqlite3-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP necesarias para Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip intl gd bcmath

# Copiar Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configurar el directorio de trabajo
WORKDIR /var/www/html

# Copiar el código del proyecto
COPY . .

# Configurar límites de PHP
RUN printf "upload_max_filesize=256M\npost_max_size=256M\nmemory_limit=512M\nmax_execution_time=600\n" > /usr/local/etc/php/conf.d/uploads.ini

# Instalar dependencias y generar assets
# Usamos --no-scripts para evitar problemas con la DB durante la construcción
RUN composer install --no-dev --optimize-autoloader --no-scripts && \
    npm install && \
    npm run build

# Ajustar permisos para carpetas de Storage y Cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer el puerto 80
EXPOSE 80

# El comando por defecto ya es apache2-foreground
