import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./legal-notice').then((m) => m.LegalNotice),
    path: '',
    pathMatch: 'full',
    title: $localize`Legal Notice`,
  },
];
