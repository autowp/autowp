import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./legal-notice.component').then((m) => m.LegalNoticeComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Legal Notice`,
  },
];
