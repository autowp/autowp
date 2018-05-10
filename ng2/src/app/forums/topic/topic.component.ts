import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ForumService, APIForumTopic } from '../../services/forum';
import { TranslateService } from '@ngx-translate/core';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';

@Component({
  selector: 'app-forums-topic',
  templateUrl: './topic.component.html'
})
@Injectable()
export class ForumsTopicComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  private querySub: Subscription;
  public topic: APIForumTopic;
  public paginator: APIPaginator;
  public page: number;
  public limit: number;
  private topic_id: number;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private forumService: ForumService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.limit = this.forumService.getLimit();

    this.querySub = this.route.queryParams.subscribe(params => {
      this.page = params.page;
      this.load();
    });

    this.routeSub = this.route.params.subscribe(params => {
      this.topic_id = params.topic_id;
      this.load();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
    this.routeSub.unsubscribe();
  }

  private load() {
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
              /*this.$scope.pageEnv({
              layout: {
                blankPage: false,
                needRight: false
              },
              name: 'page/44/name',
              pageId: 44,
              args: {
                THEME_NAME: translation,
                THEME_ID: this.topic.theme_id,
                TOPIC_NAME: this.topic.name,
                TOPIC_ID: this.topic.id
              }
            });*/
            },
            () => {
              /*this.$scope.pageEnv({
              layout: {
                blankPage: false,
                needRight: false
              },
              name: 'page/44/name',
              pageId: 44,
              args: {
                THEME_NAME: this.topic.theme.name,
                THEME_ID: this.topic.theme_id,
                TOPIC_NAME: this.topic.name,
                TOPIC_ID: this.topic.id
              }
            });*/
            }
          );
        },
        response => {
          Notify.response(response);

          this.router.navigate(['/error-404']);
        }
      );
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
