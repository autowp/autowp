import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from './api.service';
import { APIItem } from './item';
import { Observable } from 'rxjs';

export interface APIInbox {
  paginator: APIPaginator;
  prev: {
    date: string;
    count: number;
  } | null;
  current: {
    date: string;
    count: number;
  };
  next: {
    date: string;
    count: number;
  } | null;
  brands: APIItem[];
}

@Injectable()
export class InboxService {
  constructor(private http: HttpClient) {}

  public get(
    brand_id: number,
    date: string
  ): Observable<APIInbox> {
    return this.http.get<APIInbox>('/api/inbox', {
      params: {
        brand_id: brand_id ? brand_id.toString() : '',
        date: date
      }
    });
  }
}
