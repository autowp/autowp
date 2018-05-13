import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject ,  Observable } from 'rxjs';
import { AuthService } from './auth.service';
import Notify from '../notify';
import { APIPaginator } from './api.service';
import { APIUser } from './user';

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
  public newMessagesCount = new BehaviorSubject<number>(0);

  private handlers: { [key: string]: MessageCallbackType[] } = {
    sent: [],
    deleted: []
  };

  constructor(private http: HttpClient, private authService: AuthService) {
    authService.loggedIn$.subscribe(value => {
      this.refreshNewMessagesCount();
    });
  }

  public refreshNewMessagesCount() {
    if (this.authService.loggedIn) {
      this.getNewCount().then(
        count => {
          this.newMessagesCount.next(count);
        },
        response => {
          Notify.response(response);
        }
      );
    } else {
      this.newMessagesCount.next(0);
    }
  }

  public clearFolder(folder: string): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.http
        .delete('/api/message', {
          params: {
            folder: folder
          }
        })
        .subscribe(
          () => {
            this.trigger('deleted');

            this.refreshNewMessagesCount();

            resolve();
          },
          response => reject(response)
        );
    });
  }

  public deleteMessage(id: number): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.http.delete('/api/message/' + id).subscribe(
        () => {
          this.trigger('deleted');

          resolve();
        },
        response => reject(response)
      );
    });
  }

  public getSummary(): Promise<APIMessageSummaryGetResponse> {
    return new Promise<APIMessageSummaryGetResponse>((resolve, reject) => {
      this.http
        .get<APIMessageSummaryGetResponse>('/api/message/summary')
        .subscribe(response => resolve(response), response => reject(response));
    });
  }

  public getNewCount(): Promise<number> {
    return new Promise<number>((resolve, reject) => {
      this.http
        .get<APIMessageNewGetResponse>('/api/message/new')
        .subscribe(
          response => resolve(response.count),
          response => reject(response)
        );
    });
  }

  public send(userId: number, text: string): Promise<void> {
    const subscription = this.http.post<void>('/api/message', {
      user_id: userId,
      text: text
    });

    return subscription.toPromise().then(() => {
      this.trigger('sent');
    });
  }

  public bind(event: string, handler: MessageCallbackType) {
    this.handlers[event].push(handler);
  }

  public unbind(event: string, handler: MessageCallbackType) {
    const index = this.handlers[event].indexOf(handler);
    if (index !== -1) {
      this.handlers[event].splice(index, 1);
    }
  }

  public trigger(event: string) {
    for (const handler of this.handlers[event]) {
      handler();
    }
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
