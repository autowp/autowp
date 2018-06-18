import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Subscription, BehaviorSubject, combineLatest, of } from 'rxjs';
import { Router, ActivatedRoute } from '@angular/router';
import {
  APIItem,
  ItemService,
  APIItemsGetResponse
} from '../../../../services/item';
import { APIPaginator } from '../../../../services/api.service';
import {
  APIItemParent,
  ItemParentService,
  APIItemParentGetResponse
} from '../../../../services/item-parent';
import { PageEnvService } from '../../../../services/page-env.service';
import Notify from '../../../../notify';
import { chunk } from '../../../../chunk';
import {
  debounceTime,
  distinctUntilChanged,
  map,
  switchMap,
  tap
} from 'rxjs/operators';

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
    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(
          params =>
            this.itemService.getItem(params.item_id, {
              fields: 'name_html,name_text'
            }),
          (params, item) => ({ params, item })
        ),
        tap(data => {
          this.item = data.item;
          this.brandId = data.params.brand_id;

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: 'page/102/name',
            pageId: 102,
            args: {
              CAR_NAME: data.item.name_text
            }
          });
        }),
        switchMap(data => {
          return combineLatest(
            data.params.brand_id
              ? this.itemParentService.getItems({
                  limit: 500,
                  fields: 'item.name_html,item.childs_count',
                  parent_id: data.params.brand_id,
                  item_type_id: 2,
                  page: data.params.page
                })
              : of(null as APIItemParentGetResponse),
            data.params.brand_id
              ? of(null as APIItemsGetResponse)
              : this.search$.pipe(
                  map(str => str.trim()),
                  distinctUntilChanged(),
                  debounceTime(50),
                  switchMap(search =>
                    this.itemService.getItems({
                      type_id: 5,
                      order: 'name',
                      limit: 500,
                      fields: 'name_only',
                      have_childs_of_type: 2,
                      name: search ? '%' + search + '%' : null
                    })
                  )
                ),
            (items, brands) => ({ items, brands })
          );
        })
      )
      .subscribe(
        data => {
          console.log('this.brandId', this.brandId);
          if (this.brandId) {
            this.items = data.items.items;
            this.paginator = data.items.paginator;
            this.brands = [];
          } else {
            this.items = [];
            this.brands = chunk<APIItem>(data.brands.items, 6);
            this.paginator = data.brands.paginator;
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
