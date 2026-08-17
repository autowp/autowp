import type {OnInit, ResourceRef} from '@angular/core';
import type {Item} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, Injector, input, signal} from '@angular/core';
import {rxResource, toObservable} from '@angular/core/rxjs-interop';
import {GetItemVehicleTypesRequest, ItemType, UpdateItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth.service';
import {ItemService} from '@services/item';
import {catchError, EMPTY, forkJoin, map, of, tap} from 'rxjs';

import type {ItemMetaFormResult} from '../../item-meta-form/item-meta-form.component';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../../../grpc';
import {ToastsService} from '../../../../toasts/toasts.service';
import {ItemMetaFormComponent, itemMetaFormResultsToAPIItem} from '../../item-meta-form/item-meta-form.component';

@Component({
  selector: 'app-moder-items-item-meta',
  imports: [NgbProgressbar, ItemMetaFormComponent, AsyncPipe],
  templateUrl: './meta.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemMetaComponent implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #itemService = inject(ItemService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);
  readonly #injector = inject(Injector);

  readonly item = input.required<Item>();
  protected readonly item$ = toObservable(this.item);

  protected readonly loadingNumber = signal(false);

  protected readonly canEditMeta$ = this.#auth.hasRole$(Role.CARS_MODER);
  protected readonly invalidParams = signal<InvalidParams>({});

  // Constructed in ngOnInit() (with an explicit injector) rather than as a field initializer:
  // `item` is a *required* input, unreadable until Angular has bound it, which happens after
  // construction but before ngOnInit.
  protected vehicleTypeIDsResource!: ResourceRef<string[] | undefined>;

  ngOnInit(): void {
    this.vehicleTypeIDsResource = rxResource({
      // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
      // Suffixed with item().id read once at construction time - not actually a singleton across
      // different items: a static id would let a second instance of this component, created by
      // navigating away and to a different item's page before Angular's whenStable() ever
      // resolves, match TransferState's still-present entry from the first item and seed itself
      // with the wrong data.
      id: `moder-items-item-meta-vehicle-types-${this.item().id}`,
      injector: this.#injector,
      params: () => this.item(),
      stream: ({params: item}): Observable<string[]> => {
        if (item.itemTypeId === ItemType.ITEM_TYPE_VEHICLE || item.itemTypeId === ItemType.ITEM_TYPE_TWINS) {
          return this.#itemsClient
            .getItemVehicleTypes(
              new GetItemVehicleTypesRequest({
                itemId: item.id,
              }),
            )
            .pipe(map((response) => (response.items ?? []).map((row) => row.vehicleTypeId)));
        }

        return of([]);
      },
    });
  }

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the template below doesn't blow up on a non-NOT_FOUND
  // vehicleTypeIDsResource error (this form section has no inline slot for an error message, so it
  // just stays hidden instead).
  protected readonly vehicleTypeIDsData = computed(() =>
    this.vehicleTypeIDsResource.hasValue() ? this.vehicleTypeIDsResource.value() : undefined,
  );

  protected saveMeta(item: Item, event: ItemMetaFormResult) {
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
          tap(() => {
            this.invalidParams.set({});
          }),
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
      error: (error: unknown) => {
        this.loadingNumber.set(false);
        this.#toastService.handleError(error);
      },
    });
  }
}
