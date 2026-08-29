import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {ContentReportsRequest, ContentReportStatus} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {map, of, shareReplay, switchMap} from 'rxjs';

@Service()
export class APIContentReportsService {
  readonly #auth = inject(AuthService);
  readonly #autowpClient = inject(AutowpClient);

  public readonly openReportsCount$: Observable<null | number> = this.#auth.hasRole$(Role.MODER).pipe(
    switchMap((isModer) => {
      if (!isModer) {
        return of(null);
      }

      return this.#autowpClient
        .getContentReports(new ContentReportsRequest({status: ContentReportStatus.CONTENT_REPORT_STATUS_OPEN}))
        .pipe(map((response) => response.paginator?.totalItemCount ?? null));
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
