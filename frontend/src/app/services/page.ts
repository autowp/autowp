import {Service} from '@angular/core';

import type {PageId} from './page-id';

import pagesJson from './pages';

export interface Page {
  childs: Page[];
  id: PageId;
}

const parents = new Map<PageId, null | PageId>();
function walkPages(pages: Page[], parentID: null | PageId): void {
  for (const page of pages) {
    parents.set(page.id, parentID);
    walkPages(page.childs, page.id);
  }
}
walkPages(pagesJson, null);

@Service()
export class PageService {
  public isDescendant(id: PageId, parentID: PageId): boolean {
    let pageId: null | PageId | undefined = id;
    while (pageId) {
      if (pageId === parentID) {
        return true;
      }
      pageId = parents.get(pageId);
    }
    return false;
  }
}
