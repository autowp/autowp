import {inject, Injectable} from '@angular/core';
import {GetMessagesRequest, ModeratorAttention} from '@grpc/spec.pb';
import {CommentsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {Observable, of} from 'rxjs';
import {map, shareReplay, switchMap} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class APICommentsService {
  readonly #auth = inject(AuthService);
  readonly #commentsClient = inject(CommentsClient);

  public readonly attentionCommentsCount$: Observable<null | number> = this.#auth.hasRole$(Role.MODER).pipe(
    switchMap((isModer) => {
      if (!isModer) {
        return of(null);
      }

      return this.#commentsClient
        .getMessages(
          new GetMessagesRequest({
            limit: 0,
            moderatorAttention: ModeratorAttention.REQUIRED,
          }),
        )
        .pipe(map((response) => (response.paginator ? response.paginator.totalItemCount : null)));
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
