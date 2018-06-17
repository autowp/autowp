import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import {
  ItemService,
  GetItemsServiceOptions,
  APIItem
} from '../../services/item';
import Notify from '../../notify';
import { UserService, APIUser } from '../../services/user';
import { Subscription, Observable, empty, of } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { CommentService } from '../../services/comment';
import { PageEnvService } from '../../services/page-env.service';
import { switchMap, debounceTime, catchError, map } from 'rxjs/operators';
import { NgbTypeaheadSelectItemEvent } from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-moder-comments',
  templateUrl: './comments.component.html'
})
@Injectable()
export class ModerCommentsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public loading = 0;
  public comments = [];
  public paginator: APIPaginator;
  public moderatorAttention: any;

  public itemID: number;
  public itemQuery = '';
  public itemsDataSource: (text$: Observable<string>) => Observable<any[]>;

  public userID: number;
  public userQuery = '';
  public usersDataSource: (text$: Observable<string>) => Observable<any[]>;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private userService: UserService,
    private route: ActivatedRoute,
    private commentService: CommentService,
    private pageEnv: PageEnvService,
    private router: Router
  ) {
    this.itemsDataSource = (text$: Observable<string>) =>
      text$.pipe(
        debounceTime(200),
        switchMap(query => {
          if (query === '') {
            return of([]);
          }

          const params: GetItemsServiceOptions = {
            limit: 10,
            fields: 'name_text,name_html',
            id: 0,
            name: ''
          };
          if (query.substring(0, 1) === '#') {
            params.id = parseInt(query.substring(1), 10);
          } else {
            params.name = '%' + query + '%';
          }

          return this.itemService.getItems(params).pipe(
            catchError((err, caught) => {
              console.log(err, caught);
              return empty();
            }),
            map(response => response.items)
          );
        })
      );

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
            isAdminPage: true,
            needRight: false
          },
          name: 'page/110/name',
          pageId: 110
        }),
      0
    );

    this.querySub = this.route.queryParams
      .pipe(
        switchMap(params => {
          this.userID = params.user_id;
          this.moderatorAttention =
            params.moderator_attention === undefined
              ? null
              : +params.moderator_attention;
          this.itemID = params.pictures_of_item_id;

          this.loading++;

          return this.commentService.getComments({
            user: this.userID,
            moderator_attention: this.moderatorAttention,
            pictures_of_item_id: this.itemID ? this.itemID : 0,
            page: params.page,
            order: 'date_desc',
            limit: 30,
            fields: 'preview,user,is_new,status,url'
          });
        })
      )
      .subscribe(
        response => {
          this.comments = response.items;
          this.paginator = response.paginator;
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public setModeratorAttention() {
    this.router.navigate([], {
      queryParams: {
        page: null,
        moderator_attention: this.moderatorAttention
      },
      queryParamsHandling: 'merge'
    });
  }

  public itemFormatter(x: APIItem) {
    return x.name_text;
  }

  public itemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        pictures_of_item_id: e.item.id
      }
    });
  }

  public clearItem(): void {
    this.itemQuery = '';
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        pictures_of_item_id: null
      }
    });
  }

  public userFormatter(x: APIUser) {
    return x.name;
  }

  public userOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        user_id: e.item.id
      }
    });
  }

  public clearUser(): void {
    this.userQuery = '';
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        user_id: null
      }
    });
  }
}
