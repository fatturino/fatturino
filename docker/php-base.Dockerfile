FROM serversideup/php:8.4-fpm-nginx

USER root

# IPE_PROCESSOR_COUNT=3 limita i job paralleli di make durante la compilazione
# delle estensioni PHP. Su una macchina ARM64 da 8GB che compila anche AMD64
# via QEMU, un valore basso evita OOM e thrashing della memoria.
ENV IPE_PROCESSOR_COUNT=3

RUN install-php-extensions bcmath intl gd \
    && apt-get update && apt-get install -y --no-install-recommends sqlite3 git nano \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /data && chown www-data:www-data /data
