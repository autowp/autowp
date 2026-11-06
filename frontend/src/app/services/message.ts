import type {ResourceRef} from '@angular/core';
import type {MessageSummary} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Injector, Service, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {CreateMessageRequest, Message, MessagingClearFolder, MessagingDeleteMessage} from '@grpc/spec.pb';
import {MessagingClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {map, of, scan, tap} from 'rxjs';

import {AuthService} from './auth-service';
import {MessagingWebSocketService} from './messaging-ws.service';

@Service()
export class MessageService {
  readonly #auth = inject(AuthService);
  readonly #messagingClient = inject(MessagingClient);
  readonly #ws = inject(MessagingWebSocketService);

  readonly #injector = inject(Injector);
  // The WebSocket's initial emission becomes revision 0, so subscribing does not refetch.
  readonly changed = toSignal(this.#ws.messagesChanged$.pipe(scan((revision) => revision + 1, -1)), {
    requireSync: true,
  });
  readonly #newRevision = signal(0);
  readonly #summaryRevision = signal(0);
  #newResource: ResourceRef<null | number | undefined> | undefined;
  #summaryResource: ResourceRef<MessageSummary | null | undefined> | undefined;

  getNewResource(): ResourceRef<null | number | undefined> {
    this.#newResource ??= rxResource({
      injector: this.#injector,
      params: () => {
        const authenticated = this.#auth.authenticated();
        if (authenticated === undefined) return undefined;
        return {authenticated, revision: this.#newRevision(), changed: this.changed()};
      },
      stream: ({params}) =>
        params.authenticated
          ? this.#messagingClient.getMessagesNewCount(new Empty()).pipe(map((response) => response.count))
          : of(null),
    });
    return this.#newResource;
  }

  getSummaryResource(): ResourceRef<MessageSummary | null | undefined> {
    this.#summaryResource ??= rxResource({
      injector: this.#injector,
      params: () => {
        const authenticated = this.#auth.authenticated();
        if (authenticated === undefined) return undefined;
        return {authenticated, revision: this.#summaryRevision(), changed: this.changed()};
      },
      stream: ({params}) => (params.authenticated ? this.#messagingClient.getMessagesSummary(new Empty()) : of(null)),
    });
    return this.#summaryResource;
  }

  public seen(messages: Message[]) {
    let newFound = false;
    for (const message of messages) {
      if (message.isNew) {
        newFound = true;
      }
    }

    if (newFound) {
      this.#newRevision.update((value) => value + 1);
      this.#summaryRevision.update((value) => value + 1);
    }
  }

  public clearFolder$(folder: string): Observable<object> {
    return this.#messagingClient.clearFolder(new MessagingClearFolder({folder})).pipe(
      tap(() => {
        this.#newRevision.update((value) => value + 1);
        this.#summaryRevision.update((value) => value + 1);
      }),
    );
  }

  public deleteMessage$(messageId: string): Observable<object> {
    return this.#messagingClient.deleteMessage(new MessagingDeleteMessage({messageId})).pipe(
      tap(() => {
        this.#newRevision.update((value) => value + 1);
        this.#summaryRevision.update((value) => value + 1);
      }),
    );
  }

  public send$(userId: string, text: string): Observable<object> {
    return this.#messagingClient
      .createMessage(
        new CreateMessageRequest({
          message: new Message({
            text: text,
            toUserId: userId,
          }),
        }),
      )
      .pipe(
        tap(() => {
          this.#summaryRevision.update((value) => value + 1);
        }),
      );
  }
}
