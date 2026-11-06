import type {ResourceRef} from '@angular/core';

import {inject, Injector, Service} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {GetMessagesRequest, ModeratorAttention} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {map, of} from 'rxjs';

@Service()
export class APICommentsService {
  readonly #auth = inject(AuthService);
  readonly #commentsClient = inject(CommentsClient);

  readonly #injector = inject(Injector);
  readonly #isModer = this.#auth.hasRole(Role.MODER);
  #attentionCommentsCountResource: ResourceRef<null | number | undefined> | undefined;

  getAttentionCommentsCountResource(): ResourceRef<null | number | undefined> {
    this.#attentionCommentsCountResource ??= rxResource({
      injector: this.#injector,
      params: () => this.#isModer(),
      stream: ({params: isModer}) => {
        if (!isModer) return of(null);
        return this.#commentsClient
          .getMessages(
            new GetMessagesRequest({
              limit: 0,
              moderatorAttention: ModeratorAttention.REQUIRED,
            }),
          )
          .pipe(map((response) => (response.paginator ? response.paginator.totalItemCount : null)));
      },
    });
    return this.#attentionCommentsCountResource;
  }
}
