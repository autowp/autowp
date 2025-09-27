import {Routes} from '@angular/router';

export const routes: Routes = [
  {loadChildren: () => import('./about/about-routing.module').then((m) => m.routes), path: 'about'},
  {
    loadChildren: () => import('./account/account-routing.module').then((m) => m.routes),
    path: 'account',
  },
  {
    loadChildren: () => import('./articles/articles-routing.module').then((m) => m.routes),
    path: 'articles',
  },
  {loadChildren: () => import('./brands/brands-routing.module').then((m) => m.routes), path: 'brands'},
  {
    loadChildren: () => import('./cars/cars-routing.module').then((m) => m.routes),
    path: 'cars',
  },
  {
    loadChildren: () => import('./categories/categories-routing.module').then((m) => m.routes),
    path: 'category',
  },
  {loadChildren: () => import('./chart/chart-routing.module').then((m) => m.routes), path: 'chart'},
  {loadChildren: () => import('./cutaway/cutaway-routing.module').then((m) => m.routes), path: 'cutaway'},
  {
    loadChildren: () => import('./donate/donate-routing.module').then((m) => m.routes),
    path: 'donate',
  },
  {
    loadChildren: () => import('./factories/factories-routing.module').then((m) => m.routes),
    path: 'factories',
  },
  {
    loadChildren: () => import('./feedback/feedback-routing.module').then((m) => m.routes),
    path: 'feedback',
  },
  {
    loadChildren: () => import('./forums/forums-routing.module').then((m) => m.routes),
    path: 'forums',
  },
  {
    loadChildren: () => import('./inbox/inbox-routing.module').then((m) => m.routes),
    path: 'inbox',
  },
  {
    loadChildren: () => import('./info/info-routing.module').then((m) => m.routes),
    path: 'info',
  },
  {loadChildren: () => import('./log/log-routing.module').then((m) => m.routes), path: 'log'},
  {loadChildren: () => import('./map/map-routing.module').then((m) => m.routes), path: 'map'},
  {loadChildren: () => import('./mascots/mascots-routing.module').then((m) => m.routes), path: 'mascots'},
  {
    loadChildren: () => import('./moder/moder-routing.module').then((m) => m.routes),
    path: 'moder',
  },
  {
    loadChildren: () => import('./mosts/mosts-routing.module').then((m) => m.routes),
    path: 'mosts',
  },
  {loadChildren: () => import('./museum/museum-routing.module').then((m) => m.routes), path: 'museums'},
  {
    loadChildren: () => import('./new/new-routing.module').then((m) => m.routes),
    path: 'new',
  },
  {
    loadChildren: () => import('./persons/persons-routing.module').then((m) => m.routes),
    path: 'persons',
  },
  {
    loadChildren: () => import('./picture/picture-routing.module').then((m) => m.routes),
    path: 'picture',
  },
  {
    loadChildren: () => import('./gallery/gallery-routing.module').then((m) => m.routes),
    path: 'gallery',
  },
  {loadChildren: () => import('./pulse/pulse-routing.module').then((m) => m.routes), path: 'pulse'},
  {loadChildren: () => import('./rules/rules-routing.module').then((m) => m.routes), path: 'rules'},
  {loadChildren: () => import('./policy/policy-routing.module').then((m) => m.routes), path: 'policy'},
  {
    loadChildren: () => import('./telegram/telegram-routing.module').then((m) => m.routes),
    path: 'telegram',
  },
  {
    loadChildren: () => import('./twins/twins-routing.module').then((m) => m.routes),
    path: 'twins',
  },
  {loadChildren: () => import('./top-view/top-view-routing.module').then((m) => m.routes), path: 'top-view'},
  {loadChildren: () => import('./upload/upload-routing.module').then((m) => m.routes), path: 'upload'},
  {
    loadChildren: () => import('./users/users-routing.module').then((m) => m.routes),
    path: 'users',
  },
  {loadChildren: () => import('./voting/voting-routing.module').then((m) => m.routes), path: 'voting'},
  {loadChildren: () => import('./index/index-routing.module').then((m) => m.routes), path: ''},
  {loadComponent: () => import('./not-found.component').then((m) => m.PageNotFoundComponent), path: 'error-404'},
  {loadComponent: () => import('./login/login.component').then((m) => m.LoginComponent), path: 'login'},
  {
    loadChildren: () => import('./catalogue/catalogue-routing.module').then((m) => m.routes),
    // matcher: cataloguePathMatcher,
    path: ':brand',
  },
  {path: '**', redirectTo: 'error-404'},
];
