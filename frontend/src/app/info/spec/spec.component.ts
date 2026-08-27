import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';

import {InfoSpecRowComponent} from './row/row.component';

@Component({
  selector: 'app-info-spec',
  imports: [RouterLink, InfoSpecRowComponent],
  templateUrl: './spec.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InfoSpecComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly specsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'info-spec-page',
    stream: () => this.#itemsClient.getSpecs(new Empty()),
  });

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.INFO_SPEC});
  }

  protected readonly errorMessage = errorMessage;
}
