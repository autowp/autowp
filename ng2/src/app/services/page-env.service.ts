import { Injectable } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { TranslateService } from '@ngx-translate/core';
import { Observable, BehaviorSubject, of } from 'rxjs';
import { PageService } from './page';
import { switchMap } from 'rxjs/operators';

export interface LayoutParams {
  name: string;
  isAdminPage: boolean;
  sidebar: boolean;
  disablePageName: boolean;
}

export interface PageEnv {
  pageId?: number;
  title?: string;
  name?: string;
  layout: {
    needRight: boolean;
    isAdminPage?: boolean;
  };
  disablePageName?: boolean;
  args?: { [key: string]: string };
}

@Injectable()
export class PageEnvService {
  public pageEnv$ = new BehaviorSubject<PageEnv>(null);
  public layoutParams$ = new BehaviorSubject<LayoutParams>({
    name: '',
    isAdminPage: false,
    sidebar: false,
    disablePageName: false
  });

  public constructor(
    private pageService: PageService,
    private titleService: Title,
    private translate: TranslateService
  ) {
    this.pageEnv$.subscribe(data => {
      if (data && data.pageId) {
        const args = data.args ? data.args : {};


        let nameKey: string;
        let titleKey: string;
        if (data.name) {
          nameKey = data.name;
          titleKey = data.title ? data.title : data.name;
        } else {
          nameKey = 'page/' + data.pageId + '/name';
          titleKey = 'page/' + data.pageId + '/title';
        }

        this.translate.get([nameKey, titleKey]).subscribe(
          (translations: string[]) => {
            const name = this.replaceArgs(translations[nameKey], args, false);
            const title = this.replaceArgs(translations[titleKey], args, false);

            this.titleService.setTitle(title ? title : name);
            this.layoutParams$.next({
              name: name,
              isAdminPage: data.layout.isAdminPage,
              sidebar: data.layout.needRight,
              disablePageName: data.disablePageName
            });
          },
          () => {
            this.titleService.setTitle(titleKey);
            this.layoutParams$.next({
              name: nameKey,
              isAdminPage: data.layout.isAdminPage,
              sidebar: data.layout.needRight,
              disablePageName: data.disablePageName
            });
          }
        );
      } else {
        this.titleService.setTitle(data && data.title ? data.title : '');
        this.layoutParams$.next({
          name: '',
          isAdminPage: data ? data.layout.isAdminPage : false,
          sidebar: data ? data.layout.needRight : false,
          disablePageName: data ? data.disablePageName : false
        });
      }
    });
  }

  public set(data: PageEnv) {
    this.pageEnv$.next(data);
  }

  public isActive(id: number): Observable<boolean> {
    return this.pageEnv$.pipe(
      switchMap(data => {
        if (!data || !data.pageId) {
          return of(false);
        }
        return this.pageService.isDescendant(data.pageId, id);
      })
    );
  }

  public replaceArgs(str: string, args: { [key: string]: string }, url: boolean): string {
    const preparedArgs: { [key: string]: string } = {};
    for (const key in args) {
      if (args.hasOwnProperty(key)) {
        const value = args[key];
        preparedArgs['%' + key + '%'] = url ? encodeURIComponent(value) : value;
      }
    }

    for (const key in preparedArgs) {
      if (preparedArgs.hasOwnProperty(key)) {
        str = str.replace(key, preparedArgs[key]);
      }
    }

    return str;
  }
}
