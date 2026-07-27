import {AsyncPipe, DatePipe} from '@angular/common';
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
import {getUnitAbbrTranslation} from '@utils/translations';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface AttrUserValueListItem {
  path$: Observable<string[]>;
  unitAbbr$: Observable<null | string | undefined>;
  user$: Observable<null | User>;
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

  protected readonly move: {
    item_id: string;
  } = {
    item_id: '',
  };

  protected readonly itemID = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('item_id') ?? '')), {
    requireSync: true,
  });

  protected readonly dataResource = rxResource({
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
          map((response) => ({
            items: (response.items || []).map((userValue) => this.#mapUserValue(userValue)),
          })),
        ),
  });

  #mapUserValue(userValue: AttrUserValue): AttrUserValueListItem {
    const attr$ = this.#attrsService.getAttribute$(userValue.attributeId);
    return {
      path$: this.#attrsService.getPath$(userValue.attributeId),
      unitAbbr$: attr$.pipe(map((attr) => (attr?.unitId ? getUnitAbbrTranslation(attr.unitId) : null))),
      user$: this.#userService.getUser$(userValue.userId),
      userValue,
    };
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
        error: (response: unknown) => this.#toastService.handleError(response),
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
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => {
          this.dataResource.reload();
        },
      });
  }
}
