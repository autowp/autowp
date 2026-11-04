import type {Routes} from '@angular/router';

export const routes: Routes = [
  {loadChildren: () => import('./about/about.routes').then((m) => m.routes), path: 'about'},
  {
    loadChildren: () => import('./achievements/achievements.routes').then((m) => m.routes),
    path: 'achievements',
  },
  {
    loadChildren: () => import('./account/account.routes').then((m) => m.routes),
    path: 'account',
  },
  {
    loadChildren: () => import('./articles/articles.routes').then((m) => m.routes),
    path: 'articles',
  },
  {loadChildren: () => import('./brands/brands.routes').then((m) => m.routes), path: 'brands'},
  {
    loadChildren: () => import('./cars/cars.routes').then((m) => m.routes),
    path: 'cars',
  },
  {
    loadChildren: () => import('./categories/categories.routes').then((m) => m.routes),
    path: 'category',
  },
  {loadChildren: () => import('./chart/chart.routes').then((m) => m.routes), path: 'chart'},
  {loadChildren: () => import('./cutaway/cutaway.routes').then((m) => m.routes), path: 'cutaway'},
  {
    loadChildren: () => import('./donate/donate.routes').then((m) => m.routes),
    path: 'donate',
  },
  {
    loadChildren: () => import('./factories/factories.routes').then((m) => m.routes),
    path: 'factories',
  },
  {
    loadChildren: () => import('./feedback/feedback.routes').then((m) => m.routes),
    path: 'feedback',
  },
  {
    loadChildren: () => import('./forums/forums.routes').then((m) => m.routes),
    path: 'forums',
  },
  {
    loadChildren: () => import('./inbox/inbox.routes').then((m) => m.routes),
    path: 'inbox',
  },
  {
    loadChildren: () => import('./info/info.routes').then((m) => m.routes),
    path: 'info',
  },
  {loadChildren: () => import('./log/log.routes').then((m) => m.routes), path: 'log'},
  {loadChildren: () => import('./map/map.routes').then((m) => m.routes), path: 'map'},
  {loadChildren: () => import('./mascots/mascots.routes').then((m) => m.routes), path: 'mascots'},
  {
    loadChildren: () => import('./moder/moder.routes').then((m) => m.routes),
    path: 'moder',
  },
  {
    loadChildren: () => import('./mosts/mosts.routes').then((m) => m.routes),
    path: 'mosts',
  },
  {loadChildren: () => import('./museum/museum.routes').then((m) => m.routes), path: 'museums'},
  {
    loadChildren: () => import('./new/new.routes').then((m) => m.routes),
    path: 'new',
  },
  {
    loadChildren: () => import('./persons/persons.routes').then((m) => m.routes),
    path: 'persons',
  },
  {
    loadChildren: () => import('./picture/picture.routes').then((m) => m.routes),
    path: 'picture',
  },
  {
    loadChildren: () => import('./gallery/gallery.routes').then((m) => m.routes),
    path: 'gallery',
  },
  {loadChildren: () => import('./pulse/pulse.routes').then((m) => m.routes), path: 'pulse'},
  {loadChildren: () => import('./rules/rules.routes').then((m) => m.routes), path: 'rules'},
  {loadChildren: () => import('./policy/policy.routes').then((m) => m.routes), path: 'policy'},
  {loadChildren: () => import('./tos/terms.routes').then((m) => m.routes), path: 'tos'},
  {
    loadChildren: () => import('./copyright/copyright.routes').then((m) => m.routes),
    path: 'copyright',
  },
  {
    loadChildren: () => import('./licenses/licenses.routes').then((m) => m.routes),
    path: 'licenses',
  },
  {
    loadChildren: () => import('./legal-notice/legal-notice.routes').then((m) => m.routes),
    path: 'legal-notice',
  },
  {
    loadChildren: () => import('./telegram/telegram.routes').then((m) => m.routes),
    path: 'telegram',
  },
  {
    loadChildren: () => import('./twins/twins.routes').then((m) => m.routes),
    path: 'twins',
  },
  {loadChildren: () => import('./top-view/top-view.routes').then((m) => m.routes), path: 'top-view'},
  {loadChildren: () => import('./upload/upload.routes').then((m) => m.routes), path: 'upload'},
  {
    loadChildren: () => import('./users/users.routes').then((m) => m.routes),
    path: 'users',
  },
  {loadChildren: () => import('./voting/voting.routes').then((m) => m.routes), path: 'voting'},
  {loadChildren: () => import('./index/index.routes').then((m) => m.routes), path: ''},
  {loadComponent: () => import('./page-not-found').then((m) => m.PageNotFound), path: 'error-404'},
  {loadComponent: () => import('./forbidden').then((m) => m.Forbidden), path: 'error-403'},
  {loadComponent: () => import('./login/login').then((m) => m.Login), path: 'login'},
  {
    loadChildren: () => import('./catalogue/catalogue.routes').then((m) => m.routes),
    // matcher: cataloguePathMatcher,
    path: ':brand',
  },
  {path: '**', redirectTo: 'error-404'},
];
