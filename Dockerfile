FROM php:8.2-cli

WORKDIR /app

# Instalar dependencias del sistema y Node.js
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
    default-mysql-client \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP
RUN docker-php-ext-install pdo pdo_mysql mbstring zip intl

# Copiar Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar el código de la aplicación
COPY . /app

# Configurar valores de PHP para subida de archivos y otros
RUN mkdir -p /usr/local/etc/php/conf.d && \
    printf "upload_max_filesize=100M\npost_max_size=110M\nmemory_limit=512M\nmax_execution_time=600\n" > /usr/local/etc/php/conf.d/uploads.ini

# Instalar dependencias de PHP y generar assets
RUN composer install --no-dev --optimize-autoloader --no-scripts && \
    npm install && \
    npm run build

# Exponer el puerto
EXPOSE 3000

# Comando para iniciar la aplicación
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=3000"]
