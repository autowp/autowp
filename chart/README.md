# Helm chart upgrades

The application chart uses `cloudpirates/common` 2.2.1 for names, labels, images
and pull secrets, sharing the same library version as RabbitMQ and Valkey.
Images use the CloudPirates format: to pin an
image by digest, set `image.tag` to `tag@sha256:…` (for example,
`goautowp.image.tag: v6.21.2@sha256:…`). Move any existing `image.digest`
overrides into `image.tag` and remove the separate `image.digest` field.
