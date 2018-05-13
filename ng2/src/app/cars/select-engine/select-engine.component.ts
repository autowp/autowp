import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { ItemService, APIItem } from '../../services/item';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { chunk } from '../../chunk';
import { Router, ActivatedRoute } from '@angular/router';
import { ItemParentService, APIItemParent } from '../../services/item-parent';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-cars-select-engine',
  templateUrl: './select-engine.component.html'
})
@Injectable()
export class CarsSelectEngineComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  private loadBrandsCanceler: Subscription;
  public item: APIItem;
  public loading = 0;
  public paginator: APIPaginator;
  public search: string;
  public brands: APIItem[][];
  public items: APIItemParent[];
  public brandId: number;
  public loadChildCatalogues: Function;
  public selectEngine: (engineId: number) => void;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {
    this.loadChildCatalogues = (parent: any) => {
      parent.loading = true;
      this.itemParentService
        .getItems({
          limit: 500,
          fields: 'item.name_html,item.childs_count',
          parent_id: parent.item_id,
          item_type_id: 2,
          order: 'type_auto'
        })
        .subscribe(
          response => {
            parent.item.childs = response.items;
            parent.loading = false;
          },
          response => {
            Notify.response(response);
            parent.loading = false;
          }
        );
    };

    this.selectEngine = (engineId: number) => {
      this.http
        .put<void>('/api/item/' + this.item.id, {
          engine_id: engineId
        })
        .subscribe(
          response => {
            this.router.navigate(['/cars/specifications-editor'], {
              queryParams: {
                item_id: this.item.id
              }
            });
          },
          response => {
            Notify.response(response);
          }
        );
    };
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.itemService
        .getItem(params.item_id, {
          fields: 'name_html,name_text'
        })
        .subscribe(
          item => {
            this.item = item;

            this.pageEnv.set({
              layout: {
                needRight: false
              },
              name: 'page/102/name',
              pageId: 102,
              args: {
                CAR_NAME: this.item.name_text
              }
            });

            if (params.brand_id) {
              this.brandId = params.brand_id;
              this.itemParentService
                .getItems({
                  limit: 500,
                  fields: 'item.name_html,item.childs_count',
                  parent_id: params.brand_id,
                  item_type_id: 2,
                  page: params.page
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
              this.loadBrands();
            }
          },
          response => {
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private loadBrands() {
    this.loading++;

    if (this.loadBrandsCanceler) {
      this.loadBrandsCanceler.unsubscribe();
      this.loadBrandsCanceler = null;
    }

    this.loadBrandsCanceler = this.itemService
      .getItems({
        type_id: 5,
        order: 'name',
        limit: 500,
        fields: 'name_only',
        have_childs_of_type: 2,
        name: this.search ? '%' + this.search + '%' : null
      })
      .subscribe(
        result => {
          this.brands = chunk<APIItem>(result.items, 6);
          this.paginator = result.paginator;
          this.loading--;
        },
        response => {
          if (response.status !== -1) {
            Notify.response(response);
          }
          this.loading--;
        }
      );
  }
}
