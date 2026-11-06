import type {ResourceRef} from '@angular/core';
import type {PicturesUserSummary} from '@grpc/spec.pb';

import {inject, Injector, Service} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {DfDistanceListOptions, PictureListOptions, PicturesRequest, PictureStatus} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {map, of} from 'rxjs';

import {AuthService, Role} from './auth-service';

export const perspectiveIDLogotype = 22,
  perspectiveIDMixed = 25;

@Service()
export class PictureService {
  readonly #auth = inject(AuthService);
  readonly #picturesClient = inject(PicturesClient);

  readonly #injector = inject(Injector);
  readonly #isModer = this.#auth.hasRole(Role.MODER);
  #summaryResource: ResourceRef<null | PicturesUserSummary | undefined> | undefined;
  #inboxSizeResource: ResourceRef<null | number | undefined> | undefined;
  #similarPicturesCountResource: ResourceRef<null | number | undefined> | undefined;

  getSummaryResource(): ResourceRef<null | PicturesUserSummary | undefined> {
    this.#summaryResource ??= rxResource({
      injector: this.#injector,
      params: () => this.#auth.authenticated(),
      stream: ({params}) => (params ? this.#picturesClient.getUserSummary(new Empty()) : of(null)),
    });
    return this.#summaryResource;
  }

  getInboxSizeResource(): ResourceRef<null | number | undefined> {
    this.#inboxSizeResource ??= rxResource({
      injector: this.#injector,
      params: () => this.#isModer(),
      stream: ({params: isModer}) => {
        if (!isModer) return of(null);
        return this.#picturesClient
          .getPicturesPaginator(
            new PicturesRequest({
              limit: 0,
              options: new PictureListOptions({
                status: PictureStatus.PICTURE_STATUS_INBOX,
              }),
              paginator: true,
            }),
          )
          .pipe(map((paginator) => paginator.totalItemCount || null));
      },
    });
    return this.#inboxSizeResource;
  }

  getSimilarPicturesCountResource(): ResourceRef<null | number | undefined> {
    this.#similarPicturesCountResource ??= rxResource({
      injector: this.#injector,
      params: () => this.#isModer(),
      stream: ({params: isModer}) => {
        if (!isModer) return of(null);
        return this.#picturesClient
          .getPicturesPaginator(
            new PicturesRequest({
              limit: 0,
              options: new PictureListOptions({
                dfDistance: new DfDistanceListOptions({}),
              }),
              paginator: true,
            }),
          )
          .pipe(map((paginator) => paginator.totalItemCount || null));
      },
    });
    return this.#similarPicturesCountResource;
  }
}
