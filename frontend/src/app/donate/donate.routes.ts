import type {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./log/donate-log').then((m) => m.DonateLog),
    path: 'log',
    title: $localize`Donate log`,
  },
  {
    loadComponent: () => import('./success/donate-success').then((m) => m.DonateSuccess),
    path: 'success',
    title: $localize`Donate success`,
  },
  {
    children: [
      {
        loadComponent: () => import('./vod/select/donate-vod-select').then((m) => m.DonateVodSelect),
        path: 'select',
      },
      {
        loadComponent: () => import('./vod/success/donate-vod-success').then((m) => m.DonateVodSuccess),
        path: 'success',
        title: $localize`Donate success`,
      },
      {loadComponent: () => import('./vod/donate-vod').then((m) => m.DonateVod), path: ''},
    ],
    path: 'vod',
    title: $localize`Donate`,
  },
  {
    loadComponent: () => import('./donate').then((m) => m.Donate),
    path: '',
    title: $localize`Donate`,
  },
];
