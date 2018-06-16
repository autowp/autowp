import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, of, Subject, combineLatest } from 'rxjs';
import { AuthService } from './auth.service';
import { APIPaginator } from './api.service';
import { APIUser } from './user';
import { switchMap, map, debounceTime, shareReplay, tap } from 'rxjs/operators';

export type MessageCallbackType = () => void;

export interface APIMessagesGetOptions {
  folder: string;
  page: number;
  fields: string;
  user_id?: number;
}

export interface APIMessage {
  id: number;
  is_new: boolean;
  author: APIUser;
  can_delete: boolean;
  text_html: string;
  can_reply: boolean;
  dialog_with_user_id: number;
  all_messages_link: boolean;
  dialog_count: number;
  date: string;
}

export interface APIMessagesGetResponse {
  items: APIMessage[];
  paginator: APIPaginator;
}

export interface APIMessageSummaryGetResponse {
  inbox: {
    count: number;
    new_count: number;
  };
  sent: {
    count: number;
  };
  system: {
    count: number;
    new_count: number;
  };
}

export interface APIMessageNewGetResponse {
  count: number;
}

@Injectable()
export class MessageService {
  private summary$: Observable<APIMessageSummaryGetResponse>;
  private new$: Observable<number>;
  private deleted$ = new BehaviorSubject<void>(null);
  private sent$ = new BehaviorSubject<void>(null);
  private seen$ = new BehaviorSubject<void>(null);

  constructor(private http: HttpClient, private auth: AuthService) {
    this.summary$ = combineLatest(
      this.deleted$,
      this.sent$,
      this.seen$,
      this.auth.getUser(),
      (a, b, c, user) => user
    ).pipe(
      debounceTime(10),
      switchMap(user => {
        if (!user) {
          return of(null as APIMessageSummaryGetResponse);
        }

        return this.http.get<APIMessageSummaryGetResponse>(
          '/api/message/summary'
        );
      }),
      shareReplay(1)
    );

    this.new$ = combineLatest(
      this.auth.getUser(),
      this.deleted$,
      this.seen$,
      user => user
    ).pipe(
      debounceTime(10),
      switchMap(user => {
        if (!user) {
          return of(null as APIMessageNewGetResponse);
        }

        return this.http.get<APIMessageNewGetResponse>('/api/message/new');
      }),
      map(response => (response ? response.count : null))
    );
  }

  public seen(messages: APIMessage[]) {
    let newFound = false;
    for (const message of messages) {
      if (message.is_new) {
        newFound = true;
      }
    }

    if (newFound) {
      this.seen$.next(null);
    }
  }

  public clearFolder(folder: string): Observable<void> {
    return this.http
      .delete<void>('/api/message', {
        params: { folder: folder }
      })
      .pipe(tap(() => this.deleted$.next(null)));
  }

  public deleteMessage(id: number): Observable<void> {
    return this.http
      .delete<void>('/api/message/' + id)
      .pipe(tap(() => this.deleted$.next(null)));
  }

  public getSummary(): Observable<APIMessageSummaryGetResponse> {
    return this.summary$;
  }

  public getNew(): Observable<number> {
    return this.new$;
  }

  public send(userId: number, text: string): Observable<void> {
    return this.http
      .post<void>('/api/message', {
        user_id: userId,
        text: text
      })
      .pipe(tap(() => this.sent$.next(null)));
  }

  public getMessages(
    options: APIMessagesGetOptions
  ): Observable<APIMessagesGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.folder) {
      params.folder = options.folder;
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.user_id) {
      params.user_id = options.user_id.toString();
    }

    return this.http.get<APIMessagesGetResponse>('/api/message', {
      params: params
    });
  }
}
