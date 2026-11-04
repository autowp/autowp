import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./terms.component').then((m) => m.TermsComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Terms of Service`,
  },
];
