import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute} from '@angular/router';
import {
  APIUser,
  AttrUserValue,
  AttrUserValuesFields,
  AttrUserValuesRequest,
  DeleteAttrUserValuesRequest,
  MoveAttrUserValuesRequest,
} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {getUnitAbbrTranslation} from '@utils/translations';
import {BehaviorSubject, combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface AttrUserValueListItem {
  path$: Observable<string[]>;
  unitAbbr$: Observable<null | string | undefined>;
  user$: Observable<APIUser | null>;
  userValue: AttrUserValue;
}

@Component({
  selector: 'app-cars-specs-admin',
  imports: [NgbTooltip, UserComponent, FormsModule, AsyncPipe, DatePipe, TimeAgoPipe],
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

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly move: {
    item_id: string;
  } = {
    item_id: '',
  };

  protected readonly itemID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('item_id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$: Observable<{
    items: AttrUserValueListItem[];
  }> = combineLatest([this.itemID$, this.#reload$]).pipe(
    switchMap(([itemID]) =>
      this.#attrsClient.getUserValues(
        new AttrUserValuesRequest({
          fields: new AttrUserValuesFields({valueText: true}),
          itemId: itemID,
          language: this.#languageService.language,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
    map((response) => ({
      items: (response.items || []).map((userValue) => {
        const attr$ = this.#attrsService.getAttribute$(userValue.attributeId);
        return {
          path$: this.#attrsService.getPath$(userValue.attributeId),
          unitAbbr$: attr$.pipe(map((attr) => (attr?.unitId ? getUnitAbbrTranslation(attr.unitId) : null))),
          user$: this.#userService.getUser$(userValue.userId),
          userValue,
        };
      }),
    })),
  );

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
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.#reload$.next();
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
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.#reload$.next();
        },
      });
  }
}
