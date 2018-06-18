import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

export interface APIPageLinearized extends APIPage {
  level: number;
  moveUp: boolean;
  moveDown: boolean;
}

export interface APIPage {
  id: number;
  childs: APIPage[];
  name: string;
  breadcrumbs: string;
  is_group_node: boolean;
  parent_id: number;
  title: string;
  url: string;
  registered_only: boolean;
  guest_only: boolean;
  class: string;
}

export interface APIPagesGetResponse {
  items: APIPage[];
}

export interface APIPageParentsGetResponse {
  items: APIPage[];
}

export interface Page {
  id: number;
  is_group_node: boolean;
  childs: Page[];
  url: string;
  name: string;
  title: string;
  registered_only: boolean;
  guest_only: boolean;
  class: string;
  icon: string;
  routerLink: string[];
}

@Injectable()
export class PageService {
  private pages = new Map<number, Page>();
  private parents = new Map<number, number>();

  private pagesJson: Page[];

  constructor(private http: HttpClient) {}

  private walkPages(pages: Page[], parentID: number) {
    for (const page of pages) {
      this.parents.set(page.id, parentID);
      this.pages.set(page.id, page);
      this.walkPages(page.childs, page.id);
    }
  }

  private isDescendantPrivate(id: number, parentID: number): boolean {
    let pageId: number = id;
    while (pageId) {
      if (this.parents.get(pageId) === parentID) {
        return true;
      }

      pageId = this.parents.get(pageId);
    }

    return false;
  }

  private loadTree(): Observable<void> {
    return Observable.create(observer => {
      if (!this.pagesJson) {
        this.pagesJson = require('./pages.json');
        this.walkPages(this.pagesJson, null);
      }

      observer.next(true);
      observer.complete();
    });
  }

  public isDescendant(id: number, parentID: number): Observable<boolean> {
    return this.loadTree().pipe(
      map(() => {
        if (id === parentID) {
          return true;
        }

        return this.isDescendantPrivate(id, parentID);
      })
    );
  }

  public getPath(id: number): Observable<Page[]> {
    return this.loadTree().pipe(
      map(() => {
        let pageID = id;
        const result: Page[] = [];
        while (pageID) {
          if (!this.pages.has(pageID)) {
            throw new Error('Page ' + pageID + ' not found');
          }

          result.push(this.pages.get(pageID));

          pageID = this.parents.get(pageID);
        }

        return result.reverse();
      })
    );
  }

  public getPages(): Observable<APIPagesGetResponse> {
    return this.http.get<APIPagesGetResponse>('/api/page');
  }

  public toPlainArray(pages: APIPage[], level: number): APIPageLinearized[] {
    const result: APIPageLinearized[] = [];
    for (let i = 0; i < pages.length; i++) {
      const page = pages[i];
      const mPage: APIPageLinearized = {
        id: page.id,
        name: page.name,
        title: page.title,
        url: page.url,
        breadcrumbs: page.breadcrumbs,
        is_group_node: page.is_group_node,
        childs: page.childs,
        level: level,
        moveUp: i > 0,
        moveDown: i < pages.length - 1,
        parent_id: null,
        registered_only: page.registered_only,
        guest_only: page.guest_only,
        class: page['class']
      };
      result.push(mPage);
      for (const child of this.toPlainArray(page.childs, level + 1)) {
        result.push(child);
      }
    }
    return result;
  }

  public getPage(id: number): Observable<APIPage> {
    return this.http.get<APIPage>('/api/page/' + id);
  }

  private findPage(id: number, pages: Page[]): Page {
    for (const page of pages) {
      if (page.id === id) {
        return page;
      }

      if (page.childs) {
        const result = this.findPage(id, page.childs);
        if (result) {
          return result;
        }
      }
    }

    return null;
  }

  public getMenu(parentId): Observable<Page[]> {
    return this.loadTree().pipe(
      map(() => {
        const page = this.findPage(parentId, this.pagesJson);
        return page.childs;
      })
    );
  }
}
