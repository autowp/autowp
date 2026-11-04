import type {OnInit} from '@angular/core';
import type {AttrUserValue, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {form, FormField, required, submit} from '@angular/forms/signals';
import {ActivatedRoute} from '@angular/router';
import {
  AttrUserValuesFields,
  AttrUserValuesRequest,
  DeleteAttrUserValuesRequest,
  MoveAttrUserValuesRequest,
} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {getUnitAbbrTranslation} from '@utils/translations';
import {errorMessage} from 'app/grpc';
import {firstValueFrom, forkJoin, map, of, switchMap} from 'rxjs';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ToastsService} from '../../toasts/toasts.service';
import {UserLink} from '../../user/user/user-link';

interface AttrUserValueListItem {
  createdAt: Date | undefined;
  path: string[];
  unitAbbr: null | string | undefined;
  user: null | User;
  userValue: AttrUserValue;
}

@Component({
  selector: 'app-cars-specs-admin',
  imports: [NgbTooltip, UserLink, FormField, DatePipe, TimeAgoPipe],
  templateUrl: './cars-specs-admin.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecsAdmin implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #userService = inject(UserService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #languageService = inject(LanguageService);
  readonly #attrsService = inject(APIAttrsService);

  readonly #moveModel = signal({itemId: ''});
  protected readonly moveForm = form(this.#moveModel, (path) => {
    required(path.itemId);
  });

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
    // DonateLog.itemsResource in ../../donate/log/donate-log.ts) - it serializes to
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
            const rows = response.items ?? [];
            if (rows.length === 0) {
              return of({items: [] as AttrUserValueListItem[]});
            }
            return forkJoin(rows.map((userValue) => this.#mapUserValue(userValue))).pipe(map((items) => ({items})));
          }),
        ),
  });

  protected readonly errorMessage = errorMessage;

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
    this.#pageEnv.set({pageId: PageId.SPECS_ADMIN});
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
    void submit(this.moveForm, async () => {
      try {
        await firstValueFrom(
          this.#attrsClient.moveUserValues(
            new MoveAttrUserValuesRequest({
              destItemId: this.#moveModel().itemId,
              srcItemId: itemID,
            }),
          ),
        );
        this.dataResource.reload();
      } catch (response: unknown) {
        this.#toastService.handleError(response);
      }
    });
  }
}
