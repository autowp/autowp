import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { sprintf } from 'sprintf-js';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { SpecService } from '../../../services/spec';
import { ItemService, APIItem } from '../../../services/item';
import Notify from '../../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';

// Acl.isAllowed('car', 'add', 'unauthorized');

function toPlain(options: any[], deep: number): any[] {
  const result: any[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of toPlain(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

interface NewItem {
  produced_exactly: string;
  is_concept: any;
  spec_id: any;
  item_type_id: number;
  name: any;
  full_name: any;
  catname: any;
  body: any;
  begin_model_year: any;
  end_model_year: any;
  begin_year: any;
  begin_month: any;
  end_year: any;
  end_month: any;
  today: any;
  produced: any;
  is_group: boolean;
  lat: any;
  lng: any;
  vehicle_type: any;
}

@Component({
  selector: 'app-moder-items-new',
  templateUrl: './new.component.html'
})
@Injectable()
export class ModerItemsNewComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public loading = 0;
  public item: NewItem;
  public parent: APIItem;
  public parentSpec: any = null;
  public invalidParams: any;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private specService: SpecService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.item = {
      produced_exactly: '0',
      is_concept: 'inherited',
      spec_id: 'inherited',
      item_type_id: undefined,
      name: undefined,
      full_name: undefined,
      catname: undefined,
      body: undefined,
      begin_model_year: undefined,
      end_model_year: undefined,
      begin_year: undefined,
      begin_month: undefined,
      end_year: undefined,
      end_month: undefined,
      today: undefined,
      produced: undefined,
      is_group: false,
      lat: undefined,
      lng: undefined,
      vehicle_type: undefined
    };

    this.querySub = this.route.queryParams.subscribe(params => {
      this.item.item_type_id = parseInt(params.item_type_id, 10);

      if ([1, 2, 3, 4, 5, 6, 7, 8].indexOf(this.item.item_type_id) === -1) {
        this.router.navigate(['/error-404']);
        return;
      }

      if (params.parent_id) {
        this.loading++;
        this.itemService
          .getItem(params.parent_id, {
            fields: 'is_concept,name_html,spec_id'
          })
          .subscribe(
            (item: APIItem) => {
              this.parent = item;

              const specId = this.parent.spec_id;

              if (specId && Number.isInteger(specId as number)) {
                this.specService.getSpec(specId as number).then(
                  (spec: any) => {
                    this.parentSpec = spec;
                  },
                  () => {
                    Notify.error(
                      'Failed to fetch spec: ' + this.parent.spec_id
                    );
                  }
                );
              }
              this.loading--;
            },
            response => {
              Notify.response(response);
              this.loading--;
            }
          );
      }

      this.translate
        .get('item/type/' + params.item_type_id + '/new-item')
        .subscribe(
          (translation: string) => {
            /*this.$scope.pageEnv({
                  layout: {
                      isAdminPage: true,
                      blankPage: false,
                      needRight: false
                  },
                  name: 'page/163/name',
                  pageId: 163,
                  args: {
                      NEW_ITEM_OF_TYPE: translation
                  }
              });*/
          },
          () => {
            console.log('Translate failed');
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public submit() {
    this.loading++;

    const data = {
      item_type_id: this.item.item_type_id,
      name: this.item.name,
      full_name: this.item.full_name,
      catname: this.item.catname,
      body: this.item.body,
      spec_id: this.item.spec_id,
      begin_model_year: this.item.begin_model_year,
      end_model_year: this.item.end_model_year,
      begin_year: this.item.begin_year,
      begin_month: this.item.begin_month,
      end_year: this.item.end_year,
      end_month: this.item.end_month,
      today: this.item.today,
      produced: this.item.produced,
      produced_exactly: this.item.produced_exactly,
      is_concept: this.item.is_concept,
      is_group: this.item.is_group,
      lat: this.item.lat,
      lng: this.item.lng
    };

    this.http
      .post<void>('/api/item', data, {
        observe: 'response'
      })
      .subscribe(
        response => {
          const location = response.headers.get('Location');

          this.loading++;
          this.itemService.getItemByLocation(location).subscribe(
            item => {
              const promises = [];

              const ids: number[] = [];
              for (const vehicle_type of this.item.vehicle_type) {
                ids.push(vehicle_type.id);
              }
              promises.push(this.itemService.setItemVehicleTypes(item.id, ids));

              if (this.parent) {
                promises.push(
                  this.http.post<void>('/api/item-parent', {
                    parent_id: this.parent.id,
                    item_id: item.id
                  })
                );
              }

              this.loading++;
              Promise.all(promises).then(results => {
                this.router.navigate(['/moder/items/item', item.id]);
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
          Notify.response(response);
          this.invalidParams = response.error.invalid_params;
          this.loading--;
        }
      );
  }
}
