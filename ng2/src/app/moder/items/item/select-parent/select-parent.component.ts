import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../../services/api.service';
import { ItemService, APIItem } from '../../../../services/item';
import { chunk } from '../../../../chunk';
import Notify from '../../../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute, Params } from '@angular/router';
import { Subscription, combineLatest } from 'rxjs';
import {
  ItemParentService,
  APIItemParent
} from '../../../../services/item-parent';
import { PageEnvService } from '../../../../services/page-env.service';

// Acl.isAllowed('car', 'edit_meta', 'unauthorized');

export interface APIItemInSelectParent extends APIItem {
  childs: APIItemParent[];
  open: boolean;
}

@Component({
  selector: 'app-moder-items-item-select-parent',
  templateUrl: './select-parent.component.html'
})
@Injectable()
export class ModerItemsItemSelectParentComponent implements OnInit, OnDestroy {
  private paramsSub: Subscription;
  public showCatalogueTab = false;
  public showBrandsTab = false;
  public showTwinsTab = false;
  public showFactoriesTab = false;
  public tab: string;
  public brand_id: number;
  public paginator: APIPaginator;
  public page: number;
  public search = '';
  public item: APIItem;
  public brands: APIItem[][];
  public items: any[];
  public categories: APIItem[];
  public factories: APIItem[];

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  public loadChildCategories(parent: APIItemInSelectParent) {
    this.itemParentService
      .getItems({
        limit: 100,
        fields: 'item.name_html,item.childs_count',
        parent_id: parent.id,
        is_group: true,
        order: 'categories_first'
      })
      .subscribe(
        response => {
          parent.childs = response.items;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public loadChildCatalogues(parent: APIItemInSelectParent) {
    this.itemParentService
      .getItems({
        limit: 100,
        fields: 'item.name_html,item.childs_count',
        parent_id: parent.id,
        is_group: true,
        order: 'type_auto'
      })
      .subscribe(
        response => {
          parent.childs = response.items;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnInit(): void {
    this.paramsSub = combineLatest(
      this.route.params,
      this.route.queryParams,
      (route: Params, query: Params) => ({
        route,
        query
      })
    ).subscribe(data => {
      this.tab = data.query.tab || 'catalogue';
      this.page = data.query.page;
      this.brand_id = data.query.brand_id;

      this.itemService
        .getItem(data.route.id, {
          fields: 'name_text'
        })
        .subscribe(
          item => {
            this.item = item;

            this.translate
              .get('item/type/' + this.item.item_type_id + '/name')
              .subscribe((translation: string) => {
                this.pageEnv.set({
                  layout: {
                    isAdminPage: true,
                    needRight: false
                  },
                  name: 'page/144/name',
                  pageId: 144,
                  args: {
                    CAR_ID: this.item.id + '',
                    CAR_NAME: translation + ': ' + this.item.name_text
                  }
                });
              });

            this.showCatalogueTab = [1, 2, 5].includes(this.item.item_type_id);
            this.showBrandsTab = [1, 2, 5].includes(this.item.item_type_id);
            this.showTwinsTab = this.item.item_type_id === 1;
            this.showFactoriesTab = [1, 2].includes(this.item.item_type_id);

            if (this.tab === 'catalogue') {
              if (this.brand_id) {
                this.itemParentService
                  .getItems({
                    limit: 100,
                    fields: 'item.name_html,item.childs_count',
                    parent_id: this.brand_id,
                    is_group: true,
                    type_id: this.item.item_type_id,
                    page: this.page
                  })
                  .subscribe(
                    response => {
                      this.items = response.items;
                      this.paginator = response.paginator;
                    },
                    response => {
                      Notify.response(response);
                    }
                  );
              } else {
                this.loadCatalogueBrands();
              }
            }

            if (this.tab === 'brands') {
              this.loadBrands();
            }

            if (this.tab === 'categories') {
              this.itemService
                .getItems({
                  type_id: 3,
                  limit: 100,
                  fields: 'name_html,childs_count',
                  page: this.page,
                  no_parent: true
                })
                .subscribe(
                  response => {
                    this.categories = response.items;
                    this.paginator = response.paginator;
                  },
                  response => {
                    Notify.response(response);
                  }
                );
            }

            if (this.tab === 'twins') {
              if (this.brand_id) {
                this.itemService
                  .getItems({
                    type_id: 4,
                    limit: 100,
                    fields: 'name_html',
                    have_common_childs_with: this.brand_id,
                    page: this.page
                  })
                  .subscribe(
                    response => {
                      this.items = response.items;
                      this.paginator = response.paginator;
                    },
                    response => {
                      Notify.response(response);
                    }
                  );
              } else {
                this.itemService
                  .getItems({
                    type_id: 5,
                    limit: 500,
                    fields: 'name_html',
                    have_childs_with_parent_of_type: 4,
                    page: this.page
                  })
                  .subscribe(
                    response => {
                      this.brands = chunk<APIItem>(response.items, 6);
                      this.paginator = response.paginator;
                    },
                    response => {
                      Notify.response(response);
                    }
                  );
              }
            }

            if (this.tab === 'factories') {
              this.itemService
                .getItems({
                  type_id: 6,
                  limit: 100,
                  fields: 'name_html',
                  page: this.page
                })
                .subscribe(
                  response => {
                    this.factories = response.items;
                    this.paginator = response.paginator;
                  },
                  response => {
                    Notify.response(response);
                  }
                );
            }
          },
          () => {
            this.router.navigate(['/error-404']);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.paramsSub.unsubscribe();
  }

  public select(parent: APIItem) {
    this.http
      .post<void>('/api/item-parent', {
        item_id: this.item.id,
        parent_id: parent.id
      })
      .subscribe(
        response => {
          this.router.navigate(['/moder/items/item', this.item.id], {
            queryParams: {
              tab: 'catalogue'
            }
          });
        },
        response => Notify.response(response)
      );

    return false;
  }

  public doSearch() {
    if (this.tab === 'brands') {
      this.loadBrands();
    }

    if (this.tab === 'catalogue') {
      if (!this.brand_id) {
        this.loadCatalogueBrands();
      }
    }
  }

  private loadCatalogueBrands() {
    this.itemService
      .getItems({
        type_id: 5,
        limit: 500,
        fields: 'name_html',
        have_childs_of_type: this.item.item_type_id,
        name: this.search ? '%' + this.search + '%' : null,
        page: this.page
      })
      .subscribe(
        response => {
          this.brands = chunk<APIItem>(response.items, 6);
          this.paginator = response.paginator;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  private loadBrands() {
    this.itemService
      .getItems({
        type_id: 5,
        limit: 500,
        fields: 'name_html',
        name: this.search ? '%' + this.search + '%' : null,
        page: this.page
      })
      .subscribe(
        response => {
          this.brands = chunk<APIItem>(response.items, 6);
          this.paginator = response.paginator;
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
