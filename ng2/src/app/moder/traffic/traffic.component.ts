import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { IpService } from '../../services/ip';
import { Router } from '@angular/router';
import { APIUser } from '../../services/user';
import { PageEnvService } from '../../services/page-env.service';

// Acl.inheritsRole('moder', 'unauthorized');

export interface APITrafficItem {
  ip: string;
  hostname?: string;
  count: number;
  whois_url: string;
  users: APIUser[];
  ban: {
    user: APIUser;
    reason: string;
    up_to: string;
  };
}

export interface APITrafficGetResponse {
  items: APITrafficItem[];
}

@Component({
  selector: 'app-moder-traffic',
  templateUrl: './traffic.component.html'
})
@Injectable()
export class ModerTrafficComponent {
  public items: APITrafficItem[];

  constructor(
    private http: HttpClient,
    private ipService: IpService,
    private router: Router,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/77/name',
          pageId: 77
        }),
      0
    );
    this.load();
  }

  private load() {
    this.http.get<APITrafficGetResponse>('/api/traffic').subscribe(
      response => {
        this.items = response.items;

        for (const item of this.items) {
          this.ipService.getHostByAddr(item.ip).then((hostname: string) => {
            item.hostname = hostname;
          });
        }
      },
      () => {
        this.router.navigate(['/error-404']);
      }
    );
  }

  public addToWhitelist(ip: string) {
    this.http
      .post<void>('/api/traffic/whitelist', {
        ip: ip
      })
      .subscribe(response => {
        this.load();
      });
  }

  public addToBlacklist(ip: string) {
    this.http
      .post<void>('/api/traffic/blacklist', {
        ip: ip,
        period: 240,
        reason: ''
      })
      .subscribe(response => {
        this.load();
      });
  }

  public removeFromBlacklist(ip: string) {
    this.http
      .delete<void>('/api/traffic/blacklist/' + ip)
      .subscribe(response => {
        this.load();
      });
  }
}
