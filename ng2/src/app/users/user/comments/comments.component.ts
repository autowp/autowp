import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../services/api.service';
import Notify from '../../../notify';
import { UserService, APIUser } from '../../../services/user';
import { Router, ActivatedRoute, Params } from '@angular/router';
import { Subscription, empty, from, combineLatest } from 'rxjs';
import { CommentService, APIComment } from '../../../services/comment';
import { PageEnvService } from '../../../services/page-env.service';
import {
  switchMap,
  distinctUntilChanged,
  debounceTime,
  catchError,
  map,
  tap
} from 'rxjs/operators';

interface Order {
  name: string;
  value: string;
}

@Component({
  selector: 'app-users-user-comments',
  templateUrl: './comments.component.html'
})
@Injectable()
export class UsersUserCommentsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public loading = 0;
  public user: APIUser;
  public paginator: APIPaginator;
  public comments: APIComment[];
  public orders: Order[] = [
    { value: 'date_desc', name: 'users/comments/order/new' },
    { value: 'date_asc', name: 'users/comments/order/old' },
    { value: 'vote_desc', name: 'users/comments/order/positive' },
    { value: 'vote_asc', name: 'users/comments/order/negative' }
  ];
  public order: string;
  private page: number;

  constructor(
    private http: HttpClient,
    private userService: UserService,
    private router: Router,
    private route: ActivatedRoute,
    private commentService: CommentService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.querySub = combineLatest(
      this.route.params.pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          return this.userService.getByIdentity(params.identity, {fields: 'identity'}).pipe(
            catchError((err, caught) => {
              Notify.response(err);
              return empty();
            })
          );
        }),
        tap(user => {
          if (!user) {
            this.router.navigate(['/error-404']);
            return;
          }

          setTimeout(
            () =>
              this.pageEnv.set({
                layout: {
                  needRight: false
                },
                name: 'page/205/name',
                pageId: 205,
                args: {
                  USER_NAME: user.name,
                  USER_IDENTITY: user.identity
                    ? user.identity
                    : 'user' + user.id
                }
              }),
            0
          );

          this.user = user;
        })
      ),
      this.route.queryParams.pipe(
        distinctUntilChanged(),
        debounceTime(30)
      ),
      (user: APIUser, query: Params) => ({
        user,
        query
      })
    )
      .pipe(
        switchMap(data => {
          this.order = data.query.order || 'date_desc';

          return this.commentService
            .getComments({
              user_id: data.user.id,
              page: data.query.page,
              limit: 30,
              order: this.order,
              fields: 'preview,url,vote'
            })
            .pipe(
              catchError((err, caught) => {
                Notify.response(err);
                return empty();
              }),
              tap(response => {
                this.comments = response.items;
                this.paginator = response.paginator;
              })
            );
        })
      )
      .subscribe();
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
