import {computed, inject, Injectable, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Title} from '@angular/platform-browser';
import {Observable, of} from 'rxjs';
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

  public readonly pageEnv = signal<null | PageEnv>(null);
  readonly #pageEnv = toObservable(this.pageEnv);
  public readonly layoutParams = computed<LayoutParams>(() => {
    const data = this.pageEnv();

    return {
      isAdminPage: data?.layout?.isAdminPage || false,
      isGalleryPage: data?.layout?.isGalleryPage || false,
    };
  });

  public constructor() {
    this.#pageEnv.subscribe((data) => {
      if (data?.title) {
        this.#titleService.setTitle(data.title);
      }
    });
  }

  public set(data: PageEnv) {
    this.pageEnv.set(data);
  }

  public isActive$(id: number): Observable<boolean> {
    return this.#pageEnv.pipe(
      switchMap((data) => {
        if (!data?.pageId) {
          return of(false);
        }
        return this.#pageService.isDescendant$(data.pageId, id);
      }),
    );
  }
}
