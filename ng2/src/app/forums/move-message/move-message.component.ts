import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import {
  ForumService,
  MessageStateParams,
  APIForumTheme,
  APIForumTopic
} from '../../services/forum';
import { Subscription } from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';

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
    this.querySub = this.route.queryParams.subscribe(params => {
      console.log('params', params);
      this.messageID = parseInt(params.message_id, 10);
      this.themeID = parseInt(params.theme_id, 10);

      if (this.themeID) {
        this.forumService
          .getTopics({ theme_id: this.themeID })
          .subscribe(
            response => (this.topics = response.items),
            response => Notify.response(response)
          );
      } else {
        this.forumService
          .getThemes({})
          .subscribe(
            response => (this.themes = response.items),
            response => Notify.response(response)
          );
      }
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
      .subscribe(
        response => {
          this.forumService.getMessageStateParams(this.messageID).subscribe(
            params => {
              this.router.navigate(['/forums/topic', params.topic_id], {
                queryParams: {
                  page: params.page
                }
              });
            },
            subresponse => {
              Notify.response(subresponse);
            }
          );
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
