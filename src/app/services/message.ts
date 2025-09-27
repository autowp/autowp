import {inject, Injectable} from '@angular/core';
import {MessagingService} from '@rest/api/messaging.service';
import {GoautowpMessage} from '@rest/model/goautowpMessage';
import {GoautowpMessageSummary} from '@rest/model/goautowpMessageSummary';
import {BehaviorSubject, combineLatest, Observable, of} from 'rxjs';
import {catchError, debounceTime, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {AuthService} from './auth.service';

@Injectable({
  providedIn: 'root',
})
export class MessageService {
  readonly #auth = inject(AuthService);
  readonly #toasts = inject(ToastsService);
  readonly #messagingService = inject(MessagingService);

  readonly #deleted$ = new BehaviorSubject<void>(void 0);
  readonly #sent$ = new BehaviorSubject<void>(void 0);
  readonly #seen$ = new BehaviorSubject<void>(void 0);

  readonly #new$: Observable<null | number> = combineLatest([
    this.#auth.authenticated$,
    this.#deleted$,
    this.#seen$,
  ]).pipe(
    debounceTime(10),
    switchMap(([authenticated]) => {
      if (!authenticated) {
        return of(null);
      }

      return this.#messagingService.messagingGetMessagesNewCount();
    }),
    catchError((response: unknown) => {
      this.#toasts.handleError(response);
      return of(null);
    }),
    map((response) => (response ? response.count : null)),
  );

  readonly #summary$: Observable<GoautowpMessageSummary | null> = combineLatest([
    this.#deleted$,
    this.#sent$,
    this.#seen$,
    this.#auth.authenticated$,
  ]).pipe(
    map(([, , , authenticated]) => authenticated),
    debounceTime(10),
    switchMap((authenticated) => {
      if (!authenticated) {
        return of(null);
      }

      return this.#messagingService.messagingGetMessagesSummary();
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public seen(messages: GoautowpMessage[]) {
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
    return this.#messagingService.messagingClearFolder({folder}).pipe(tap(() => this.#deleted$.next()));
  }

  public deleteMessage$(messageId: string): Observable<object> {
    return this.#messagingService.messagingDeleteMessage({messageId}).pipe(tap(() => this.#deleted$.next()));
  }

  public getSummary$(): Observable<GoautowpMessageSummary | null> {
    return this.#summary$;
  }

  public getNew$(): Observable<null | number> {
    return this.#new$;
  }

  public send$(userId: string, text: string): Observable<object> {
    return this.#messagingService
      .messagingCreateMessage({
        message: {
          text: text,
          toUserId: userId,
          id: '',
          isNew: false,
          canDelete: false,
          canReply: false,
          dialogCount: 0,
          date: new Date(),
          allMessagesLink: false,
          authorId: '',
          dialogWithUserId: '',
        },
      })
      .pipe(tap(() => this.#sent$.next()));
  }
}
