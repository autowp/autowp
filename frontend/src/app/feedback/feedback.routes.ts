import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./sent/feedback-sent').then((m) => m.FeedbackSent),
    path: 'sent',
    pathMatch: 'full',
    title: $localize`Message sent`,
  },
  {
    loadComponent: () => import('./feedback').then((m) => m.Feedback),
    path: '',
    pathMatch: 'full',
    title: $localize`Feedback`,
  },
];
