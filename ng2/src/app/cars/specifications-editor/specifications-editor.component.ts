import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { Subscription } from 'rxjs';
import { APIItem, ItemService } from '../../services/item';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../services/acl.service';
import { ActivatedRoute, Router } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';
import { APIUser } from '../../services/user';
import Notify from '../../notify';

@Component({
  selector: 'app-cars-specifications-editor',
  templateUrl: './specifications-editor.component.html'
})
@Injectable()
export class CarsSpecificationsEditorComponent implements OnInit, OnDestroy {
  public enginesCount = 0;
  private querySub: Subscription;
  public item: APIItem;
  public isSpecsAdmin = false;
  public isModer = false;
  public resultHtml = '';
  public engine: APIItem;
  public tab = 'info';
  public loading = 0;
  public specsWeight: number;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private acl: ACLService,
    private router: Router,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.acl
      .isAllowed('specifications', 'admin')
      .then(
        allow => (this.isSpecsAdmin = !!allow),
        () => (this.isSpecsAdmin = false)
      );

    this.acl
      .inheritsRole('moder')
      .then(
        inherits => (this.isModer = !!inherits),
        () => (this.isModer = false)
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

    this.querySub = this.route.queryParams.subscribe(params => {
      this.tab = params.tab || 'info';

      this.loadItem(params.item_id);
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private loadItem(itemID: number) {
    this.loading++;
    this.itemService
      .getItem(itemID, {
        fields: 'name_html,name_text,engine_id,attr_zone_id'
      })
      .subscribe(
        item => {
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

          this.enginesCount = this.item.engine_id ? 1 : 0;

          if (this.tab === 'spec') {
          }

          this.loading--;
        },
        response => {
          this.router.navigate(['/error-404']);
          this.loading--;
        }
      );
  }

  public onEngineChanged() {
    this.loadItem(this.item.id);
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
}
