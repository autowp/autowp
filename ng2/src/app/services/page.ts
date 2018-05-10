import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import * as _ from 'lodash';
import { Observable } from 'rxjs';

type pageCallbackType = () => void;

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
  private pages: Map<number, any> = new Map<number, any>();
  private current = 0;
  private args: any = {};
  private promises: Map<number, any> = new Map<number, any>();

  private handlers: { [key: string]: pageCallbackType[] } = {
    currentChanged: []
  };

  private pagesJson: Page[];

  constructor(private http: HttpClient) {
    this.pagesJson = require('./pages.json');
  }

  public setCurrent(id: number, newArgs: any) {
    if (this.current !== id || !_.isEqual(this.args, newArgs)) {
      this.current = id;
      this.args = newArgs;
      this.trigger('currentChanged');
    }
  }

  public getCurrent(): number {
    return this.current;
  }

  public getCurrentArgs(): any {
    return this.args;
  }

  public isActive(id: number): Promise<boolean> {
    return this.isDescendant(this.current, id);
  }

  private isDescendantPrivate(id: number, parentId: number): boolean {
    let pageId: number = id;
    while (pageId) {
      if (this.pages.get(pageId).parent_id === parentId) {
        return true;
      }

      pageId = this.pages.get(pageId).parent_id;
    }

    return false;
  }

  private loadTree(id: number): Promise<void> {
    if (this.promises.has(id)) {
      return this.promises.get(id);
    }

    const promise = new Promise<void>((resolve, reject) => {
      this.http
        .get<APIPageParentsGetResponse>('/api/page/parents', {
          params: {
            id: id.toString()
          }
        })
        .subscribe(
          response => {
            for (const page of response.items) {
              this.pages.set(page.id, page);
            }

            resolve();
          },
          response => reject(response)
        );
    });

    this.promises.set(id, promise);

    return promise;
  }

  public isDescendant(id: number, parentId: number): Promise<boolean> {
    return new Promise<boolean>((resolve, reject) => {
      this.loadTree(id).then(
        () => {
          if (id === parentId) {
            resolve(true);
            return;
          }

          const result = this.isDescendantPrivate(id, parentId);
          resolve(result);
        },
        response => reject(response)
      );
    });
  }

  public getPath(id: number): Promise<APIPage[]> {
    return new Promise<APIPage[]>((resolve, reject) => {
      this.loadTree(id).then(
        () => {
          let pageId = id;
          const result = [];
          while (pageId) {
            if (!this.pages.has(pageId)) {
              throw new Error('Page ' + pageId + ' not found');
            }

            result.push(this.pages.get(pageId));

            pageId = this.pages.get(pageId).parent_id;
          }

          resolve(result.reverse());
        },
        response => reject(response)
      );
    });
  }

  public bind(event: string, handler: pageCallbackType) {
    this.handlers[event].push(handler);
  }

  public unbind(event: string, handler: pageCallbackType) {
    const index = this.handlers[event].indexOf(handler);
    if (index !== -1) {
      this.handlers[event].splice(index, 1);
    }
  }

  public trigger(event: string) {
    for (const handler of this.handlers[event]) {
      handler();
    }
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

  public getMenu(parentId) {
    const page = this.findPage(parentId, this.pagesJson);

    return page.childs;
  }
}
