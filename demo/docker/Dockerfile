FROM php:8.2-apache

# Allow .htaccess files in the docroot, and enable the rewrite module for the API routes
RUN a2enmod rewrite \
    && { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/htaccess-override.conf \
    && a2enconf htaccess-override
