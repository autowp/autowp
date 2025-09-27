import {inject, Injectable} from '@angular/core';
import {Title} from '@angular/platform-browser';
import {BehaviorSubject, Observable, of} from 'rxjs';
import {switchMap} from 'rxjs/operators';

import {PageService} from './page';

export interface LayoutParams {
  isAdminPage: boolean;
  isGalleryPage: boolean;
}

export interface PageEnv {
  layout?: {
    isAdminPage?: boolean;
    isGalleryPage?: boolean;
  };
  pageId?: number;
  title?: string;
}

@Injectable({
  providedIn: 'root',
})
export class PageEnvService {
  readonly #pageService = inject(PageService);
  readonly #titleService = inject(Title);

  public readonly pageEnv$ = new BehaviorSubject<null | PageEnv>(null);
  public readonly layoutParams$ = new BehaviorSubject<LayoutParams>({
    isAdminPage: false,
    isGalleryPage: false,
  });

  public constructor() {
    this.pageEnv$.subscribe((data) => {
      if (data) {
        this.layoutParams$.next({
          isAdminPage: !!data.layout?.isAdminPage,
          isGalleryPage: !!data.layout?.isGalleryPage,
        });

        if (data.title) {
          this.#titleService.setTitle(data.title);
        }
      }
    });
  }

  public set(data: PageEnv) {
    this.pageEnv$.next(data);
  }

  public isActive$(id: number): Observable<boolean> {
    return this.pageEnv$.pipe(
      switchMap((data) => {
        if (!data?.pageId) {
          return of(false);
        }
        return this.#pageService.isDescendant$(data.pageId, id);
      }),
    );
  }
}
