FROM php:8.2-apache
RUN apt update
RUN apt install -y nano

# Allow .htaccess files in the docroot to take effect (the base apache2.conf
# ships with AllowOverride None for /var/www/).
RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/htaccess-override.conf \
    && a2enconf htaccess-override
