# serversideup/php: nginx + php-fpm, www-data olarak çalışır, 8080'i dinler.
# AUTORUN_ENABLED=true açılışta migrate + config/route/view cache yapar.
FROM serversideup/php:8.4-fpm-nginx@sha256:79d80de95e275a4664e7e1733a4d2aadb94bb793ee6190febb427bd335dd8084

ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1

USER root
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

COPY --chown=www-data:www-data . /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Port .env içindeki APP_PORT ile değişir (compose bunu NGINX_HTTP_PORT olarak verir).
EXPOSE 8080
