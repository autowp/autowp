FROM docker.io/library/node:24-alpine

# node:24-alpine still trails Alpine's security updates (e.g. openssl 3.5.7-r0 vs the
# published 3.5.8-r0 fix for CVE-2026-63073). Pull pending patched packages at build time
# so the image doesn't wait on an upstream base-image rebuild.
RUN apk --no-cache upgrade

LABEL app-name="autowp-frontend" \
      maintainer="dmitry@pereslegin.ru"

COPY --chown=101:101 ./dist/ /app

WORKDIR /app

CMD ["node", "server/server.mjs"]
