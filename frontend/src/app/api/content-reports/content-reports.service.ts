import type {ResourceRef} from '@angular/core';

import {inject, Injector, Service} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {ContentReportsRequest, ContentReportStatus} from '@grpc/spec.pb';
import {AutowpClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {map, of} from 'rxjs';

@Service()
export class APIContentReportsService {
  readonly #auth = inject(AuthService);
  readonly #autowpClient = inject(AutowpClient);

  readonly #injector = inject(Injector);
  readonly #isModer = this.#auth.hasRole(Role.MODER);
  #openReportsCountResource: ResourceRef<null | number | undefined> | undefined;

  getOpenReportsCountResource(): ResourceRef<null | number | undefined> {
    this.#openReportsCountResource ??= rxResource({
      injector: this.#injector,
      params: () => this.#isModer(),
      stream: ({params: isModer}) => {
        if (!isModer) return of(null);
        return this.#autowpClient
          .getContentReports(new ContentReportsRequest({status: ContentReportStatus.CONTENT_REPORT_STATUS_OPEN}))
          .pipe(map((response) => response.paginator?.totalItemCount ?? null));
      },
    });
    return this.#openReportsCountResource;
  }
}
