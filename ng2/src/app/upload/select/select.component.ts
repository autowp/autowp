import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { ItemService, APIItem } from '../../services/item';
import { chunk } from '../../chunk';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { ItemParentService, APIItemParent } from '../../services/item-parent';
import { PageEnvService } from '../../services/page-env.service';

export interface APIItemInUploadSelect extends APIItem {
  childs?: APIItemParentInUploadSelect[];
}

export interface APIItemParentInUploadSelect extends APIItemParent {
  loading?: boolean;
  item: APIItemInUploadSelect;
}

export type UploadSelectLoadChildFunc = (
  parent: APIItemParentInUploadSelect
) => void;

@Component({
  selector: 'app-upload-select',
  templateUrl: './select.component.html'
})
@Injectable()
export class UploadSelectComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  private loadBrandsCanceler: Subscription;
  public brand: APIItem;
  public brands: APIItem[][];
  public paginator: APIPaginator;
  public vehicles: APIItemParent[] = [];
  public engines: APIItemParent[] = [];
  public loadChildCatalogues: UploadSelectLoadChildFunc;
  public search: string;
  public loading = 0;
  public concepts: APIItemParent[] = [];
  public conceptsOpen = false;
  private page: number;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/30/name',
          pageId: 30
        }),
      0
    );
    this.loadChildCatalogues = (parent: APIItemParentInUploadSelect) => {
      parent.loading = true;
      this.itemParentService
        .getItems({
          limit: 500,
          fields: 'item.name_html,item.childs_count',
          parent_id: parent.item_id,
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
    this.querySub = this.route.queryParams.subscribe(params => {
      const brandId = parseInt(params.brand_id, 10);
      this.page = params.page;
      if (brandId) {
        this.loading++;
        this.itemService.getItem(brandId).subscribe(
          (item: APIItem) => {
            this.brand = item;

            this.loading++;
            this.itemParentService
              .getItems({
                limit: 500,
                fields: 'item.name_html,item.childs_count',
                parent_id: this.brand.id,
                exclude_concept: true,
                order: 'name',
                item_type_id: 1
              })
              .subscribe(
                response => {
                  this.vehicles = response.items;
                  this.loading--;
                },
                response => {
                  Notify.response(response);
                  this.loading--;
                }
              );

            this.loading++;
            this.itemParentService
              .getItems({
                limit: 500,
                fields: 'item.name_html,item.childs_count',
                parent_id: this.brand.id,
                exclude_concept: true,
                order: 'name',
                item_type_id: 2
              })
              .subscribe(
                response => {
                  this.engines = response.items;
                  this.loading--;
                },
                response => {
                  Notify.response(response);
                  this.loading--;
                }
              );

            this.loading++;
            this.itemParentService
              .getItems({
                limit: 500,
                fields: 'item.name_html,item.childs_count',
                parent_id: this.brand.id,
                concept: true,
                order: 'name'
              })
              .subscribe(
                response => {
                  this.concepts = response.items;
                  this.loading--;
                },
                response => {
                  Notify.response(response);
                  this.loading--;
                }
              );

            this.loading--;
          },
          response => {
            this.router.navigate(['/error-404']);
            this.loading--;
          }
        );
      } else {
        this.loadBrands();
      }
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
        name: this.search ? '%' + this.search + '%' : null,
        page: this.page
      })
      .subscribe(
        result => {
          this.brands = chunk(result.items, 6);
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

  /*public toggle(item: any) {
    if (!item.expanded) {
      item.expanded = true;
    } else {
      item.expanded = false;
    }
  }*/
}
