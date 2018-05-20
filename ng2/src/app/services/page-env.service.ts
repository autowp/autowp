import { Injectable } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { TranslateService } from '@ngx-translate/core';
import { Observable, BehaviorSubject } from 'rxjs';

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

function replaceArgs(str: string, args: any): string {
  for (const key in args) {
    if (args.hasOwnProperty(key)) {
      str = str.replace(key, args[key]);
    }
  }
  return str;
}

@Injectable()
export class PageEnvService {
  public pageID$ = new BehaviorSubject<number>(0);
  public layoutParams$ = new BehaviorSubject<LayoutParams>({
    name: '',
    isAdminPage: false,
    sidebar: false,
    disablePageName: false
  });

  public constructor(
    private titleService: Title,
    private translate: TranslateService
  ) {}

  public set(data: PageEnv) {
    const args = data.args ? data.args : {};
    const preparedUrlArgs: any = {};
    const preparedNameArgs: any = {};
    for (const key in args) {
      if (args.hasOwnProperty(key)) {
        const value = args[key];
        preparedUrlArgs['%' + key + '%'] = encodeURIComponent(value);
        preparedNameArgs['%' + key + '%'] = value;
      }
    }

    this.pageID$.next(data.pageId);
    // PageService.setCurrent(data.pageId, preparedNameArgs);

    if (data.pageId) {
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
          const name = replaceArgs(translations[nameKey], preparedNameArgs);
          const title = replaceArgs(translations[titleKey], preparedNameArgs);

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
      this.titleService.setTitle(data.title ? data.title : '');
      this.layoutParams$.next({
        name: '',
        isAdminPage: data.layout.isAdminPage,
        sidebar: data.layout.needRight,
        disablePageName: data.disablePageName
      });
    }
  }
}
