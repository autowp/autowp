import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./move-message/move-message.component').then((m) => m.ForumsMoveMessageComponent),
    path: 'move-message',
    title: $localize`Move`,
  },
  {
    loadComponent: () => import('./move-topic/move-topic.component').then((m) => m.ForumsMoveTopicComponent),
    path: 'move-topic',
    title: $localize`Move`,
  },
  {
    loadComponent: () => import('./new-topic/new-topic.component').then((m) => m.ForumsNewTopicComponent),
    path: 'new-topic/:theme_id',
    title: $localize`New topic`,
  },
  {
    loadComponent: () => import('./subscriptions/subscriptions.component').then((m) => m.ForumsSubscriptionsComponent),
    path: 'subscriptions',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./topic/topic.component').then((m) => m.ForumsTopicComponent),
    path: 'topic/:topic_id',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./message/message.component').then((m) => m.MessageComponent),
    path: 'message/:message_id',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./forums.component').then((m) => m.ForumsComponent),
    path: ':theme_id',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./forums.component').then((m) => m.ForumsComponent),
    path: '',
    title: $localize`Forums`,
  },
];
