# Backend agent instructions

## Build and test

Run these commands from `goautowp/`.

```sh
go build ./...
go vet ./...
golangci-lint run ./... --timeout 5m   # full lint config in .golangci.yml
go test ./...                          # all tests, including integration tests
go test ./... -run TestName            # single test
go test ./internal/items/...           # single package
go test -race ./tests/integration      # extracted integration suite
```

Integration-style tests need real Postgres/Redis/RabbitMQ/Keycloak/S3 backing services (S3 is
RustFS, kept under the historical `minio` service name/hostname). Bring them up with the local
`docker-compose.yml` (in `goautowp/`, distinct from the root one), wait for them, then run
migrations and the full suite:

```sh
docker compose up -d
./tools/wait.sh
go test -run TestPostgresMigrations
docker exec -t goautowp_postgres_test sh -c "psql --username=traffic traffic < /dump.sql"
gotestsum --junitfile report.xml --format testname -- -coverpkg=./... -coverprofile=cov.out -covermode count ./... -timeout=20m
```

CI's actual test job runs the suite with `-race -covermode atomic` (catches lazy-init cache races
in the DI container and repositories). Two recurring race-detector traps in this codebase's
parallel subtests, not real bugs in the code under test: a function-scoped `*rand.Rand` read
inside a `t.Run(..., func(t *testing.T){ t.Parallel(); ... })` closure (not concurrency-safe —
draw the value on the parent goroutine before `t.Run`), and a closure that assigns into the
enclosing func's `err` variable (`_, err = ...`, not `:=`) across parallel subtests (shadow it with
`:=` on first use inside the closure). When `-race` fails N tests, find the actual `WARNING: DATA
RACE` stack frames rather than treating every failure as a distinct bug — one race trips the
detector for every test running concurrently with it; `gotestsum --format testname` hides that
block, so add `--jsonfile x.json` and grep it, or run the failing tests directly with
`go test -race -run 'TestFoo|TestBar' ./...`.

Config is loaded via `config.LoadConfig(".")` (viper), layering `goautowp/defaults.yaml` with
`goautowp/config.yaml` (local overrides, gitignored-style test config). See `goautowp/internal/config/` for
the typed config structs.

Shared test assets live in `goautowp/tests/testdata/` (images, PostgreSQL dump and Keycloak realm).
Test environment initialization scripts live in `goautowp/tests/environment/`; both Compose files
reference these paths. See `goautowp/tests/README.md` for environment preparation.

Cross-service integration tests live in `goautowp/tests/integration/`. Its
`TestMain` loads config from the backend root and assembles services through the public
`Container` API; individual tests use `cnt.Config()`. Both suites share the in-memory gRPC
server/client lifecycle helper in `internal/testutil/`. Database migrations and baseline fixture
imports remain separate preparation steps. Package-specific tests stay with their code. The root
keeps application, migration and HTTP shutdown tests, plus their shared bootstrap.
Catalogue creation is shared through `internal/testutil.CreateItem`, taking a gRPC Items client
and authenticated context. Suite-local adapters own account login; application tests prepare
catalogue data through repositories directly. Map, mosts and attrs integration tests live in `tests/integration/`.
Forums and comments integration tests use `internal/testutil.UserWithCleanHistory` to log in existing fixture
accounts and reset posting/voting limits. Neither shared fixture helper depends on the root
application package; the user fixture is called directly from the integration suite.
Users gRPC integration tests also live in `tests/integration/`. Tests mutating language or
notification preferences use fresh accounts to avoid interfering with comment notification tests.
Rating and REST items/pictures integration tests use upload helpers in `internal/testutil/`.
`CreatePicture` accepts an `http.Handler`; suite-local adapters assemble routers. Application-test
credentials stay in the single application test that uses them. Upload and query-barrier
fixtures are kept with the suites that use them.
Items gRPC integration tests live in `tests/integration/items-grpc_test.go` and use the shared
catalogue/user/upload fixtures with suite-local adapters.
Pictures gRPC integration tests also live in `tests/integration/` and reuse those adapters.
YooMoney webhook and duplicate-finder integration scenarios live there as well, including the
HTTP-fetch deadline regression test.
Repository-level user import, persistent cache invalidation and visit tests live alongside
`internal/users/` as external-package database tests. Their local fixture uses one connection
for temporary tables and an atomic SQL query counter, without constructing the application.
Batch event-log and mosts vehicle-type repository tests live alongside `internal/log/` and
`internal/mosts/`. Shared `internal/testutil.NewPostgres`, `QueryLogger` and `BlockQueries`
provide single-connection temporary-table fixtures, SQL counts and a parallel-query barrier.
Related-group repository tests are split between `internal/items/`, `internal/pictures/` and
`internal/image/storage/`; the corresponding API scenario lives in `tests/integration/`.
Duplicate-parent extraction tests live with `internal/transport/grpc/itemparentextractor/`.
They use local temporary-table fixtures and direct item/parent extractor dependencies through
`NewPostgres`, without a root database or container adapter.
Mosts field-batching tests live with `internal/transport/grpc/mosts/` and construct their item
extractor from repository callbacks. Empty-preload tests live with `itemextractor/`; nested
picture fields exercised through the API are covered in `tests/integration/`. Shared query-logging
and barrier helpers live in `internal/testutil/`.
Picture field, remaining-field, descendant-picture, df-distance filter and nested-budget tests
are grouped under `internal/transport/grpc/pictureextractor/`. Their local fixture constructs
repositories and the picture/item/parent/distance extractor graph directly; all fixture rows,
including suggested authors and localized names, are temporary and need no application container.
Parent-cache field batching and related parent-item extraction tests are grouped under
`internal/transport/grpc/parentcacheextractor/`, with temporary items/localized names and
direct repository/extractor dependencies instead of gRPC-created catalogue fixtures.
Concurrent mosts card and df-distance extraction tests share the local item-of-day/image fixture
under `internal/transport/grpc/itemextractor/`. That fixture creates catalogue rows directly and
cleans up its database rows and stored images; the root item-of-day/container adapters are removed.
The remaining item, parent and picture-item preload tests and heavy-field extraction test share
the same directly wired graph in `internal/transport/grpc/itemextractor/`, including parent-cache,
picture-item and distance dependencies. All extractor tests have left the application root.

## Architecture

- **`api/pb/`** — generated protobuf messages, clients, and server contracts. Import this package
  from handlers and clients; it must not depend on application packages. Keep validation in
  hand-written functions outside the generated package rather than adding methods to messages.
- **`internal/auth/`** — Keycloak authentication and the request's `User` identity. Use `WithUser`
  and `FromContext` to share verified identities between middleware and handlers; the context
  key is private. Import as `authn` when local `auth` variables would shadow the package.
- **`internal/audit/`** — action-log `Recorder` and `Event` types, constructed by the DI container.
- **`internal/articles/`** — PostgreSQL repository returning `schema.ArticleRow`. Owns enabled filtering,
  ordering, and pagination; it must not import protobuf or gRPC. The articles transport converts
  rows to protobuf, builds preview URLs, and maps repository errors to gRPC status codes.
- **`internal/textstorage/`**, **`internal/donations/`**, **`internal/statistics/`** — own revision reads, transaction queries,
  and statistics queries respectively. SQL-result Row types live in `internal/schema/`; repositories do
  not depend on protobuf or gRPC. Statistics transport uses `errgroup.WithContext` to propagate
  query errors and cancel sibling reads instead of returning a partially populated success.
- **`internal/votings/`**, **`internal/achievements/`** — voting and achievement repositories,
  constructed by the DI container independently of their gRPC servers.
- **`internal/maps/`**, **`internal/forums/`** — map spatial queries/clustering and forum persistence/enrichment.
  Map query projections and aggregates live in `internal/maps/`; table Row types live in `internal/schema/`.
  Their gRPC servers live under
  `internal/transport/grpc/{maps,forums}/`. Forum enrichment owns its `errgroup`; it must not
  depend on item extraction. Forums and comments use the shared `commentextractor`.
- **`internal/comments/`** — comment persistence, subscriptions, and topic statistics,
  shared by forum queries, transports, and maintenance jobs.
- **`internal/transport/grpc/comments/`**, **`internal/transport/grpc/commentextractor/`** — comment
  RPCs and shared single/batch message enrichment. Forums inject the same extractor directly.
  Message list and reply queries execute in `comments.Repository.Messages`; transports use the
  repository's sorting enum rather than SQL expressions. Keep subscription notifications unchanged
  until message extraction succeeds. Batch, recursive-read and cancellation tests live with the code.
- **`internal/compliance/`**, **`internal/contentreport/`**, **`internal/feedback/`** — domain
  repositories for GDPR objections, content reports, and feedback delivery.
- **`internal/itemofday/`** — item-of-day selection and persistence, shared by schedulers,
  donation handlers, and catalogue extraction.
- **`internal/transport/grpc/autowp/`** — common Autowp gRPC server, including feedback,
  content reports and GDPR objection handlers. These remain one protobuf service with one server;
  validation and row-to-protobuf mapping stay local. Root integration tests check registration.
- **`internal/config/`** — application configuration types and loader. `defaults.yaml` and
  `config.yaml` remain at the backend root; `LoadConfig` resolves its caller-supplied path.
- **`internal/i18nbundle/`**, **`internal/util/`** — embedded JSON translations and shared Go
  helpers, including pagination and resource handling.
- **`internal/frontend/`**, **`internal/filter/`**, **`internal/logging/`** — Go helpers for
  frontend links/routes, filename sanitization, and the process-wide logger.
- **`internal/email/`**, **`internal/telegram/`** — mail delivery and Telegram integration.
  **`internal/hosts/`** resolves public host URLs and timezones by language.
- **`internal/ban/`**, **`internal/index/`** — IP-block persistence and the Redis-backed
  catalogue index cache. **`internal/attrsamqp/`** owns the attribute-update AMQP consumer.
- **`internal/pictures/`** — picture persistence, EXIF/author handling, localized names,
  batched reads and view recording, plus the picture WebSocket hub and Redis events.
- **`internal/items/`** — catalogue persistence, parent graphs and caches, localized names,
  vehicle-type inheritance, and batch query implementations.
- **`internal/attrs/`** — attribute persistence, value aggregation and conflict handling,
  specification rendering, and the embedded `specs.tmpl` template.
- **`internal/users/`** — user persistence, Keycloak synchronization, import/visit caches,
  role constants, and legacy contact backfill.
- **`internal/transport/grpc/users/`** — users gRPC server, request validation and local field
  mapping. Its dependencies are injected by the container; root integration tests check registration.
  Shared timezone discovery used by validation and the common API lives in `internal/timezones/`.
- **`internal/traffic/`** — traffic repository, crawler whitelist, monitoring consumer, and
  buffered request recorder. The container supplies the recorder's service lifecycle context.
  The recorder includes whitelisted traffic; quota queries exclude it. `traffic_source` stores
  editable observation groups (CIDRs, DNS suffixes, reference URLs), and `ip_source` stores
  individually verified DNS identities independently of whitelist exemptions. Migration 57 seeds
  known operators and imports existing verified whitelist identities. Source statistics aggregate
  minutes per IP before classification, prefer verified identities then the longest matching CIDR,
  and count each IP once. Source configuration never grants exemptions or creates group bans;
  automatic whitelist DNS domains remain explicitly approved in code.
- **`internal/messaging/`** — personal-message persistence, dialog counts, WebSocket hub, and
  Redis event publication. Application workers own subscriptions and transport wiring.
- **`internal/usercontacts/`** — external-profile platform registry and handle parsing,
  shared by user gRPC validation and the legacy contact backfill.
- **`internal/contacts/`** — contact relationship persistence shared by the contacts and users
  transports. The repository owns SQL and must not import protobuf or gRPC.
- **`internal/transport/grpc/{contacts,traffic}/`** — contacts and traffic gRPC handlers. Traffic
  concurrency/cancellation tests live with its server; root integration tests check registration.
- **`internal/transport/middleware/`** — gRPC interceptors, Gin ban/monitoring middleware,
  shared ban-check cache, trusted-client-IP options and logging adapter. The container keeps the
  interceptor order and shares one `BanChecker` across HTTP and gRPC. Tests live with middleware.
- **`internal/transport/extraction/`** — shared bounded lookup runner and picture concurrency
  budget. Nested lookups execute inline within their parent's slot. Context keys stay private;
  use the package accessors when splitting budgets across nested picture batches.
- **`internal/transport/grpc/mosts/`** — ratings gRPC server with a narrow item-extraction
  interface. Shared previews precede bounded parallel row extraction; keep the request cloned
  before clearing preview fields. Root integration tests exercise the extraction phase and RPCs.
- **`internal/transport/grpc/itemparentextractor/`** — parent/child relationship extraction,
  duplicate selection and descendant-picture batches. Injects lazy repository factories and
  narrow item/picture `ExtractRows` interfaces. Empty/unselected requests resolve no dependencies;
  nested work retains the shared extraction budget. Root integration fixtures remain test-only.
- **`internal/transport/grpc/pictureitemextractor/`** — picture/item relationship enrichment.
  Injects narrow item/picture `ExtractRows` interfaces and explicit lazy factories for the
  items/pictures repositories and parent-cache extractor. Only selected preloads resolve these
  dependencies; plain relationships and empty requests require no repository setup. Construction
  stays in the container; selected preloads share the bounded runner.
- **`internal/transport/grpc/{dfdistanceextractor,parentcacheextractor}/`** — duplicate-distance
  and parent-cache enrichment with explicit repositories and narrow `ExtractRows` interfaces.
  Context-aware container factories construct them only in selected request branches; nested
  picture extraction retains the inherited concurrency budget. No application-container imports.
- **`internal/transport/grpc/{userextractor,ipextractor}/`** — enrich and map users/IPs to protobuf
  with explicitly injected repositories and image storage, without the application container.
- **`internal/catalogue/`** — catalogue/reference-data service and its tree/perspective tests.
  Receives its databases explicitly; SQL and protobuf mapping remain together during migration.
- **`internal/brandssprite/`** — brand sprite/CSS generation with explicit repository, image
  storage and file-storage configuration. The application calls `Build`; API URLs use the same
  exported filenames. S3/image generation behavior is unchanged.
- **`internal/duplicatefinder/`** — perceptual hashes, duplicate indexing and its AMQP consumer;
  repositories/configuration are injected. Hash tests live here; integration tests stay at the root.
- **`internal/itemofdaycache/`** — item-of-day protobuf cache and explicit invalidation.
  Depends on repositories, Redis and a narrow item `Extract` interface rather than the root
  application extractor. Preserve the existing cache key format and TTL during migration.
- **`internal/inboxbrands/`** — cached per-language inbox brand lists and explicit invalidation.
- **`internal/daypictures/`** — date navigation and per-day picture counts with an explicitly
  injected pictures repository. These components retain their existing logic and SQL/cache behavior.
- **`internal/picturefetcher/`** — perspective-based preview selection with an explicitly injected
  pictures repository. Selection results/options live here rather than in `internal/schema/`; this package
  has no protobuf or application-container dependency.
- **`internal/transport/grpc/{itemmapper,picturemapper,commentmapper}/`** — pure conversions
  between domain/schema types and protobuf. These packages must not query repositories or use
  authentication or the application container. `querymapper/` converts the mutually recursive
  item/picture request filters to query options and reuses the enum mappers.
  Links and picture moderation votes are stateless mapper functions, without extractor objects.
- **`internal/transport/grpc/imagemapper/`** — shared storage-image to protobuf conversion.
- **`internal/transport/grpc/grpcerrors/`** — shared gRPC authentication-error mapping and field
  violation details. Transport handlers use these helpers without importing the root package.
- **`internal/transport/grpc/<domain>/`** — migrated gRPC servers expose `Server` and `NewServer`,
  and are constructed by the DI container with explicit dependencies. Attribute mapping and
  batch-read tests, and messaging summary tests, live alongside their servers; root integration
  tests check registration. Transport packages must not import the root application package or
  receive its `Container`, and must query repositories rather than constructing SQL directly.
- **`internal/transport/http/{users,items,pictures,yoomoney}/`** — REST handlers expose `Server`, `NewServer`,
  and `SetupRouter`. Repositories own persistence; HTTP handlers own multipart parsing and responses.
  `internal/transport/http/httperrors/` holds the shared validation-error response and codes.
  Pictures depend on small cache invalidation interfaces; cache implementations stay in the root.
  YooMoney keeps its webhook validation and payment handling together in the HTTP package.
  Shared REST/gRPC source-URL and licence validation lives in `internal/transport/picturevalidation/`.
- **`internal/transport/websocket/{messaging,pictures}/`** — WebSocket upgrade and connection
  handlers. Hubs remain in `internal/messaging/` and `internal/pictures/`; application workers own Redis subscriptions.

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
  handler; `GRPCServerWithServices` (in `container.go`) registers every `XxxGRPCServer` against the
  proto-generated `RegisterXxxServer` functions.
- **Per-domain gRPC servers**: `internal/transport/grpc/<domain>/server.go` implements
  `pb.UnimplementedXxxServer` and translates between protobuf and repository types. Domain
  business logic and DB access live in `internal/<domain>/`, typically in a `Repository` built
  on `goqu` query builders. The root application package owns composition and startup.
- **`internal/schema/`** — typed constants for every Postgres table/column plus enum-like ID types (e.g.
  `schema.AttrsAttributeTypeIDString`, `schema.ItemTableItemTypeIDBrand`). Query code should build
  goqu expressions against these constants instead of hardcoding table/column strings.
- **`internal/validation/`** — shared input filters and validators used by request handlers.
- **`internal/query/`** and **`internal/filter/`** — shared query-building and filter-parsing helpers used across
  repositories for paginated/filtered list endpoints.
- **`migrations/`** — golang-migrate SQL migrations (paired `NNN_name.up.sql` /
  `NNN_name.down.sql`), applied by `Application.MigratePostgres` / the `migrate-postgres` CLI
  command and automatically before `serve`.
- **`internal/attrsamqp/`, `internal/duplicatefinder/`, `internal/traffic/monitoring.go`** — background AMQP consumers
  wired up as opt-in workers via `ServeOptions`.
- **`internal/image/storage/`** — S3-backed image storage abstraction (formatted image variants, naming
  strategies, flush/list maintenance commands exposed through the CLI).
