import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { IpService } from '../../services/ip';
import { APIUser } from '../../services/user';
import { PageEnvService } from '../../services/page-env.service';
import { Subscription, Observable, BehaviorSubject, forkJoin } from 'rxjs';
import { map, tap, switchMap, switchMapTo } from 'rxjs/operators';

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
export class ModerTrafficComponent implements OnInit, OnDestroy {
  public items: APITrafficItem[];
  private sub: Subscription;
  private change$ = new BehaviorSubject<null>(null);

  constructor(
    private http: HttpClient,
    private ipService: IpService,
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
          name: 'page/77/name',
          pageId: 77
        }),
      0
    );

    this.sub = this.change$
      .pipe(
        switchMapTo(this.http.get<APITrafficGetResponse>('/api/traffic')),
        map(response => response.items),
        tap(items => {
          this.items = items;
        }),
        switchMap(items => {
          const observables: Observable<string>[] = [];
          for (const item of items) {
            observables.push(
              this.ipService
                .getHostByAddr(item.ip)
                .pipe(tap(hostname => (item.hostname = hostname)))
            );
          }

          return forkJoin(...observables);
        })
      )
      .subscribe();
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public addToWhitelist(ip: string) {
    this.http
      .post<void>('/api/traffic/whitelist', {
        ip: ip
      })
      .subscribe(() => this.change$.next(null));
  }

  public addToBlacklist(ip: string) {
    this.http
      .post<void>('/api/traffic/blacklist', {
        ip: ip,
        period: 240,
        reason: ''
      })
      .subscribe(() => this.change$.next(null));
  }

  public removeFromBlacklist(ip: string) {
    this.http
      .delete<void>('/api/traffic/blacklist/' + ip)
      .subscribe(() => this.change$.next(null));
  }
}
