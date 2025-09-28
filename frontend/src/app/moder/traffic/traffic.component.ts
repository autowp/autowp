import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {TrafficService} from '@rest/api/traffic.service';
import {GoautowpTrafficTopItem} from '@rest/model/goautowpTrafficTopItem';
import {IpService} from '@services/ip';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, Observable} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {UserComponent} from '../../user/user/user.component';

interface ListItem {
  hostname$: Observable<string>;
  item: GoautowpTrafficTopItem;
}

@Component({
  selector: 'app-moder-traffic',
  imports: [RouterLink, UserComponent, AsyncPipe, DatePipe],
  templateUrl: './traffic.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTrafficComponent implements OnInit {
  readonly #trafficService = inject(TrafficService);
  readonly #ipService = inject(IpService);
  readonly #pageEnv = inject(PageEnvService);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<ListItem[]> = this.#change$.pipe(
    switchMap(() => this.#trafficService.trafficGetTrafficTop()),
    map((response) =>
      (response.items ? response.items : []).map((item) => ({
        hostname$: this.#ipService.getHostByAddr$(item.ip),
        item,
      })),
    ),
  );

  ngOnInit(): void {
    setTimeout(
      () =>
        this.#pageEnv.set({
          layout: {isAdminPage: true},
          pageId: 77,
        }),
      0,
    );
  }

  protected addToWhitelist(ip: string) {
    this.#trafficService
      .trafficCreateTrafficWhitelistItem({item: {ip, description: ''}})
      .subscribe(() => this.#change$.next());
  }

  protected addToBlacklist(ip: string) {
    this.#trafficService
      .trafficCreateTrafficBlacklistItem({
        item: {
          ip: ip,
          period: 240,
          reason: '',
        },
      })
      .subscribe(() => this.#change$.next());
  }

  protected removeFromBlacklist(ip: string) {
    this.#trafficService.trafficDeleteTrafficBlacklistItem({ip}).subscribe(() => this.#change$.next());
  }
}
