# Helm chart agent instructions

The Helm chart deploys both services together using `goautowp.image` and `frontend.image`.

Local development uses nginx with the repository's `router.conf` in place of the ingress controller.
Keep its routes in sync with `templates/routes.yaml`.
