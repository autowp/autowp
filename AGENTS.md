# Agent instructions

This file provides guidance to coding agents working in this repository.

Read `AGENTS.local.md` when present for additional local instructions. It is intentionally
excluded from Git.

Each `AGENTS.md` is the shared source for its directory. `CLAUDE.md` and `GEMINI.md`
are relative symlinks to it.
`AGENTS.local.md` is the local source; `CLAUDE.local.md` is a relative symlink to it.

## Directory instructions

Read the relevant guide before working in a component, including when starting from the repository root.

- [Backend](goautowp/AGENTS.md) covers Go builds, tests, and architecture.
- [Frontend](frontend/AGENTS.md) covers Angular builds, SSR, linting, and UI conventions.
- [Helm](chart/AGENTS.md) covers deployment and routing. Consult it when editing the root `router.conf`.

## Project overview

This is the monorepo for **wheelsage.org / autowp.ru**, a car catalogue/wiki website. It has two main
components that share a single gRPC contract:

- `goautowp/` — Go backend: gRPC services, a public HTTP/REST gateway, background workers (AMQP
  consumers, schedulers), CLI tooling, and PostgreSQL access.
- `frontend/` — Angular SPA with SSR (Express server), consuming the backend over grpc-web.

The gRPC API surface (services + messages) is defined once in `spec.proto` at the repo root and
code-generated into both sides — do not hand-edit generated files.

## Code generation

`spec.proto` is the single source of truth for the API. After changing it, regenerate both the Go
server stubs and the Angular client:

```sh
./protoc.sh
```

This runs `protoc` with `--go_out`/`--go-grpc_out` into `goautowp/api/pb/` and `--ng_out` into
`frontend/src/grpc/`. It requires `protoc-gen-go`, `protoc-gen-go-grpc`, and the local
`protoc-gen-ng` (from `frontend/node_modules/.bin`, selected explicitly by the script). The Go
plugins must be on `PATH`. Install frontend dependencies first with
`npm ci --prefix frontend --include=dev`. Backend-only CI jobs use `./protoc.sh --go-only`,
which generates only Go stubs and does not require frontend dependencies. CI runs generation
before building either side, so a proto change without regenerating output will build stale code locally.

### API conventions

- **Update RPCs carry a field mask.** An `UpdateXxxRequest` message is
  `{ Xxx resource = 1; google.protobuf.FieldMask update_mask = 2; }` (see `UpdateUserRequest`);
  the handler applies only the fields listed in `update_mask.GetPaths()`. Do not add per-field
  scalar `UpdateXxxRequest` messages.

## Cross-cutting notes

- **Cancellation during mutations** — an HTTP/gRPC client can disconnect or cancel its request at
  any step, cancelling the request context and interrupting the remaining work. For every mutating
  call, define the cancellation boundary: honour cancellation before starting the mutation, use a
  transaction for changes that must roll back together, and ensure required follow-up work after a
  committed change cannot be skipped by request cancellation. Where that work must finish, derive
  a bounded context with `context.WithTimeout(context.WithoutCancel(ctx), timeout)` and release it
  with `defer cancel()`. Do not detach an entire request indiscriminately or leave detached work
  without a timeout. Background buffers/workers must use the service lifecycle context, not the
  context of the request that happened to initialize them. Test cancellation between persistent
  mutation steps when partial completion could leave inconsistent state.
- Auth for both gRPC and REST is backed by Keycloak (`goautowp/internal/auth/`, `KeycloakConfig`); local
  dev/test Keycloak is provisioned via `goautowp/docker-compose.yml` with `tests/testdata/keycloak/realm.json`.
- CI (`.gitlab-ci.yml`) is the authoritative source for exact lint/build/test invocations if this
  file and reality diverge — check it first when in doubt.

## Commits

We use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)
