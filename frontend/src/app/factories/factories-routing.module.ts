import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    children: [
      {loadComponent: () => import('./items/items.component').then((m) => m.FactoryItemsComponent), path: 'items'},
      {
        loadComponent: () => import('./factories.component').then((m) => m.FactoryComponent),
        path: '',
        pathMatch: 'full',
      },
    ],
    path: ':id',
    title: $localize`Products`,
  },
];
