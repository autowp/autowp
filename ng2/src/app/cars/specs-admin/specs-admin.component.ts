import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { AttrsService, APIAttrUserValue } from '../../services/attrs';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-cars-specs-admin',
  templateUrl: './specs-admin.component.html'
})
@Injectable()
export class CarsSpecsAdminComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public values: APIAttrUserValue[] = [];
  public paginator: APIPaginator;
  public move = {
    item_id: null
  };

  private item_id = 0;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private attrService: AttrsService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/103/name',
          pageId: 103
        }),
      0
    );

    this.load();
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.item_id = params.item_id;
      this.load();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private load() {
    this.attrService
      .getUserValues({
        item_id: this.item_id,
        fields: 'user,path,unit'
      })
      .subscribe(
        response => {
          this.values = response.items;
          this.paginator = response.paginator;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public deleteValue(value: APIAttrUserValue) {
    this.http
      .delete(
        '/api/attr/user-value/' +
          value.attribute_id +
          '/' +
          value.item_id +
          '/' +
          value.user_id
      )
      .subscribe(
        response => {
          for (let i = 0; i < this.values.length; i++) {
            if (this.values[i] === value) {
              this.values.splice(i, 1);
              break;
            }
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public moveValues() {
    this.http
      .patch<void>(
        '/api/attr/user-value',
        {
          item_id: this.move.item_id
        },
        {
          params: {
            item_id: this.item_id.toString()
          }
        }
      )
      .subscribe(
        response => {
          this.load();
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
