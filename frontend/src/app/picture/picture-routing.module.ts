import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./picture-page.component').then((m) => m.PicturePageComponent),
    path: ':identity',
  },
];
