FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for Laravel and Google Cloud MySQL
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Update Apache to listen on the port specified by Cloud Run
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy custom Apache configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy Composer from the official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Set proper permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Install application dependencies
RUN composer install --no-dev --optimize-autoloader

# Cloud Run defaults exposed port to 8080
ENV PORT=8080
EXPOSE $PORT

CMD ["apache2-foreground"]
