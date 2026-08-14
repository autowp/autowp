import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute} from '@angular/router';
import {
  AttrUserValue,
  AttrUserValuesFields,
  AttrUserValuesRequest,
  DeleteAttrUserValuesRequest,
  MoveAttrUserValuesRequest,
  User,
} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {getUnitAbbrTranslation} from '@utils/translations';
import {forkJoin, map, Observable, of, switchMap} from 'rxjs';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface AttrUserValueListItem {
  createdAt: Date | undefined;
  path: string[];
  unitAbbr: null | string | undefined;
  user: null | User;
  userValue: AttrUserValue;
}

@Component({
  selector: 'app-cars-specs-admin',
  imports: [NgbTooltip, UserComponent, FormsModule, DatePipe, TimeAgoPipe],
  templateUrl: './specs-admin.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecsAdminComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #userService = inject(UserService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #languageService = inject(LanguageService);
  readonly #attrsService = inject(APIAttrsService);

  protected readonly move: {
    item_id: string;
  } = {
    item_id: '',
  };

  protected readonly itemID = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('item_id') ?? '')), {
    requireSync: true,
  });

  protected readonly dataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the item id read once at construction time - a static id would let a second
    // instance of this component, created by navigating away and back with a different
    // `?item_id=` before Angular's whenStable() ever resolves, match TransferState's
    // still-present entry from the first item and seed itself with the wrong data.
    //
    // #mapUserValue() below resolves path/unitAbbr/user eagerly (via forkJoin) into plain fields
    // rather than storing them as path$/unitAbbr$/user$ Observables on each row: an Observable
    // doesn't survive the TransferState JSON round-trip (see the identical note on
    // DonateLogComponent.itemsResource in ../../donate/log/log.component.ts) - it serializes to
    // '{}', and AsyncPipe throws on that non-Observable, non-Promise value on hydration.
    id: `cars-specs-admin-${this.itemID()}`,
    params: () => this.itemID(),
    stream: ({params: itemId}) =>
      this.#attrsClient
        .getUserValues(
          new AttrUserValuesRequest({
            fields: new AttrUserValuesFields({valueText: true}),
            itemId,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          switchMap((response) => {
            const rows = response.items || [];
            if (rows.length === 0) {
              return of({items: [] as AttrUserValueListItem[]});
            }
            return forkJoin(rows.map((userValue) => this.#mapUserValue(userValue))).pipe(map((items) => ({items})));
          }),
        ),
  });

  #mapUserValue(userValue: AttrUserValue): Observable<AttrUserValueListItem> {
    const attr$ = this.#attrsService.getAttribute$(userValue.attributeId);
    return forkJoin([
      this.#attrsService.getPath$(userValue.attributeId),
      attr$.pipe(map((attr) => (attr?.unitId ? getUnitAbbrTranslation(attr.unitId) : null))),
      this.#userService.getUser$(userValue.userId),
    ]).pipe(
      map(([path, unitAbbr, user]) => ({
        createdAt: timestampToDate(userValue.updateTime),
        path,
        unitAbbr,
        user,
        userValue,
      })),
    );
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 103});
  }

  protected deleteValue(value: AttrUserValueListItem) {
    this.#attrsClient
      .deleteUserValues(
        new DeleteAttrUserValuesRequest({
          attributeId: value.userValue.attributeId,
          itemId: value.userValue.itemId,
          userId: value.userValue.userId,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          this.dataResource.reload();
        },
      });
  }

  protected moveValues(itemID: string) {
    if (this.move.item_id) {
      return;
    }

    this.#attrsClient
      .moveUserValues(
        new MoveAttrUserValuesRequest({
          destItemId: this.move.item_id,
          srcItemId: itemID,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          this.dataResource.reload();
        },
      });
  }
}
