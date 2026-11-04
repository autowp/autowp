import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./gallery-page').then((m) => m.GalleryPage),
    path: ':identity',
  },
];
