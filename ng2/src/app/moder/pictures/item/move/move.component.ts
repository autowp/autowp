import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../../../services/api.service';
import { Subscription, of, combineLatest, BehaviorSubject } from 'rxjs';
import { PictureItemService } from '../../../../services/picture-item';
import { ItemService, APIItem } from '../../../../services/item';
import { chunk } from '../../../../chunk';
import { Router, ActivatedRoute } from '@angular/router';
import {
  ItemParentService,
  APIItemParent
} from '../../../../services/item-parent';
import { PageEnvService } from '../../../../services/page-env.service';
import {
  switchMap,
  distinctUntilChanged,
  debounceTime,
  tap
} from 'rxjs/operators';
import { PictureService } from '../../../../services/picture';
import { TranslateService } from '@ngx-translate/core';

// Acl.inheritsRole( 'moder', 'unauthorized' );

export interface PictureItemMoveSelection {
  itemId: number;
  perspectiveId: number;
  type: number;
}

@Component({
  selector: 'app-moder-pictures-item-move',
  templateUrl: './move.component.html'
})
@Injectable()
export class ModerPicturesItemMoveComponent implements OnInit, OnDestroy {
  private sub: Subscription;
  private id: number;
  public concepts_expanded = false;
  public srcItemID: number;
  public srcType: number;
  public show_museums: boolean;
  public show_factories: boolean;
  public show_persons: boolean;
  public show_authors: boolean;
  public museums_paginator: APIPaginator;
  public factories_paginator: APIPaginator;
  public brands_paginator: APIPaginator;
  public authors_paginator: APIPaginator;
  public brand_id: number;
  public museums: APIItem[] = [];
  public factories: APIItem[] = [];
  public vehicles: APIItemParent[] = [];
  public engines: APIItemParent[] = [];
  public authors: APIItem[] = [];
  public persons_paginator: APIPaginator;
  public persons: APIItem[] = [];
  public concepts: APIItemParent[] = [];
  public brands: APIItem[][] = [];

  public searchBrand: string;
  public searchBrand$ = new BehaviorSubject<string>('');
  public searchPerson: string;
  public searchPerson$ = new BehaviorSubject<string>('');
  public searchAuthor: string;
  public searchAuthor$ = new BehaviorSubject<string>('');

  constructor(
    private pictureItemService: PictureItemService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService,
    private translate: TranslateService,
    private pictureService: PictureService
  ) {}

  ngOnInit(): void {
    this.sub = combineLatest(
      this.route.params.pipe(
        tap(params => {
          this.id = params.id;
        }),
        switchMap(params =>
          combineLatest(
            this.pictureService.getPicture(params.id),
            this.translate.get('moder/picture/picture-n-%s'),
            (picture, translation) => ({ picture, translation })
          )
        ),
        tap(data =>
          this.pageEnv.set({
            layout: {
              isAdminPage: true,
              needRight: false
            },
            name: 'page/149/name',
            pageId: 149,
            args: {
              PICTURE_ID: data.picture.id + '',
              PICTURE_NAME: sprintf(data.translation, data.picture.id)
            }
          })
        )
      ),
      this.route.queryParams.pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.srcItemID = params.src_item_id;
          this.srcType = params.src_type;

          this.show_museums = params.show_museums;
          this.show_factories = params.show_factories;
          this.show_persons = params.show_persons;
          this.show_authors = params.show_authors;
          this.brand_id = params.brand_id;

          if (this.srcType === 2) {
            this.show_authors = true;
          }

          let museums$ = of(null);
          if (this.show_museums) {
            museums$ = this.itemService
              .getItems({
                type_id: 7,
                fields: 'name_html',
                limit: 50,
                page: params.page
              })
              .pipe(
                tap(response => {
                  this.museums = response.items;
                  this.museums_paginator = response.paginator;
                })
              );
          }

          let factories$ = of(null);
          if (this.show_factories) {
            factories$ = this.itemService
              .getItems({
                type_id: 6,
                fields: 'name_html',
                limit: 50,
                page: params.page
              })
              .pipe(
                tap(response => {
                  this.factories = response.items;
                  this.factories_paginator = response.paginator;
                })
              );
          }

          let persons$ = of(null);
          if (this.show_persons) {
            persons$ = this.searchPerson$.pipe(
              distinctUntilChanged(),
              debounceTime(30),
              switchMap(search =>
                this.itemService.getItems({
                  type_id: 8,
                  fields: 'name_html',
                  limit: 50,
                  name: search ? '%' + search + '%' : null,
                  page: params.page
                })
              ),
              tap(response => {
                this.persons = response.items;
                this.persons_paginator = response.paginator;
              })
            );
          }

          let authors$ = of(null);
          if (this.show_authors) {
            authors$ = this.searchAuthor$.pipe(
              distinctUntilChanged(),
              debounceTime(30),
              switchMap(search =>
                this.itemService.getItems({
                  type_id: 8,
                  fields: 'name_html',
                  limit: 50,
                  name: search ? '%' + search + '%' : null,
                  page: params.page
                })
              ),
              tap(response => {
                this.authors = response.items;
                this.authors_paginator = response.paginator;
              })
            );
          }

          let brandItems$ = of(null);
          let brands$ = of(null);
          if (
            !this.show_museums &&
            !this.show_factories &&
            !this.show_persons &&
            !this.show_authors
          ) {
            if (this.brand_id) {
              brandItems$ = combineLatest(
                this.itemParentService
                  .getItems({
                    item_type_id: 1,
                    parent_id: this.brand_id,
                    fields: 'item.name_html,item.childs_count',
                    limit: 500,
                    page: 1
                  })
                  .pipe(tap(response => (this.vehicles = response.items))),
                this.itemParentService
                  .getItems({
                    item_type_id: 2,
                    parent_id: this.brand_id,
                    fields: 'item.name_html,item.childs_count',
                    limit: 500,
                    page: 1
                  })
                  .pipe(tap(response => (this.engines = response.items))),

                this.itemParentService
                  .getItems({
                    item_type_id: 1,
                    concept: true,
                    ancestor_id: this.brand_id,
                    fields: 'item.name_html,item.childs_count',
                    limit: 500,
                    page: 1
                  })
                  .pipe(tap(response => (this.concepts = response.items)))
              );
            } else {
              brands$ = this.searchBrand$.pipe(
                distinctUntilChanged(),
                debounceTime(30),
                switchMap(search =>
                  this.itemService.getItems({
                    type_id: 5,
                    fields: 'name_html',
                    limit: 200,
                    name: search ? '%' + search + '%' : null,
                    page: params.page
                  })
                ),
                tap(response => {
                  this.brands = chunk<APIItem>(response.items, 6);
                  this.brands_paginator = response.paginator;
                })
              );
            }
          }

          return combineLatest(
            museums$,
            factories$,
            persons$,
            authors$,
            brandItems$,
            brands$
          );
        })
      )
    ).subscribe();
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public selectItem(selection: PictureItemMoveSelection) {
    if (this.srcItemID && this.srcType) {
      this.pictureItemService
        .changeItem(this.id, this.srcType, this.srcItemID, selection.itemId)
        .pipe(
          switchMap(() => {
            if (!selection.perspectiveId) {
              return of(null);
            }

            return this.pictureItemService.setPerspective(
              this.id,
              selection.itemId,
              this.srcType,
              selection.perspectiveId
            );
          })
        )
        .subscribe(() => {
          this.router.navigate(['/moder/pictures', this.id]);
        });
    } else {
      const data = {
        perspective_id: selection.perspectiveId ? selection.perspectiveId : null
      };

      this.pictureItemService
        .create(this.id, selection.itemId, selection.type, data)
        .subscribe(() => {
          this.router.navigate(['/moder/pictures', this.id]);
        });
    }

    return false;
  }

  public toggleConcepts() {
    this.concepts_expanded = !this.concepts_expanded;
    return false;
  }

  public doSearchBrand() {
    this.searchBrand$.next(this.searchBrand);
  }

  public doSearchPerson() {
    this.searchPerson$.next(this.searchPerson);
  }

  public doSearchAuthor() {
    this.searchAuthor$.next(this.searchAuthor);
  }
}
