import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {rxResource, toObservable} from '@angular/core/rxjs-interop';
import {APIGetItemVehicleTypesRequest, APIItem, ItemType, UpdateItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth.service';
import {ItemService} from '@services/item';
import {InvalidParams} from '@utils/invalid-params.pipe';
import {EMPTY, forkJoin, Observable, of} from 'rxjs';
import {catchError, map, tap} from 'rxjs/operators';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../../../grpc';
import {ToastsService} from '../../../../toasts/toasts.service';
import {
  ItemMetaFormComponent,
  ItemMetaFormResult,
  itemMetaFormResultsToAPIItem,
} from '../../item-meta-form/item-meta-form.component';

@Component({
  selector: 'app-moder-items-item-meta',
  imports: [NgbProgressbar, ItemMetaFormComponent, AsyncPipe],
  templateUrl: './meta.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemMetaComponent {
  readonly #auth = inject(AuthService);
  readonly #itemService = inject(ItemService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);

  readonly item = input.required<APIItem>();
  protected readonly item$ = toObservable(this.item);

  protected readonly loadingNumber = signal(false);

  protected readonly canEditMeta$ = this.#auth.hasRole$(Role.CARS_MODER);
  protected readonly invalidParams = signal<InvalidParams>({});

  protected readonly vehicleTypeIDsResource = rxResource({
    stream: (): Observable<string[]> => {
      const item = this.item();

      if (item.itemTypeId === ItemType.ITEM_TYPE_VEHICLE || item.itemTypeId === ItemType.ITEM_TYPE_TWINS) {
        return this.#itemsClient
          .getItemVehicleTypes(
            new APIGetItemVehicleTypesRequest({
              itemId: item.id,
            }),
          )
          .pipe(map((response) => (response.items ? response.items : []).map((row) => row.vehicleTypeId)));
      }

      return of([]);
    },
  });

  protected saveMeta(item: APIItem, event: ItemMetaFormResult) {
    this.loadingNumber.set(true);

    const newItem = itemMetaFormResultsToAPIItem(event);
    newItem.id = item.id;

    const mask = ['name'];

    switch (item.itemTypeId) {
      case ItemType.ITEM_TYPE_BRAND:
        mask.push('begin_year', 'end_year', 'begin_month', 'end_month', 'today', 'full_name', 'catname');
        break;
      case ItemType.ITEM_TYPE_CATEGORY:
        mask.push('begin_year', 'end_year', 'begin_month', 'end_month', 'today', 'catname');
        break;
      case ItemType.ITEM_TYPE_COPYRIGHT:
        break;
      case ItemType.ITEM_TYPE_ENGINE:
      case ItemType.ITEM_TYPE_VEHICLE:
        mask.push(
          'begin_year',
          'end_year',
          'begin_month',
          'end_month',
          'today',
          'spec_id',
          'spec_inherit',
          'is_concept',
          'is_concept_inherit',
          'produced',
          'produced_exactly',
          'body',
          'begin_model_year',
          'end_model_year',
          'begin_model_year_fraction',
          'end_model_year_fraction',
          'is_group',
        );
        break;
      case ItemType.ITEM_TYPE_FACTORY:
      case ItemType.ITEM_TYPE_MUSEUM:
        mask.push('begin_year', 'end_year', 'begin_month', 'end_month', 'today', 'location');
        break;
      case ItemType.ITEM_TYPE_PERSON:
        mask.push('begin_year', 'end_year', 'begin_month', 'end_month', 'today');
        break;
      case ItemType.ITEM_TYPE_TWINS:
        mask.push('begin_year', 'end_year', 'begin_month', 'end_month', 'today', 'body');
        break;
    }

    const pipes: Observable<void>[] = [
      this.#itemsClient
        .updateItem(
          new UpdateItemRequest({
            item: newItem,
            updateMask: new FieldMask({paths: mask}),
          }),
        )
        .pipe(
          catchError((response: unknown) => {
            if (response instanceof GrpcStatusEvent) {
              const fieldViolations = extractFieldViolations(response);
              this.invalidParams.set(fieldViolations2InvalidParams(fieldViolations));
            } else {
              this.#toastService.handleError(response);
            }
            return EMPTY;
          }),
          tap(() => this.invalidParams.set({})),
          map(() => void 0),
        ),
    ];
    if ([ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)) {
      pipes.push(this.#itemService.setItemVehicleTypes$(item.id, event.vehicle_type_id));
    }

    forkJoin(pipes).subscribe({
      complete: () => {
        this.loadingNumber.set(false);
      },
    });
  }
}
