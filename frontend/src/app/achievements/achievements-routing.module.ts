import {Routes} from '@angular/router';

export const routes: Routes = [
  {
    loadComponent: () => import('./achievements.component').then((m) => m.AchievementsComponent),
    path: '',
    pathMatch: 'full',
    title: $localize`Achievements`,
  },
];
