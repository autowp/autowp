import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./rules.component').then((m) => m.RulesComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Rules`,
  },
];
