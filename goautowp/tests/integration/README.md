# Backend integration tests

This package tests services assembled by the application's public `Container` API. gRPC calls
use the registered server over an in-memory `bufconn` connection, with real PostgreSQL, Redis,
Keycloak and other configured backing services.

Run from `goautowp/`, after the normal test database migrations and fixture import described in
the repository's `AGENTS.md`:

```sh
go test -race ./tests/integration -timeout 10m
```

The package is also included in `go test ./...`. `TestMain` loads `defaults.yaml` and `config.yaml`
from the backend root and starts one shared server. Individual tests use the container's config.
Tests do not apply migrations or reload the shared database. Test accounts and baseline records
come from the existing test realm and database dump.

The root suite and this package share the gRPC client/server lifecycle helper in
`internal/testutil`. It starts the server once and stops it, closes the client, and waits for
the serving goroutine when the suite finishes.

Catalogue fixtures use `internal/testutil.CreateItem`, which accepts an Items client and an
authenticated context without depending on `Container`. Each suite keeps its account login
adapter locally. Map, mosts and attrs tests use this helper from the integration package.

Forums tests use `internal/testutil.UserWithCleanHistory` to authenticate an existing fixture
account and reset its posting/voting limits. This helper needs only Keycloak configuration,
the gRPC connection, and database access.
Comments tests use the same helper. Notification delivery and comment moves keep their sequential
execution within this package, alongside the forum scenarios.

Users tests keep their registration helpers local and resolve the avatar fixture relative to this
package. Tests that change language or notification preferences create separate accounts so they
can run alongside comment-notification scenarios without changing the shared tester.

Rating and REST items/pictures tests also live here. Upload fixtures use
`internal/testutil.CreatePictureRequest` and `CreatePicture`; the latter accepts an HTTP handler
instead of the application container. The suite assembles its own router. Application tests
prepare catalogue data directly through repositories; their login credentials stay in the single
application test that uses them.

Items gRPC scenarios also live here and reuse the same catalogue, user and upload fixtures.
Their status-setting upload adapter uses this suite's gRPC connection; it does not receive the
application container.

Pictures gRPC scenarios reuse the same upload and status-setting adapters. All callers of the
user-limit fixture are now in this package, so the root no longer needs a user fixture adapter.

YooMoney webhook and duplicate-finder scenarios also run here. They use the suite's public
container dependencies and existing database/image fixtures, including the duplicate-finder
HTTP deadline regression test.

The related-group preview API scenario runs here; its lower-level catalogue-path, ancestor,
picture-selection and formatted-image checks live with their repositories and image storage.

Package-specific tests live alongside their implementation, including the directly wired
extractor graphs and their database/image fixtures. The root keeps application, migration and
HTTP shutdown tests, plus their shared bootstrap.

Shared image fixtures live in `../testdata/images/`; the PostgreSQL baseline and Keycloak realm
live in sibling directories under `../testdata/`. See [the test guide](../README.md) for setup.
