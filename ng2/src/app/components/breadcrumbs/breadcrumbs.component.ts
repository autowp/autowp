import { PageService, APIPage, Page } from '../../services/page';
import { TranslateService } from '@ngx-translate/core';
import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';
import { Subscription, of, empty } from 'rxjs';
import { tap, switchMap } from 'rxjs/operators';

interface PageInBreadcrumbs extends Page {
  name_translated?: string;
}

@Component({
  selector: 'app-breadcrumbs',
  templateUrl: './breadcrumbs.component.html'
})
@Injectable()
export class BreadcrumbsComponent implements OnInit, OnDestroy {
  private sub: Subscription;
  public items: PageInBreadcrumbs[] = [];

  constructor(
    private pageService: PageService,
    private translate: TranslateService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.sub = this.pageEnv.pageEnv$
      .pipe(
        tap(() => (this.items = [])),
        switchMap(
          data => {
            if (!data || !data.pageId) {
              return empty();
            }

            return this.pageService.getPath(data.pageId);
          },
          (data, path) => ({ args: data ? data.args : {}, path: path })
        )
      )
      .subscribe(data => {
        this.items = [];
        for (const item of data.path) {
          const bItem: PageInBreadcrumbs = item;
          if (bItem.url) {
            bItem.url = this.pageEnv.replaceArgs(bItem.url, data.args, true);
          }
          if (bItem.routerLink) {
            const replaced = [];
            for (const part of bItem.routerLink) {
              replaced.push(this.pageEnv.replaceArgs(part, data.args, true));
            }
            bItem.routerLink = replaced;
          }
          this.items.push(bItem);
          const key = 'page/' + bItem.id + '/breadcrumbs';
          this.translate.get(key).subscribe(
            (translation: string) => {
              if (translation === key) {
                this.translatePage(
                  bItem,
                  'page/' + bItem.id + '/name',
                  data.args
                );
              } else {
                bItem.name_translated = this.pageEnv.replaceArgs(
                  translation,
                  data.args,
                  false
                );
              }
            },
            () => {
              this.translatePage(
                bItem,
                'page/' + bItem.id + '/name',
                data.args
              );
            }
          );
        }
      });
  }

  translatePage(
    page: PageInBreadcrumbs,
    str: string,
    args: { [key: string]: string }
  ) {
    this.translate.get(str).subscribe((translation: string) => {
      page.name_translated = this.pageEnv.replaceArgs(translation, args, false);
    });
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }
}
