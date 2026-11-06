import type {FieldTree} from '@angular/forms/signals';
import type {AttrListOption, AttrValue, Item, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, Injector, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {applyEach, disabled, form, FormField, submit, validate} from '@angular/forms/signals';
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
import {AuthService} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {SelectMultiple} from '@utils/select-multiple/select-multiple';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {
  getAttrDescriptionTranslation,
  getAttrListOptionsTranslation,
  getAttrsTranslation,
  getUnitAbbrTranslation,
  getUnitNameTranslation,
} from '@utils/translations';
import {firstValueFrom, map, of, shareReplay, switchMap, tap, throwError} from 'rxjs';

import type {AttrAttributeTreeItem} from '../../../api/attrs/attrs.service';

import {APIAttrsService} from '../../../api/attrs/attrs.service';
import {ToastsService} from '../../../toasts/toasts.service';
import {UserLink} from '../../../user/user/user-link';
export interface APIAttrAttributeInSpecEditor extends AttrAttributeTreeItem {
  deep: number;
  options$: Observable<ListOption[]>;
  step: number;
  typeId: AttrAttributeType.Id;
  unitAbbr: string;
  unitName: string;
}

interface AttrUserValueWithUser {
  user$: Observable<null | User>;
  userValue: AttrUserValue;
}

interface ListOption {
  id: string;
  name: string;
}

const booleanOptions: ListOption[] = [
  {
    id: '',
    name: '—',
  },
  {
    id: 'false',
    name: $localize`no`,
  },
  {
    id: 'true',
    name: $localize`yes`,
  },
];

interface SpecFormValue {
  itemId: string;
  rows: {
    id: string;
    list: string[];
    multiple: boolean;
    notApplicable: boolean;
    type: AttrAttributeType.Id;
    value: string;
  }[];
}

interface SpecEditor {
  attributes: APIAttrAttributeInSpecEditor[];
  form: FieldTree<SpecFormValue>;
}

@Component({
  selector: 'app-cars-specifications-editor-spec',
  imports: [ResourceStatus, FormField, SelectMultiple, UserLink, NgbTooltip, AsyncPipe, DatePipe, TimeAgoPipe],
  templateUrl: './cars-specifications-editor-spec.html',
  styleUrl: './cars-specifications-editor-spec.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditorSpec {
  readonly #injector = inject(Injector);
  readonly #attrsService = inject(APIAttrsService);
  readonly #auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #userService = inject(UserService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();
  readonly #user = this.#auth.user;

  protected readonly AttrAttributeTypeId = AttrAttributeType.Id;

  protected readonly formResource = rxResource({
    params: () => {
      const user = this.#user();
      return user ? {item: this.item(), user} : null;
    },
    stream: ({params}) => {
      if (params === null) return of(undefined);
      const {item, user} = params;
      if (!item.attrZoneId || item.attrZoneId === '0')
        return throwError(() => new Error('Failed to detect attr_zone_id'));
      return this.#attrsService
        .getAttributes$(item.attrZoneId, null)
        .pipe(
          map((attributes) => this.toPlain(attributes, 0)),
          switchMap((attributes) =>
            this.#attrsClient
              .getUserValues(
                new AttrUserValuesRequest({
                  itemId: item.id,
                  language: this.#languageService.language,
                  userId: user.id,
                  zoneId: item.attrZoneId,
                }),
              )
              .pipe(map((response) => ({attributes, itemId: item.id, response}))),
          ),
          tap({
            error: (error: unknown) => {
              this.#toastService.handleError(error);
            },
          }),
        )
        .pipe(
          map(({attributes, itemId, response}) => {
            const currentUserValues = new Map((response.items ?? []).map((value) => [value.attributeId, value]));
            return {attributes, itemId, currentUserValues};
          }),
          map(({attributes, currentUserValues, itemId}): SpecEditor => {
            const rows: SpecFormValue['rows'] = attributes.map((attr) => {
              const current = currentUserValues.get(attr.id)?.value;
              const notApplicable = !!current?.isEmpty;
              const valid = !!current?.valid && !notApplicable;
              let value = '';
              let list: string[] = [];
              if (current && valid) {
                switch (attr.typeId) {
                  case AttrAttributeType.Id.BOOLEAN:
                    value = String(current.boolValue);
                    break;
                  case AttrAttributeType.Id.FLOAT:
                    value = String(current.floatValue);
                    break;
                  case AttrAttributeType.Id.INTEGER:
                    value = String(current.intValue);
                    break;
                  case AttrAttributeType.Id.LIST:
                  case AttrAttributeType.Id.TREE:
                    list = [...current.listValue];
                    value = list[0] ?? '';
                    break;
                  case AttrAttributeType.Id.STRING:
                  case AttrAttributeType.Id.TEXT:
                    value = current.stringValue;
                    break;
                }
              }
              return {id: attr.id, type: attr.typeId, multiple: attr.isMultiple, value, list, notApplicable};
            });
            const field = form(
              signal({itemId, rows}),
              (path) => {
                disabled(path, {when: ({state}) => state.submitting()});
                applyEach(path.rows, (row) => {
                  disabled(row.value, {when: ({valueOf}) => valueOf(row.notApplicable)});
                  disabled(row.list, {when: ({valueOf}) => valueOf(row.notApplicable)});
                  validate(row.value, ({value, valueOf}) => {
                    const type = valueOf(row.type);
                    if (
                      value().trim() === '' ||
                      (type !== AttrAttributeType.Id.INTEGER && type !== AttrAttributeType.Id.FLOAT)
                    )
                      return undefined;
                    const number = Number(value());
                    return !Number.isFinite(number) ||
                      (type === AttrAttributeType.Id.INTEGER &&
                        (!Number.isInteger(number) || number < -2147483648 || number > 2147483647))
                      ? {kind: 'number'}
                      : undefined;
                  });
                });
              },
              {injector: this.#injector},
            );
            return {attributes, form: field};
          }),
        );
    },
  });

  protected readonly valuesResource = rxResource({
    params: () => (this.formResource.hasValue() && this.formResource.value().attributes.length ? this.item() : null),
    stream: ({params: item}) =>
      item === null
        ? of(undefined)
        : this.#attrsClient
            .getValues(
              new AttrValuesRequest({
                itemId: item.id,
                language: this.#languageService.language,
                zoneId: item.attrZoneId,
              }),
            )
            .pipe(
              map((response) => {
                const values = new Map<string, AttrValue>();
                for (const value of response.items ?? []) {
                  values.set(value.attributeId, value);
                }
                return values;
              }),
            ),
  });

  protected readonly userValuesResource = rxResource({
    params: () => (this.formResource.hasValue() && this.formResource.value().attributes.length ? this.item() : null),
    stream: ({params: item}) =>
      item === null
        ? of(undefined)
        : this.#attrsClient
            .getUserValues(
              new AttrUserValuesRequest({
                fields: new AttrUserValuesFields({valueText: true}),
                itemId: item.id,
                language: this.#languageService.language,
                zoneId: item.attrZoneId,
              }),
            )
            .pipe(
              map((response) => {
                const uv = new Map<string, AttrUserValueWithUser[]>();
                this.applyUserValues(uv, response.items ?? []);
                return uv;
              }),
            ),
  });

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

  protected saveSpecs(field: FieldTree<SpecFormValue>): void {
    if (field().value().itemId !== this.item().id) return;
    void submit(field, async () => {
      const model = field().value();
      const items = model.rows.map((row) => {
        const value = new AttrValueValue({type: row.type, isEmpty: row.notApplicable});
        if (row.notApplicable) {
          value.valid = true;
          return new AttrUserValue({attributeId: row.id, itemId: model.itemId, value});
        }
        switch (row.type) {
          case AttrAttributeType.Id.BOOLEAN:
            value.valid = row.value !== '';
            value.boolValue = row.value === 'true';
            break;
          case AttrAttributeType.Id.FLOAT:
            value.valid = row.value.trim() !== '';
            value.floatValue = Number(row.value);
            break;
          case AttrAttributeType.Id.INTEGER:
            value.valid = row.value.trim() !== '';
            value.intValue = Number(row.value);
            break;
          case AttrAttributeType.Id.LIST:
          case AttrAttributeType.Id.TREE:
            value.listValue = (row.multiple ? row.list : [row.value]).filter((id) => id !== '');
            value.valid = value.listValue.length > 0;
            break;
          case AttrAttributeType.Id.STRING:
          case AttrAttributeType.Id.TEXT:
            value.stringValue = row.value;
            value.valid = row.value !== '';
            break;
        }
        return new AttrUserValue({attributeId: row.id, itemId: model.itemId, value});
      });
      try {
        await firstValueFrom(this.#attrsClient.setUserValues(new AttrSetUserValuesRequest({items})));
        if (this.item().id === model.itemId) {
          this.formResource.reload();
          this.valuesResource.reload();
          this.userValuesResource.reload();
        }
      } catch (response: unknown) {
        this.#toastService.handleError(response);
      }
    });
  }

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
                id: '',
                name: '—',
              },
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
