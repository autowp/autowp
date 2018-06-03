import {
  Component,
  Injectable,
  OnInit,
  OnDestroy,
  ViewChild
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../../services/acl.service';
import { ItemService, APIItem } from '../../../services/item';
import { chunkBy } from '../../../chunk';
import { TranslateService } from '@ngx-translate/core';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router, Params } from '@angular/router';
import { APIPicture, PictureService } from '../../../services/picture';
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

interface Tab {
  count: number;
  initialized?: boolean;
  visible: boolean;
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

  public item: APIItem = null;
  public specsAllowed = false;
  public canEditSpecifications = false;

  public tree: APIItemTreeItem;

  public randomPicture: APIPicture;

  public metaTab: Tab = {
    count: 0,
    visible: true
  };
  public nameTab: Tab = {
    count: 0,
    visible: true
  };
  public logoTab: Tab = {
    count: 0,
    visible: true
  };
  public catalogueTab: Tab = {
    count: 0,
    visible: true
  };
  public vehiclesTab: Tab = {
    count: 0,
    visible: true
  };
  public treeTab: Tab = {
    count: 0,
    visible: true
  };
  public picturesTab: Tab = {
    count: 0,
    visible: true
  };
  public linksTab: Tab = {
    count: 0,
    visible: true
  };

  public activeTab = 'meta';

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private acl: ACLService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.acl
      .isAllowed('specifications', 'edit')
      .then(
        allow => (this.canEditSpecifications = !!allow),
        () => (this.canEditSpecifications = false)
      );

    this.querySub = this.route.queryParams.subscribe(params => {
      this.activeTab = params.tab ? params.tab : 'meta';

      switch (this.activeTab) {
        case 'tree':
          if (!this.treeTab.initialized) {
            this.treeTab.initialized = true;
            this.initTreeTab();
          }
          break;
      }
    });

    this.routeSub = this.route.params.subscribe(params => {
      this.loading++;
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
          item => {
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
            this.nameTab.count = this.item.item_language_count;
            this.logoTab.count = this.item.logo ? 1 : 0;
            this.catalogueTab.count =
              this.item.parents_count + this.item.childs_count;
            this.vehiclesTab.count = this.item.engine_vehicles_count;
            this.picturesTab.count = this.item.pictures_count;
            this.linksTab.count = this.item.links_count;

            this.metaTab.visible = true;
            this.nameTab.visible = true;
            this.catalogueTab.visible = this.item.item_type_id !== 7;
            this.treeTab.visible = this.item.item_type_id !== 7;
            this.linksTab.visible =
              [5, 7, 8].indexOf(this.item.item_type_id) !== -1;
            this.logoTab.visible = this.item.item_type_id === 5;
            this.vehiclesTab.visible = this.item.item_type_id === 2;
            this.picturesTab.visible =
              [2, 1, 5, 6, 7, 8].indexOf(this.item.item_type_id) !== -1;

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
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
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
}
