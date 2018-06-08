import {
  OnChanges,
  OnInit,
  Injectable,
  Component,
  Input,
  SimpleChanges
} from '@angular/core';
import { APIItem } from '../../../services/item';
import { HttpClient } from '@angular/common/http';
import Notify from '../../../notify';
import {
  AttrsService,
  APIAttrUserValue,
  APIAttrValue,
  APIAttrAttribute,
  APIAttrUserValueGetResponse,
  APIAttrAttributesGetResponse
} from '../../../services/attrs';
import { TranslateService } from '@ngx-translate/core';
import { Observable, combineLatest } from 'rxjs';
import { AuthService } from '../../../services/auth.service';
import { map } from 'rxjs/operators';

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
  implements OnInit, OnChanges {
  @Input() item: APIItem;
  public loading = 0;
  public attributes: APIAttrAttributeInSpecEditor[] = [];
  public values = new Map<number, APIAttrValue>();
  public userValues = new Map<number, APIAttrUserValue[]>();
  public currentUserValues: { [key: number]: APIAttrUserValue } = {};
  public userValuesLoading = 0;

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private translate: TranslateService,
    private auth: AuthService
  ) {}

  ngOnInit(): void {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.load();
    }
  }

  private load() {
    this.loading++;
    combineLatest(
      this.attrsService.getAttributes({
        fields: 'unit,options,childs.unit,childs.options',
        zone_id: this.item.attr_zone_id,
        recursive: true
      }),
      this.translate.get([
        'specifications/boolean/false',
        'specifications/boolean/true'
      ]),
      (attributes: APIAttrAttributesGetResponse, translations: string[]) => ({
        attributes,
        translations
      })
    )
      .pipe(
        map(data => {
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

          return {
            attributes: attibutes
          };
        })
      )
      .subscribe(
        data => {
          this.attributes = data.attributes;

          this.loading++;
          this.attrsService
            .getValues({
              item_id: this.item.id,
              zone_id: this.item.attr_zone_id,
              limit: 500,
              fields: 'value,value_text'
            })
            .subscribe(
              subresponse => {
                this.values.clear();
                for (const value of subresponse.items) {
                  this.values.set(value.attribute_id, value);
                }
                this.loading--;
              },
              subresponse => {
                Notify.response(subresponse);
                this.loading--;
              }
            );

          this.loadUserValues();

          this.loadAllValues();

          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  public saveSpecs() {
    const items = [];
    for (const attribute_id in this.currentUserValues) {
      if (this.currentUserValues.hasOwnProperty(attribute_id)) {
        items.push({
          item_id: this.item.id,
          attribute_id: attribute_id,
          user_id: this.auth.user.id,
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
        response => {
          this.loadUserValues();
          this.loadAllValues();
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  private loadUserValues() {
    this.loading++;
    this.userValuesLoading++;
    this.attrsService
      .getUserValues({
        item_id: this.item.id,
        user_id: this.auth.user.id,
        zone_id: this.item.attr_zone_id,
        limit: 500,
        fields: 'value'
      })
      .subscribe(
        response => {
          const currentUserValues: { [key: number]: APIAttrUserValue } = {};
          for (const value of response.items) {
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
                user_id: this.auth.user.id,
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
          this.loading--;
          this.userValuesLoading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
          this.userValuesLoading--;
        }
      );
  }

  public getStep(attribute: APIAttrAttribute): number {
    return Math.pow(10, -attribute.precision);
  }

  private httpUserValues(
    page: number
  ): Observable<APIAttrUserValueGetResponse> {
    return this.attrsService.getUserValues({
      item_id: this.item.id,
      // exclude_user_id: this.$scope.user.id,
      zone_id: this.item.attr_zone_id,
      limit: 500,
      fields: 'value_text,user'
    });
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

  private loadAllValues() {
    this.loading++;

    this.httpUserValues(1).subscribe(
      response => {
        this.userValues.clear();
        this.applyUserValues(response.items);

        for (let i = 2; i <= response.paginator.pageCount; i++) {
          this.loading++;
          this.httpUserValues(i).subscribe(
            subresponse => {
              this.applyUserValues(subresponse.items);
              this.loading--;
            },
            subresponse => {
              Notify.response(subresponse);
              this.loading--;
            }
          );
        }

        this.loading--;
      },
      response => {
        Notify.response(response);
        this.loading--;
      }
    );
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
