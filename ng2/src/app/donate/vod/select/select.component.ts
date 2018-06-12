import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../../services/api.service';
import {
  APIItem,
  ItemService,
  APIItemsGetResponse
} from '../../../services/item';
import Notify from '../../../notify';
import { chunk } from '../../../chunk';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription, combineLatest, of } from 'rxjs';
import {
  ItemParentService,
  APIItemParent,
  APIItemParentGetResponse
} from '../../../services/item-parent';
import { PageEnvService } from '../../../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  finalize
} from 'rxjs/operators';

@Component({
  selector: 'app-donate-vod-select',
  templateUrl: './select.component.html'
})
@Injectable()
export class DonateVodSelectComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public page: number;
  public brands: APIItem[][];
  public paginator: APIPaginator;
  public brand: APIItem;
  public vehicles: APIItemParent[];
  public vehiclesPaginator: APIPaginator;
  public concepts: APIItemParent[];
  private date: string;
  private anonymous: boolean;
  public loading = 0;
  public conceptsExpanded = false;

  constructor(
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  public selectItem(itemID: number) {
    this.router.navigate(['/donate/vod'], {
      queryParams: {
        item_id: itemID,
        date: this.date,
        anonymous: this.anonymous ? 1 : null
      }
    });
  }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/196/name',
          pageId: 196
        }),
      0
    );

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.page = params.page || 1;
          this.date = params.date;
          this.anonymous = !!params.anonymous;
          const brandID = params.brand_id;

          this.loading++;
          this.loading++;
          return combineLatest(
            (brandID
              ? of(null as APIItemsGetResponse)
              : this.itemService.getItems({
                  type_id: 5,
                  limit: 500,
                  fields: 'name_only',
                  page: this.page
                })
            ).pipe(
              finalize(() => {
                this.loading--;
              })
            ),
            (brandID
              ? this.itemService.getItem(brandID).pipe(
                  switchMap(
                    brand =>
                      combineLatest(
                        this.itemParentService.getItems({
                          item_type_id: 1,
                          parent_id: brand.id,
                          fields:
                            'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                          limit: 500,
                          page: 1
                        }),
                        this.itemParentService.getItems({
                          item_type_id: 1,
                          concept: true,
                          ancestor_id: brand.id,
                          fields:
                            'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                          limit: 500,
                          page: 1
                        }),
                        (vehicles, concepts) => ({ vehicles, concepts })
                      ),
                    (brand, data) => ({
                      brand: brand,
                      vehicles: data.vehicles,
                      concepts: data.concepts
                    })
                  )
                )
              : of(null as {
                  brand: APIItem;
                  vehicles: APIItemParentGetResponse;
                  concepts: APIItemParentGetResponse;
                })
            ).pipe(
              finalize(() => {
                this.loading--;
              })
            ),
            (items, brand) => ({ items, brand })
          );
        })
      )
      .subscribe(data => {
        if (data.brand) {
          this.brand = data.brand.brand;
          this.vehicles = data.brand.vehicles.items;
          this.vehiclesPaginator = data.brand.vehicles.paginator;
          this.concepts = data.brand.concepts.items;
          this.brands = [];
          this.paginator = null;
        } else {
          this.brand = null;
          this.vehicles = [];
          this.vehiclesPaginator = null;
          this.concepts = [];
          this.brands = chunk(data.items.items, 6);
          this.paginator = data.items.paginator;
        }
      });
  }

  public toggleConcepts() {
    this.conceptsExpanded = !this.conceptsExpanded;
    return false;
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
