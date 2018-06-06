import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Subscription, BehaviorSubject } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';
import { APIItem, ItemService } from '../../../../services/item';
import { APIPaginator } from '../../../../services/api.service';
import {
  APIItemParent,
  ItemParentService
} from '../../../../services/item-parent';
import { PageEnvService } from '../../../../services/page-env.service';
import Notify from '../../../../notify';
import { chunk } from '../../../../chunk';
import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';

@Component({
  selector: 'app-cars-engine-select',
  templateUrl: './select.component.html'
})
@Injectable()
export class CarsEngineSelectComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public item: APIItem;
  public loading = 0;
  public paginator: APIPaginator;
  public brands: APIItem[][];
  public items: APIItemParent[];
  public brandId: number;
  public search: string;
  public search$ = new BehaviorSubject<string>('');

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  public onInput() {
    this.search$.next(this.search);
  }

  public selectEngine(engineId: number) {
    this.http
      .put<void>('/api/item/' + this.item.id, {
        engine_id: engineId
      })
      .subscribe(
        response => {
          this.router.navigate(['/cars/specifications-editor'], {
            queryParams: {
              item_id: this.item.id,
              tab: 'engine'
            }
          });
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnInit(): void {

    this.search$.pipe(
      map(str => str.trim()),
      distinctUntilChanged(),
      debounceTime(50)
    ).subscribe(search => {
      this.loadBrands(search);
    });

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
              this.brandId = null;
              this.loadBrands('');
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

  private loadBrands(search: string) {
    this.loading++;

    this.itemService
      .getItems({
        type_id: 5,
        order: 'name',
        limit: 500,
        fields: 'name_only',
        have_childs_of_type: 2,
        name: search ? '%' + search + '%' : null
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
