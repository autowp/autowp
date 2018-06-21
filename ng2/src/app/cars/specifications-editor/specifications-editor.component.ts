import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { Subscription, combineLatest, BehaviorSubject } from 'rxjs';
import { APIItem, ItemService } from '../../services/item';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../services/acl.service';
import { ActivatedRoute, Router } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';
import { APIUser } from '../../services/user';
import Notify from '../../notify';
import {
  switchMap,
  distinctUntilChanged,
  debounceTime,
  switchMapTo
} from 'rxjs/operators';

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
  private change$ = new BehaviorSubject<null>(null);

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private acl: ACLService,
    private router: Router,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
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

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.tab = params.tab || 'info';

          return combineLatest(
            this.change$.pipe(
              switchMapTo(
                this.itemService.getItem(params.item_id, {
                  fields: 'name_html,name_text,engine_id,attr_zone_id'
                })
              )
            ),
            this.acl.isAllowed('specifications', 'admin'),
            this.acl.inheritsRole('moder'),
            (item, isSpecsAdmin, isModer) => ({ item, isSpecsAdmin, isModer })
          );
        })
      )
      .subscribe(
        data => {
          this.isSpecsAdmin = data.isSpecsAdmin;
          this.isModer = data.isModer;

          this.item = data.item;

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
        },
        () => {
          this.router.navigate(['/error-404']);
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public onEngineChanged() {
    this.change$.next(null);
  }

  public refreshInheritance() {
    this.http
      .post<void>('/api/item/' + this.item.id + '/refresh-inheritance', {})
      .subscribe(
        () => {
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
