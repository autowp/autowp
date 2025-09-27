import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./spec/spec.component').then((m) => m.InfoSpecComponent),
    path: 'spec',
    title: $localize`Specs`,
  },
  {
    loadComponent: () => import('./text/text.component').then((m) => m.InfoTextComponent),
    path: 'text/:id',
    title: $localize`Text history`,
  },
];
