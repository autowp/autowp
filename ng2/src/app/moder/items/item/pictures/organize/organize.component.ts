import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import {
  APIPictureItem,
  PictureItemService
} from '../../../../../services/picture-item';
import { APIItem, ItemService } from '../../../../../services/item';
import { PageEnvService } from '../../../../../services/page-env.service';
import Notify from '../../../../../notify';
import { APIItemVehicleTypeGetResponse } from '../../../../../services/api.service';

// Acl.isAllowed('car', 'move', 'unauthorized');

interface APIPictureItemInOrganizePictures extends APIPictureItem {
  selected?: boolean;
}

@Component({
  selector: 'app-moder-items-item-pictures-organize',
  templateUrl: './organize.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class ModerItemsItemPicturesOrganizeComponent
  implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public item: APIItem;
  public newItem: any = null;
  public hasSelectedPicture = false;
  public loading = 0;
  public pictures: APIPictureItemInOrganizePictures[];
  public invalidParams: any;
  public vehicleTypeIDs: number[] = [];

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private pictureItemService: PictureItemService,
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
            this.newItem.is_group = false;
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

      this.pictureItemService
        .getItems({
          item_id: params.id,
          limit: 500,
          fields: 'picture.thumb_medium,picture.name_text',
          order: 'status'
        })
        .subscribe(
          response => {
            this.pictures = response.items;
          },
          response => {
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public pictureSelected(picture: APIPictureItemInOrganizePictures) {
    picture.selected = !picture.selected;
    let result = false;
    for (const ipicture of this.pictures) {
      if (ipicture.selected) {
        result = true;
      }
    }

    this.hasSelectedPicture = result;
  }

  public submit() {
    this.loading++;

    const data = {
      item_type_id: this.newItem.item_type_id,
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

    const promises: Promise<any>[] = [
      this.http
        .post<void>('/api/item', data, {
          observe: 'response'
        })
        .toPromise()
    ];

    if (!this.item.is_group) {
      promises.push(
        this.http
          .put<void>('/api/item/' + this.item.id, {
            is_group: true
          })
          .toPromise()
      );
    }

    Promise.all(promises).then(
      (responses: any) => {
        const location = responses[0].headers('Location');

        this.loading++;
        this.itemService.getItemByLocation(location, {}).subscribe(
          (response: APIItem) => {
            const subpromises: Promise<any>[] = [];

            subpromises.push(
              this.itemService.setItemVehicleTypes(
                response.id,
                this.vehicleTypeIDs
              )
            );

            subpromises.push(
              this.http
                .post<void>('/api/item-parent', {
                  parent_id: this.item.id,
                  item_id: response.id
                })
                .toPromise()
            );

            for (const picture of this.pictures) {
              if (picture.selected) {
                subpromises.push(
                  this.http
                    .put<void>(
                      '/api/picture-item/' +
                        picture.picture_id +
                        '/' +
                        picture.item_id +
                        '/' +
                        picture.type,
                      {
                        item_id: response.id
                      }
                    )
                    .toPromise()
                    .then(
                      () => {},
                      subresponse => {
                        Notify.response(subresponse);
                      }
                    )
                );
              }
            }

            this.loading++;
            Promise.all(subpromises).then(results => {
              this.router.navigate(['/moder/items/item', response.id], {
                queryParams: {
                  tab: 'pictures'
                }
              });
              this.loading--;
            });

            this.loading--;
          },
          subresponse => {
            Notify.response(subresponse);
          }
        );

        this.loading--;
      },
      response => {
        this.invalidParams = response.error.invalid_params;
        this.loading--;
      }
    );
  }
}