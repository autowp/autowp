FROM nginxinc/nginx-unprivileged:1-alpine

LABEL app_name="autowp-backend" \
      maintainer="dmitry@pereslegin.ru"

ENV FASTCGI=localhost:9000

EXPOSE 8080

HEALTHCHECK --interval=5m --timeout=3s CMD curl -f http://localhost:8081/health || exit 1

COPY --chown=101:101 ./etc/nginx /etc/nginx
