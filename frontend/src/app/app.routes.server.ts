import {RenderMode, ServerRoute} from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  {
    path: 'articles/**',
    renderMode: RenderMode.Server,
  },
  {
    path: 'donate/success',
    renderMode: RenderMode.Server,
  },
  {
    path: 'policy',
    renderMode: RenderMode.Server,
  },
  {
    path: 'telegram',
    renderMode: RenderMode.Server,
  },
  {
    path: '**',
    renderMode: RenderMode.Client,
  },
];
