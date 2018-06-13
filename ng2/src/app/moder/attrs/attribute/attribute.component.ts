import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import {
  AttrsService,
  APIAttrAttribute,
  APIAttrAttributeType,
  APIAttrUnit,
  APIAttrAttributeGetResponse,
  APIAttrListOption
} from '../../../services/attrs';
import Notify from '../../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { PageEnvService } from '../../../services/page-env.service';

@Component({
  selector: 'app-moder-attrs-attribute',
  templateUrl: './attribute.component.html'
})
@Injectable()
export class ModerAttrsAttributeComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public attribute: APIAttrAttribute;
  public attributes: APIAttrAttribute[] = [];
  public loading = 0;
  public addLoading = 0;
  public addListOptionLoading = 0;

  public typeOptionsDefaults = [{ id: null, name: '-' }];
  public typeOptions: {
    id: number;
    name: string;
  }[] = [];
  public typeMap: { [id: number]: string } = {};

  public unitOptionsDefaults = [{ id: null, name: '-' }];
  public unitOptions: {
    id: number;
    name: string;
  }[] = [];

  public defaultAttribute: APIAttrAttribute = {
    id: null,
    type_id: null,
    name: null,
    description: null,
    precision: null,
    unit_id: null,
    is_multiple: false,
    parent_id: null
  };

  public newAttribute: APIAttrAttribute = {
    id: null,
    type_id: null,
    name: null,
    description: null,
    precision: null,
    unit_id: null,
    is_multiple: false,
    parent_id: null
  };

  public listOptions: APIAttrListOption[] = [];

  public listOptionsDefaults = [{ id: null, name: '-' }];
  public listOptionsOptions: {
    id: number | null;
    name: string;
  }[] = [];
  public newListOption = {
    parent_id: null,
    name: ''
  };

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private translate: TranslateService,
    private router: Router,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {
    Object.assign(this.newAttribute, this.defaultAttribute);
  }

  ngOnInit(): void {
    this.attrsService.getUnits().then(
      items => {
        this.unitOptions = this.unitOptionsDefaults.slice(0);
        for (const item of items) {
          this.unitOptions.push({
            id: item.id,
            name: item.name
          });
        }
      },
      response => {
        Notify.response(response);
      }
    );

    this.routeSub = this.route.params.subscribe(params => {
      this.attrsService.getAttribute(params.id).then(
        attribute => {
          this.attribute = attribute;

          this.translate.get(this.attribute.name).subscribe(
            (translation: string) => {
              this.pageEnv.set({
                layout: {
                  isAdminPage: true,
                  needRight: false
                },
                name: 'page/101/name',
                pageId: 101,
                args: {
                  ATTR_NAME: translation
                }
              });
            },
            () => {
              this.pageEnv.set({
                layout: {
                  isAdminPage: true,
                  needRight: false
                },
                name: 'page/101/name',
                pageId: 101,
                args: {
                  ATTR_NAME: this.attribute.name
                }
              });
            }
          );

          this.attrsService.getAttributeTypes().then(
            types => {
              this.typeOptions = this.typeOptionsDefaults.slice(0);
              for (const item of types) {
                this.typeMap[item.id] = item.name;
                this.typeOptions.push({
                  id: item.id,
                  name: item.name
                });
              }
            },
            response => {
              Notify.response(response);
            }
          );

          this.http
            .get<APIAttrAttributeGetResponse>('/api/attr/attribute', {
              params: {
                parent_id: this.attribute.id.toString(),
                recursive: '0',
                fields: 'unit'
              }
            })
            .subscribe(
              response => {
                this.attributes = response.items;
              },
              response => {
                Notify.response(response);
              }
            );

          this.loadListOptions();
        },
        response => {
          this.router.navigate(['/error-404']);
        }
      );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public saveAttribute() {
    this.loading++;
    this.attrsService
      .updateAttribute(this.attribute.id, this.attribute)
      .subscribe(
        response => {
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  public addAttribute() {
    this.newAttribute.parent_id = this.attribute.id;

    this.addLoading++;
    this.attrsService.createAttribute(this.newAttribute).subscribe(
      response => {
        const location = response.headers.get('Location');

        Object.assign(this.newAttribute, this.defaultAttribute);

        this.addLoading++;
        this.attrsService.getAttributeByLocation(location).subscribe(
          subresponse => {
            this.router.navigate(['/moder/attrs/attribute', subresponse.id]);

            this.addLoading--;
          },
          subresponse => {
            Notify.response(subresponse);
            this.addLoading--;
          }
        );

        this.addLoading--;
      },
      response => {
        Notify.response(response);
        this.addLoading--;
      }
    );
  }

  public addListOption() {
    this.addListOptionLoading++;

    this.attrsService
      .createListOption({
        attribute_id: this.attribute.id,
        parent_id: this.newListOption.parent_id,
        name: this.newListOption.name
      })
      .subscribe(
        response => {
          this.newListOption.name = '';

          this.loadListOptions();

          this.addListOptionLoading--;
        },
        response => {
          Notify.response(response);
          this.addListOptionLoading--;
        }
      );

    return false;
  }

  public loadListOptions() {
    this.loading++;
    this.attrsService
      .getListOptions({
        attribute_id: this.attribute.id
      })
      .subscribe(
        response => {
          this.listOptions = response.items;
          this.listOptionsOptions = this.listOptionsDefaults.slice(0);
          for (const item of this.listOptions) {
            this.listOptionsOptions.push({
              id: item.id,
              name: item.name
            });
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
