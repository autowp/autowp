import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-account-access',
  templateUrl: './access.component.html'
})
@Injectable()
export class AccountAccessComponent {
  public invalidParams: any;
  public form: any;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private router: Router
  ) {
    this.form = {
      password_old: null,
      password: null,
      password_confirm: null
    };

    this.invalidParams = {};

    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/133/name',
      pageId: 133
    });*/
  }

  public submit() {
    this.invalidParams = {};

    this.http
      .put<void>('/api/user/me', this.form)
      .subscribe(
        () => {
          this.form = {
            password_old: null,
            password: null,
            password_confirm: null
          };

          this.translate.get('account/access/change-password/saved').subscribe(
            translation => {
              Notify.custom(
                {
                  icon: 'fa fa-check',
                  message: translation
                },
                {
                  type: 'success'
                }
              );
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
