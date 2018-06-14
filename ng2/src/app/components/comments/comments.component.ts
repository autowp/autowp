import { APIPaginator } from '../../services/api.service';
import {
  Injectable,
  Component,
  Input,
  OnChanges,
  SimpleChanges,
  OnInit,
  OnDestroy
} from '@angular/core';
import { APIUser } from '../../services/user';
import Notify from '../../notify';
import { Router } from '@angular/router';
import { CommentService, APIComment } from '../../services/comment';
import { AuthService } from '../../services/auth.service';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-comments',
  templateUrl: './comments.component.html'
})
@Injectable()
export class CommentsComponent implements OnChanges, OnInit, OnDestroy {
  private sub: Subscription;

  public messages: APIComment[] = [];
  public paginator: APIPaginator;
  public user: APIUser;

  @Input() itemID: number;
  @Input() typeID: number;
  @Input() limit: number;
  @Input() page: number;

  constructor(
    private router: Router,
    private commentService: CommentService,
    public auth: AuthService
  ) {}

  ngOnInit(): void {
    this.sub = this.auth.getUser().subscribe(user => (this.user = user));
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public onSent(location: string) {
    if (this.limit) {
      this.commentService
        .getCommentByLocation(location, {
          fields: 'page',
          limit: this.limit
        })
        .subscribe(
          response => {
            if (this.page !== response.page) {
              this.router.navigate([], {
                queryParams: { page: response.page },
                queryParamsHandling: 'merge'
              });
            } else {
              this.load();
            }
          },
          response => Notify.response(response)
        );
    } else {
      this.load();
    }
  }

  ngOnChanges(changes: SimpleChanges) {
    this.load();
  }

  public load() {
    if (!this.typeID || !this.itemID) {
      return;
    }

    this.commentService
      .getComments({
        type_id: this.typeID,
        item_id: this.itemID,
        no_parents: true,
        fields:
          'user.avatar,user.gravatar,replies,text_html,datetime,vote,user_vote',
        order: 'date_asc',
        limit: this.limit ? this.limit : null,
        page: this.page
      })
      .subscribe(
        response => {
          this.messages = response.items;
          this.paginator = response.paginator;
        },
        response => Notify.response(response)
      );
  }
}
