import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { APIForumTheme, ForumService } from '../../services/forum';
import { AuthService } from '../../services/auth.service';
import { PageEnvService } from '../../services/page-env.service';

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

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private forumService: ForumService,
    public auth: AuthService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.forumService.getTheme(params.theme_id, {}).subscribe(
        response => {
          this.theme = response;

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
    });
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
