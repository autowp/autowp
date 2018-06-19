import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import {
  ForumService,
  APIForumTheme,
  APIForumTopic
} from '../../services/forum';
import { Subscription, empty, of, combineLatest } from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  catchError,
  map,
  switchMapTo
} from 'rxjs/operators';

@Component({
  selector: 'app-forums-move-message',
  templateUrl: './move-message.component.html'
})
@Injectable()
export class ForumsMoveMessageComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public messageID: number;
  public themeID: number;
  public themes: APIForumTheme[] = [];
  public topics: APIForumTopic[] = [];

  constructor(
    private http: HttpClient,
    private forumService: ForumService,
    private router: Router,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/83/name',
          pageId: 83
        }),
      0
    );
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.messageID = parseInt(params.message_id, 10);
          this.themeID = parseInt(params.theme_id, 10);

          let topics = of(null as APIForumTopic[]);
          let themes = of(null as APIForumTheme[]);
          if (this.themeID) {
            topics = this.forumService
              .getTopics({ theme_id: this.themeID })
              .pipe(
                catchError(response => {
                  Notify.response(response);
                  return empty();
                }),
                map(response => response.items)
              );
          } else {
            themes = this.forumService.getThemes({}).pipe(
              catchError(response => {
                Notify.response(response);
                return empty();
              }),
              map(response => response.items)
            );
          }

          return combineLatest(topics, themes);
        })
      )
      .subscribe(data => {
        this.topics = data[0];
        this.themes = data[1];
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public selectTopic(topic: APIForumTopic) {
    this.http
      .put<void>('/api/comment/' + this.messageID, {
        item_id: topic.id
      })
      .pipe(
        switchMapTo(this.forumService.getMessageStateParams(this.messageID))
      )
      .subscribe(
        params =>
          this.router.navigate(['/forums/topic', params.topic_id], {
            queryParams: {
              page: params.page
            }
          }),
        subresponse => Notify.response(subresponse)
      );
  }
}
