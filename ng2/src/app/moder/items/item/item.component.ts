import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../../services/acl.service';
import { ItemService, APIItem } from '../../../services/item';
import { TranslateService } from '@ngx-translate/core';
import { Subscription, of, combineLatest } from 'rxjs';
import { ActivatedRoute, Router, Params } from '@angular/router';
import { APIPicture, PictureService } from '../../../services/picture';
import { PageEnvService } from '../../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  finalize,
  catchError,
  tap,
  switchMapTo
} from 'rxjs/operators';
import Notify from '../../../notify';

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
  private aclSub: Subscription;

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
    this.aclSub = this.acl
      .isAllowed('specifications', 'edit')
      .subscribe(allow => (this.canEditSpecifications = allow));

    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.loading++;
          return this.itemService.getItem(params.id, {
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
          });
        }),
        finalize(() => {
          this.loading--;
        }),
        catchError((err, caught) => {
          Notify.response(err);
          this.router.navigate(['/error-404']);
          return of(null);
        }),
        tap(item => {
          this.item = item;

          const typeID = item.item_type_id;

          this.specsAllowed = [1, 2].indexOf(typeID) !== -1;

          this.nameTab.count = item.item_language_count;
          this.logoTab.count = item.logo ? 1 : 0;
          this.catalogueTab.count = item.parents_count + item.childs_count;
          this.vehiclesTab.count = item.engine_vehicles_count;
          this.picturesTab.count = item.pictures_count;
          this.linksTab.count = item.links_count;

          this.metaTab.visible = true;
          this.nameTab.visible = true;
          this.catalogueTab.visible = typeID !== 7;
          this.treeTab.visible = typeID !== 7;
          this.linksTab.visible = [5, 7, 8].indexOf(typeID) !== -1;
          this.logoTab.visible = typeID === 5;
          this.vehiclesTab.visible = typeID === 2;
          this.picturesTab.visible = [2, 1, 5, 6, 7, 8].indexOf(typeID) !== -1;
        }),
        switchMap(
          item => {
            this.loading++;
            return combineLatest(
              this.translate.get('item/type/' + item.item_type_id + '/name'),
              this.pictureService.getPictures({
                fields: 'thumb_medium',
                limit: 1,
                item_id: item.id
              })
            );
          },
          (item, combined) => ({
            item: item,
            translation: combined[0],
            pictures: combined[1].pictures
          })
        ),
        finalize(() => {
          this.loading--;
        }),
        tap(data => {
          this.pageEnv.set({
            layout: {
              isAdminPage: true,
              needRight: false
            },
            name: 'page/78/name',
            pageId: 78,
            args: {
              CAR_ID: data.item.id + '',
              CAR_NAME: data.translation + ': ' + data.item.name_text
            }
          });
          this.randomPicture =
            data.pictures.length > 0 ? data.pictures[0] : null;
        }),
        switchMapTo(this.route.queryParams),
        distinctUntilChanged(),
        debounceTime(30),
        tap(params => {
          this.activeTab = params.tab ? params.tab : 'meta';

          switch (this.activeTab) {
            case 'tree':
              if (!this.treeTab.initialized) {
                this.treeTab.initialized = true;
                this.initTreeTab();
              }
              break;
          }
        })
      )
      .subscribe();
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.aclSub.unsubscribe();
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
