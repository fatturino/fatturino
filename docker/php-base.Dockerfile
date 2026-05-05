FROM serversideup/php:8.4-fpm-nginx

USER root

# IPE_PROCESSOR_COUNT=3 limita i job paralleli di make durante la compilazione
# delle estensioni PHP. Su una macchina ARM64 da 8GB che compila anche AMD64
# via QEMU, un valore basso evita OOM e thrashing della memoria.
ENV IPE_PROCESSOR_COUNT=3

RUN install-php-extensions bcmath intl gd \
    && apt-get update && apt-get install -y --no-install-recommends sqlite3 git nano su-exec \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /data && chown www-data:www-data /data

# Workaround for s6-overlay v3 suexec bug under Dokku:
# Remove the user services bundle that uses s6-overlay-suexec (requires PID 1),
# and replace with services that use su-exec to drop privileges.
RUN rm -rf /etc/s6-overlay/s6-rc.d/user \
    && rm -rf /etc/s6-overlay/s6-rc.d/nginx \
    && rm -rf /etc/s6-overlay/s6-rc.d/php-fpm

# Recreate nginx and php-fpm as regular longrun services using su-exec
RUN mkdir -p /etc/s6-overlay/s6-rc.d/nginx /etc/s6-overlay/s6-rc.d/php-fpm

RUN echo 'longrun' > /etc/s6-overlay/s6-rc.d/nginx/type \
    && printf '#!/command/execlineb -P\nfdmove -c 2 1\nsu-exec www-data:www-data /usr/sbin/nginx -g "daemon off;"\n' > /etc/s6-overlay/s6-rc.d/nginx/run \
    && chmod +x /etc/s6-overlay/s6-rc.d/nginx/run

RUN echo 'longrun' > /etc/s6-overlay/s6-rc.d/php-fpm/type \
    && printf '#!/command/execlineb -P\nfdmove -c 2 1\nsu-exec www-data:www-data /usr/sbin/php-fpm8.4 --nodaemonize --fpm-config /etc/php/8.4/fpm/php-fpm.conf\n' > /etc/s6-overlay/s6-rc.d/php-fpm/run \
    && chmod +x /etc/s6-overlay/s6-rc.d/php-fpm/run

# Ensure services are in the default bundle
RUN mkdir -p /etc/s6-overlay/s6-rc.d/default/contents.d \
    && echo "nginx" > /etc/s6-overlay/s6-rc.d/default/contents.d/nginx \
    && echo "php-fpm" > /etc/s6-overlay/s6-rc.d/default/contents.d/php-fpm
