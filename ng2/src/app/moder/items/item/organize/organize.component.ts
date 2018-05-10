import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { ItemService, APIItem } from '../../../../services/item';
import { ACLService } from '../../../../services/acl.service';
import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import {
  ItemParentService,
  APIItemParent
} from '../../../../services/item-parent';

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

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private acl: ACLService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService
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
          (item: APIItem) => {
            this.item = item;
            this.newItem = Object.assign({}, this.item);
            this.translate
              .get('item/type/' + this.item.item_type_id + '/name')
              .subscribe(translation => {
                /*$scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/78/name',
                    pageId: 78,
                    args: {
                        CAR_ID: this.item.id,
                        CAR_NAME: translation + ': ' + this.item.name_text
                    }
                });*/
              });
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
      .subscribe(
        response => {
          const location = response.headers.get('Location');

          this.loading++;
          this.itemService.getItemByLocation(location).subscribe(item => {
            const promises: Promise<any>[] = [];

            const vehicleTypeIds: number[] = [];
            for (const vehicle_type of this.newItem.vehicle_type) {
              vehicleTypeIds.push(vehicle_type.id);
            }
            promises.push(
              this.itemService.setItemVehicleTypes(item.id, vehicleTypeIds)
            );

            promises.push(
              this.http
                .post<void>('/api/item-parent', {
                  parent_id: this.item.id,
                  item_id: item.id
                })
                .toPromise()
            );

            for (const child of this.childs) {
              if (child.selected) {
                promises.push(
                  this.http
                    .put<void>(
                      '/api/item-parent/' +
                        child.item_id +
                        '/' +
                        child.parent_id,
                      {
                        parent_id: item.id
                      }
                    )
                    .toPromise()
                );
              }
            }

            this.loading++;
            Promise.all(promises).then(results => {
              this.router.navigate(['/moder/items/item', this.item.id], {
                queryParams: {
                  tab: 'catalogue'
                }
              });
              this.loading--;
            });

            this.loading--;
          });

          this.loading--;
        },
        response => {
          this.invalidParams = response.error.invalid_params;
          this.loading--;
        }
      );
  }
}
