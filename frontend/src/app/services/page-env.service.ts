import type {Observable} from 'rxjs';

import {computed, inject, Service, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Title} from '@angular/platform-browser';
import {of, switchMap} from 'rxjs';

import type {PageId} from './page-id';

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
  pageId?: PageId;
  title?: string;
}

@Service()
export class PageEnvService {
  readonly #pageService = inject(PageService);
  readonly #titleService = inject(Title);

  public readonly pageEnv = signal<null | PageEnv>(null);
  readonly #pageEnv = toObservable(this.pageEnv);
  public readonly layoutParams = computed<LayoutParams>(() => {
    const data = this.pageEnv();

    return {
      isAdminPage: data?.layout?.isAdminPage ?? false,
      isGalleryPage: data?.layout?.isGalleryPage ?? false,
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

  public isActive$(id: PageId): Observable<boolean> {
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
