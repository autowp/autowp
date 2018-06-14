import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { APIUserGetResponse, UserService, APIUser } from '../../services/user';
import {
  APIAttrUserValueGetResponse,
  AttrsService,
  APIAttrUserValue
} from '../../services/attrs';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription, Observable, of, empty } from 'rxjs';
import { ACLService } from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  catchError,
  map
} from 'rxjs/operators';
import { NgbTypeaheadSelectItemEvent } from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-cars-attrs-change-log',
  templateUrl: './attrs-change-log.component.html'
})
@Injectable()
export class CarsAttrsChangeLogComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public items: APIAttrUserValue[] = [];
  public paginator: APIPaginator;
  public isModer = false;

  public userID: number;
  public userQuery = '';
  public usersDataSource: (text$: Observable<string>) => Observable<any[]>;

  constructor(
    private userService: UserService,
    private attrService: AttrsService,
    private route: ActivatedRoute,
    private router: Router,
    private acl: ACLService,
    private pageEnv: PageEnvService
  ) {
    this.usersDataSource = (text$: Observable<string>) =>
      text$.pipe(
        debounceTime(200),
        switchMap(query => {
          if (query === '') {
            return of([]);
          }

          const params = {
            limit: 10,
            id: [],
            search: ''
          };
          if (query.substring(0, 1) === '#') {
            params.id.push(parseInt(query.substring(1), 10));
          } else {
            params.search = query;
          }

          return this.userService.get(params).pipe(
            catchError((err, caught) => {
              console.log(err, caught);
              return empty();
            }),
            map(response => response.items)
          );
        })
      );
  }

  ngOnInit(): void {
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

    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    /* const $userIdElement = $($element[0]).find(':input[name=user_id]');
    $userIdElement.val(this.user_id ? '#' + this.user_id : '');
    let userIdLastValue = $userIdElement.val();
    $userIdElement
      .on('typeahead:select', (ev: any, item: any) => {
        userIdLastValue = item.name;
        this.user_id = item.id;
        this.load();
      })
      .bind('change blur', (ev: any, item: any) => {
        const curValue = $(this).val();
        if (userIdLastValue && !curValue) {
          this.user_id = 0;
          this.load();
        }
        userIdLastValue = curValue;
      })
      .typeahead(
        {},
        {
          display: (item: any) => {
            return item.name;
          },
          templates: {
            suggestion: (item: any) => {
              return $('<div class="tt-suggestion tt-selectable"></div>').text(
                item.name
              );
            }
          },
          source: (
            query: string,
            syncResults: Function,
            asyncResults: Function
          ) => {
            const params = {
              limit: 10,
              id: [],
              search: ''
            };
            if (query.substring(0, 1) === '#') {
              params.id.push(parseInt(query.substring(1), 10));
            } else {
              params.search = query;
            }

            this.userService.get(params).subscribe(response => {
              asyncResults(response.items);
            });
          }
        }
      );*/

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(
          params =>
            this.attrService.getUserValues({
              user_id: params.user_id ? params.user_id : null,
              item_id: params.item_id,
              page: params.page,
              fields: 'user,item.name_html,path,unit,value_text'
            }),
          (params, items) => ({ params, items })
        )
      )
      .subscribe(data => {
        this.userID = data.params.user_id ? parseInt(data.params.user_id, 10) : 0;
        if (this.userID && !this.userQuery) {
          this.userQuery = '#' + this.userID;
        }
        this.items = data.items.items;
        this.paginator = data.items.paginator;

        /*this.$state.go(STATE_NAME, params, {
          notify: false,
          reload: false,
          location: 'replace'
        });*/
      });
  }

  userFormatter(x: APIUser) {
    return x.name;
  }

  userOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        user_id: e.item.id
      }
    });
  }

  clearUser(): void {
    this.userQuery = '';
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        user_id: null
      }
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
