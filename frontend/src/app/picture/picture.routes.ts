import type {Routes} from '@angular/router';

import {pictureCanonicalGuard} from './picture-canonical.guard';

export const routes: Routes = [
  {
    canActivate: [pictureCanonicalGuard],
    loadComponent: () => import('./picture-page.component').then((m) => m.PicturePageComponent),
    path: ':identity',
  },
];
