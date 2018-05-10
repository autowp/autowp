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

@Component({
  selector: 'app-moder-attrs-attribute',
  templateUrl: './attribute.component.html'
})
@Injectable()
export class ModerAttrsAttributeComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public attribute: APIAttrAttribute;
  public attributes: APIAttrAttribute[];
  public loading = 0;
  public addLoading = 0;
  public addListOptionLoading = 0;

  public typeOptionsDefaults = [{ id: null, name: '-' }];
  public typeOptions: any[] = [];
  public typeMap: any = {};

  public unitOptionsDefaults = [{ id: null, name: '-' }];
  public unitOptions: any[] = [];

  public newAttribute: APIAttrAttribute;

  public listOptions: APIAttrListOption[] = [];

  public listOptionsDefaults = [{ id: null, name: '-' }];
  public listOptionsOptions: any[] = [];
  public newListOption: any = {
    parent_id: null,
    name: ''
  };

  constructor(
    private http: HttpClient,
    private attrsService: AttrsService,
    private translate: TranslateService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.attrsService.getUnits().then(
      (items: APIAttrUnit[]) => {
        this.unitOptions = this.unitOptionsDefaults;
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
        (attribute: APIAttrAttribute) => {
          this.attribute = attribute;

          /*this.translate.get(this.attribute.name).subscribe(
            (translation: string) => {
              this.$scope.pageEnv({
                      layout: {
                          isAdminPage: true,
                          blankPage: false,
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
              this.$scope.pageEnv({
                      layout: {
                          isAdminPage: true,
                          blankPage: false,
                          needRight: false
                      },
                      name: 'page/101/name',
                      pageId: 101,
                      args: {
                          ATTR_NAME: this.attribute.name
                      }
                  });
            }
          );*/

          this.attrsService.getAttributeTypes().then(
            (types: APIAttrAttributeType[]) => {
              this.typeOptions = this.typeOptionsDefaults;
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
    this.http
      .patch('/api/attr/attribute/' + this.attribute.id, {
        data: this.attribute
      })
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
    const data: any = this.newAttribute;
    data.parent_id = this.attribute.id;

    this.addLoading++;
    this.http
      .post<void>('/api/attr/attribute', data, {
        observe: 'response'
      })
      .subscribe(
        response => {
          const location = response.headers.get('Location');

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

    const data: any = this.newListOption;
    data.attribute_id = this.attribute.id;

    this.http
      .post('/api/attr/list-option', {
        data: data
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
          this.listOptionsOptions = this.listOptionsDefaults;
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
