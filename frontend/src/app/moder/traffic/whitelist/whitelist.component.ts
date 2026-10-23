import type {OnInit} from '@angular/core';
import type {TrafficWhitelistItem} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {DeleteTrafficWhitelistItemRequest, GetTrafficWhitelistItemsRequest} from '@grpc/spec.pb';
import {TrafficClient} from '@grpc/spec.pbsc';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError} from 'app/grpc';
import {PaginatorComponent} from 'app/paginator/paginator/paginator.component';
import {map} from 'rxjs';

@Component({
  selector: 'app-moder-traffic-whitelist',
  imports: [RouterLink, PaginatorComponent],
  templateUrl: './whitelist.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTrafficWhitelistComponent implements OnInit {
  readonly #trafficClient = inject(TrafficClient);
  readonly #notFound = inject(NotFoundService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);

  readonly #page = toSignal(
    this.#route.queryParamMap.pipe(
      map((params) => {
        const page = Number(params.get('page'));
        return Number.isInteger(page) && page > 0 && page <= 2147483647 ? page : 1;
      }),
    ),
    {requireSync: true},
  );

  protected readonly itemsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `moder-traffic-whitelist-${this.#page()}`,
    params: () => this.#page(),
    stream: ({params: page}) =>
      this.#trafficClient.getTrafficWhitelistItems(new GetTrafficWhitelistItemsRequest({page})),
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
