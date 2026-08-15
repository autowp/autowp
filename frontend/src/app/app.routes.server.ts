import type {ServerRoute} from '@angular/ssr';

import {RenderMode} from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  // Prerender: fully static pages built once at deploy time, no per-request data dependency.
  // achievements is deliberately not in this list, even though it looks just as static -
  // AchievementsComponent calls AchievementsClient.getAchievementStats() for live per-achievement
  // user counts, so prerendering it would both freeze those counts until the next deploy and
  // require the backend to be reachable during `ng build` in CI, which it isn't. It's Server via
  // the fallback at the bottom of this file like everything else not listed here.
  {
    path: 'donate/success',
    renderMode: RenderMode.Prerender,
  },
  {
    path: 'policy',
    renderMode: RenderMode.Prerender,
  },
  {
    path: 'telegram',
    renderMode: RenderMode.Prerender,
  },

  // Every route is Server by default (see the `**` fallback at the bottom) - including the whole
  // :brand/** catalogue tree (bare brand page, mixed/other/logotypes galleries, cars, recent,
  // engines, concepts, mosts, and the deep vehicle-catalogue tree matched client-side via a custom
  // UrlMatcher). Routes below are pinned to Client explicitly because they aren't safe or valuable
  // to server-render yet - without these entries they'd silently be swallowed by that fallback.
  {path: 'account/**', renderMode: RenderMode.Client}, // Keycloak never runs server-side
  // The rest of cars/* (dateless, attrs-change-log) is content and falls through to the Server
  // fallback on its own; these three are moderator editing tools, not content.
  {path: 'cars/select-engine', renderMode: RenderMode.Client},
  {path: 'cars/specifications-editor', renderMode: RenderMode.Client},
  {path: 'cars/specs-admin', renderMode: RenderMode.Client},
  {path: 'chart/**', renderMode: RenderMode.Client}, // Chart.js (ng2-charts), no platform guard yet
  // donate and donate/log fall through to the Server fallback on their own; donate/vod/** stays
  // Client - it's a payment form whose content (anonymous checkbox, current user) is inherently
  // personalized, same as new/inbox below.
  {path: 'donate/vod/**', renderMode: RenderMode.Client},
  {path: 'forums/move-message', renderMode: RenderMode.Client},
  {path: 'forums/move-topic', renderMode: RenderMode.Client},
  // No `/**` needed for forums/:theme_id or forums/topic/:topic_id to reach the Server fallback -
  // both are 2 segments, so they never collide with these two 3-segment Client routes.
  {path: 'forums/new-topic/**', renderMode: RenderMode.Client},
  {path: 'forums/message/**', renderMode: RenderMode.Client},
  {path: 'forums/subscriptions', renderMode: RenderMode.Client},
  {path: 'inbox/**', renderMode: RenderMode.Client}, // auth-gated, no SSR value
  // info/spec falls through to the Server fallback on its own; info/text/** stays Client -
  // InfoTextComponent renders Monaco's DiffEditorComponent directly in the template with no
  // @defer/platform guard.
  {path: 'info/text/**', renderMode: RenderMode.Client},
  // Requires RoleModer server-side (LogGRPCServer.GetEvents) - an anonymous SSR pass would only
  // ever render its permission-denied state.
  {path: 'log/**', renderMode: RenderMode.Client},
  {path: 'login/**', renderMode: RenderMode.Client}, // Keycloak never runs server-side
  // Leaflet, no platform guard yet - unlike factories/** and museums/** (Server by fallback),
  // whose own Leaflet usage is isolated in FactoryMapComponent/MuseumMapComponent, used only
  // inside an @defer block, so it never loads server-side.
  {path: 'map/**', renderMode: RenderMode.Client},
  {path: 'moder/**', renderMode: RenderMode.Client}, // auth-gated moderation tools, no SSR value
  // Redirects client-side to today's date on load; no SSR redirect mechanism exists yet for that.
  {path: 'new/**', renderMode: RenderMode.Client},
  {path: 'pulse/**', renderMode: RenderMode.Client}, // Chart.js (ng2-charts), no platform guard yet
  {path: 'upload/**', renderMode: RenderMode.Client}, // auth-gated file upload, no SSR value

  {
    path: '**',
    renderMode: RenderMode.Server,
  },
];
