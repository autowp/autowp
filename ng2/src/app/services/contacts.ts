import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { APIUser } from './user';

export interface APIContactsGetOptions {
  fields: string;
}

export interface APIContactsGetResponse {
  items: APIUser[];
}

@Injectable()
export class ContactsService {
  private hostnames: Map<string, string> = new Map<string, string>();

  constructor(private http: HttpClient) {}

  public deleteMessage(id: number): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.http
        .delete('/api/message/' + id)
        .subscribe(() => resolve(), response => reject(response));
    });
  }

  public isInContacts(userId: number): Promise<boolean> {
    return new Promise<boolean>((resolve, reject) => {
      this.http.get<APIUser>('/api/contacts/' + userId).subscribe(
        response => resolve(true),
        response => {
          if (response.status === 404) {
            resolve(false);
          } else {
            reject(response);
          }
        }
      );
    });
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
