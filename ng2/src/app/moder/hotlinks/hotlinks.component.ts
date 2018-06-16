import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  APIHotlinksHostsGetResponse,
  APIHotlinksHost
} from '../../services/api.service';
import { ACLService } from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';
import { combineLatest, Subscription, BehaviorSubject } from 'rxjs';
import { switchMapTo } from 'rxjs/operators';

@Component({
  selector: 'app-moder-hotlinks',
  templateUrl: './hotlinks.component.html'
})
@Injectable()
export class ModerHotlinksComponent implements OnInit, OnDestroy {
  public hosts: APIHotlinksHost[] = [];
  public canManage = false;
  private sub: Subscription;
  private change$ = new BehaviorSubject<null>(null);

  constructor(
    private http: HttpClient,
    private acl: ACLService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/67/name',
          pageId: 67
        }),
      0
    );

    this.sub = combineLatest(
      this.acl.isAllowed('hotlinks', 'manage'),
      this.change$.pipe(
        switchMapTo(
          this.http.get<APIHotlinksHostsGetResponse>('/api/hotlinks/hosts')
        )
      )
    )
      .pipe()
      .subscribe(data => {
        this.canManage = data[0];
        this.hosts = data[1].items;
      });
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public clearAll() {
    this.http.delete('/api/hotlinks/hosts').subscribe(() => {
      this.change$.next(null);
    });
  }

  public clear(host: string) {
    this.http
      .delete('/api/hotlinks/hosts/' + encodeURIComponent(host))
      .subscribe(() => {
        this.change$.next(null);
      });
  }

  public addToWhitelist(host: string) {
    this.http
      .post<void>('/api/hotlinks/whitelist', {
        host: host
      })
      .subscribe(() => {
        this.change$.next(null);
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
        this.change$.next(null);
      });
  }
}
