import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { ForumService, APIForumTopic } from '../../services/forum';
import { PageEnvService } from '../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

@Component({
  selector: 'app-forums-subscriptions',
  templateUrl: './subscriptions.component.html'
})
@Injectable()
export class ForumsSubscriptionsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public topics: APIForumTopic[] = [];
  public paginator: APIPaginator;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private forumService: ForumService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
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

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params =>
          this.forumService.getTopics({
            fields: 'author,messages,last_message.datetime,last_message.user',
            subscription: true,
            page: params.page
          })
        )
      )
      .subscribe(
        response => {
          this.topics = response.items;
          this.paginator = response.paginator;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public unsubscribe(topic: APIForumTopic) {
    this.http
      .put<void>('/api/forum/topic/' + topic.id, {
        subscription: 0
      })
      .subscribe(
        response => {
          for (let i = this.topics.length - 1; i >= 0; i--) {
            if (this.topics[i].id === topic.id) {
              this.topics.splice(i, 1);
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
