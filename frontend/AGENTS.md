# Frontend agent instructions

## Build and test

Run these commands from `frontend/`.

```sh
npm ci
ng serve                                 # dev server
ng build --configuration production      # production build (also: npm run build)
ng lint                                  # eslint, includes Angular template rules
npx stylelint "src/**/*.scss"
```

Note: clear `frontend/dist` directory before run `ng lint` to prevent OOM during linting

Run frontend builds and lint sequentially. Never run `ng build` / `npm run build` and
`ng lint` / `npm run lint` at the same time.

There is no unit test suite configured for the frontend (no `*.spec.ts` files, no `test` builder
in `angular.json`) — don't assume Karma/Jest is set up.

## Architecture

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

## Conventions

- **Component naming** — follow the Angular 2025 style: `UserProfile` in `user-profile.ts`,
  with matching `user-profile.html` and `user-profile.scss`. Omit the `Component` suffix.
  Choose descriptive names such as `UserLink` or `MapPage` when a shorter name conflicts with
  a domain/API type. Keep existing selectors stable when renaming.
- **Class member order** — declare fields before the constructor and methods. Preserve field
  initialization order; do not sort fields by visibility or alphabetically. ESLint checks the
  groups with `local-angular/member-order` (arrow functions remain fields); the generic
  `@typescript-eslint/member-ordering` and `perfectionist/sort-classes` rules stay disabled.
- **Component template API** — use `protected` for members used only by the component and its
  template. Inputs, outputs, models and methods called by consumers remain public. Check callers
  before narrowing visibility; this distinction is reviewed manually.
- **Naming checks** — the local ESLint `local-angular/component-names` rule checks component class,
  filename and colocated template/style names. Run `npm run lint:rules` when changing lint rules.

- **`preserveWhitespaces: false`** — set globally in `tsconfig.json`'s `angularCompilerOptions`
  (Angular's SSR recommendation); no component overrides it, and none should. When a template edit
  drops an inline gap that used to come from template whitespace, restore it with `&ngsp;` (a
  single significant space between bare text and an element) or, for repeated inline lists / icon
  rows, a Bootstrap spacing utility — never by re-adding `preserveWhitespaces: true`. Common
  trigger: a `<ng-container i18n>Label:</ng-container>` immediately followed on the next line by an
  element or an `@if`/`@else` block collapses to no gap, and two adjacent `@if`/`@else` blocks
  render with no gap between them either — fix with `&ngsp;`.
- **SSR-safe not-found / redirects** — a routed component that finds its URL has no content must
  call `inject(NotFoundService).report()` (`src/app/services/not-found.ts`), never
  `router.navigate(['/error-404'])` from an `effect()` or lifecycle hook: an imperative navigation
  fired mid-render is not honoured by Angular SSR (`whenStable()` can serialize the router outlet
  mid-transition, producing a blank page instead of the 404). `report()` flips a signal
  `App` reads to render `<app-page-not-found>` in place of `<router-outlet>` and sets
  `RESPONSE_INIT.status = 404` under SSR, working identically client/server. Likewise a
  **data-dependent redirect** (e.g. canonical-URL normalisation) belongs in a `CanActivateFn`
  returning `new RedirectCommand(router.createUrlTree(route), {replaceUrl: true})`, not an
  imperative `router.navigate()` from an `effect()` — see `pictureCanonicalGuard`
  (`src/app/picture/picture-canonical.guard.ts`). Config-level redirects
  (`{path: '**', redirectTo: 'error-404'}`) and guard/resolver redirects work fine under SSR since
  they resolve before the component tree is built.
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
- **`ItemRequest`/`GetTreeRequest` need `language`** — any call requesting localized `ItemFields`
  (`nameHtml`, `nameText`, `nameDefault`, `fullName`, ...) or `GetTreeRequest` must pass
  `language: this.#languageService.language`, otherwise the response falls back to a
  non-localized/default-language rendering (e.g. an unlocalized "pr." instead of "н.в." in a
  title). Copy an existing sibling call in the same file rather than constructing the request
  from scratch.
- **int64 proto fields arrive as strings** — grpc-web via `ngx-grpc` renders proto `int64` as a TS
  `string`, so an "unset" id is the literal string `"0"`, which is truthy in JS. Guard with an
  explicit `!== '0'` check, not a bare truthiness check, when treating such a field as
  present/selected.
