FROM docker.io/library/node:26-alpine

# Pull pending Alpine security updates without waiting for a base-image rebuild.
RUN apk --no-cache upgrade

LABEL app-name="autowp-frontend" \
      maintainer="dmitry@pereslegin.ru"

COPY --chown=101:101 ./dist/ /app

WORKDIR /app

CMD ["node", "server/server.mjs"]
