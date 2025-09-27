import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-moder-stat',
  imports: [RouterLink, NgbProgressbar, AsyncPipe],
  templateUrl: './stat.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerStatComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly items$ = this.#itemsClient.getStats(new Empty()).pipe(
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => response.values),
  );

  ngOnInit(): void {
    setTimeout(
      () =>
        this.#pageEnv.set({
          layout: {isAdminPage: true},
          pageId: 119,
        }),
      0,
    );
  }
}
