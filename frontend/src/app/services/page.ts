import type {Observer} from 'rxjs';

import {Service} from '@angular/core';
import {map, Observable} from 'rxjs';

import type {PageId} from './page-id';

import pagesJson from './pages';

export interface Page {
  childs: Page[];
  id: PageId;
}

@Service()
export class PageService {
  readonly #pages = new Map<PageId, Page>();
  readonly #parents = new Map<PageId, null | PageId>();

  private walkPages(pages: Page[], parentID: null | PageId) {
    for (const page of pages) {
      this.#parents.set(page.id, parentID);
      this.#pages.set(page.id, page);
      this.walkPages(page.childs, page.id);
    }
  }

  private isDescendantPrivate(id: PageId, parentID: PageId): boolean {
    let pageId: null | PageId | undefined = id;
    while (pageId) {
      if (this.#parents.get(pageId) === parentID) {
        return true;
      }

      pageId = this.#parents.get(pageId);
    }

    return false;
  }

  private loadTree$(): Observable<boolean> {
    return new Observable<boolean>((observer: Observer<boolean>) => {
      // #pages.size is the real "have we walked the tree yet" signal - a previous #pagesJson
      // field served this purpose but was initialized to [] (truthy), so its `if (!this.#pagesJson)`
      // guard could never actually fire: the tree was never walked, #parents stayed permanently
      // empty, and isDescendant$()/isDescendantPrivate() silently always returned false.
      if (this.#pages.size === 0) {
        this.walkPages(pagesJson, null);
      }

      observer.next(true);
      observer.complete();
    });
  }

  public isDescendant$(id: PageId, parentID: PageId): Observable<boolean> {
    return this.loadTree$().pipe(
      map(() => {
        if (id === parentID) {
          return true;
        }

        return this.isDescendantPrivate(id, parentID);
      }),
    );
  }
}
