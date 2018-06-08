import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ActivatedRoute, Params } from '@angular/router';
import { Subscription, of, Subject, combineLatest, BehaviorSubject } from 'rxjs';
import { AttrsService, APIAttrUserValue } from '../../services/attrs';
import { PageEnvService } from '../../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  catchError,
  tap
} from 'rxjs/operators';

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
  public move$ = new BehaviorSubject<boolean>(false);

  private itemID = 0;

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
  }

  ngOnInit(): void {
    this.querySub = combineLatest(
      this.route.queryParams.pipe(
        debounceTime(10),
        distinctUntilChanged(),
        tap(params => (this.itemID = params.item_id))
      ),
      this.move$,
      (query: Params, move: any) => ({
        query,
        move
      })
    )
      .pipe(
        switchMap(params => {
          return this.attrService.getUserValues({
            item_id: params.query.item_id,
            page: params.query.page,
            fields: 'user,path,unit'
          });
        }),
        catchError((err, caught) => {
          if (err.status !== -1) {
            Notify.response(err);
          }
          return of({
            items: [],
            paginator: null
          });
        })
      )
      .subscribe(data => {
        this.values = data.items;
        this.paginator = data.paginator;
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
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
            item_id: this.itemID.toString()
          }
        }
      )
      .subscribe(
        response => {
          this.move$.next(true);
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
