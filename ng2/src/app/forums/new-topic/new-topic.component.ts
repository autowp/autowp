import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription, combineLatest } from 'rxjs';
import { APIForumTheme, ForumService } from '../../services/forum';
import { AuthService } from '../../services/auth.service';
import { PageEnvService } from '../../services/page-env.service';
import { APIUser } from '../../services/user';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

@Component({
  selector: 'app-forums-new-topic',
  templateUrl: './new-topic.component.html'
})
@Injectable()
export class ForumsNewTopicComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public form = {
    name: '',
    text: '',
    moderator_attention: false,
    subscription: false
  };
  public invalidParams: any;
  public theme: APIForumTheme;
  public user: APIUser;

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private forumService: ForumService,
    public auth: AuthService,
    private pageEnv: PageEnvService
  ) { }

  ngOnInit(): void {
    this.routeSub = combineLatest(
      this.route.params,
      this.auth.getUser(),
      (params, user) => ({ params, user })
    )
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(
          data => this.forumService.getTheme(data.params.theme_id, {}),
          (data, theme) => ({
            user: data.user,
            theme: theme
          })
        )
      )
      .subscribe(
        data => {
          this.theme = data.theme;
          this.user = data.user;

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: 'page/45/name',
            pageId: 45,
            args: {
              THEME_NAME: this.theme.name,
              THEME_ID: this.theme.id + ''
            }
          });
        },
        response => {
          this.router.navigate(['/error-404']);
        }
      );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public submit() {
    this.invalidParams = {};

    this.forumService
      .postTopic({
        theme_id: this.theme.id,
        name: this.form.name,
        text: this.form.text,
        moderator_attention: this.form.moderator_attention,
        subscription: this.form.subscription
      })
      .subscribe(
        response => {
          const location = response.headers.get('Location');

          this.forumService.getTopicByLocation(location, {}).subscribe(
            topic => {
              this.router.navigate(['/forums/topic', topic.id]);
            },
            subresponse => {
              Notify.response(response);
            }
          );
        },
        response => {
          if (response.status === 400) {
            this.invalidParams = response.error.invalid_params;
          } else {
            Notify.response(response);
          }
        }
      );
  }
}
