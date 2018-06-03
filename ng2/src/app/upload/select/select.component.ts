import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { ItemService, APIItem, APIItemsGetResponse } from '../../services/item';
import { chunk } from '../../chunk';
import Notify from '../../notify';
import {
  Subscription,
  Observable,
  empty,
  forkJoin,
  of,
  BehaviorSubject,
  combineLatest
} from 'rxjs';
import { ActivatedRoute, Router, Params } from '@angular/router';
import { ItemParentService, APIItemParent } from '../../services/item-parent';
import { PageEnvService } from '../../services/page-env.service';
import {
  distinctUntilChanged,
  switchMap,
  map,
  catchError,
  tap,
  debounceTime
} from 'rxjs/operators';

@Component({
  selector: 'app-upload-select',
  templateUrl: './select.component.html'
})
@Injectable()
export class UploadSelectComponent implements OnInit {
  public brand: {
    item: APIItem;
    vehicles: APIItem[];
    engines: APIItem[];
    concepts: APIItem[];
  };
  public brands: APIItem[][];
  public paginator: APIPaginator;
  public search = '';
  public search$ = new BehaviorSubject<string>('');
  public loading = 0;
  public conceptsOpen = false;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  public onSearchInput() {
    this.search$.next(this.search);
  }

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

    combineLatest(
      this.search$.pipe(
        map(value => value.trim()),
        distinctUntilChanged(),
        debounceTime(50)
      ),
      this.route.queryParams,
      (search: string, query: Params) => ({
        search,
        query
      })
    )
      .pipe(
        distinctUntilChanged(),
        tap(() => {
          this.loading = 1;
          this.brand = null;
        }),
        switchMap(params => {
          const brandId = parseInt(params.query.brand_id, 10);
          const page = parseInt(params.query.page, 10);

          return forkJoin(
            brandId ? this.brandObservable(brandId) : of(null),
            brandId ? of(null) : this.brandsObservable(page, params.search)
          ).pipe(
            map(data => {
              return {
                brand: data[0],
                brands: data[1]
              };
            })
          );
        }),
        tap(() => (this.loading = 0))
      )
      .subscribe(data => {
        console.log('total', data);
        if (data.brands) {
          this.brands = chunk(data.brands.items, 6);
          this.paginator = data.brands.paginator;
        }
        if (data.brand) {
          this.brand = data.brand;
        }
      });
  }

  private brandsObservable(
    page: number,
    search: string
  ): Observable<APIItemsGetResponse> {
    return this.itemService
      .getItems({
        type_id: 5,
        order: 'name',
        limit: 500,
        fields: 'name_only',
        name: search ? '%' + search + '%' : null,
        page: page
      })
      .pipe(
        catchError((err, caught) => {
          if (err.status !== -1) {
            Notify.response(err);
          }
          return empty();
        })
      );
  }

  private brandObservable(
    brandId: number
  ): Observable<{
    item: APIItem;
    vehicles: APIItemParent[];
    engines: APIItemParent[];
    concepts: APIItemParent[];
  }> {
    return this.itemService.getItem(brandId).pipe(
      catchError((err, caught) => {
        this.router.navigate(['/error-404']);
        return empty();
      }),
      switchMap((item, sindex) => {
        return this.brandItemsObservable(item);
      })
    );
  }

  private brandItemsObservable(item: APIItem) {
    return forkJoin(
      this.itemParentService
        .getItems({
          limit: 500,
          fields: 'item.name_html,item.childs_count',
          parent_id: item.id,
          exclude_concept: true,
          order: 'name',
          item_type_id: 1
        })
        .pipe(
          map(response => response.items),
          catchError((err, caught) => {
            Notify.response(err);
            return empty();
          })
        ),
      this.itemParentService
        .getItems({
          limit: 500,
          fields: 'item.name_html,item.childs_count',
          parent_id: item.id,
          exclude_concept: true,
          order: 'name',
          item_type_id: 2
        })
        .pipe(
          map(response => response.items),
          catchError((err, caught) => {
            Notify.response(err);
            return empty();
          })
        ),
      this.itemParentService
        .getItems({
          limit: 500,
          fields: 'item.name_html,item.childs_count',
          parent_id: item.id,
          concept: true,
          order: 'name'
        })
        .pipe(
          map(response => response.items),
          catchError((err, caught) => {
            Notify.response(err);
            return empty();
          })
        )
    ).pipe(
      map(data => {
        return {
          item: item,
          vehicles: data[0],
          engines: data[1],
          concepts: data[2]
        };
      })
    );
  }
}
