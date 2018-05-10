import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-account-delete',
  templateUrl: './delete.component.html'
})
@Injectable()
export class AccountDeleteComponent {
  public form = {
    password_old: ''
  };
  public invalidParams: any;

  constructor(
    private http: HttpClient,
    private router: Router,
    private auth: AuthService
  ) {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/137/name',
      pageId: 137
    });*/
  }

  public submit() {
    this.http
      .put<void>('/api/user/me', {
        password_old: this.form.password_old,
        deleted: 1
      })
      .subscribe(
        () => {
          this.auth.user = null;
          this.auth.setLoggedIn(false);
          this.router.navigate(['/account/delete/deleted']);
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
