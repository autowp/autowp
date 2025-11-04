import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
import {InfoSpecRowComponent} from './row/row.component';

@Component({
  selector: 'app-info-spec',
  imports: [RouterLink, InfoSpecRowComponent, AsyncPipe],
  templateUrl: './spec.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InfoSpecComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly specs$ = this.#itemsClient.getSpecs(new Empty()).pipe(
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((specs) => specs.items),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 174}), 0);
  }
}
