import {inject, Service} from '@angular/core';
import {
  CreateMessageRequest,
  Message,
  MessageSummary,
  MessagingClearFolder,
  MessagingDeleteMessage,
} from '@grpc/spec.pb';
import {MessagingClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {BehaviorSubject, combineLatest, Observable, of} from 'rxjs';
import {catchError, debounceTime, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {AuthService} from './auth.service';
import {MessagingWebSocketService} from './messaging-ws.service';

@Service()
export class MessageService {
  readonly #auth = inject(AuthService);
  readonly #toasts = inject(ToastsService);
  readonly #messagingClient = inject(MessagingClient);
  readonly #ws = inject(MessagingWebSocketService);

  readonly #deleted$ = new BehaviorSubject<void>(void 0);
  readonly #sent$ = new BehaviorSubject<void>(void 0);
  readonly #seen$ = new BehaviorSubject<void>(void 0);

  // Passthrough for consumers (e.g. the messages list screen) that want to react to any
  // server-side change (send/delete/clear, from this session or another tab/device).
  public readonly changed$: Observable<void> = this.#ws.messagesChanged$;

  readonly #new$: Observable<null | number> = combineLatest([
    this.#auth.authenticated$,
    this.#deleted$,
    this.#seen$,
    this.#ws.messagesChanged$,
  ]).pipe(
    debounceTime(10),
    switchMap(([authenticated]) => {
      if (!authenticated) {
        return of(null);
      }

      return this.#messagingClient.getMessagesNewCount(new Empty());
    }),
    catchError((response: unknown) => {
      this.#toasts.handleError(response);
      return of(null);
    }),
    map((response) => (response ? response.count : null)),
  );

  readonly #summary$: Observable<MessageSummary | null> = combineLatest([
    this.#deleted$,
    this.#sent$,
    this.#seen$,
    this.#auth.authenticated$,
    this.#ws.messagesChanged$,
  ]).pipe(
    map(([, , , authenticated]) => authenticated),
    debounceTime(10),
    switchMap((authenticated) => {
      if (!authenticated) {
        return of(null);
      }

      return this.#messagingClient.getMessagesSummary(new Empty());
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public seen(messages: Message[]) {
    let newFound = false;
    for (const message of messages) {
      if (message.isNew) {
        newFound = true;
      }
    }

    if (newFound) {
      this.#seen$.next();
    }
  }

  public clearFolder$(folder: string): Observable<object> {
    return this.#messagingClient.clearFolder(new MessagingClearFolder({folder})).pipe(tap(() => this.#deleted$.next()));
  }

  public deleteMessage$(messageId: string): Observable<object> {
    return this.#messagingClient
      .deleteMessage(new MessagingDeleteMessage({messageId}))
      .pipe(tap(() => this.#deleted$.next()));
  }

  public getSummary$(): Observable<MessageSummary | null> {
    return this.#summary$;
  }

  public getNew$(): Observable<null | number> {
    return this.#new$;
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
      .pipe(tap(() => this.#sent$.next()));
  }
}
