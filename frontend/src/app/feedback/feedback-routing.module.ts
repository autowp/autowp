import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./sent/sent.component').then((m) => m.FeedbackSentComponent),
    path: 'sent',
    pathMatch: 'full',
    title: $localize`Message sent`,
  },
  {
    loadComponent: () => import('./feedback.component').then((m) => m.FeedbackComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Feedback`,
  },
];
