import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  APIHotlinksHostsGetResponse,
  APIHotlinksHost
} from '../../services/api.service';
import { ACLService } from '../../services/acl.service';

@Component({
  selector: 'app-moder-hotlinks',
  templateUrl: './hotlinks.component.html'
})
@Injectable()
export class ModerHotlinksComponent {
  public hosts: APIHotlinksHost[] = [];
  public canManage = false;

  constructor(private http: HttpClient, private acl: ACLService) {
    /*this.$scope.pageEnv({
      layout: {
        isAdminPage: true,
        blankPage: false,
        needRight: false
      },
      name: 'page/67/name',
      pageId: 67
    });*/

    this.acl.isAllowed('hotlinks', 'manage').then(
      allow => {
        this.canManage = !!allow;
      },
      () => {
        this.canManage = false;
      }
    );

    this.loadHosts();
  }

  private loadHosts() {
    this.http.get<APIHotlinksHostsGetResponse>('/api/hotlinks/hosts').subscribe(
      response => {
        this.hosts = response.items;
      },
      response => {
        console.log(response);
      }
    );
  }

  public clearAll(host: string) {
    this.http.delete('/api/hotlinks/hosts').subscribe(() => {
      this.loadHosts();
    });
  }

  public clear(host: string) {
    this.http
      .delete('/api/hotlinks/hosts/' + encodeURIComponent(host))
      .subscribe(() => {
        this.loadHosts();
      });
  }

  public addToWhitelist(host: string) {
    this.http
      .post<void>('/api/hotlinks/whitelist', {
        host: host
      })
      .subscribe(() => {
        this.loadHosts();
      });
  }

  public addToWhitelistAndClear(host: string) {
    this.addToWhitelist(host);
    this.clear(host);
  }

  public addToBlacklist(host: string) {
    this.http
      .post<void>('/api/hotlinks/blacklist', {
        host: host
      })
      .subscribe(() => {
        this.loadHosts();
      });
  }
}
