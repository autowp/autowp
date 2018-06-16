import { PageService, APIPage } from '../../services/page';
import { TranslateService } from '@ngx-translate/core';
import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';
import { Subscription } from 'rxjs';

function replaceArgs(str: string, args: { [key: string]: string }): string {
  for (const key in args) {
    if (args.hasOwnProperty(key)) {
      const value = args[key];
      str = str.replace(key, value);
    }
  }
  return str;
}

interface APIPageInBreadcrumbs extends APIPage {
  name_translated?: string;
}

@Component({
  selector: 'app-breadcrumbs',
  templateUrl: './breadcrumbs.component.html'
})
@Injectable()
export class BreadcrumbsComponent implements OnInit, OnDestroy {
  private sub: Subscription;
  public items: APIPageInBreadcrumbs[] = [];

  constructor(
    private pageService: PageService,
    private translate: TranslateService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.sub = this.pageEnv.pageID$.subscribe(pageID => {
      this.load(pageID);
    });
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  private load(pageID: number) {
    this.items = [];
    if (pageID) {
      const args = this.pageService.getCurrentArgs();
      this.pageService.getPath(pageID).subscribe(path => {
        this.items = [];
        for (const item of path) {
          const bItem: APIPageInBreadcrumbs = item;
          bItem.url = replaceArgs(bItem.url, args);
          this.items.push(bItem);
          this.translate.get('page/' + bItem.id + '/breadcrumbs').subscribe(
            (translation: string) => {
              bItem.name_translated = replaceArgs(translation, args);
            },
            () => {
              this.translate
                .get('page/' + bItem.id + '/name')
                .subscribe((translation: string) => {
                  bItem.name_translated = replaceArgs(translation, args);
                });
            }
          );
        }
      });
    }
  }
}
