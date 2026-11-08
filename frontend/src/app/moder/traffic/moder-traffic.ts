import type {OnInit} from '@angular/core';
import type {TrafficSourceItem, TrafficTopItem} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  CreateTrafficBlacklistItemRequest,
  CreateTrafficNetworkBanRequest,
  CreateTrafficWhitelistItemRequest,
  DeleteTrafficBlacklistItemRequest,
  DeleteTrafficNetworkBanRequest,
  DeleteTrafficSourceRequest,
  GetTrafficNetworkIPsRequest,
  GetTrafficSourceIPsRequest,
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
import {SourceEditor} from './source-editor';
interface ListItem {
  hostname$: Observable<string>;
  item: TrafficTopItem;
}

@Component({
  selector: 'app-moder-traffic',
  imports: [ResourceStatus, RouterLink, UserLink, AsyncPipe, DatePipe, SourceEditor],
  templateUrl: './moder-traffic.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTraffic implements OnInit {
  readonly #trafficClient = inject(TrafficClient);
  readonly #ipService = inject(IpService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  readonly #route = inject(ActivatedRoute);
  readonly #queryParams = toSignal(this.#route.queryParamMap, {initialValue: this.#route.snapshot.queryParamMap});

  protected readonly networkView = computed(() => this.#queryParams().get('view') === 'networks');
  protected readonly sourceView = computed(() => this.#queryParams().get('view') === 'sources');
  protected readonly selectedSource = computed(() =>
    this.sourceView() ? (this.#queryParams().get('source') ?? '') : '',
  );
  protected readonly sourcesResource = rxResource({
    params: () => (this.sourceView() ? true : undefined),
    stream: () => this.#trafficClient.getTrafficSources(new Empty()).pipe(map((response) => response.items ?? [])),
  });
  protected readonly source = computed(() =>
    this.sourcesResource.value()?.find((item) => item.id === this.selectedSource()),
  );
  protected readonly selectedNetwork = computed(() =>
    this.networkView() ? (this.#queryParams().get('network') ?? '') : '',
  );
  protected readonly networksResource = rxResource({
    params: () => (this.networkView() && !this.selectedNetwork() ? true : undefined),
    stream: () => this.#trafficClient.getTrafficNetworks(new Empty()).pipe(map((response) => response.items ?? [])),
  });
  protected readonly itemsResource = rxResource({
    params: () =>
      (this.networkView() && !this.selectedNetwork()) || (this.sourceView() && !this.selectedSource())
        ? undefined
        : {network: this.selectedNetwork(), source: this.selectedSource()},
    stream: ({params}) => this.#getItems(params).pipe(map((response) => response.items ?? [])),
  });
  protected readonly items = computed<ListItem[] | undefined>(() =>
    this.itemsResource.hasValue()
      ? this.itemsResource.value().map((item) => ({item, hostname$: this.#ipService.getHostByAddr$(item.ipAddress)}))
      : undefined,
  );

  protected readonly editingSource = signal<TrafficSourceItem | undefined>(undefined);
  protected readonly creatingSource = signal(false);
  protected readonly deletingSource = signal('');
  protected readonly networkMutation = signal('');

  protected banNetwork(network: string): void {
    this.networkMutation.set(network);
    this.#trafficClient
      .createTrafficNetworkBan(new CreateTrafficNetworkBanRequest({network, periodHours: 24}))
      .subscribe({
        next: () => {
          this.#reloadBans();
        },
        error: (error: unknown) => {
          this.networkMutation.set('');
          this.#toastService.handleError(error);
        },
      });
  }

  protected unbanRange(network: string): void {
    const [address, bits] = network.split('/');
    const host = !bits || bits === (address.includes(':') ? '128' : '32');
    this.networkMutation.set(network);
    const request = host
      ? this.#trafficClient.deleteTrafficBlacklistItem(new DeleteTrafficBlacklistItemRequest({ipAddress: address}))
      : this.#trafficClient.deleteTrafficNetworkBan(new DeleteTrafficNetworkBanRequest({network}));
    request.subscribe({
      next: () => {
        this.#reloadBans();
      },
      error: (error: unknown) => {
        this.networkMutation.set('');
        this.#toastService.handleError(error);
      },
    });
  }

  protected sourceSaved(): void {
    this.creatingSource.set(false);
    this.editingSource.set(undefined);
    this.sourcesResource.reload();
    this.itemsResource.reload();
  }

  protected deleteSource(id: string): void {
    this.#trafficClient.deleteTrafficSource(new DeleteTrafficSourceRequest({id})).subscribe({
      next: () => {
        this.deletingSource.set('');
        this.sourcesResource.reload();
      },
      error: (error: unknown) => {
        this.#toastService.handleError(error);
      },
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_TRAFFIC,
    });
  }

  protected addToWhitelist(ip: string) {
    this.#trafficClient
      .createTrafficWhitelistItem(
        new CreateTrafficWhitelistItemRequest({item: {ipAddress: ip, description: '', sourceId: ''}}),
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
  #getItems(params: {network: string; source: string}) {
    if (params.source) {
      return this.#trafficClient.getTrafficSourceIPs(new GetTrafficSourceIPsRequest({sourceId: params.source}));
    }
    if (params.network) {
      return this.#trafficClient.getTrafficNetworkIPs(new GetTrafficNetworkIPsRequest({network: params.network}));
    }
    return this.#trafficClient.getTrafficTop(new Empty());
  }
  #reloadBans(): void {
    this.networkMutation.set('');
    this.networksResource.reload();
    this.sourcesResource.reload();
    this.itemsResource.reload();
  }
}
