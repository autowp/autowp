import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription, Observable, empty, forkJoin } from 'rxjs';
import {
  APIItemParent,
  ItemParentService
} from '../../../../../services/item-parent';
import { APIItem, ItemService } from '../../../../../services/item';
import { ACLService } from '../../../../../services/acl.service';
import { PageEnvService } from '../../../../../services/page-env.service';
import { APIItemVehicleTypeGetResponse } from '../../../../../services/api.service';
import { switchMap, catchError } from 'rxjs/operators';

// Acl.isAllowed('car', 'move', 'unauthorized');

interface APIItemParentInOrganize extends APIItemParent {
  selected?: boolean;
}

@Component({
  selector: 'app-moder-items-item-organize',
  templateUrl: './organize.component.html'
})
@Injectable()
export class ModerItemsItemOrganizeComponent implements OnInit, OnDestroy {
  private item_type_id: number;
  private routeSub: Subscription;
  private querySub: Subscription;
  public item: APIItem;
  public newItem: any = null;
  public hasSelectedChild = false;
  public loading = 0;
  public childs: APIItemParentInOrganize[];
  public invalidParams: any;
  public vehicleTypeIDs: number[] = [];

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private acl: ACLService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.itemService
        .getItem(params.id, {
          fields: [
            'name_text',
            'name',
            'is_concept',
            'name_default',
            'body',
            'subscription',
            'begin_year',
            'begin_month',
            'end_year',
            'end_month',
            'today',
            'begin_model_year',
            'end_model_year',
            'produced',
            'is_group',
            'spec_id',
            'full_name',
            'catname',
            'lat',
            'lng'
          ].join(',')
        })
        .subscribe(
          item => {
            this.item = item;
            this.newItem = Object.assign({}, this.item);
            this.translate
              .get('item/type/' + this.item.item_type_id + '/name')
              .subscribe(translation => {
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

            if (this.item.item_type_id === 1 || this.item.item_type_id === 4) {
              this.loading++;
              this.http
                .get<APIItemVehicleTypeGetResponse>('/api/item-vehicle-type', {
                  params: {
                    item_id: this.item.id.toString()
                  }
                })
                .subscribe(
                  response => {
                    const ids: number[] = [];
                    for (const row of response.items) {
                      ids.push(row.vehicle_type_id);
                    }

                    this.vehicleTypeIDs = ids;

                    this.loading--;
                  },
                  () => {
                    this.loading--;
                  }
                );
            }
          },
          () => {
            this.router.navigate(['/error-404']);
          }
        );

      this.itemParentService
        .getItems({
          parent_id: params.id,
          limit: 500,
          fields: 'item.name_html',
          order: 'type_auto'
        })
        .subscribe(
          response => {
            this.childs = response.items;
          },
          () => {}
        );
    });

    this.querySub = this.route.queryParams.subscribe(params => {
      this.item_type_id = params.item_type_id;
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }

  public childSelected() {
    let result = false;
    for (const child of this.childs) {
      if (child.selected) {
        result = true;
      }
    }

    this.hasSelectedChild = result;
  }

  public submit() {
    this.loading++;

    const data = {
      item_type_id: this.item_type_id,
      name: this.newItem.name,
      full_name: this.newItem.full_name,
      catname: this.newItem.catname,
      body: this.newItem.body,
      spec_id: this.newItem.spec_id,
      begin_model_year: this.newItem.begin_model_year,
      end_model_year: this.newItem.end_model_year,
      begin_year: this.newItem.begin_year,
      begin_month: this.newItem.begin_month,
      end_year: this.newItem.end_year,
      end_month: this.newItem.end_month,
      today: this.newItem.today,
      produced: this.newItem.produced,
      produced_exactly: this.newItem.produced_exactly,
      is_concept: this.newItem.is_concept,
      is_group: this.newItem.is_group,
      lat: this.newItem.lat,
      lng: this.newItem.lng
    };

    this.http
      .post<void>('/api/item', data, {
        observe: 'response'
      })
      .pipe(
        catchError(response => {
          this.invalidParams = response.error.invalid_params;
          this.loading--;

          return empty();
        }),
        switchMap(response =>
          this.itemService.getItemByLocation(
            response.headers.get('Location'),
            {}
          )
        ),
        switchMap(item => {
          const promises: Observable<any>[] = [
            this.itemService.setItemVehicleTypes(item.id, this.vehicleTypeIDs),
            this.http.post<void>('/api/item-parent', {
              parent_id: this.item.id,
              item_id: item.id
            })
          ];

          for (const child of this.childs) {
            if (child.selected) {
              promises.push(
                this.http.put<void>(
                  '/api/item-parent/' + child.item_id + '/' + child.parent_id,
                  {
                    parent_id: item.id
                  }
                )
              );
            }
          }

          return forkJoin(...promises);
        })
      )
      .subscribe(
        response => {
          this.loading--;
          this.router.navigate(['/moder/items/item', this.item.id], {
            queryParams: {
              tab: 'catalogue'
            }
          });
        },
        response => {
          this.invalidParams = response.error.invalid_params;
          this.loading--;
        }
      );
  }
}
