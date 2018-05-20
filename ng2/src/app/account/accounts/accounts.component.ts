import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router } from '@angular/router';
import {
  APIAccountStartPostResponse,
  APIAccountItemsGetResponse,
  APIAccount
} from '../../services/account.service';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-account-accounts',
  templateUrl: './accounts.component.html'
})
@Injectable()
export class AccountAccountsComponent {
  public service = null;
  public accounts: APIAccount[] = [];
  public connectFailed = false;
  public disconnectFailed = false;
  public services = [
    {
      id: 'facebook',
      name: 'Facebook'
    },
    {
      id: 'vk',
      name: 'VK'
    },
    {
      id: 'google-plus',
      name: 'Google+'
    },
    {
      id: 'twitter',
      name: 'Twitter'
    },
    {
      id: 'github',
      name: 'Github'
    },
    {
      id: 'linkedin',
      name: 'Linkedin'
    }
  ];

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private router: Router,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/123/name',
          pageId: 123
        }),
      0
    );

    this.load();
  }

  public load() {
    this.http.get<APIAccountItemsGetResponse>('/api/account').subscribe(
      response => {
        this.accounts = response.items;
      },
      response => {
        Notify.response(response);
      }
    );
  }

  public start() {
    if (!this.service) {
      return;
    }

    this.http
      .post<APIAccountStartPostResponse>('/api/account/start', {
        service: this.service
      })
      .subscribe(
        response => {
          window.location.href = response.url;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public remove(account: APIAccount) {
    this.http.delete('/api/account/' + account.id).subscribe(
      response => {
        this.translate
          .get('account/accounts/removed')
          .subscribe((translation: string) => {
            Notify.custom(
              {
                icon: 'fa fa-check',
                message: translation
              },
              {
                type: 'success'
              }
            );
          });

        this.load();
      },
      response => {
        this.disconnectFailed = true;
        Notify.response(response);
      }
    );
  }
}
