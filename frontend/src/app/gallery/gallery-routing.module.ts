import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./gallery-page.component').then((m) => m.GalleryPageComponent),
    path: ':identity',
  },
];
