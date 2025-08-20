FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP SQLite extensions
RUN docker-php-ext-configure pdo_sqlite && \
    docker-php-ext-install pdo_sqlite

# Configure PHP
RUN echo "upload_max_filesize = 256M" > /usr/local/etc/php/conf.d/upload.ini && \
    echo "post_max_size = 256M" >> /usr/local/etc/php/conf.d/upload.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/upload.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/upload.ini

# Enable Apache modules
RUN a2enmod rewrite headers

# Create necessary directories
RUN mkdir -p /var/www/html/data /var/www/html/public/uploads

# Copy Apache configuration
COPY infrastructure/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

# Set working directory
WORKDIR /var/www/html

# Copy application source into the image (production build)
# Note: data/ and uploads/ are runtime volumes, so we only create directories.
COPY public /var/www/html/public
COPY src /var/www/html/src
COPY views /var/www/html/views

# Ensure correct permissions for Apache user and sensible defaults
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Default to production environment inside the container
ENV ENV=production