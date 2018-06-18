import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import {
  ForumService,
  APIForumTopic,
  APIForumTheme
} from '../../services/forum';
import { PageEnvService } from '../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

@Component({
  selector: 'app-forums-move-topic',
  templateUrl: './move-topic.component.html'
})
@Injectable()
export class ForumsMoveTopicComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public message_id: number;
  public themes: APIForumTheme[] = [];
  public topic: APIForumTopic = null;

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private forumService: ForumService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/83/name',
      pageId: 83
    });

    this.forumService.getThemes({}).subscribe(
      response => {
        this.themes = response.items;
      },
      response => {
        Notify.response(response);
      }
    );

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => this.forumService.getTopic(params.topic_id, {}))
      )
      .subscribe(
        response => {
          this.topic = response;
        },
        response => {
          this.router.navigate(['/error-404']);
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public selectTheme(theme: APIForumTheme) {
    this.http
      .put<void>('/api/forum/topic/' + this.topic.id, {
        theme_id: theme.id
      })
      .subscribe(
        response => {
          this.router.navigate(['/forums/topic', this.topic.id]);
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
