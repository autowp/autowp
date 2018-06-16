import {
  Component,
  Injectable,
  OnInit,
  OnChanges,
  Input,
  SimpleChanges,
  OnDestroy
} from '@angular/core';
import { APIItem, ItemService } from '../../../../services/item';
import { ACLService } from '../../../../services/acl.service';
import { HttpClient } from '@angular/common/http';
import { APIItemVehicleTypeGetResponse } from '../../../../services/api.service';
import { Subscription, empty, forkJoin } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';

@Component({
  selector: 'app-moder-items-item-meta',
  templateUrl: './meta.component.html'
})
@Injectable()
export class ModerItemsItemMetaComponent
  implements OnInit, OnDestroy, OnChanges {
  @Input() item: APIItem;

  public loading = 0;

  public canEditMeta = false;
  public vehicleTypeIDs: number[] = [];
  public invalidParams: any;
  private aclSub: Subscription;

  constructor(
    private acl: ACLService,
    private http: HttpClient,
    private itemService: ItemService
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
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
    }
  }

  ngOnInit(): void {
    this.aclSub = this.acl
      .isAllowed('car', 'edit_meta')
      .subscribe(allow => (this.canEditMeta = allow));
  }

  ngOnDestroy(): void {
    this.aclSub.unsubscribe();
  }

  public saveMeta(e) {
    this.loading++;

    const data = {
      // item_type_id: this.$state.params.item_type_id,
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

    forkJoin(
      this.http.put<void>('/api/item/' + this.item.id, data).pipe(
        catchError(response => {
          this.invalidParams = response.error.invalid_params;
          return empty();
        }),
        tap(() => (this.invalidParams = {}))
      ),
      this.itemService.setItemVehicleTypes(this.item.id, this.vehicleTypeIDs)
    ).subscribe(() => {}, () => {}, () => this.loading--);
  }
}
