import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import {
  VehicleTypeService,
  APIVehicleType
} from '../../../services/vehicle-type';
import { ACLService } from '../../../services/acl.service';
import { ContentLanguageService } from '../../../services/content-language';
import { ItemService, APIItem } from '../../../services/item';
import {
  APIItemVehicleTypeGetResponse,
  APIImage
} from '../../../services/api.service';
import { chunkBy } from '../../../chunk';
import { TranslateService } from '@ngx-translate/core';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { APIPicture, PictureService } from '../../../services/picture';
import { ItemParentService } from '../../../services/item-parent';
import { ItemLinkService, APIItemLink } from '../../../services/item-link';
import { ItemLanguageService, APIItemLanguage } from '../../../services/item-language';
import { PageEnvService } from '../../../services/page-env.service';

// Acl.isAllowed('car', 'edit_meta', 'unauthorized');

export interface APIItemTreeItem {
  id: number;
  type: number;
  name: string;
  childs: APIItemTreeItem[];
}

export interface APIItemTreeGetResponse {
  item: APIItemTreeItem;
}

interface APIItemInModerItem extends APIItem {
  vehicle_type?: APIVehicleType[];
}

interface Tab {
  id: string;
  count: number;
  init: Function;
  active?: boolean;
}

@Component({
  selector: 'app-moder-items-item',
  templateUrl: './item.component.html'
})
@Injectable()
export class ModerItemsItemComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  private routeSub: Subscription;
  public loading = 0;
  public metaLoading = 0;
  public catalogueLoading = 0;
  public languagesLoading = 0;
  public linksLoading = 0;
  public logoLoading = 0;

  public item: APIItemInModerItem = null;
  public specsAllowed = false;
  public canMove = false;
  public canEditSpecifications = false;
  public picturesChunks: APIPicture[][] = [];
  public canEditMeta = false;
  public canLogo = false;

  public canHaveParents = false;
  public canHaveParentBrand = false;

  public currentLanguage: any = null;

  public itemLanguages: APIItemLanguage[] = [];
  public tree: APIItemTreeItem;

  public parents: any[] = [];
  public childs: any[] = [];
  public suggestions: any[] = [];

  public randomPicture: APIPicture;

  public newLink = {
    name: '',
    url: '',
    type_id: 'default'
  };

  public tabs: {[key: string]: Tab} = {
    meta: {
      id: 'meta',
      count: 0,
      init: this.initMetaTab
    },
    name: {
      id: 'name',
      count: 0,
      init: this.initItemLanguageTab
    },
    logo: {
      id: 'logo',
      count: 0,
      init: this.initLogoTab
    },
    catalogue: {
      id: 'catalogue',
      count: 0,
      init: this.initCatalogueTab
    },
    vehicles: {
      id: 'vehicles',
      count: 0,
      init: this.initVehiclesTab
    },
    tree: {
      id: 'tree',
      count: 0,
      init: this.initTreeTab
    },
    pictures: {
      id: 'pictures',
      count: 0,
      init: this.initPicturesTab
    },
    links: {
      id: 'links',
      count: 0,
      init: this.initLinksTab
    }
  };

  public organizeTypeId: number;
  public canUseTurboGroupCreator = false;

  public pictures: APIPicture[];
  public engineVehicles: any[];
  public links: APIItemLink[];
  public invalidParams: any;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private vehicleTypeService: VehicleTypeService,
    private acl: ACLService,
    private contentLanguage: ContentLanguageService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    private itemParentService: ItemParentService,
    private itemLinkService: ItemLinkService,
    private itemLanguageService: ItemLanguageService,
    private pageEnv: PageEnvService
  ) {
    this.loading++;
  }

  ngOnInit(): void {
    this.acl.isAllowed('specifications', 'edit').then(
      allow => {
        this.canEditSpecifications = !!allow;
      },
      () => {
        this.canEditSpecifications = false;
      }
    );

    this.acl.isAllowed('car', 'move').then(
      allow => {
        this.canMove = !!allow;
      },
      () => {
        this.canMove = false;
      }
    );

    /* const $input = $($element[0]).find('.item-autocomplete');
    $input
      .on('typeahead:select', (ev: any, car: any) => {
        this.addParent(car.id);
        $input.typeahead('val', '');
      })
      .typeahead(
        {},
        {
          display: (car: any) => {
            return car.name;
          },
          templates: {
            suggestion: (sitem: any) => {
              const $div = $(
                '<div class="tt-suggestion tt-selectable car"></div>'
              ).html(sitem.name_html);

              if (sitem.brandicon) {
                $div.prepend(
                  $('<img />', {
                    src: sitem.brandicon.src
                  })
                );
              }

              return $div[0];
            }
          },
          source: (
            query: string,
            syncResults: Function,
            asyncResults: Function
          ) => {
            if (!this.item) {
              syncResults([]);
              return;
            }

            this.itemService
              .getItems({
                autocomplete: query,
                exclude_self_and_childs: this.item.id,
                is_group: true,
                parent_types_of: this.item.item_type_id,
                fields: 'name_html,brandicon',
                limit: 15
              })
              .subscribe(response => {
                asyncResults(response.items);
              });
          }
        }
      );*/

    this.routeSub = this.route.params.subscribe(params => {
      this.itemService
        .getItem(params.id, {
          fields: [
            'name_text',
            'name_html',
            'name',
            'is_concept',
            'name_default',
            'body',
            'subscription',
            'begin_year',
            'begin_month',
            'end_year',
            'end_month',
            'today',
            'begin_model_year',
            'end_model_year',
            'produced',
            'is_group',
            'spec_id',
            'childs_count',
            'full_name',
            'catname',
            'lat',
            'lng',
            'pictures_count',
            'specifications_count',
            'links_count',
            'parents_count',
            'item_language_count',
            'engine_vehicles_count',
            'logo'
          ].join(',')
        })
        .subscribe(
          (item: APIItem) => {
            this.item = item;

            this.specsAllowed = [1, 2].indexOf(this.item.item_type_id) !== -1;

            this.translate
              .get('item/type/' + this.item.item_type_id + '/name')
              .subscribe(translation => {
                this.pageEnv.set({
                  layout: {
                    isAdminPage: true,
                    needRight: false
                  },
                  name: 'page/78/name',
                  pageId: 78,
                  args: {
                    CAR_ID: this.item.id + '',
                    CAR_NAME: translation + ': ' + this.item.name_text
                  }
                });
              });
            this.tabs.name.count = this.item.item_language_count;
            this.tabs.logo.count = this.item.logo ? 1 : 0;
            this.tabs.catalogue.count =
              this.item.parents_count + this.item.childs_count;
            this.tabs.vehicles.count = this.item.engine_vehicles_count;
            this.tabs.pictures.count = this.item.pictures_count;
            this.tabs.links.count = this.item.links_count;

            if (this.item.item_type_id === 7) {
              delete this.tabs.catalogue;
              delete this.tabs.tree;
            }

            if ([5, 7, 8].indexOf(this.item.item_type_id) === -1) {
              delete this.tabs.links;
            }

            if (this.item.item_type_id !== 5) {
              //  || ! $this->user()->isAllowed('brand', 'logo')
              delete this.tabs.logo;
            }

            if (this.item.item_type_id !== 2) {
              delete this.tabs.vehicles;
            }

            if ([2, 1, 5, 6, 7, 8].indexOf(this.item.item_type_id) === -1) {
              delete this.tabs.pictures;
            }

            /*if ($this->user()->get()->id == 1) {
                $tabs['modifications'] = [
                    icon: 'glyphicon glyphicon-th',
                    title: 'moder/vehicle/tabs/modifications',
                    'data-load' => $this->url()->fromRoute('moder/cars/params', [
                        'action' => 'car-modifications'
                    ], [], true),
                    count: 0
                ];
            }*/

            this.metaLoading++;
            this.acl.isAllowed('car', 'edit_meta').then(
              allow => {
                this.canEditMeta = !!allow;
                this.metaLoading--;
              },
              () => {
                this.canEditMeta = false;
                this.metaLoading--;
              }
            );
            this.canHaveParents = ![4, 6].includes(this.item.item_type_id);
            this.canHaveParentBrand = [1, 2].includes(this.item.item_type_id);

            this.organizeTypeId = this.item.item_type_id;
            switch (this.organizeTypeId) {
              case 5:
                this.organizeTypeId = 1;
                break;
            }

            this.canUseTurboGroupCreator =
              [1, 2].indexOf(this.item.item_type_id) !== -1;

            if (this.item.item_type_id === 1 || this.item.item_type_id === 4) {
              this.metaLoading++;
              this.http
                .get<APIItemVehicleTypeGetResponse>('/api/item-vehicle-type', {
                  params: {
                    item_id: this.item.id.toString()
                  }
                })
                .subscribe(
                  response => {
                    const ids: number[] = [];
                    for (const row of response.items) {
                      ids.push(row.vehicle_type_id);
                    }

                    this.vehicleTypeService.getTypesById(ids).then(types => {
                      this.item.vehicle_type = types;
                    });
                    this.metaLoading--;
                  },
                  () => {
                    this.metaLoading--;
                  }
                );
            }

            this.loading++;
            this.pictureService
              .getPictures({
                fields: 'thumb_medium',
                limit: 1,
                item_id: this.item.id
              })
              .subscribe(
                response => {
                  this.randomPicture =
                    response.pictures.length > 0 ? response.pictures[0] : null;
                  this.loading--;
                },
                () => {
                  this.loading--;
                }
              );

            this.loading--;
          },
          () => {
            this.router.navigate(['/error-404']);
            this.loading--;
          }
        );
    });
    this.querySub = this.route.queryParams.subscribe(params => {
      this.setActiveTab(params.tab ? params.tab : 'meta');
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }

  public getTabs(): Tab[] {
    return Object.values(this.tabs);
  }

  private initItemLanguageTab() {
    // TODO: move to service
    this.languagesLoading++;
    this.contentLanguage.getList().then(
      contentLanguages => {
        this.currentLanguage = contentLanguages[0];

        const languages = new Map<string, APIItemLanguage>();

        for (const language of contentLanguages) {
          languages.set(language, {
            language: language,
            name: null,
            text: null,
            full_text: null
          });
        }

        this.itemLanguageService.getItems(this.item.id).subscribe(response => {
          for (const itemLanguage of response.items) {
            languages.set(itemLanguage.language, itemLanguage);
          }

          this.itemLanguages = Array.from(languages.values());
        });
        this.languagesLoading--;
      },
      () => {
        this.languagesLoading--;
      }
    );
  }

  private initMetaTab() {
    setTimeout(() => {
      // his.$rootScope.$broadcast('invalidateSize', {});
    }, 100);
  }

  private initLogoTab() {
    this.acl.isAllowed('brand', 'logo').then(
      (allow: boolean) => {
        this.canLogo = !!allow;
      },
      () => {
        this.canLogo = false;
      }
    );
  }

  private initCatalogueTab() {
    this.catalogueLoading++;
    this.itemParentService
      .getItems({
        parent_id: this.item.id,
        limit: 500,
        fields:
          'name,duplicate_child.name_html,item.name_html,item.name,item.public_urls',
        order: 'type_auto'
      })
      .subscribe(
        response => {
          this.childs = response.items;
          this.catalogueLoading--;
        },
        () => {
          this.catalogueLoading--;
        }
      );

    this.catalogueLoading++;
    this.itemParentService
      .getItems({
        item_id: this.item.id,
        limit: 500,
        fields:
          'name,duplicate_parent.name_html,parent.name_html,parent.name,parent.public_urls'
      })
      .subscribe(
        response => {
          this.parents = response.items;
          this.catalogueLoading--;
        },
        () => {
          this.catalogueLoading--;
        }
      );

    this.catalogueLoading++;
    this.itemService
      .getItems({
        suggestions_to: this.item.id,
        limit: 3,
        fields: 'name_text'
      })
      .subscribe(
        response => {
          this.suggestions = response.items;
          this.catalogueLoading--;
        },
        () => {
          this.catalogueLoading--;
        }
      );
  }

  private initTreeTab() {
    this.http
      .get<APIItemTreeGetResponse>('/api/item/' + this.item.id + '/tree')
      .subscribe(
        response => {
          this.tree = response.item;
        },
        () => {}
      );
  }

  private initPicturesTab() {
    this.loading++;
    this.pictureService
      .getPictures({
        exact_item_id: this.item.id,
        limit: 500,
        fields:
          'owner,thumb_medium,moder_vote,votes,similar,comments_count,perspective_item,name_html,name_text,views',
        order: 14
      })
      .subscribe(
        response => {
          this.pictures = response.pictures;
          this.picturesChunks = chunkBy<APIPicture>(this.pictures, 6);
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }

  private initVehiclesTab() {
    this.itemService
      .getItems({
        engine_id: this.item.id,
        limit: 100,
        fields: 'name_html'
      })
      .subscribe(response => {
        this.engineVehicles = response.items;
      });
  }

  private initLinksTab() {
    this.linksLoading++;
    this.itemLinkService
      .getItems({
        item_id: this.item.id
      })
      .subscribe(
        response => {
          this.links = response.items;
          this.linksLoading--;
        },
        () => {
          this.linksLoading--;
        }
      );
  }

  public setActiveTab(tab: string) {
    if (!this.tabs[tab]) {
      throw new Error('Unexpected tab: ' + tab);
    }
    for (const id in this.tabs) {
      if (this.tabs.hasOwnProperty(id)) {
        this.tabs[id].active = false;
      }
    }
    this.tabs[tab].active = true;

    /*this.$state.go(
      STATE_NAME,
      { tab: tab },
      {
        notify: false,
        reload: false,
        location: 'replace'
      }
    );*/

    if (this.tabs[tab].init) {
      this.tabs[tab].init.call(this);
    }
  }

  public toggleSubscription() {
    const newValue = !this.item.subscription;
    this.http
      .put<void>('/api/item/' + this.item.id, {
        subscription: newValue ? 1 : 0
      })
      .subscribe(() => {
        this.item.subscription = newValue;
      });
  }

  public saveMeta() {
    this.metaLoading++;

    const data = {
      // item_type_id: this.$state.params.item_type_id,
      name: this.item.name,
      full_name: this.item.full_name,
      catname: this.item.catname,
      body: this.item.body,
      spec_id: this.item.spec_id,
      begin_model_year: this.item.begin_model_year,
      end_model_year: this.item.end_model_year,
      begin_year: this.item.begin_year,
      begin_month: this.item.begin_month,
      end_year: this.item.end_year,
      end_month: this.item.end_month,
      today: this.item.today,
      produced: this.item.produced,
      produced_exactly: this.item.produced_exactly,
      is_concept: this.item.is_concept,
      is_group: this.item.is_group,
      lat: this.item.lat,
      lng: this.item.lng
    };

    this.http.put<void>('/api/item/' + this.item.id, data).subscribe(
      response => {
        this.invalidParams = {};

        const promises = [];

        const ids: number[] = [];
        for (const vehicle_type of this.item.vehicle_type) {
          ids.push(vehicle_type.id);
        }
        promises.push(this.itemService.setItemVehicleTypes(this.item.id, ids));

        this.loading++;
        Promise.all(promises).then(results => {
          this.loading--;
        });

        this.metaLoading--;
      },
      response => {
        this.invalidParams = response.error.invalid_params;
        this.metaLoading--;
      }
    );
  }

  public saveLanguages() {
    for (const language of this.itemLanguages) {
      this.languagesLoading++;
      this.http
        .put<void>(
          '/api/item/' + this.item.id + '/language/' + language.language,
          {
            name: language.name,
            text: language.text,
            full_text: language.full_text
          }
        )
        .subscribe(
          response => {
            this.languagesLoading--;
          },
          response => {
            this.languagesLoading--;
          }
        );
    }
  }

  public deleteParent(parentId: number) {
    this.catalogueLoading++;
    this.http
      .delete('/api/item-parent/' + this.item.id + '/' + parentId)
      .subscribe(
        () => {
          this.initCatalogueTab();
          this.catalogueLoading--;
        },
        () => {
          this.catalogueLoading--;
        }
      );
  }

  public deleteChild(itemId: number) {
    this.catalogueLoading++;
    this.http
      .delete('/api/item-parent/' + itemId + '/' + this.item.id)
      .subscribe(
        () => {
          this.initCatalogueTab();
          this.catalogueLoading--;
        },
        () => {
          this.catalogueLoading--;
        }
      );
  }

  public addParent(parentId: number) {
    this.catalogueLoading++;
    this.http
      .post<void>('/api/item-parent', {
        item_id: this.item.id,
        parent_id: parentId
      })
      .subscribe(
        () => {
          this.initCatalogueTab();
          this.catalogueLoading--;
        },
        () => {
          this.catalogueLoading--;
        }
      );
  }

  public saveLinks() {
    const promises: Promise<any>[] = [];

    if (this.newLink.url) {
      const o = this.http.post<void>('/api/item-link', {
        item_id: this.item.id,
        name: this.newLink.name,
        url: this.newLink.url,
        type_id: this.newLink.type_id
      });
      o.subscribe(() => {
        this.newLink.name = '';
        this.newLink.url = '';
        this.newLink.type_id = 'default';
      });
      promises.push(o.toPromise());
    }

    for (const link of this.links) {
      if (link.url) {
        promises.push(
          this.http
            .put<void>('/api/item-link/' + link.id, {
              name: link.name,
              url: link.url,
              type_id: link.type_id
            })
            .toPromise()
        );
      } else {
        promises.push(
          this.http.delete<void>('/api/item-link/' + link.id).toPromise()
        );
      }
    }

    this.linksLoading++;
    Promise.all(promises).then(
      results => {
        this.initLinksTab();
        this.linksLoading--;
      },
      () => {
        this.linksLoading--;
      }
    );
  }

  public uploadLogo() {
    this.logoLoading++;
    const element = $('#logo-upload') as any;
    this.http
      .put<void>('/api/item/' + this.item.id + '/logo', element[0].files[0], {
        headers: { 'Content-Type': undefined }
      })
      .subscribe(
        response => {
          this.logoLoading++;
          this.http
            .get<APIImage>('/api/item/' + this.item.id + '/logo')
            .subscribe(
              subresponse => {
                this.item.logo = subresponse;
                this.logoLoading--;
              },
              () => {
                this.logoLoading--;
              }
            );

          this.logoLoading--;
        },
        response => {
          this.logoLoading--;
        }
      );
  }
}
