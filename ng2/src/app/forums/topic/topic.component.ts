import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ForumService, APIForumTopic } from '../../services/forum';
import { TranslateService } from '@ngx-translate/core';
import { Subscription, zip, BehaviorSubject, combineLatest } from 'rxjs';
import { ActivatedRoute, Router, Params } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';
import { debounceTime } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-forums-topic',
  templateUrl: './topic.component.html'
})
@Injectable()
export class ForumsTopicComponent implements OnInit, OnDestroy {
  private paramsSub: Subscription;
  public topic: APIForumTopic;
  public paginator: APIPaginator;
  public page: number;
  public limit: number;
  private topic_id: number;
  private load$ = new BehaviorSubject<boolean>(true);

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private forumService: ForumService,
    private route: ActivatedRoute,
    private router: Router,
    private pageEnv: PageEnvService,
    public auth: AuthService
  ) {}

  ngOnInit(): void {
    this.limit = this.forumService.getLimit();

    this.load$.pipe(debounceTime(50)).subscribe(() => {
      this.forumService
        .getTopic(this.topic_id, {
          fields: 'author,theme,subscription',
          page: this.page
        })
        .subscribe(
          response => {
            this.topic = response;

            this.translate.get(this.topic.theme.name).subscribe(
              (translation: string) => {
                this.pageEnv.set({
                  layout: {
                    needRight: false
                  },
                  name: 'page/44/name',
                  pageId: 44,
                  args: {
                    THEME_NAME: translation,
                    THEME_ID: this.topic.theme_id + '',
                    TOPIC_NAME: this.topic.name,
                    TOPIC_ID: this.topic.id + ''
                  }
                });
              },
              () => {
                this.pageEnv.set({
                  layout: {
                    needRight: false
                  },
                  name: 'page/44/name',
                  pageId: 44,
                  args: {
                    THEME_NAME: this.topic.theme.name,
                    THEME_ID: this.topic.theme_id + '',
                    TOPIC_NAME: this.topic.name,
                    TOPIC_ID: this.topic.id + ''
                  }
                });
              }
            );
          },
          response => {
            Notify.response(response);

            this.router.navigate(['/error-404']);
          }
        );
    });

    this.paramsSub = combineLatest(
      this.route.params,
      this.route.queryParams,
      (route: Params, query: Params) => ({
        route,
        query
      })
    ).subscribe(data => {
      this.topic_id = parseInt(data.route.topic_id, 10);
      this.page = parseInt(data.query.page, 10);
      this.load$.next(true);
    });
  }

  ngOnDestroy(): void {
    this.paramsSub.unsubscribe();
  }

  public subscribe() {
    this.http
      .put<void>('/api/forum/topic/' + this.topic.id, {
        subscription: 1
      })
      .subscribe(
        response => {
          this.topic.subscription = true;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public unsubscribe() {
    this.http
      .put<void>('/api/forum/topic/' + this.topic.id, {
        subscription: 0
      })
      .subscribe(
        response => {
          this.topic.subscription = false;
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
