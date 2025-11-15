import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {
  CreateTrafficBlacklistItemRequest,
  CreateTrafficWhitelistItemRequest,
  DeleteTrafficBlacklistItemRequest,
  TrafficTopItem,
} from '@grpc/spec.pb';
import {TrafficClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {IpService} from '@services/ip';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, Observable} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {UserComponent} from '../../user/user/user.component';

interface ListItem {
  hostname$: Observable<string>;
  item: TrafficTopItem;
}

@Component({
  selector: 'app-moder-traffic',
  imports: [RouterLink, UserComponent, AsyncPipe, DatePipe],
  templateUrl: './traffic.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTrafficComponent implements OnInit {
  readonly #trafficClient = inject(TrafficClient);
  readonly #ipService = inject(IpService);
  readonly #pageEnv = inject(PageEnvService);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<ListItem[]> = this.#change$.pipe(
    switchMap(() => this.#trafficClient.getTrafficTop(new Empty())),
    map((response) =>
      (response.items ? response.items : []).map((item) => ({
        hostname$: this.#ipService.getHostByAddr$(item.ip),
        item,
      })),
    ),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 77,
    });
  }

  protected addToWhitelist(ip: string) {
    this.#trafficClient
      .createTrafficWhitelistItem(new CreateTrafficWhitelistItemRequest({item: {ip, description: ''}}))
      .subscribe(() => this.#change$.next());
  }

  protected addToBlacklist(ip: string) {
    this.#trafficClient
      .createTrafficBlacklistItem(
        new CreateTrafficBlacklistItemRequest({
          item: {
            ip: ip,
            period: 240,
            reason: '',
          },
        }),
      )
      .subscribe(() => this.#change$.next());
  }

  protected removeFromBlacklist(ip: string) {
    this.#trafficClient
      .deleteTrafficBlacklistItem(new DeleteTrafficBlacklistItemRequest({ip}))
      .subscribe(() => this.#change$.next());
  }
}
