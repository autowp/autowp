import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ForumService, APIForumTopic } from '../../services/forum';
import { TranslateService } from '@ngx-translate/core';
import { Subscription, combineLatest } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';
import { AuthService } from '../../services/auth.service';
import { switchMap } from 'rxjs/operators';
import { APIUser } from '../../services/user';

@Component({
  selector: 'app-forums-topic',
  templateUrl: './topic.component.html'
})
@Injectable()
export class ForumsTopicComponent implements OnInit, OnDestroy {
  private paramsSub: Subscription;
  public topic: APIForumTopic;
  public paginator: APIPaginator;
  public limit: number;
  public user: APIUser;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private forumService: ForumService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService,
    public auth: AuthService
  ) { }

  ngOnInit(): void {
    this.limit = this.forumService.getLimit();

    this.paramsSub = combineLatest(
      this.route.params,
      this.route.queryParams,
      this.auth.getUser(),
      (route, query, user) => ({ route, query, user })
    ).pipe(
      switchMap(data => {
        this.user = data.user;
        const topicID = parseInt(data.route.topic_id, 10);
        const page = parseInt(data.query.page, 10);
        return this.forumService
          .getTopic(topicID, {
            fields: 'author,theme,subscription',
            page: page
          });
      })
    ).subscribe(topic => {

      this.topic = topic;

      this.translate.get(this.topic.theme.name).subscribe(
        (translation: string) => {
          this.setPageEnv(translation);
        },
        () => {
          this.setPageEnv(this.topic.theme.name);
        });

    });
  }

  private setPageEnv(themeName: string) {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/44/name',
      pageId: 44,
      args: {
        THEME_NAME: themeName,
        THEME_ID: this.topic.theme_id + '',
        TOPIC_NAME: this.topic.name,
        TOPIC_ID: this.topic.id + ''
      }
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
