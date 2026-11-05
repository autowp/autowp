import type {OnInit} from '@angular/core';
import type {TrafficTopItem} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  CreateTrafficBlacklistItemRequest,
  CreateTrafficWhitelistItemRequest,
  DeleteTrafficBlacklistItemRequest,
} from '@grpc/spec.pb';
import {TrafficClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {IpService} from '@services/ip';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {ToastsService} from 'app/toasts/toasts.service';
import {map} from 'rxjs';

import {UserLink} from '../../user/user/user-link';
interface ListItem {
  hostname$: Observable<string>;
  item: TrafficTopItem;
}

@Component({
  selector: 'app-moder-traffic',
  imports: [ResourceStatus, RouterLink, UserLink, AsyncPipe, DatePipe],
  templateUrl: './moder-traffic.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTraffic implements OnInit {
  readonly #trafficClient = inject(TrafficClient);
  readonly #ipService = inject(IpService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly itemsResource = rxResource({
    stream: () => this.#trafficClient.getTrafficTop(new Empty()).pipe(map((response) => response.items ?? [])),
  });
  protected readonly items = computed<ListItem[] | undefined>(() =>
    this.itemsResource.hasValue()
      ? this.itemsResource.value().map((item) => ({item, hostname$: this.#ipService.getHostByAddr$(item.ipAddress)}))
      : undefined,
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_TRAFFIC,
    });
  }

  protected addToWhitelist(ip: string) {
    this.#trafficClient
      .createTrafficWhitelistItem(new CreateTrafficWhitelistItemRequest({item: {ipAddress: ip, description: ''}}))
      .subscribe({
        error: (error: unknown) => {
          this.#toastService.handleError(error);
        },
        next: () => {
          this.itemsResource.reload();
        },
      });
  }

  protected addToBlacklist(ip: string) {
    this.#trafficClient
      .createTrafficBlacklistItem(
        new CreateTrafficBlacklistItemRequest({
          item: {
            ipAddress: ip,
            period: 240,
            reason: '',
          },
        }),
      )
      .subscribe({
        error: (error: unknown) => {
          this.#toastService.handleError(error);
        },
        next: () => {
          this.itemsResource.reload();
        },
      });
  }

  protected removeFromBlacklist(ip: string) {
    this.#trafficClient.deleteTrafficBlacklistItem(new DeleteTrafficBlacklistItemRequest({ipAddress: ip})).subscribe({
      error: (error: unknown) => {
        this.#toastService.handleError(error);
      },
      next: () => {
        this.itemsResource.reload();
      },
    });
  }
}
