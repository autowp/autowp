import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import { APIItem, ItemService } from '../../services/item';
import { APIUser } from '../../services/user';
import { ACLService } from '../../services/acl.service';
import Notify from '../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute } from '@angular/router';
import {
  AttrsService,
  APIAttrUserValueGetResponse,
  APIAttrAttribute,
  APIAttrUserValue,
  APIAttrAttributeValue,
  APIAttrValue
} from '../../services/attrs';
import { AuthService } from '../../services/auth.service';
import { Observable, Subscription } from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';

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
  selector: 'app-cars-specifications-editor',
  templateUrl: './specifications-editor.component.html'
})
@Injectable()
export class CarsSpecificationsEditorComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public item: APIItem;
  public isAllowedEditEngine = false;
  public isSpecsAdmin = false;
  public isModer = false;
  public resultHtml = '';
  public engine: APIItem;
  public engineInherited: boolean;
  public tab = 'info';
  public tabs = {
    info: {
      id: 'info',
      icon: 'fa fa-info',
      title: 'specifications-editor/tabs/info',
      count: 0,
      visible: true
    },
    engine: {
      id: 'engine',
      icon: 'glyphicon glyphicon-align-left',
      title: 'specifications-editor/tabs/engine',
      count: 0,
      visible: true
    },
    spec: {
      id: 'spec',
      icon: 'fa fa-car',
      title: 'specifications-editor/tabs/specs',
      count: 0,
      visible: true
    },
    result: {
      id: 'result',
      icon: 'fa fa-table',
      title: 'specifications-editor/tabs/result',
      count: 0,
      visible: true
    },
    admin: {
      id: 'admin',
      icon: 'fa fa-cog',
      title: 'specifications-editor/tabs/admin',
      count: 0,
      visible: false
    }
  };
  public attributes: APIAttrAttributeInSpecEditor[] = [];
  public values: Map<number, APIAttrValue>;
  public userValues: Map<number, APIAttrUserValue[]>;
  public currentUserValues: { [key: number]: APIAttrUserValue } = {};
  public loading = 0;
  public userValuesLoading = 0;
  public specsWeight: number;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private acl: ACLService,
    private translate: TranslateService,
    private router: Router,
    private attrsService: AttrsService,
    private auth: AuthService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {
    this.values = new Map<number, APIAttrValue>();
    this.userValues = new Map<number, APIAttrUserValue[]>();

    this.acl.isAllowed('specifications', 'edit-engine').then(
      (allow: boolean) => {
        this.isAllowedEditEngine = !!allow;
      },
      () => {
        this.isAllowedEditEngine = false;
      }
    );

    this.acl.isAllowed('specifications', 'admin').then(
      (allow: boolean) => {
        this.isSpecsAdmin = !!allow;
        if (this.isSpecsAdmin) {
          this.tabs.admin.visible = true;
        }
      },
      () => {
        this.isSpecsAdmin = false;
      }
    );

    this.acl.inheritsRole('moder').then(
      (inherits: boolean) => {
        this.isModer = !!inherits;
      },
      () => {
        this.isModer = false;
      }
    );

    this.loading++;
    this.http
      .get<APIUser>('/api/user/me', {
        params: {
          fields: 'specs_weight'
        }
      })
      .subscribe(
        response => {
          this.specsWeight = response.specs_weight;
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.tab = params.tab || 'info';

      this.loading++;
      this.itemService
        .getItem(params.item_id, {
          fields: 'name_html,name_text,engine_id,attr_zone_id'
        })
        .subscribe(
          (item: APIItem) => {
            this.item = item;

            this.pageEnv.set({
              layout: {
                needRight: false
              },
              name: 'page/102/name',
              pageId: 102,
              args: {
                CAR_NAME: this.item.name_text
              }
            });

            this.tabs.engine.count = this.item.engine_id ? 1 : 0;

            if (this.tab === 'engine') {
              if (this.item.engine_id) {
                this.loading++;
                this.itemService
                  .getItem(this.item.engine_id, {
                    fields: 'name_html,name_text,engine_id'
                  })
                  .subscribe(
                    (engine: APIItem) => {
                      this.engine = engine;
                      this.loading--;
                    },
                    response => {
                      Notify.response(response);
                      this.loading--;
                    }
                  );
              }
            }

            if (this.tab === 'result') {
              this.loading++;
              this.http
                .get<string>('/api/item/' + this.item.id + '/specifications')
                .subscribe(
                  response => {
                    this.resultHtml = response;
                    this.loading--;
                  },
                  response => {
                    Notify.response(response);
                    this.loading--;
                  }
                );
            }

            if (this.tab === 'spec') {
              this.loading++;
              this.attrsService
                .getAttributes({
                  fields: 'unit,options,childs.unit,childs.options',
                  zone_id: item.attr_zone_id,
                  recursive: true
                })
                .subscribe(
                  response => {
                    this.translate
                      .get([
                        'specifications/boolean/false',
                        'specifications/boolean/true'
                      ])
                      .subscribe(translations => {
                        this.attributes = toPlain(response.items, 0);
                        for (const attribute of this.attributes) {
                          if (attribute.options) {
                            attribute.options.splice(0, 0, {
                              name: '—',
                              id: null
                            });
                          }

                          if (attribute.type_id === 5) {
                            attribute.options = [
                              {
                                name: '—',
                                id: null
                              },
                              {
                                name: translations[1],
                                id: 0
                              },
                              {
                                name: translations[2],
                                id: 1
                              }
                            ];
                          }
                        }
                      });

                    this.loading++;
                    this.attrsService
                      .getValues({
                        item_id: item.id,
                        zone_id: item.attr_zone_id,
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

                    this.loadValues();

                    this.loadAllValues();

                    this.loading--;
                  },
                  response => {
                    Notify.response(response);
                    this.loading--;
                  }
                );
            }

            this.loading--;
          },
          response => {
            this.router.navigate(['/error-404']);
            this.loading--;
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
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

  public inheritEngine() {
    this.http
      .put<void>('/api/item/' + this.item.id, {
        engine_id: 'inherited'
      })
      .subscribe(
        response => {
          this.router.navigate(['/cars/specifications-editor'], {
            queryParams: {
              item_id: this.item.id,
              tab: 'engine'
            }
          });
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public cancelInheritance() {
    this.http
      .put<void>('/api/item/' + this.item.id, {
        engine_id: ''
      })
      .subscribe(
        response => {
          this.router.navigate(['/cars/specifications-editor'], {
            queryParams: {
              item_id: this.item.id,
              tab: 'engine'
            }
          });
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public refreshInheritance() {
    this.http
      .post<void>('/api/item/' + this.item.id + '/refresh-inheritance', {})
      .subscribe(
        response => {
          this.router.navigate(['/cars/specifications-editor'], {
            queryParams: {
              item_id: this.item.id,
              tab: 'admin'
            }
          });
        },
        response => {
          Notify.response(response);
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
          this.loadValues();
          this.loadAllValues();
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  private loadValues() {
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
}
