import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../../services/api.service';
import { Subscription } from 'rxjs';
import { PictureItemService } from '../../../../services/picture-item';
import { ItemService, APIItem } from '../../../../services/item';
import { chunk } from '../../../../chunk';
import Notify from '../../../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import {
  ItemParentService,
  APIItemParent
} from '../../../../services/item-parent';
import { PageEnvService } from '../../../../services/page-env.service';

// Acl.inheritsRole( 'moder', 'unauthorized' );

export type PictureItemMoveSelectItem = (
  itemId: number,
  perspectiveId: number,
  type: number
) => void;
type ModerPicturesItemMoveDoSearch = () => void;

@Component({
  selector: 'app-moder-pictures-item-move',
  templateUrl: './move.component.html'
})
@Injectable()
export class ModerPicturesItemMoveComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  private querySub: Subscription;
  private id: number;
  public search: string;
  public selectItem: PictureItemMoveSelectItem;
  public concepts_expanded = false;
  public doSearch: ModerPicturesItemMoveDoSearch;
  private src_item_id: number;
  private page: number;
  public src_type: number;
  public show_museums: boolean;
  public show_factories: boolean;
  public show_persons: boolean;
  public show_authors: boolean;
  public museums_paginator: APIPaginator;
  public factories_paginator: APIPaginator;
  public brands_paginator: APIPaginator;
  public authors_paginator: APIPaginator;
  public brand_id: number;
  public museums: APIItem[] = [];
  public factories: APIItem[] = [];
  public vehicles: APIItemParent[] = [];
  public engines: APIItemParent[] = [];
  public authors: APIItem[] = [];
  private authorsSubscription: Subscription;
  private personsSubscription: Subscription;
  public persons_paginator: APIPaginator;
  public persons: APIItem[] = [];
  public concepts: APIItemParent[] = [];
  public brands: APIItem[][] = [];

  constructor(
    private http: HttpClient,
    private pictureItemService: PictureItemService,
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.pageEnv.set({
      layout: {
        isAdminPage: true,
        needRight: false
      },
      name: 'page/149/name',
      pageId: 149
    });

    this.selectItem = (itemId: number, perspectiveId: number, type: number) => {
      if (this.src_item_id && this.src_type) {
        this.pictureItemService
          .changeItem(this.id, this.src_type, this.src_item_id, itemId)
          .then(() => {
            if (Number.isInteger(perspectiveId)) {
              this.pictureItemService
                .setPerspective(this.id, this.src_type, itemId, perspectiveId)
                .then(() => {
                  this.router.navigate(['/moder/pictures', this.id]);
                });
            } else {
              this.router.navigate(['/moder/pictures', this.id]);
            }
          });
      } else {
        const data = {
          perspective_id: perspectiveId ? perspectiveId : null
        };

        this.pictureItemService.create(this.id, itemId, type, data).then(() => {
          this.router.navigate(['/moder/pictures', this.id]);
        });
      }
    };

    this.routeSub = this.route.params.subscribe(params => {
      this.id = params.id;
    });
    this.querySub = this.route.queryParams.subscribe(params => {
      this.page = params.page;
      this.src_item_id = params.src_item_id;
      this.src_type = params.src_type;

      this.show_museums = params.show_museums;
      this.show_factories = params.show_factories;
      this.show_persons = params.show_persons;
      this.show_authors = params.show_authors;
      this.brand_id = params.brand_id;

      if (this.src_type === 2) {
        this.show_authors = true;
      }

      if (this.show_museums) {
        this.itemService
          .getItems({
            type_id: 7,
            fields: 'name_html',
            limit: 50,
            page: this.page
          })
          .subscribe(
            response => {
              this.museums = response.items;
              this.museums_paginator = response.paginator;
            },
            response => {
              Notify.response(response);
            }
          );
      }

      if (this.show_factories) {
        this.itemService
          .getItems({
            type_id: 6,
            fields: 'name_html',
            limit: 50,
            page: this.page
          })
          .subscribe(
            response => {
              this.factories = response.items;
              this.factories_paginator = response.paginator;
            },
            response => {
              Notify.response(response);
            }
          );
      }

      if (this.show_persons) {
        this.doSearch = () => {
          this.loadPersons();
        };

        this.loadPersons();
      }

      if (this.show_authors) {
        this.doSearch = () => {
          this.loadAuthors();
        };

        this.loadAuthors();
      }

      if (
        !this.show_museums &&
        !this.show_factories &&
        !this.show_persons &&
        !this.show_authors
      ) {
        if (this.brand_id) {
          this.itemParentService
            .getItems({
              item_type_id: 1,
              parent_id: this.brand_id,
              fields: 'item.name_html,item.childs_count',
              limit: 500,
              page: 1
            })
            .subscribe(
              response => {
                this.vehicles = response.items;
              },
              response => {
                Notify.response(response);
              }
            );
          this.itemParentService
            .getItems({
              item_type_id: 2,
              parent_id: this.brand_id,
              fields: 'item.name_html,item.childs_count',
              limit: 500,
              page: 1
            })
            .subscribe(
              response => {
                this.engines = response.items;
              },
              response => {
                Notify.response(response);
              }
            );

          this.itemParentService
            .getItems({
              item_type_id: 1,
              concept: true,
              ancestor_id: this.brand_id,
              fields: 'item.name_html,item.childs_count',
              limit: 500,
              page: 1
            })
            .subscribe(
              response => {
                this.concepts = response.items;
              },
              response => {
                Notify.response(response);
              }
            );
        } else {
          this.doSearch = () => {
            this.loadBrands();
          };

          this.loadBrands();
        }
      }
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }

  private loadBrands() {
    this.itemService
      .getItems({
        type_id: 5,
        fields: 'name_html',
        limit: 200,
        name: this.search ? '%' + this.search + '%' : null,
        page: this.page
      })
      .subscribe(
        response => {
          this.brands = chunk<APIItem>(response.items, 6);
          this.brands_paginator = response.paginator;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public toggleConcepts() {
    this.concepts_expanded = !this.concepts_expanded;
  }

  private loadAuthors() {
    if (this.authorsSubscription) {
      this.authorsSubscription.unsubscribe();
      this.authorsSubscription = null;
    }

    this.authorsSubscription = this.itemService
      .getItems({
        type_id: 8,
        fields: 'name_html',
        limit: 50,
        name: this.search ? '%' + this.search + '%' : null,
        page: this.page
      })
      .subscribe(
        response => {
          this.authors = response.items;
          this.authors_paginator = response.paginator;
        },
        response => {
          if (response.status !== -1) {
            Notify.response(response);
          }
        }
      );
  }

  private loadPersons() {
    if (this.personsSubscription) {
      this.personsSubscription.unsubscribe();
      this.personsSubscription = null;
    }

    this.personsSubscription = this.itemService
      .getItems({
        type_id: 8,
        fields: 'name_html',
        limit: 50,
        name: this.search ? '%' + this.search + '%' : null,
        page: this.page
      })
      .subscribe(
        response => {
          this.persons = response.items;
          this.persons_paginator = response.paginator;
        },
        response => {
          if (response.status !== -1) {
            Notify.response(response);
          }
        }
      );
  }
}
