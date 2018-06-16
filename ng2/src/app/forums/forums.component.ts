import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import { ACLService } from '../services/acl.service';
import Notify from '../notify';
import { TranslateService } from '@ngx-translate/core';
import { Subscription, combineLatest } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { ForumService, APIForumTopic, APIForumTheme } from '../services/forum';
import { PageEnvService } from '../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  map
} from 'rxjs/operators';

@Component({
  selector: 'app-forums',
  templateUrl: './forums.component.html',
  styles: ['app-forums {display:block}']
})
@Injectable()
export class ForumsComponent implements OnInit, OnDestroy {
  private paramsSub: Subscription;
  public paginator: APIPaginator;
  public forumAdmin = false;
  public theme: APIForumTheme;
  public themes: APIForumTheme[];

  constructor(
    private http: HttpClient,
    private acl: ACLService,
    private translate: TranslateService,
    private route: ActivatedRoute,
    private forumService: ForumService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.paramsSub = combineLatest(
      this.route.params,
      this.route.queryParams,
      this.acl.isAllowed('forums', 'moderate'),
      (route, query, forumAdmin) => ({
        route,
        query,
        forumAdmin
      })
    )
      .pipe(
        distinctUntilChanged(),
        debounceTime(50),
        switchMap(
          data => {
            if (!data.route.theme_id) {
              return this.forumService
                .getThemes({
                  fields:
                    'last_message.datetime,last_message.user,last_topic,description',
                  topics: { page: data.query.page }
                })
                .pipe(
                  map(response => ({
                    theme: null as APIForumTheme,
                    themes: response.items
                  }))
                );
            } else {
              return this.forumService
                .getTheme(data.route.theme_id, {
                  fields:
                    'themes.last_message.user,themes.last_message.datetime,themes.last_topic,' +
                    'themes.description,topics.author,topics.messages,topics.last_message.datetime,topics.last_message.user',
                  topics: { page: data.query.page }
                })
                .pipe(
                  map(response => ({
                    theme: response,
                    themes: response.themes
                  }))
                );
            }
          },
          (params, data) => ({
            forumAdmin: params.forumAdmin,
            theme: data.theme,
            themes: data.themes
          })
        )
      )
      .subscribe(data => {
        this.forumAdmin = data.forumAdmin;
        this.theme = data.theme;
        this.themes = data.themes;

        if (this.theme) {
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
        } else {
          setTimeout(
            () =>
              this.pageEnv.set({
                layout: {
                  needRight: false
                },
                name: 'page/42/name',
                pageId: 42
              }),
            0
          );
        }
      });
  }

  ngOnDestroy(): void {
    this.paramsSub.unsubscribe();
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
