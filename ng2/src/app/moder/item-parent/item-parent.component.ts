import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIItemParentLanguageGetResponse } from '../../services/api.service';
import { ContentLanguageService } from '../../services/content-language';
import { ItemService, APIItem } from '../../services/item';
import { TranslateService } from '@ngx-translate/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription, combineLatest, Observable, empty, forkJoin } from 'rxjs';
import { APIItemParent } from '../../services/item-parent';
import { PageEnvService } from '../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  catchError
} from 'rxjs/operators';

// return Acl.isAllowed('car', 'move', 'unauthorized');

@Component({
  selector: 'app-moder-item-parent',
  templateUrl: './item-parent.component.html'
})
@Injectable()
export class ModerItemParentComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public item: APIItem;
  public parent: APIItem;
  public itemParent: any;
  public languages: any[] = [];
  public typeOptions = [
    {
      value: 0,
      name: 'catalogue/stock-model'
    },
    {
      value: 1,
      name: 'catalogue/related'
    },
    {
      value: 2,
      name: 'catalogue/sport'
    },
    {
      value: 3,
      name: 'catalogue/design'
    }
  ];

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private ContentLanguage: ContentLanguageService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          return combineLatest(
            this.http.get<APIItemParent>(
              '/api/item-parent/' + params.item_id + '/' + params.parent_id
            ),
            this.itemService.getItem(params.item_id, {
              fields: ['name_text', 'name_html'].join(',')
            }),
            this.itemService.getItem(params.parent_id, {
              fields: ['name_text', 'name_html'].join(',')
            }),
            this.ContentLanguage.getList(),
            this.http.get<APIItemParentLanguageGetResponse>(
              '/api/item-parent/' +
                params.item_id +
                '/' +
                params.parent_id +
                '/language'
            )
          );
        })
      )
      .subscribe(responses => {
        this.itemParent = responses[0];
        this.item = responses[1];
        this.parent = responses[2];

        for (const language of responses[3]) {
          this.languages.push({
            language: language,
            name: null
          });
        }

        for (const languageData of responses[4].items) {
          for (const item of this.languages) {
            if (item.language === languageData.language) {
              item.name = languageData.name;
            }
          }
        }

        this.translate
          .get('item/type/' + this.item.item_type_id + '/name')
          .subscribe((translation: string) => {
            this.pageEnv.set({
              layout: {
                isAdminPage: true,
                needRight: false
              },
              name: 'page/78/name',
              pageId: 78,
              args: {
                CAR_ID: this.item.id + '',
                CAR_NAME: translation + ': ' + this.item.name_text
              }
            });
          });
      });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public reloadItemParent() {
    this.http
      .get(
        '/api/item-parent/' +
          this.itemParent.item_id +
          '/' +
          this.itemParent.parent_id
      )
      .subscribe(response => {
        this.itemParent = response;
      });
  }

  public save() {
    const promises: Observable<void>[] = [
      this.http.put<void>(
        '/api/item-parent/' +
          this.itemParent.item_id +
          '/' +
          this.itemParent.parent_id,
        {
          catname: this.itemParent.catname,
          type_id: this.itemParent.type_id
        }
      )
    ];

    for (const language of this.languages) {
      language.invalidParams = null;
      promises.push(
        this.http
          .put<void>(
            '/api/item-parent/' +
              this.itemParent.item_id +
              '/' +
              this.itemParent.parent_id +
              '/language/' +
              language.language,
            {
              name: language.name
            }
          )
          .pipe(
            catchError(response => {
              language.invalidParams = response.error.invalid_params;
              return empty();
            })
          )
      );
    }

    forkJoin(...promises).subscribe(() => {
      this.reloadItemParent();
    });
  }
}
