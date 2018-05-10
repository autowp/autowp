import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { APIItem, ItemService } from '../../services/item';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { ACLService } from '../../services/acl.service';

@Component({
  selector: 'app-factory-items',
  templateUrl: './items.component.html'
})
@Injectable()
export class FactoryItemsComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  private querySub: Subscription;
  public factory: APIItem;
  public items: APIItem[];
  public paginator: APIPaginator;
  private id = 0;
  private page = 1;
  public isModer = false;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private acl: ACLService
  ) {
    this.load();
  }

  ngOnInit(): void {
    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    this.routeSub = this.route.params.subscribe(params => {
      this.id = params.id;
      this.load();
    });

    this.querySub = this.route.queryParams.subscribe(params => {
      this.page = params.page;
      this.load();
    });
  }

  load() {
    this.factory = null;
    this.items = [];
    this.paginator = null;

    if (!this.id) {
      return;
    }

    this.itemService
      .getItem(this.id, {
        fields: ['name_text', 'name_html', 'lat', 'lng', 'description'].join(
          ','
        )
      })
      .subscribe(
        item => {
          this.factory = item;

          if (this.factory.item_type_id !== 6) {
            this.router.navigate(['/error-404']);
            return;
          }

          /*this.$scope.pageEnv({
          layout: {
            blankPage: false,
            needRight: true
          },
          name: 'page/182/name',
          pageId: 182,
          args: {
            FACTORY_ID: this.factory.id,
            FACTORY_NAME: this.factory.name_text
          }
        });*/

          this.itemService
            .getItems({
              related_groups_of: this.factory.id,
              page: this.page,
              limit: 10,
              fields: [
                'name_html,name_default,description,has_text,produced',
                'design,engine_vehicles',
                'url,spec_editor_url,specs_url,more_pictures_url',
                'categories.url,categories.name_html,twins_groups',
                'preview_pictures.picture.thumb_medium,childs_count,total_pictures'
              ].join(',')
            })

            .subscribe(
              response => {
                this.items = response.items;
                this.paginator = response.paginator;
              },
              response => {
                Notify.response(response);
              }
            );
        },
        response => {
          this.router.navigate(['/error-404']);
        }
      );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }
}
