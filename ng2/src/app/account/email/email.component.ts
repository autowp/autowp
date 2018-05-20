import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Router } from '@angular/router';
import { APIUser } from '../../services/user';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-account-email',
  templateUrl: './email.component.html'
})
@Injectable()
export class AccountEmailComponent {
  public email: string | null = null;
  public newEmail: string | null = null;
  public invalidParams: any;
  public sent = false;

  constructor(
    private http: HttpClient,
    private router: Router,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/55/name',
          pageId: 55
        }),
      0
    );
    this.http
      .get<APIUser>('/api/user/me', {
        params: {
          fields: 'email'
        }
      })
      .subscribe(
        response => {
          this.email = response.email;
          this.newEmail = response.email;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public submit() {
    this.invalidParams = {};

    this.http
      .put<void>('/api/user/me', {
        email: this.newEmail
      })
      .subscribe(
        () => {
          this.sent = true;
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
