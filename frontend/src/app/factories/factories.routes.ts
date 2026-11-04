import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {loadComponent: () => import('./items/factory-items').then((m) => m.FactoryItems), path: 'items'},
      {
        loadComponent: () => import('./factory').then((m) => m.Factory),
        path: '',
        pathMatch: 'full',
      },
    ],
    path: ':id',
    title: $localize`Products`,
  },
  {path: '', pathMatch: 'full', redirectTo: '/error-404'},
];
