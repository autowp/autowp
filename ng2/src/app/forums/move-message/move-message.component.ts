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

@Component({
  selector: 'app-forums-move-message',
  templateUrl: './move-message.component.html'
})
@Injectable()
export class ForumsMoveMessageComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public message_id: number;
  public themes: APIForumTheme[] = [];
  public theme: APIForumTheme = null;
  public topics: APIForumTopic[] = [];

  constructor(
    private http: HttpClient,
    private forumService: ForumService,
    private router: Router,
    private route: ActivatedRoute
  ) {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/83/name',
      pageId: 83
    });*/
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.message_id = params.message_id;
    });

    this.load();
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private load() {
    this.forumService.getThemes({}).subscribe(
      response => {
        this.themes = response.items;
      },
      response => {
        Notify.response(response);
      }
    );
  }

  public selectTheme(theme: APIForumTheme) {
    this.theme = theme;
    this.forumService.getTopics({ theme_id: theme.id }).subscribe(
      response => {
        this.topics = response.items;
      },
      response => {
        Notify.response(response);
      }
    );
  }

  public selectTopic(topic: APIForumTopic) {
    this.http
      .put<void>('/api/comment/' + this.message_id, {
        item_id: topic.id
      })
      .subscribe(
        response => {
          this.forumService.getMessageStateParams(this.message_id).then(
            (params: MessageStateParams) => {
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
