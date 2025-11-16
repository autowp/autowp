import {RenderMode, ServerRoute} from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  {
    path: 'account/*',
    renderMode: RenderMode.Client,
  },
  {
    path: 'inbox',
    renderMode: RenderMode.Client,
  },
  {
    path: 'inbox/**',
    renderMode: RenderMode.Client,
  },
  {
    path: 'moder',
    renderMode: RenderMode.Client,
  },
  {
    path: 'moder/**',
    renderMode: RenderMode.Client,
  },
  {
    path: 'map',
    renderMode: RenderMode.Client,
  },
  {
    path: 'new',
    renderMode: RenderMode.Client,
  },
  {
    path: 'new/**',
    renderMode: RenderMode.Client,
  },
  {
    path: '**',
    renderMode: RenderMode.Server,
  },
];
