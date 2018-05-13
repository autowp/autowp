import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import { ACLService } from '../services/acl.service';
import Notify from '../notify';
import { TranslateService } from '@ngx-translate/core';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import {
  APIForumThemesGetResponse,
  ForumService,
  APIForumTopic,
  APIForumTheme
} from '../services/forum';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-forums',
  templateUrl: './forums.component.html'
})
@Injectable()
export class ForumsComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  private querySub: Subscription;
  public paginator: APIPaginator;
  public forumAdmin = false;
  public theme: APIForumTheme;
  public themes: APIForumTheme[];
  private theme_id: number;
  private page: number;

  constructor(
    private http: HttpClient,
    private acl: ACLService,
    private translate: TranslateService,
    private route: ActivatedRoute,
    private router: Router,
    private forumService: ForumService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.acl.isAllowed('forums', 'moderate').then(
      allow => {
        this.forumAdmin = !!allow;
      },
      () => {
        this.forumAdmin = false;
      }
    );

    this.routeSub = this.route.params.subscribe(params => {
      this.theme_id = params.theme_id;
      this.load();
    });
    this.querySub = this.route.queryParams.subscribe(params => {
      this.page = params.page;
      this.load();
    });

    this.load();
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }

  private load() {
    if (!this.theme_id) {
      this.pageEnv.set({
        layout: {
          needRight: false
        },
        name: 'page/42/name',
        pageId: 42
      });

      this.forumService
        .getThemes({
          fields:
            'last_message.datetime,last_message.user,last_topic,description',
          topics: {
            page: this.page
          }
        })
        .subscribe(
          response => {
            this.themes = response.items;
          },
          response => {
            Notify.response(response);
          }
        );
    } else {
      this.forumService
        .getTheme(this.theme_id, {
          fields:
            'themes.last_message.user,themes.last_message.datetime,themes.last_topic,' +
            'themes.description,topics.author,topics.messages,topics.last_message.datetime,topics.last_message.user',
          topics: { page: this.page }
        })
        .subscribe(
          response => {
            this.theme = response;
            this.themes = response.themes;

            this.translate.get(this.theme.name).subscribe(
              (translation: string) => {
                this.pageEnv.set({
                  layout: {
                    needRight: false
                  },
                  name: 'page/43/name',
                  pageId: 43,
                  args: {
                    THEME_NAME: translation,
                    THEME_ID: this.theme.id + ''
                  }
                });
              },
              () => {
                this.pageEnv.set({
                  layout: {
                    needRight: false
                  },
                  name: 'page/43/name',
                  pageId: 43,
                  args: {
                    THEME_NAME: this.theme.name,
                    THEME_ID: this.theme.id + ''
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
    }
  }

  public openTopic(topic: APIForumTopic) {
    this.http
      .put<void>('/api/forum/topic/' + topic.id, {
        status: 'normal'
      })
      .subscribe(
        response => {
          topic.status = 'normal';
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public closeTopic(topic: APIForumTopic) {
    this.http
      .put<void>('/api/forum/topic/' + topic.id, {
        status: 'closed'
      })
      .subscribe(
        response => {
          topic.status = 'closed';
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public deleteTopic(topic: APIForumTopic) {
    this.http
      .put<void>('/api/forum/topic/' + topic.id, {
        status: 'deleted'
      })
      .subscribe(
        response => {
          for (let i = this.theme.topics.items.length - 1; i >= 0; i--) {
            if (this.theme.topics.items[i].id === topic.id) {
              this.theme.topics.items.splice(i, 1);
              break;
            }
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
