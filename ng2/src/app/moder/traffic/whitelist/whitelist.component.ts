import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { IpService } from '../../../services/ip';
import { Router } from '@angular/router';
import { PageEnvService } from '../../../services/page-env.service';

// Acl.inheritsRole('moder', 'unauthorized');

export interface APITrafficWhitelistItem {
  ip: string;
  hostname: string;
  description: string;
  whois_url: string;
}

export interface APITrafficWhitelistGetResponse {
  items: APITrafficWhitelistItem[];
}

@Component({
  selector: 'app-moder-traffic-whitelist',
  templateUrl: './whitelist.component.html'
})
@Injectable()
export class ModerTrafficWhitelistComponent {
  public items: APITrafficWhitelistItem[];

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

    this.http
      .get<APITrafficWhitelistGetResponse>('/api/traffic/whitelist')
      .subscribe(
        response => {
          this.items = response.items;

          /*for(const item of $scope.items) {
                ipService.getHostByAddr(item.ip).subscribe((hostname) => {
                    item.hostname = hostname;
                });
            }*/
        },
        () => {
          this.router.navigate(['/error-404']);
        }
      );
  }

  public deleteItem(item: APITrafficWhitelistItem) {
    this.http
      .delete<void>('/api/traffic/whitelist/' + item.ip)
      .subscribe(response => {
        const index = this.items.indexOf(item);
        if (index !== -1) {
          this.items.splice(index, 1);
        }
        /*for(const item of $scope.items) {
                ipService.getHostByAddr(item.ip).subscribe((hostname) => {
                    item.hostname = hostname;
                });
            }*/
      });
  }
}
