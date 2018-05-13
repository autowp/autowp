import { Injectable } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { TranslateService } from '@ngx-translate/core';
import { Observable } from 'rxjs';

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
  public isAdminPage = false;
  public disablePageName = false;
  public pageName = '';
  public needRight = false;
  public spanRight = 0;
  public spanCenter = 0;
  public changes = Observable.create(observer => {
    observer.next('Hello');
    observer.next('World');
  });

  public constructor(
    private titleService: Title,
    private translate: TranslateService
  ) {}

  private setSidebars(right: boolean) {
    this.needRight = right;

    this.spanRight = right ? 4 : 0;
    this.spanCenter = 12 - this.spanRight;
  }

  public set(data: PageEnv) {
    this.setSidebars(data.layout.needRight);
    this.isAdminPage = data.layout.isAdminPage;
    this.disablePageName = !!data.disablePageName;

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
          this.pageName = name;
          this.titleService.setTitle(title ? title : name);
        },
        () => {
          this.pageName = nameKey;
          this.titleService.setTitle(titleKey);
        }
      );
    } else {
      this.pageName = null;
      this.titleService.setTitle(data.title ? data.title : '');
    }
  }
}
