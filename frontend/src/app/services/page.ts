import type {Observer} from 'rxjs';

import {Service} from '@angular/core';
import {map, Observable} from 'rxjs';

import pagesJson from './pages.json';

export interface Page {
  childs: Page[];
  id: number;
}

@Service()
export class PageService {
  readonly #pages = new Map<number, Page>();
  readonly #parents = new Map<number, null | number>();

  private walkPages(pages: Page[], parentID: null | number) {
    for (const page of pages) {
      this.#parents.set(page.id, parentID);
      this.#pages.set(page.id, page);
      this.walkPages(page.childs, page.id);
    }
  }

  private isDescendantPrivate(id: number, parentID: number): boolean {
    let pageId: null | number | undefined = id;
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

  public isDescendant$(id: number, parentID: number): Observable<boolean> {
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
