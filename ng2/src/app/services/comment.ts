import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { APIPaginator } from './api.service';
import { APIUser } from './user';

export interface APICommentItemGetOptions {
  fields?: string;
  limit?: number;
}

export interface APICommentGetOptions {
  user_id?: number;
  type_id?: number;
  item_id?: number;
  no_parents?: boolean;
  limit: number;
  page?: number;
  order?: string;
  fields?: string;
  user?: string | number;
  moderator_attention?: string;
  pictures_of_item_id?: number;
}

export interface APICommentGetResponse {
  items: APIComment[];
  paginator: APIPaginator;
}

export interface APIComment {
  id: number;
  item_id: number;
  page: number;
  deleted: boolean;
  user_vote: number;
  vote: number;
  replies: APIComment[];
  ip: string;
  text_html: string;
  user: APIUser;
  datetime: string;
  moder_attention: boolean; // TODO: enum
  url: string;
  preview: string;
}

@Injectable()
export class CommentService {
  constructor(private http: HttpClient) {}

  public getComments(
    options: APICommentGetOptions
  ): Observable<APICommentGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.user_id) {
      params.user_id = options.user_id.toString();
    }

    if (options.type_id) {
      params.type_id = options.type_id.toString();
    }

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    if (options.no_parents) {
      params.no_parents = '1';
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.order) {
      params.order = options.order;
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.user) {
      params.user = options.user.toString();
    }

    if (options.moderator_attention) {
      params.moderator_attention = options.moderator_attention;
    }

    if (options.pictures_of_item_id) {
      params.pictures_of_item_id = options.pictures_of_item_id.toString();
    }

    return this.http.get<APICommentGetResponse>('/api/comment', {
      params: params
    });
  }

  public setIsDeleted(id: number, value: boolean): Observable<void> {
    return this.http.put<void>('/api/comment/' + id, {
      deleted: value ? '1' : '0'
    });
  }

  public vote(id: number, value: number): Observable<void> {
    return this.http.put<void>('/api/comment/' + id, {
      user_vote: value
    });
  }

  public getComment(
    id: number,
    options: APICommentItemGetOptions
  ): Observable<APIComment> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    return this.getCommentByLocation('/api/comment/' + id, options);
  }

  public getCommentByLocation(
    location: string,
    options: APICommentItemGetOptions
  ): Observable<APIComment> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    return this.http.get<APIComment>(location, {
      params: params
    });
  }

  public getVotes(id: number): Observable<string> {
    return this.http.get('/comments/votes', {
      params: {
        id: id.toString()
      },
      responseType: 'text'
    });
  }
}
