import { Injectable } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { APIPaginator } from './api.service';
import { Observable, of, BehaviorSubject, ReplaySubject } from 'rxjs';
import { APIUser } from './user';
import { APIComment } from './comment';
import { AuthService } from './auth.service';
import {
  switchMap,
  share,
  publish,
  distinctUntilChanged,
  shareReplay,
  map
} from 'rxjs/operators';

export interface APIForumGetTopicOptions {
  fields?: string;
  page?: number;
}

export interface APIForumGetTopicsOptions {
  theme_id?: number;
  fields?: string;
  subscription?: boolean;
  page?: number;
}

export interface APIForumTopicPostData {
  theme_id: number;
  name: string;
  text: string;
  moderator_attention: boolean;
  subscription: boolean;
}

export interface APIForumGetThemeOptions {
  fields?: string;
  topics?: {
    page: number;
  };
}

export interface APIForumGetThemesOptions {
  fields?: string;
  topics?: {
    page: number;
  };
}

export interface APIForumTopic {
  id: number;
  theme_id: number;
  name: string;
  theme: APIForumTheme;
  subscription: boolean;
  last_message: APIComment;
  add_datetime: string;
  author: APIUser;
  status: string;
  old_messages: number;
  new_messages: number;
}

export interface APIForumTheme {
  id: number;
  name: string;
  themes: APIForumTheme[];
  description: string;
  subthemes: APIForumTheme[];
  url: string;
  last_topic: APIForumTopic;
  last_message: APIComment;
  topics: {
    items: APIForumTopic[];
    paginator: APIPaginator;
  };
  disable_topics: boolean;
}

export interface APIForumTopicsGetResponse {
  items: APIForumTopic[];
  paginator: APIPaginator;
}

export interface APIForumThemesGetResponse {
  items: APIForumTheme[];
}

export interface APIForumUserSummaryGetResponse {
  subscriptionsCount: number;
}

export interface MessageStateParams {
  topic_id: number;
  page: number;
}

const LIMIT = 20;

@Injectable()
export class ForumService {
  private summary$: Observable<APIForumUserSummaryGetResponse>;

  constructor(private http: HttpClient, private auth: AuthService) {
    this.summary$ = this.auth.getUser().pipe(
      switchMap(user => {
        if (!user) {
          return of(null);
        }
        return this.http.get<APIForumUserSummaryGetResponse>(
          '/api/forum/user-summary'
        );
      }),
      shareReplay(1)
    );
  }

  public getUserSummary(): Observable<APIForumUserSummaryGetResponse> {
    return this.summary$;
  }

  public getLimit(): number {
    return LIMIT;
  }

  public getMessageStateParams(
    messageID: number
  ): Observable<MessageStateParams> {
    return this.http
      .get<APIComment>('/api/comment/' + messageID, {
        params: {
          fields: 'page',
          limit: LIMIT.toString()
        }
      })
      .pipe(
        map(response => ({
          topic_id: response.item_id,
          page: response.page
        }))
      );
  }

  public getThemes(
    options: APIForumGetThemesOptions
  ): Observable<APIForumThemesGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.topics) {
      if (options.topics.page) {
        params['topics[page]'] = options.topics.page.toString();
      }
    }

    return this.http.get<APIForumThemesGetResponse>('/api/forum/themes', {
      params: params
    });
  }

  public getTheme(
    id: number,
    options: APIForumGetThemeOptions
  ): Observable<APIForumTheme> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.topics) {
      if (options.topics.page) {
        params['topics[page]'] = options.topics.page.toString();
      }
    }

    return this.http.get<APIForumTheme>('/api/forum/themes/' + id, {
      params: params
    });
  }

  public getTopics(
    options: APIForumGetTopicsOptions
  ): Observable<APIForumTopicsGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.theme_id) {
      params.theme_id = options.theme_id.toString();
    }

    if (options.subscription) {
      params.subscription = '1';
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    return this.http.get<APIForumTopicsGetResponse>('/api/forum/topic', {
      params: params
    });
  }

  public getTopic(
    id: number,
    options: APIForumGetTopicOptions
  ): Observable<APIForumTopic> {
    return this.getTopicByLocation('/api/forum/topic/' + id, options);
  }

  public getTopicByLocation(
    location: string,
    options: APIForumGetTopicOptions
  ): Observable<APIForumTopic> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.page) {
      params.page = options.page.toString();
    }
    return this.http.get<APIForumTopic>(location, {
      params: params
    });
  }

  public postTopic(
    data: APIForumTopicPostData
  ): Observable<HttpResponse<void>> {
    return this.http.post<void>(
      '/api/forum/topic',
      {
        theme_id: data.theme_id.toString(),
        name: data.name,
        text: data.text,
        moderator_attention: data.moderator_attention ? '1' : '',
        subscription: data.subscription ? '1' : ''
      },
      {
        observe: 'response'
      }
    );
  }
}
