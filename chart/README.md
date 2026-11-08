# Helm chart upgrades

The application chart uses `cloudpirates/common` 2.2.1 for names, labels, images
and pull secrets, sharing the same library version as RabbitMQ and Valkey.
It no longer depends on Bitnami. Images use the CloudPirates format: to pin an
image by digest, set `image.tag` to `tag@sha256:…` (for example,
`goautowp.image.tag: v6.21.2@sha256:…`). Move any existing `image.digest`
overrides into `image.tag` and remove the separate `image.digest` field.

## Redis to Valkey (0.4.0)

The Bitnami Redis dependency is replaced by CloudPirates Valkey 0.25.16, using
the upstream `valkey/valkey` image. Valkey runs one master and two replicas,
without Sentinel, authentication or persistent volumes, as Redis did previously.
The backend still uses its `redis` configuration key and the Redis protocol;
its endpoint now points to the Valkey master service.

Update deployment values before upgrading:

- Replace `redis.enabled` with `valkey.enabled`.
- Move master/replica resource settings to `valkey.resources` (shared by all nodes).
- Use `valkey.replicaCount` for the total node count, including the master.
  Replica autoscaling is no longer configured.
- Move metrics settings to `valkey.metrics`; ServiceMonitor labels use
  `valkey.metrics.serviceMonitor.selector`.
- Remove Bitnami image overrides. The new chart pins upstream images by digest.
- Use `valkey.nameOverride` / `valkey.fullnameOverride` for custom resource names;
  the backend derives the master endpoint from the same chart helper.

Redis data is deliberately **not migrated**. The old Redis workloads are removed
during the upgrade and Valkey starts with an empty cache. Plan a maintenance
window for the switch and cache rebuild. Once Valkey and the backend are ready,
trigger the existing catalogue index cache CronJob (example for the `autowp`
release in the `autowp` namespace):

```sh
kubectl -n autowp create job --from=cronjob/autowp-goautowp-scheduler-generate-index-cache valkey-index-cache-rebuild
kubectl -n autowp logs -f job/valkey-index-cache-rebuild
```

Other caches refill through normal application activity. A rollback to Redis
also starts without the Valkey data and requires another index cache rebuild.
