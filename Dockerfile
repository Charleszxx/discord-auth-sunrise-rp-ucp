FROM php:8.1-apache

# Copy project files
COPY . /var/www/html/

# Enable mysqli and fix DirectoryIndex
RUN docker-php-ext-install mysqli \
    && echo "DirectoryIndex index.php index.html" >> /etc/apache2/apache2.conf

EXPOSE 80
