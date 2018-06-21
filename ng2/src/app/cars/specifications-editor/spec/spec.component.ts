import {
  OnChanges,
  OnInit,
  Injectable,
  Component,
  Input,
  SimpleChanges,
  OnDestroy
} from '@angular/core';
import { APIItem } from '../../../services/item';
import { HttpClient } from '@angular/common/http';
import Notify from '../../../notify';
import {
  AttrsService,
  APIAttrUserValue,
  APIAttrValue,
  APIAttrAttribute,
  APIAttrUserValueGetResponse
} from '../../../services/attrs';
import { TranslateService } from '@ngx-translate/core';
import {
  Observable,
  Subscription,
  forkJoin,
  BehaviorSubject,
  combineLatest
} from 'rxjs';
import { AuthService } from '../../../services/auth.service';
import {
  map,
  tap,
  switchMap,
  switchMapTo,
  distinctUntilChanged
} from 'rxjs/operators';
import { APIUser } from '../../../services/user';

export interface APIAttrAttributeInSpecEditor extends APIAttrAttribute {
  deep?: number;
}

function toPlain(
  options: APIAttrAttributeInSpecEditor[],
  deep: number
): APIAttrAttributeInSpecEditor[] {
  const result: APIAttrAttributeInSpecEditor[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of toPlain(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

@Component({
  selector: 'app-cars-specifications-editor-spec',
  templateUrl: './spec.component.html'
})
@Injectable()
export class CarsSpecificationsEditorSpecComponent
  implements OnInit, OnChanges, OnDestroy {
  @Input() item: APIItem;
  private item$ = new BehaviorSubject<APIItem>(null);
  public loading = 0;
  public attributes: APIAttrAttributeInSpecEditor[] = [];
  public values = new Map<number, APIAttrValue>();
  public userValues = new Map<number, APIAttrUserValue[]>();
  public currentUserValues: { [key: number]: APIAttrUserValue } = {};
  public userValuesLoading = 0;
  private user: APIUser;
  private sub: Subscription;
  private change$ = new BehaviorSubject<null>(null);

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private translate: TranslateService,
    private auth: AuthService
  ) {}

  private applyCurrentUserValues(values) {
    const currentUserValues: { [key: number]: APIAttrUserValue } = {};
    for (const value of values) {
      const attribute = this.getAttribute(value.attribute_id);
      if (attribute.type_id === 2 || attribute.type_id === 3) {
        if (value.value !== null) {
          value.value = +value.value;
        }
      }
      if (attribute.is_multiple) {
        if (!(value.value instanceof Array)) {
          value.value = [value.value.toString()];
        }
      }
      currentUserValues[value.attribute_id] = value;
    }

    for (const attr of this.attributes) {
      if (!currentUserValues.hasOwnProperty(attr.id)) {
        currentUserValues[attr.id] = {
          item_id: this.item.id,
          user_id: this.user.id,
          attribute_id: attr.id,
          value: null,
          empty: true,
          value_text: '',
          user: null,
          update_date: null,
          item: null,
          unit: null,
          path: null
        };
      }
    }

    this.currentUserValues = currentUserValues;
  }

  ngOnInit(): void {
    this.sub = this.item$
      .pipe(
        distinctUntilChanged(),
        switchMap(item =>
          combineLatest(
            combineLatest(
              this.auth.getUser().pipe(tap(user => (this.user = user))),
              this.change$
            ).pipe(
              switchMap(user =>
                combineLatest(
                  this.attrsService
                    .getValues({
                      item_id: item.id,
                      zone_id: item.attr_zone_id,
                      limit: 500,
                      fields: 'value,value_text'
                    })
                    .pipe(
                      tap(values => {
                        this.values.clear();
                        for (const value of values.items) {
                          this.values.set(value.attribute_id, value);
                        }
                      })
                    ),
                  this.attrsService
                    .getUserValues({
                      item_id: item.id,
                      user_id: user[0].id,
                      zone_id: item.attr_zone_id,
                      limit: 500,
                      fields: 'value'
                    })
                    .pipe(
                      tap(response =>
                        this.applyCurrentUserValues(response.items)
                      )
                    ),
                  this.attrsService
                    .getUserValues({
                      item_id: item.id,
                      page: 1,
                      zone_id: item.attr_zone_id,
                      limit: 500,
                      fields: 'value_text,user'
                    })
                    .pipe(
                      tap(response => {
                        this.userValues.clear();
                        this.applyUserValues(response.items);
                      }),
                      switchMap(response => {
                        const observables: Observable<
                          APIAttrUserValueGetResponse
                        >[] = [];
                        for (
                          let i = 2;
                          i <= response.paginator.pageCount;
                          i++
                        ) {
                          observables.push(
                            this.attrsService
                              .getUserValues({
                                item_id: item.id,
                                page: i,
                                zone_id: item.attr_zone_id,
                                limit: 500,
                                fields: 'value_text,user'
                              })
                              .pipe(
                                tap(subresponse => {
                                  this.userValues.clear();
                                  this.applyUserValues(subresponse.items);
                                })
                              )
                          );
                        }

                        return forkJoin(...observables);
                      })
                    )
                )
              )
            ),
            combineLatest(
              this.attrsService.getAttributes({
                fields: 'unit,options,childs.unit,childs.options',
                zone_id: item.attr_zone_id,
                recursive: true
              }),
              this.translate.get([
                'specifications/boolean/false',
                'specifications/boolean/true'
              ]),
              (attributes, translations: string[]) => ({
                attributes,
                translations
              })
            ).pipe(
              tap(data => {
                const booleanOptions = [
                  {
                    name: '—',
                    id: null
                  },
                  {
                    name: data.translations[1],
                    id: 0
                  },
                  {
                    name: data.translations[2],
                    id: 1
                  }
                ];

                const attibutes = toPlain(data.attributes.items, 0);
                for (const attribute of attibutes) {
                  if (attribute.options) {
                    attribute.options.splice(0, 0, {
                      name: '—',
                      id: null
                    });
                  }

                  if (attribute.type_id === 5) {
                    attribute.options = booleanOptions;
                  }
                }

                this.attributes = attibutes;
              })
            )
          )
        )
      )
      .subscribe(() => {}, response => Notify.response(response));
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.item$.next(changes.item.currentValue);
    }
  }

  public saveSpecs() {
    const items = [];
    for (const attribute_id in this.currentUserValues) {
      if (this.currentUserValues.hasOwnProperty(attribute_id)) {
        items.push({
          item_id: this.item.id,
          attribute_id: attribute_id,
          user_id: this.user.id,
          value: this.currentUserValues[attribute_id].value,
          empty: this.currentUserValues[attribute_id].empty
        });
      }
    }

    this.loading++;
    this.http
      .patch('/api/attr/user-value', {
        items: items
      })
      .subscribe(
        () => {
          this.change$.next(null);
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  public getStep(attribute: APIAttrAttribute): number {
    return Math.pow(10, -attribute.precision);
  }

  private applyUserValues(items: APIAttrUserValue[]) {
    for (const value of items) {
      const values = this.userValues.get(value.attribute_id);
      if (values === undefined) {
        this.userValues.set(value.attribute_id, [value]);
      } else {
        values.push(value);
        this.userValues.set(value.attribute_id, values);
      }
    }
  }

  private getAttribute(id: number): APIAttrAttribute {
    for (const attribute of this.attributes) {
      if (attribute.id === id) {
        return attribute;
      }
    }

    return undefined;
  }

  private isMultipleAttribute(id: number): boolean {
    for (const attribute of this.attributes) {
      if (attribute.id === id) {
        return attribute.is_multiple;
      }
    }

    return false;
  }
}
