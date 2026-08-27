# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

This is the monorepo for **wheelsage.org / autowp.ru**, a car catalogue/wiki website. It has two main
components that share a single gRPC contract:

- `goautowp/` — Go backend: gRPC services, a public HTTP/REST gateway, background workers (AMQP
  consumers, schedulers), CLI tooling, and PostgreSQL access.
- `frontend/` — Angular 21 SPA with SSR (Express server), consuming the backend over grpc-web.

The gRPC API surface (services + messages) is defined once in `spec.proto` at the repo root and
code-generated into both sides — do not hand-edit generated files.

## Code generation

`spec.proto` is the single source of truth for the API. After changing it, regenerate both the Go
server stubs and the Angular client:

```sh
./protoc.sh
```

This runs `protoc` with `--go_out`/`--go-grpc_out` into `goautowp/` and `--ng_out` into
`frontend/src/grpc/`. It requires `protoc-gen-go`, `protoc-gen-go-grpc`, and the local
`protoc-gen-ng` (from `frontend/node_modules/.bin`) to be on `PATH`. CI runs this before building
either side, so a proto change without regenerating output will build stale code locally.

## Backend (goautowp/)

All commands below run from `goautowp/`.

```sh
go build ./...
go vet ./...
golangci-lint run ./... --timeout 5m   # full lint config in .golangci.yml
go test ./...                          # unit tests
go test ./... -run TestName            # single test
go test ./items/...                    # single package
```

Integration-style tests need real Postgres/Redis/RabbitMQ/Keycloak/MinIO backing services. Bring
them up with the local `docker-compose.yml` (in `goautowp/`, distinct from the root one), wait for
them, then run migrations and the full suite:

```sh
docker compose up -d
./tools/wait.sh
go test -run TestPostgresMigrations
docker exec -t goautowp_postgres_test sh -c "psql --username=traffic traffic < /dump.sql"
gotestsum --junitfile report.xml --format testname -- -coverpkg=./... -coverprofile=cov.out -covermode count ./... -timeout=20m
```

Config is loaded via `config.LoadConfig(".")` (viper), layering `goautowp/defaults.yaml` with
`goautowp/config.yaml` (local overrides, gitignored-style test config). See `goautowp/config/` for
the typed config structs.

### Backend architecture

- **`cmd/goautowp/goautowp.go`** — CLI entrypoint (urfave/cli). Subcommands include `serve`,
  `migrate-postgres`, `scheduler-hourly/daily/midnight`, `image-storage ...`, `pictures ...`,
  `telegram ...`, catalogue/spec-refresh maintenance jobs, etc. `serve` always runs Postgres
  migrations first, then starts whichever workers are enabled via flags (`--grpc`, `--public`,
  `--df-amqp`, `--monitoring-amqp`, `--autoban`, `--attrs-update-values-amqp`).
- **`app.go`** (`Application`) — owns the `Container` and exposes one `Serve*`/worker method per
  `ServeOptions` flag; `Application.Serve` fans these out as goroutines behind a shared `quit`
  channel for graceful shutdown.
- **`container.go`** (`Container`) — a large hand-written, lazily-initialized DI container. Every
  repository, gRPC server, and shared client (Postgres `*sql.DB` / goqu `*goqu.Database`, Redis,
  Keycloak, S3-backed image/file storage, email, telegram) is a field with a `Container.Xxx(ctx)`
  accessor that constructs-and-caches on first use. When adding a new service, follow this pattern
  rather than constructing dependencies ad hoc.
- **gRPC + REST on one port**: `Container.PublicRouter` wraps the grpc.Server with
  `grpcweb.WrapServer` and multiplexes grpc-web and JSON/REST (gin) requests on the same HTTP
  handler; `GRPCServerWithServices` (in `grpc.go`) registers every `XxxGRPCServer` against the
  proto-generated `RegisterXxxServer` functions.
- **Per-domain gRPC servers**: files named `<domain>-grpc.go` at the package root (e.g.
  `attrs-grpc.go`, `comments-grpc.go`, `items-grpc.go`) implement one `UnimplementedXxxServer` each
  and translate between proto messages and the domain's repository types. Domain business logic
  and DB access lives in matching subpackages (`attrs/`, `items/`, `pictures/`, `comments/`,
  `users/`, `traffic/`, `votings/`, `mosts/`, `log/`, ...), typically as a `Repository` type built
  on `goqu` query builders.
- **`schema/`** — typed constants for every Postgres table/column plus enum-like ID types (e.g.
  `schema.AttrsAttributeTypeIDString`, `schema.ItemTableItemTypeIDBrand`). Query code should build
  goqu expressions against these constants instead of hardcoding table/column strings.
- **`query/`** and **`filter/`** — shared query-building and filter-parsing helpers used across
  repositories for paginated/filtered list endpoints.
- **`migrations/`** — golang-migrate SQL migrations (paired `NNN_name.up.sql` /
  `NNN_name.down.sql`), applied by `Application.MigratePostgres` / the `migrate-postgres` CLI
  command and automatically before `serve`.
- **`attrsamqp/`, `duplicate-finder.go`, `traffic/monitoring.go`** — background AMQP consumers
  wired up as opt-in workers via `ServeOptions`.
- **`image/storage/`** — S3-backed image storage abstraction (formatted image variants, naming
  strategies, flush/list maintenance commands exposed through the CLI).

## Frontend (frontend/)

All commands below run from `frontend/`.

```sh
npm ci
ng serve                                 # dev server
ng build --configuration production      # production build (also: npm run build)
ng lint                                  # eslint, includes Angular template rules
npx stylelint "src/**/*.scss"
```

Note: clear `frontend/dist` directory before run `ng lint` to prevent OOM during linting

There is no unit test suite configured for the frontend (no `*.spec.ts` files, no `test` builder
in `angular.json`) — don't assume Karma/Jest is set up.

### Frontend architecture

- Angular 22 standalone app with SSR: `main.ts` (browser bootstrap), `main.server.ts` +
  `server.ts` (Express SSR entry), `app.config.ts` / `app.config.server.ts` split
  client/server providers, `app.routes.ts` / `app.routes.server.ts` split client/server routing.
- **`src/grpc/`** — generated grpc-web client from `spec.proto` (via `@ngx-grpc`); treat as
  read-only/regenerate-only, mirrors the Go server definitions.
- **`src/app/grpc.ts`** and **`src/app/grpc-web-client/`** — hand-written glue around the
  generated client (error/status decoding, field-violation extraction for form validation, etc.).
- **`src/app/<domain>/`** — one directory per feature area (cars, items, pictures, forums,
  comments, users, moder, catalogue, ...), each roughly mirroring a backend gRPC service.
- Path aliases (see `tsconfig.json`): `@grpc/*` → `src/grpc/*`, `@services/*` →
  `src/app/services/*`, `@utils/*` → `src/app/utils/*`, `@environment/*` → `src/environments/*`.
- Uses ng-bootstrap (Bootstrap 5) for UI, Keycloak (`keycloak-angular`/`keycloak-js`) for auth,
  Leaflet for maps, Monaco editor and `marked`/`remark` for markdown editing, Chart.js for charts.
- i18n implemented using angular/i18n. Command `ng extract-i18n --output-path src/locale` extracts
  tokens for internationalization into `messages.xlf` file. Each time frontend code was changed check for
  new internationalization tokens and translate them into other `messages.*.xlf files`

### Frontend conventions

- **`preserveWhitespaces: false`** — set globally in `tsconfig.json`'s `angularCompilerOptions`
  (Angular's SSR recommendation); no component overrides it, and none should. When a template edit
  drops an inline gap that used to come from template whitespace, restore it with `&ngsp;` (a
  single significant space between bare text and an element) or, for repeated inline lists / icon
  rows, a Bootstrap spacing utility — never by re-adding `preserveWhitespaces: true`.
- **Spacing** — prefer Bootstrap utility classes (`me-1`/`me-2`, `ms-1`, `gap-1`, `d-flex
  flex-wrap gap-2`) in the template over a component `styles`/`styleUrl` rule with
  `margin-inline-end` & co.
- **i18n text** — put `i18n` on a `<ng-container i18n>` wrapping just the text, not on an element
  that prettier might reflow onto multiple lines (adding a class can push a `<button i18n>` past
  printWidth 120). A wrapped element's leading/trailing whitespace can leak into the extracted
  message and change its id, orphaning the translation. Do not pin lines with `<!-- prettier-ignore -->`.
- **Page ids** — `src/app/services/page-id.ts` (`PageId` enum) names every CMS page id the app
  refers to. Use `PageId.*` in `pageEnv.set({pageId})`, the account sidebar, and route `data`, never
  a bare number. `src/app/services/pages.ts` is the trimmed page hierarchy (only `PageId` members,
  used for menu active-state via `PageService.isDescendant$`).

## Cross-cutting notes

- Auth for both gRPC and REST is backed by Keycloak (`goautowp/auth.go`, `KeycloakConfig`); local
  dev/test Keycloak is provisioned via `goautowp/docker-compose.yml` with `test/realm.json`.
- The `chart/` directory holds the Helm chart used to deploy both services together
  (`goautowp.image` / `frontend.image`).
- CI (`.gitlab-ci.yml`) is the authoritative source for exact lint/build/test invocations if this
  file and reality diverge — check it first when in doubt.
- nginx with `router.conf` is used to replace ingress-controller with local development. `router.conf` routes
  and `chart/templates/routes.yaml` must match

## Commits

We use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)
