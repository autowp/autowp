import type {OnInit} from '@angular/core';

import {DatePipe, isPlatformBrowser} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, PLATFORM_ID, RESPONSE_INIT} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {GetIPRequest} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {timestampToDate} from '@utils/timestamp';
import {catchError, map, of} from 'rxjs';

@Component({
  selector: 'app-forbidden',
  imports: [DatePipe],
  templateUrl: './forbidden.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForbiddenComponent implements OnInit {
  readonly #response = inject(RESPONSE_INIT);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  readonly #autowp = inject(AutowpClient);

  // Browser-only: an IP ban is only ever visible to the browser's own direct calls - during SSR
  // the backend sees this pod over loopback, not the visitor (see BanChecker.IsBanned). An empty
  // ipAddress means "my own IP" (grpc.go GetIP), and GetIP is exempt from the ban interceptor.
  protected readonly ban = this.#isBrowser
    ? toSignal(
        this.#autowp.getIP(new GetIPRequest({fields: ['blacklist'], ipAddress: ''})).pipe(
          map((ip) => ip.blacklist),
          catchError(() => of(undefined)),
        ),
      )
    : toSignal(of(undefined));

  protected readonly until = computed(() => timestampToDate(this.ban()?.until));

  ngOnInit(): void {
    if (this.#response) {
      this.#response.status = 403;
      this.#response.statusText = 'Forbidden';
    }
  }
}
