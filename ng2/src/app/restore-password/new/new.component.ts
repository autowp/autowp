import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-restore-password-new',
  templateUrl: './new.component.html'
})
@Injectable()
export class RestorePasswordNewComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public form = {
    code: '',
    password: '',
    password_confirm: ''
  };
  public invalidParams: any;
  public failure = false;

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    /*$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/134/name',
      pageId: 134
    });*/
    this.routeSub = this.route.params.subscribe(params => {
      this.form.code = params.code;

      this.http
        .get('/api/restore-password/new', {
          params: {
            code: params.code
          }
        })
        .subscribe(
          () => {},
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
    this.http
      .post('/api/restore-password/new', {
        data: this.form
      })
      .subscribe(
        () => {
          this.router.navigate(['/restore-password/new/ok']);
        },
        response => {
          this.failure = response.status === 404;
          if (response.status === 400) {
            this.invalidParams = response.error.invalid_params;
          } else if (response.status === 404) {
          } else {
            Notify.response(response);
          }
        }
      );
  }
}
