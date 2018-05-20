import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../services/api.service';
import Notify from '../../../notify';
import { UserService, APIUser } from '../../../services/user';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { CommentService, APIComment } from '../../../services/comment';
import { PageEnvService } from '../../../services/page-env.service';

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
  private routeSub: Subscription;
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
    this.routeSub = this.route.params.subscribe(params => {
      const result = params.identity.match(/^user([0-9]+)$/);

      if (result) {
        this.userService
          .getUser(result[1], {
            fields: 'identity'
          })
          .then(
            response => {
              this.user = response;
              this.init();
            },
            response => {
              Notify.response(response);
            }
          );
      } else {
        this.userService
          .get({
            identity: params.identity,
            limit: 1,
            fields: 'identity'
          })
          .subscribe(
            response => {
              if (response.items.length <= 0) {
                this.router.navigate(['/error-404']);
              }
              this.user = response.items[0];
              this.init();
            },
            response => {
              Notify.response(response);
            }
          );
      }
    });
    this.querySub = this.route.queryParams.subscribe(params => {
      this.order = params.order || 'date_desc';
      this.page = params.page;
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public init() {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/205/name',
          pageId: 205,
          args: {
            USER_NAME: this.user.name,
            USER_IDENTITY: this.user.identity
              ? this.user.identity
              : 'user' + this.user.id
          }
        }),
      0
    );

    this.loading++;
    this.commentService
      .getComments({
        user_id: this.user.id,
        page: this.page,
        limit: 30,
        order: this.order,
        fields: 'preview,url,vote'
      })
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
}
