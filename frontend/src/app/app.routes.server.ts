import type {ServerRoute} from '@angular/ssr';

import {RenderMode} from '@angular/ssr';

/**
 * Which routes are rendered on the server, which are prerendered at build time, and which are left
 * to the browser.
 *
 * How a request finds its rule: @angular/ssr walks the path segment by segment through a tree built
 * from the entries below, preferring a literal segment over a `:param` one and `:param` over `**`.
 * `prefix/**` matches `/prefix` itself as well as everything under it. Position in this array
 * decides nothing - `forums/move-message` wins over `forums/**` by being more specific, not by
 * being anywhere in particular - so the entries are sorted by path, with the exceptions sitting
 * directly under the rule they carve out of. The one thing order does decide is a tie: two entries
 * with the same path (`:brand/**` and `:x/**` are the same path) leave the later one standing.
 *
 * Every route in app.routes.ts has to match some entry here or the build fails with "does not match
 * any route defined in the server routing configuration"; the `**` at the bottom is what guarantees
 * that for anything added without touching this file.
 *
 * Top-level routes are spelled out one by one rather than left to that `**`, so that adding one is
 * a decision about server rendering rather than something that happens by default - the fallback
 * used to turn every new route into a server-rendered one silently, the deep catalogue tree
 * included. A route added *inside* one of these modules is still covered by its `prefix/**`.
 */
export const serverRoutes: ServerRoute[] = [
  {path: '', renderMode: RenderMode.Server}, // the index page

  {path: 'about/**', renderMode: RenderMode.Server},

  {path: 'account/**', renderMode: RenderMode.Client}, // Keycloak never runs server-side

  // Not prerendered, though it looks as static as policy or telegram: AchievementsComponent calls
  // AchievementsClient.getAchievementStats() for live per-achievement user counts, so prerendering
  // would both freeze those counts until the next deploy and require the backend to be reachable
  // during `ng build` in CI, which it isn't.
  {path: 'achievements/**', renderMode: RenderMode.Server},

  {path: 'articles/**', renderMode: RenderMode.Server},
  {path: 'brands/**', renderMode: RenderMode.Server},

  // dateless and attrs-change-log are content; the three below are moderator editing tools.
  {path: 'cars/**', renderMode: RenderMode.Server},
  {path: 'cars/select-engine', renderMode: RenderMode.Client},
  {path: 'cars/specifications-editor', renderMode: RenderMode.Client},
  {path: 'cars/specs-admin', renderMode: RenderMode.Client},

  {path: 'category/**', renderMode: RenderMode.Server},

  {path: 'chart/**', renderMode: RenderMode.Client}, // Chart.js (ng2-charts), no platform guard yet

  {path: 'cutaway/**', renderMode: RenderMode.Server},

  // donate and donate/log are content. success is a fixed thank-you page with no per-request data,
  // and vod is a payment form whose content (anonymous checkbox, current user) is inherently
  // personalized, same as inbox and new below.
  {path: 'donate/**', renderMode: RenderMode.Server},
  {path: 'donate/success', renderMode: RenderMode.Prerender},
  {path: 'donate/vod/**', renderMode: RenderMode.Client},

  // Server-rendered so the response carries the status the page is about, rather than a 200 with an
  // empty shell.
  {path: 'error-403/**', renderMode: RenderMode.Server},
  {path: 'error-404/**', renderMode: RenderMode.Server},

  {path: 'factories/**', renderMode: RenderMode.Server},
  {path: 'feedback/**', renderMode: RenderMode.Server},

  // forums/:theme_id and forums/topic/:topic_id are content; the rest are either authoring forms or
  // personalized.
  {path: 'forums/**', renderMode: RenderMode.Server},
  {path: 'forums/message/**', renderMode: RenderMode.Client},
  {path: 'forums/move-message', renderMode: RenderMode.Client},
  {path: 'forums/move-topic', renderMode: RenderMode.Client},
  {path: 'forums/new-topic/**', renderMode: RenderMode.Client},
  {path: 'forums/subscriptions', renderMode: RenderMode.Client},

  {path: 'gallery/**', renderMode: RenderMode.Server},

  {path: 'inbox/**', renderMode: RenderMode.Client}, // auth-gated, no SSR value

  // info/spec is content; InfoTextComponent renders Monaco's DiffEditorComponent directly in its
  // template, with no @defer or platform guard.
  {path: 'info/**', renderMode: RenderMode.Server},
  {path: 'info/text/**', renderMode: RenderMode.Client},

  // Requires RoleModer server-side (LogGRPCServer.GetEvents) - an anonymous SSR pass would only
  // ever render its permission-denied state.
  {path: 'log/**', renderMode: RenderMode.Client},

  {path: 'login/**', renderMode: RenderMode.Client}, // Keycloak never runs server-side

  // Leaflet, no platform guard yet - unlike factories/** and museums/**, whose own Leaflet usage is
  // isolated in FactoryMapComponent/MuseumMapComponent, used only inside an @defer block, so it
  // never loads server-side.
  {path: 'map/**', renderMode: RenderMode.Client},

  {path: 'mascots/**', renderMode: RenderMode.Server},

  {path: 'moder/**', renderMode: RenderMode.Client}, // auth-gated moderation tools, no SSR value

  {path: 'mosts/**', renderMode: RenderMode.Server},
  {path: 'museums/**', renderMode: RenderMode.Server},

  // Redirects client-side to today's date on load; no SSR redirect mechanism exists yet for that.
  {path: 'new/**', renderMode: RenderMode.Client},

  {path: 'persons/**', renderMode: RenderMode.Server},
  {path: 'picture/**', renderMode: RenderMode.Server},

  {path: 'policy', renderMode: RenderMode.Prerender}, // static text, no per-request data

  {path: 'pulse/**', renderMode: RenderMode.Client}, // Chart.js (ng2-charts), no platform guard yet

  {path: 'rules/**', renderMode: RenderMode.Server},

  {path: 'telegram', renderMode: RenderMode.Prerender}, // static text, no per-request data

  {path: 'top-view/**', renderMode: RenderMode.Server},
  {path: 'twins/**', renderMode: RenderMode.Server},

  {path: 'upload/**', renderMode: RenderMode.Client}, // auth-gated file upload, no SSR value

  {path: 'users/**', renderMode: RenderMode.Server},
  {path: 'voting/**', renderMode: RenderMode.Server},

  // The catalogue, one level at a time.
  //
  // `:brand` is the largest and most expensive tree on the site - it is where the crawler spends
  // its time, and where a render costs the most gRPC calls - so server rendering is being turned on
  // for it a level at a time rather than all at once. So far: the brand page itself, and the three
  // galleries under it with everything inside them - their index, the `gallery/:identity` view and
  // the `:identity` picture pages.
  //
  // The rest of the tree stays client-rendered for now: cars, engines, concepts, recent and mosts,
  // and the vehicle tree matched by cataloguePathMatcher, which is the deep half of the catalogue
  // and the one worth turning on last. Note what that costs while it lasts - a nested catalogue URL
  // that doesn't exist answers 200 with an empty shell rather than a 404, because deciding that
  // requires the render this step skips.
  //
  // Sorted last rather than under `b`, because `:brand` is not a prefix like the ones above: it
  // matches any single segment that isn't one of them.
  {path: ':brand', renderMode: RenderMode.Server},
  {path: ':brand/logotypes/**', renderMode: RenderMode.Server},
  {path: ':brand/mixed/**', renderMode: RenderMode.Server},
  {path: ':brand/other/**', renderMode: RenderMode.Server},
  {path: ':brand/**', renderMode: RenderMode.Client},

  // Only reachable by a route that was added without being listed above. Server rather than Client
  // so that such a route behaves as it did before this file enumerated anything, rather than
  // quietly losing its server rendering.
  {path: '**', renderMode: RenderMode.Server},
];
