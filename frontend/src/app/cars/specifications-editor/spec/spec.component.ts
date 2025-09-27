import {AsyncPipe, DatePipe, NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {FormArray, FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {
  APIItem,
  APIUser,
  AttrAttributeType,
  AttrListOption,
  AttrSetUserValuesRequest,
  AttrUserValue,
  AttrUserValuesFields,
  AttrUserValuesRequest,
  AttrValue,
  AttrValuesRequest,
  AttrValueValue,
} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {
  getAttrDescriptionTranslation,
  getAttrListOptionsTranslation,
  getAttrsTranslation,
  getUnitAbbrTranslation,
  getUnitNameTranslation,
} from '@utils/translations';
import {BehaviorSubject, combineLatest, EMPTY, Observable, of, throwError} from 'rxjs';
import {catchError, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {APIAttrsService, AttrAttributeTreeItem} from '../../../api/attrs/attrs.service';
import {ToastsService} from '../../../toasts/toasts.service';
import {UserComponent} from '../../../user/user/user.component';

export interface APIAttrAttributeInSpecEditor extends AttrAttributeTreeItem {
  deep: number;
  options$: Observable<ListOption[]>;
  step: number;
  unitAbbr: string;
  unitName: string;
}

interface AttrUserValueWithUser {
  user$: Observable<APIUser | null>;
  userValue: AttrUserValue;
}

interface ListOption {
  id: boolean | null | string;
  name: string;
}

const booleanOptions: ListOption[] = [
  {
    id: null,
    name: '—',
  },
  {
    id: false,
    name: $localize`no`,
  },
  {
    id: true,
    name: $localize`yes`,
  },
];

type AttrFormControls =
  | AttrFormControl<boolean | null>
  | AttrFormControl<null | number>
  | AttrFormControl<string>
  | AttrFormControl<string[]>;

export class AttrFormControl<TValue> extends FormControl {
  public attr: APIAttrAttributeInSpecEditor;
  constructor(attr: APIAttrAttributeInSpecEditor, value: TValue, disabled: boolean) {
    super({disabled, value});

    this.attr = attr;
  }
}

@Component({
  selector: 'app-cars-specifications-editor-spec',
  imports: [FormsModule, NgStyle, ReactiveFormsModule, UserComponent, NgbTooltip, AsyncPipe, DatePipe, TimeAgoPipe],
  templateUrl: './spec.component.html',
  styleUrl: './spec.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditorSpecComponent {
  readonly #attrsService = inject(APIAttrsService);
  readonly #auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #userService = inject(UserService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<APIItem>();
  readonly item$ = toObservable(this.item);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  // fields: 'options,childs.options',
  protected readonly attributes$: Observable<APIAttrAttributeInSpecEditor[]> = this.item$.pipe(
    distinctUntilChanged(),
    switchMap((item) =>
      item?.attrZoneId
        ? this.#attrsService.getAttributes$(item.attrZoneId, null)
        : throwError(() => new Error('Failed to detect attr_zone_id')),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((attributes) => this.toPlain(attributes, 0)),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly AttrAttributeTypeId = AttrAttributeType.Id;

  protected readonly currentUserValues$: Observable<Record<string, AttrUserValue>> = combineLatest([
    this.item$,
    this.#auth.user$,
    this.attributes$,
    this.#change$,
  ]).pipe(
    switchMap(([item, user, attributes]) =>
      item && user
        ? this.#attrsClient
            .getUserValues(
              new AttrUserValuesRequest({
                itemId: item.id,
                language: this.#languageService.language,
                userId: user.id,
                zoneId: item.attrZoneId,
              }),
            )
            .pipe(map((response) => ({attributes, response})))
        : EMPTY,
    ),
    map(({attributes, response}) => {
      const currentUserValues: Record<string, AttrUserValue> = {};
      for (const value of response.items || []) {
        currentUserValues[value.attributeId] = value;
      }

      for (const attr of attributes) {
        if (!currentUserValues[attr.id]) {
          currentUserValues[attr.id] = new AttrUserValue({
            value: new AttrValueValue(),
          });
        }
      }

      return currentUserValues;
    }),
  );

  protected readonly form$: Observable<FormArray<AttrFormControls>> = combineLatest([
    this.attributes$,
    this.currentUserValues$,
  ]).pipe(
    map(([attributes, currentUserValues]) => {
      const controls: AttrFormControls[] = attributes.map((attr) => {
        const currentUserValue = currentUserValues[attr.id].value;
        const disabled = !!currentUserValue?.isEmpty;
        const valid = currentUserValue?.valid && !disabled;

        switch (attr.typeId) {
          case AttrAttributeType.Id.BOOLEAN:
            return new AttrFormControl<boolean | null>(
              attr,
              valid ? (currentUserValue?.boolValue ?? null) : null,
              disabled,
            );
          case AttrAttributeType.Id.FLOAT:
            return new AttrFormControl<null | number>(
              attr,
              valid ? (currentUserValue?.floatValue ?? null) : null,
              disabled,
            );
          case AttrAttributeType.Id.INTEGER:
            return new AttrFormControl<null | number>(
              attr,
              valid ? (currentUserValue?.intValue ?? null) : null,
              disabled,
            );
          case AttrAttributeType.Id.LIST:
          case AttrAttributeType.Id.TREE:
            return new AttrFormControl<string[]>(attr, valid ? currentUserValue?.listValue || [] : [], disabled);
          case AttrAttributeType.Id.STRING:
          case AttrAttributeType.Id.TEXT:
            return new AttrFormControl<string>(attr, valid ? (currentUserValue?.stringValue ?? '') : '', disabled);
        }
        return new AttrFormControl<null>(attr, null, disabled);
      });
      return new FormArray<AttrFormControls>(controls);
    }),
  );

  private applyUserValues(userValues: Map<string, AttrUserValueWithUser[]>, items: AttrUserValue[]) {
    for (const userValue of items) {
      const v: AttrUserValueWithUser = {user$: this.#userService.getUser$(userValue.userId), userValue};
      const values = userValues.get(userValue.attributeId);
      if (values === undefined) {
        userValues.set(userValue.attributeId, [v]);
      } else {
        values.push(v);
        userValues.set(userValue.attributeId, values);
      }
    }
  }

  protected readonly values$ = combineLatest([this.item$, this.#change$]).pipe(
    switchMap(([item]) =>
      item
        ? this.#attrsClient.getValues(
            new AttrValuesRequest({
              itemId: item.id,
              language: this.#languageService.language,
              zoneId: item.attrZoneId,
            }),
          )
        : EMPTY,
    ),
    map((response) => {
      const values = new Map<string, AttrValue>();
      for (const value of response?.items || []) {
        values.set(value.attributeId, value);
      }
      return values;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly userValues$: Observable<Map<string, AttrUserValueWithUser[]>> = combineLatest([
    this.item$,
    this.#change$,
  ]).pipe(
    switchMap(([item]) =>
      item
        ? this.#attrsClient.getUserValues(
            new AttrUserValuesRequest({
              fields: new AttrUserValuesFields({valueText: true}),
              itemId: item.id,
              language: this.#languageService.language,
              zoneId: item.attrZoneId,
            }),
          )
        : EMPTY,
    ),
    map((response) => {
      const uv = new Map<string, AttrUserValueWithUser[]>();
      this.applyUserValues(uv, response?.items || []);
      return uv;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected saveSpecs(item: APIItem, form: FormArray<AttrFormControls>) {
    const items = form.controls.map((control) => {
      const typeId = control.attr.typeId;
      let valid = false;
      let stringValue = undefined;
      let listValue = [];
      let boolValue = undefined;
      let floatValue = undefined;
      let intValue = undefined;
      switch (typeId) {
        case AttrAttributeType.Id.BOOLEAN:
          valid = control.value !== null;
          boolValue = control.value;
          break;
        case AttrAttributeType.Id.FLOAT:
          valid = control.value !== null;
          floatValue = control.value;
          break;
        case AttrAttributeType.Id.INTEGER:
          valid = control.value !== null;
          intValue = control.value | 0;
          break;
        case AttrAttributeType.Id.LIST:
        case AttrAttributeType.Id.TREE:
          valid = control.value !== null && control.value !== undefined && control.value.length > 0;
          listValue = (control.value || []).filter((v: unknown) => !!v);
          break;
        case AttrAttributeType.Id.STRING:
        case AttrAttributeType.Id.TEXT:
          valid = control.value !== null && control.value !== undefined && control.value.length > 0;
          stringValue = control.value;
          break;
        default:
          valid = control.value !== null;
          break;
      }
      return new AttrUserValue({
        attributeId: control.attr.id,
        itemId: item.id,
        value: new AttrValueValue({
          boolValue,
          floatValue,
          intValue,
          isEmpty: control.disabled,
          listValue,
          stringValue,
          type: typeId,
          valid: valid || control.disabled,
        }),
      });
    });

    this.#attrsClient.setUserValues(new AttrSetUserValuesRequest({items})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#change$.next();
      },
    });
  }

  #listOptions$: Observable<{attributeId: string; id: string; name: string; parentId: string}[]> = this.#attrsService
    .getListOptions$(undefined)
    .pipe(
      map((response) =>
        (response.items ? response.items : []).map((i) => ({
          ...i.toObject(),
          name: getAttrListOptionsTranslation(i.name),
        })),
      ),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  private listOptionsTree(items: AttrListOption.AsObject[], parentID: string): ListOption[] {
    const result: ListOption[] = [];
    items
      .filter((i) => i.parentId === parentID)
      .forEach((i) => {
        result.push(
          i,
          ...this.listOptionsTree(items, i.id).map((i) => ({
            id: i.id,
            name: '…' + i.name,
          })),
        );
      });

    return result;
  }

  private toPlain(options: AttrAttributeTreeItem[], deep: number): APIAttrAttributeInSpecEditor[] {
    const result: APIAttrAttributeInSpecEditor[] = [];
    for (const item of options) {
      let options$: Observable<ListOption[]> = of([]);

      if (item.typeId === AttrAttributeType.Id.LIST || item.typeId === AttrAttributeType.Id.TREE) {
        options$ = this.#listOptions$.pipe(
          map((response) => {
            const opts: ListOption[] = this.listOptionsTree(
              response.filter((o) => o.attributeId === item.id),
              '0',
            );
            return [
              {
                id: null,
                name: '—',
              } as ListOption,
            ].concat(opts);
          }),
        );
      }

      if (item.typeId === AttrAttributeType.Id.BOOLEAN) {
        options$ = of(booleanOptions);
      }
      result.push({
        ...item,
        deep,
        description: getAttrDescriptionTranslation(item.description),
        name: getAttrsTranslation(item.name),
        options$,
        step: Math.pow(10, -item.precision),
        unitAbbr: item.unitId !== '0' ? getUnitAbbrTranslation(item.unitId) : '',
        unitName: item.unitId !== '0' ? getUnitNameTranslation(item.unitId) : '',
      });
      for (const subitem of this.toPlain(item.childs, deep + 1)) {
        result.push(subitem);
      }
    }
    return result;
  }
}
