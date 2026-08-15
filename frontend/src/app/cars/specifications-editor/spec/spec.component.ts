import type {AttrListOption, AttrValue, Item, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {FormArray, FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {
  AttrAttributeType,
  AttrSetUserValuesRequest,
  AttrUserValue,
  AttrUserValuesFields,
  AttrUserValuesRequest,
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
import {
  BehaviorSubject,
  catchError,
  combineLatest,
  distinctUntilChanged,
  EMPTY,
  map,
  of,
  shareReplay,
  switchMap,
  throwError,
} from 'rxjs';

import type {AttrAttributeTreeItem} from '../../../api/attrs/attrs.service';

import {APIAttrsService} from '../../../api/attrs/attrs.service';
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
  user$: Observable<null | User>;
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
  // Declared, not inherited from FormControl<TValue>: extending the generic FormControl<TValue>
  // here hits a real TS limitation (TS2510, "base constructors must all have the same return
  // type") once TValue ranges over a union of unrelated types (boolean | null, string, string[],
  // ...) - the constructor overload resolution becomes ambiguous. This narrows the same `.value`
  // getter's return type without touching construction.
  declare value: TValue;

  constructor(attr: APIAttrAttributeInSpecEditor, value: TValue, disabled: boolean) {
    super({disabled, value});

    this.attr = attr;
  }
}

@Component({
  selector: 'app-cars-specifications-editor-spec',
  imports: [FormsModule, ReactiveFormsModule, UserComponent, NgbTooltip, AsyncPipe, DatePipe, TimeAgoPipe],
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

  readonly item = input.required<Item>();
  readonly item$ = toObservable(this.item);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  // fields: 'options,childs.options',
  readonly #attributes$: Observable<APIAttrAttributeInSpecEditor[]> = this.item$.pipe(
    distinctUntilChanged(),
    switchMap((item) =>
      // item is a required input(), always defined here.
      item.attrZoneId
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

  readonly #currentUserValues$: Observable<Record<string, AttrUserValue>> = combineLatest([
    this.item$,
    this.#auth.user$,
    this.#attributes$,
    this.#change$,
  ]).pipe(
    switchMap(([item, user, attributes]) =>
      // item is a required input(), always defined here.
      user
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
      for (const value of response.items ?? []) {
        currentUserValues[value.attributeId] = value;
      }

      for (const attr of attributes) {
        // Object.hasOwn(), not `!currentUserValues[attr.id]`: without noUncheckedIndexedAccess,
        // TS types a Record's index access as always-present, so the truthiness check reads as
        // "always false" to the type checker even though the key can genuinely be absent here -
        // Object.hasOwn() reflects the real runtime check instead of the unsound static type.
        if (!Object.hasOwn(currentUserValues, attr.id)) {
          currentUserValues[attr.id] = new AttrUserValue({
            value: new AttrValueValue(),
          });
        }
      }

      return currentUserValues;
    }),
  );

  protected readonly form$: Observable<FormArray<AttrFormControls>> = combineLatest([
    this.#attributes$,
    this.#currentUserValues$,
  ]).pipe(
    map(([attributes, currentUserValues]) => {
      const controls: AttrFormControls[] = attributes.map((attr) => {
        const currentUserValue = currentUserValues[attr.id].value;
        const disabled = !!currentUserValue?.isEmpty;
        const valid = currentUserValue?.valid && !disabled;

        // currentUserValue is narrowed non-nullish inside every `valid ? ... : ...` true branch
        // below: valid is `currentUserValue?.valid && !disabled`, so valid can only be truthy if
        // that optional chain already found currentUserValue defined.
        // The `?? fallback : null/'' /[]` shapes below aren't redundant null-guards on top of the
        // narrowing above - boolValue/floatValue/intValue/stringValue/listValue are proto3 scalar
        // fields (one active per attribute type, per its `type` discriminant) that are never
        // null/undefined themselves; the actual fallback happens on `valid` alone.
        switch (attr.typeId) {
          case AttrAttributeType.Id.BOOLEAN:
            return new AttrFormControl<boolean | null>(attr, valid ? currentUserValue.boolValue : null, disabled);
          case AttrAttributeType.Id.FLOAT:
            return new AttrFormControl<null | number>(attr, valid ? currentUserValue.floatValue : null, disabled);
          case AttrAttributeType.Id.INTEGER:
            return new AttrFormControl<null | number>(attr, valid ? currentUserValue.intValue : null, disabled);
          case AttrAttributeType.Id.LIST:
          case AttrAttributeType.Id.TREE:
            return new AttrFormControl<string[]>(attr, valid ? currentUserValue.listValue : [], disabled);
          case AttrAttributeType.Id.STRING:
          case AttrAttributeType.Id.TEXT:
            return new AttrFormControl<string>(attr, valid ? currentUserValue.stringValue : '', disabled);
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
    // item is a required input(), always defined here.
    switchMap(([item]) =>
      this.#attrsClient.getValues(
        new AttrValuesRequest({
          itemId: item.id,
          language: this.#languageService.language,
          zoneId: item.attrZoneId,
        }),
      ),
    ),
    map((response) => {
      const values = new Map<string, AttrValue>();
      for (const value of response.items ?? []) {
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
    // item is a required input(), always defined here.
    switchMap(([item]) =>
      this.#attrsClient.getUserValues(
        new AttrUserValuesRequest({
          fields: new AttrUserValuesFields({valueText: true}),
          itemId: item.id,
          language: this.#languageService.language,
          zoneId: item.attrZoneId,
        }),
      ),
    ),
    map((response) => {
      const uv = new Map<string, AttrUserValueWithUser[]>();
      this.applyUserValues(uv, response.items ?? []);
      return uv;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected saveSpecs(item: Item, form: FormArray<AttrFormControls>) {
    const items = form.controls.map((control) => {
      // typescript-eslint's type-checked linting reports this specific assignment as "error
      // typed" (effectively `any`), but ts.getPreEmitDiagnostics() on this file directly (via the
      // TypeScript compiler API, matching `npx tsc --noEmit`) returns zero diagnostics - a false
      // positive from eslint's own type resolution, not a real type hole. The explicit annotation
      // keeps it from also poisoning every downstream use of typeId.
      // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
      const typeId: AttrAttributeType.Id = control.attr.typeId;
      let valid;
      let stringValue;
      let listValue: string[] = [];
      let boolValue;
      let floatValue;
      let intValue;
      // control is typed as the AttrFormControls union (one AttrFormControl<T> per attribute
      // type), but that union isn't discriminated on typeId the way TS can narrow automatically -
      // form$ above is the only place controls are constructed, and it pairs each case here with
      // the exact same typeId, so the cast in each branch reflects a real invariant rather than
      // papering over an unknown one.
      switch (typeId) {
        case AttrAttributeType.Id.BOOLEAN: {
          const value = (control as AttrFormControl<boolean | null>).value;
          valid = value !== null;
          boolValue = value ?? undefined;
          break;
        }
        case AttrAttributeType.Id.FLOAT: {
          const value = (control as AttrFormControl<null | number>).value;
          valid = value !== null;
          floatValue = value ?? undefined;
          break;
        }
        case AttrAttributeType.Id.INTEGER: {
          const value = (control as AttrFormControl<null | number>).value;
          valid = value !== null;
          intValue = value !== null ? value | 0 : undefined;
          break;
        }
        case AttrAttributeType.Id.LIST:
        case AttrAttributeType.Id.TREE: {
          const value = (control as AttrFormControl<string[]>).value;
          valid = value.length > 0;
          listValue = value.filter((v) => !!v);
          break;
        }
        case AttrAttributeType.Id.STRING:
        case AttrAttributeType.Id.TEXT: {
          const value = (control as AttrFormControl<string>).value;
          valid = value.length > 0;
          stringValue = value;
          break;
        }
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

  readonly #listOptions$: Observable<{attributeId: string; id: string; name: string; parentId: string}[]> =
    this.#attrsService.getListOptions$(undefined).pipe(
      map((response) =>
        (response.items ?? []).map((i) => ({
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
