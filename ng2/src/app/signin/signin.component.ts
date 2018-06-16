import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../services/auth.service';
import {
  APILoginServicesGetResponse,
  APILoginStartPostResponse
} from '../services/api.service';
import { PageEnvService } from '../services/page-env.service';
import { APIUser } from '../services/user';

interface SignInService {
  id: string;
  name: string;
  icon: string;
}

@Component({
  selector: 'app-signin',
  templateUrl: './signin.component.html'
})
@Injectable()
export class SignInComponent {
  public services: SignInService[] = [];
  public form = {
    login: '',
    password: '',
    remember: false
  };
  public invalidParams: any = {};
  public user: APIUser;

  constructor(
    public auth: AuthService,
    private http: HttpClient,
    private pageEnv: PageEnvService
  ) {
    this.auth.getUser().subscribe(user => (this.user = user));

    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/79/name',
          pageId: 79
        }),
      0
    );

    this.http.get<APILoginServicesGetResponse>('/api/login/services').subscribe(
      response => {
        for (const key in response.items) {
          if (response.items.hasOwnProperty(key)) {
            const item = response.items[key];
            this.services.push({
              id: key,
              name: item.name,
              icon: item.icon
            });
          }
        }
      },
      response => {
        console.log(response);
      }
    );
  }

  public submit($event) {
    $event.preventDefault();

    this.auth
      .login(this.form.login, this.form.password, this.form.remember)
      .subscribe(
        user => {},
        response => {
          if (response.status === 400) {
            this.invalidParams = response.error.invalid_params;
          } else {
            console.log(response);
          }
        }
      );
  }

  public start(serviceId: string) {
    this.http
      .get<APILoginStartPostResponse>('/api/login/start', {
        params: {
          type: serviceId
        }
      })
      .subscribe(
        response => {
          window.location.href = response.url;
        },
        response => {
          console.log(response);
        }
      );
  }
}
