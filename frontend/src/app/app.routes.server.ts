import {RenderMode, ServerRoute} from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  {
    path: 'articles/**',
    renderMode: RenderMode.Server,
  },
  {
    path: 'donate/success',
    renderMode: RenderMode.Server,
  },
  {
    path: 'policy',
    renderMode: RenderMode.Server,
  },
  {
    path: 'telegram',
    renderMode: RenderMode.Server,
  },
  {
    path: 'feedback/**',
    renderMode: RenderMode.Server,
  },
  {
    path: 'brands/**',
    renderMode: RenderMode.Server,
  },
  {
    path: 'cutaway/**',
    renderMode: RenderMode.Server,
  },
  {
    path: 'top-view/**',
    renderMode: RenderMode.Server,
  },
  {
    path: 'picture/**',
    renderMode: RenderMode.Server,
  },
  // No `/**` here deliberately: only the bare brand page (e.g. /toyota) is server-rendered, not
  // most other things nested under it (e.g. /toyota/corolla, /toyota/moder-only-routes...). A
  // path with more segments than this fails to match this node (it has no children) and falls
  // through to the final Client catch-all below instead - except the entries below, which have
  // their own more specific paths and are matched first. :brand/{mixed,other,logotypes}/** all
  // share the same CatalogueMixed*Component classes.
  {
    path: ':brand',
    renderMode: RenderMode.Server,
  },
  {
    path: ':brand/mixed/**',
    renderMode: RenderMode.Server,
  },
  {
    path: ':brand/other/**',
    renderMode: RenderMode.Server,
  },
  {
    path: ':brand/logotypes/**',
    renderMode: RenderMode.Server,
  },
  {
    path: ':brand/recent',
    renderMode: RenderMode.Server,
  },
  {
    path: ':brand/cars/**',
    renderMode: RenderMode.Server,
  },
  // Every other top-level route pinned to Client explicitly. `:brand` above is a generic
  // positional wildcard — @angular/ssr's route tree normalizes any ':xxx' segment to the same
  // wildcard match regardless of name, and at each level tries an exact literal match before
  // falling back to it — so without these entries, :brand would silently swallow every route
  // below too (moder/account/upload have no SSR value, Keycloak never runs server-side; new/inbox
  // render user-specific, timezone-dependent content SSR can't produce correctly; map/museums/
  // factories/chart/pulse/info depend on browser-only libraries — Leaflet, Chart.js, Monaco —
  // with no platform guards yet).
  {path: 'about/**', renderMode: RenderMode.Client},
  {path: 'achievements/**', renderMode: RenderMode.Client},
  {path: 'account/**', renderMode: RenderMode.Client},
  {path: 'cars/**', renderMode: RenderMode.Client},
  {path: 'category/**', renderMode: RenderMode.Client},
  {path: 'chart/**', renderMode: RenderMode.Client},
  {path: 'donate/**', renderMode: RenderMode.Client},
  {path: 'factories/**', renderMode: RenderMode.Client},
  {path: 'forums/**', renderMode: RenderMode.Client},
  {path: 'inbox/**', renderMode: RenderMode.Client},
  {path: 'info/**', renderMode: RenderMode.Client},
  {path: 'log/**', renderMode: RenderMode.Client},
  {path: 'map/**', renderMode: RenderMode.Client},
  {path: 'mascots/**', renderMode: RenderMode.Client},
  {path: 'moder/**', renderMode: RenderMode.Client},
  {path: 'mosts/**', renderMode: RenderMode.Client},
  {path: 'museums/**', renderMode: RenderMode.Client},
  {path: 'new/**', renderMode: RenderMode.Client},
  {path: 'persons/**', renderMode: RenderMode.Client},
  {path: 'gallery/**', renderMode: RenderMode.Client},
  {path: 'pulse/**', renderMode: RenderMode.Client},
  {path: 'rules/**', renderMode: RenderMode.Client},
  {path: 'twins/**', renderMode: RenderMode.Client},
  {path: 'upload/**', renderMode: RenderMode.Client},
  {path: 'users/**', renderMode: RenderMode.Client},
  {path: 'voting/**', renderMode: RenderMode.Client},
  {path: 'login/**', renderMode: RenderMode.Client},
  {path: 'error-404/**', renderMode: RenderMode.Client},
  {
    path: '**',
    renderMode: RenderMode.Client,
  },
];
