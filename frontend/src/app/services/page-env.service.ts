import {computed, effect, inject, Service, signal} from '@angular/core';
import {Title} from '@angular/platform-browser';

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
  public readonly layoutParams = computed<LayoutParams>(
    () => {
      const data = this.pageEnv();

      return {
        isAdminPage: data?.layout?.isAdminPage ?? false,
        isGalleryPage: data?.layout?.isGalleryPage ?? false,
      };
    },
    {
      equal: (previous, current) =>
        previous.isAdminPage === current.isAdminPage && previous.isGalleryPage === current.isGalleryPage,
    },
  );

  public constructor() {
    effect(() => {
      const data = this.pageEnv();
      if (data?.title) {
        this.#titleService.setTitle(data.title);
      }
    });
  }

  public set(data: PageEnv) {
    this.pageEnv.set(data);
  }

  public isActive(id: PageId): boolean {
    const pageId = this.pageEnv()?.pageId;
    return !!pageId && this.#pageService.isDescendant(pageId, id);
  }
}
