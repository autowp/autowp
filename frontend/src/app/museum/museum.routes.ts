import type {Routes} from '@angular/router';

export const routes: Routes = [{loadComponent: () => import('./museum').then((m) => m.Museum), path: ':id'}];
