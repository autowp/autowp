FROM docker.io/nginxinc/nginx-unprivileged:1-alpine

LABEL app-name="autowp-frontend" \
      maintainer="dmitry@pereslegin.ru"

HEALTHCHECK --interval=5m --timeout=3s CMD curl -f http://localhost:8081/health || exit 1

COPY --chown=101:101 ./etc/nginx /etc/nginx

COPY --chown=101:101 ./robots /usr/share/nginx/html/robots

COPY --chown=101:101 ./dist/browser/ /usr/share/nginx/html/
