import type {OnInit} from '@angular/core';
import type {TrafficWhitelistItem} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {DeleteTrafficWhitelistItemRequest} from '@grpc/spec.pb';
import {TrafficClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError} from 'app/grpc';

@Component({
  selector: 'app-moder-traffic-whitelist',
  imports: [RouterLink],
  templateUrl: './whitelist.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTrafficWhitelistComponent implements OnInit {
  readonly #trafficClient = inject(TrafficClient);
  readonly #notFound = inject(NotFoundService);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly itemsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-traffic-whitelist',
    stream: () => this.#trafficClient.getTrafficWhitelistItems(new Empty()),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.itemsResource.error())) {
        this.#notFound.report();
      }
    });
  }

  protected deleteItem(item: TrafficWhitelistItem) {
    this.#trafficClient
      .deleteTrafficWhitelistItem(new DeleteTrafficWhitelistItemRequest({ipAddress: item.ipAddress}))
      .subscribe(() => {
        this.itemsResource.reload();
      });
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_TRAFFIC,
    });
  }

  protected readonly errorMessage = errorMessage;
}
