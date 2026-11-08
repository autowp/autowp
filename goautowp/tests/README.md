# Backend tests

- `integration/` contains scenarios that exercise several services through gRPC or HTTP.
- `testdata/images/` contains shared image fixtures used by integration and package tests.
- `testdata/postgres/dump.sql` contains the baseline database fixture, loaded after migrations.
- `testdata/keycloak/realm.json` provisions test accounts and the test realm.
- `environment/` contains S3 bucket creation and PostgreSQL initialization scripts.

Package-specific tests and their private fixtures stay beside the code under `internal/`.
Application, migration and HTTP shutdown tests stay at the backend root.

Run commands from `goautowp/`. The test environment is defined in `docker-compose.yml`:

```sh
docker compose up -d
./tools/wait.sh
go test -run TestPostgresMigrations
docker exec -i goautowp_postgres_test sh -c 'psql --username=traffic traffic < /dump.sql'
go test -race ./... -timeout 20m
```

Import the baseline once when preparing a fresh database. Individual test runs reuse it.
The nginx service serves `testdata/images/` for tests that download images over HTTP.
Container paths such as `/dump.sql` remain independent of the host fixture layout.

Keycloak 26.8.2 requires the `kc-admin-api` client scope for Admin REST API tokens.
Both realm imports assign it as a default scope to the `autowp` service client. When
upgrading an existing realm, assign this scope to that client in the Admin Console;
startup imports skip realms that already exist. See the
[Keycloak upgrade guide](https://www.keycloak.org/docs/26.8.2/upgrading/#_migrating_to_26_8_2).
