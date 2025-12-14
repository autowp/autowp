FROM docker.io/library/node:24-alpine

LABEL app-name="autowp-frontend" \
      maintainer="dmitry@pereslegin.ru"

COPY --chown=101:101 ./dist/ /app

WORKDIR /app

CMD ["node", "server/server.mjs"]
