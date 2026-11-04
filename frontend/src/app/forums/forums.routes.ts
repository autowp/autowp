import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./move-message/forums-move-message').then((m) => m.ForumsMoveMessage),
    path: 'move-message',
    title: $localize`Move`,
  },
  {
    loadComponent: () => import('./move-topic/forums-move-topic').then((m) => m.ForumsMoveTopic),
    path: 'move-topic',
    title: $localize`Move`,
  },
  {
    loadComponent: () => import('./new-topic/forums-new-topic').then((m) => m.ForumsNewTopic),
    path: 'new-topic/:theme_id',
    title: $localize`New topic`,
  },
  {
    loadComponent: () => import('./subscriptions/forums-subscriptions').then((m) => m.ForumsSubscriptions),
    path: 'subscriptions',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./topic/forums-topic').then((m) => m.ForumsTopic),
    path: 'topic/:topic_id',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./message/message').then((m) => m.Message),
    path: 'message/:message_id',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./forums').then((m) => m.Forums),
    path: ':theme_id',
    title: $localize`Forums`,
  },
  {
    loadComponent: () => import('./forums').then((m) => m.Forums),
    path: '',
    title: $localize`Forums`,
  },
];
