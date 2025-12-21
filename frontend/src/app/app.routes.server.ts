import {RenderMode, ServerRoute} from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  {
    path: 'telegram',
    renderMode: RenderMode.Server,
  },
  {
    path: '**',
    renderMode: RenderMode.Client,
  },
];
