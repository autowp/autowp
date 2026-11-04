import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./map-page').then((m) => m.MapPage),
    path: '',
    pathMatch: 'full',
    title: $localize`Map`,
  },
];
