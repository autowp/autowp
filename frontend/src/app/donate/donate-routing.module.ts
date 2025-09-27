import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./log/log.component').then((m) => m.DonateLogComponent),
    path: 'log',
    title: $localize`Donate log`,
  },
  {
    loadComponent: () => import('./success/success.component').then((m) => m.DonateSuccessComponent),
    path: 'success',
    title: $localize`Donate success`,
  },
  {
    children: [
      {
        loadComponent: () => import('./vod/select/select.component').then((m) => m.DonateVodSelectComponent),
        path: 'select',
      },
      {
        loadComponent: () => import('./vod/success/success.component').then((m) => m.DonateVodSuccessComponent),
        path: 'success',
        title: $localize`Donate success`,
      },
      {loadComponent: () => import('./vod/vod.component').then((m) => m.DonateVodComponent), path: ''},
    ],
    path: 'vod',
    title: $localize`Donate`,
  },
  {
    loadComponent: () => import('./donate.component').then((m) => m.DonateComponent),
    path: '',
    title: $localize`Donate`,
  },
];
