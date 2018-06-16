import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, throwError, of } from 'rxjs';
import { APIUser } from './user';
import { catchError, map } from 'rxjs/operators';

export interface APIContactsGetOptions {
  fields: string;
}

export interface APIContactsGetResponse {
  items: APIUser[];
}

export interface APIContactsContactGetResponse {
  contact_user_id: number;
}

@Injectable()
export class ContactsService {
  constructor(private http: HttpClient) {}

  public isInContacts(userId: number): Observable<boolean> {
    return this.http
      .get<APIContactsContactGetResponse>('/api/contacts/' + userId)
      .pipe(
        map(response => !!response.contact_user_id),
        catchError(err => {
          if (err.status === 404) {
            return of(false);
          }

          return throwError(err);
        })
      );
  }

  public getContacts(
    options: APIContactsGetOptions
  ): Observable<APIContactsGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    return this.http.get<APIContactsGetResponse>('/api/contacts', {
      params: params
    });
  }
}
