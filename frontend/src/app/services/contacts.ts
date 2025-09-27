import {inject, Injectable} from '@angular/core';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {ContactsService} from '@rest/api/contacts.service';
import {GoautowpContactItems} from '@rest/model/goautowpContactItems';
import {Observable, of, throwError} from 'rxjs';
import {catchError, map, switchMap} from 'rxjs/operators';

import {AuthService} from './auth.service';

@Injectable({
  providedIn: 'root',
})
export class AppContactsService {
  readonly #auth = inject(AuthService);
  readonly #contactsService = inject(ContactsService);

  public isInContacts$(userId: string): Observable<boolean> {
    return this.#contactsService.contactsGetContact({userId}).pipe(
      map((response) => !!response.contactUserId),
      catchError((err: unknown) => {
        if (err instanceof GrpcStatusEvent && err.statusCode === 5) {
          return of(false);
        }

        return throwError(() => err);
      }),
    );
  }

  public getContacts$(): Observable<GoautowpContactItems> {
    return this.#auth.authenticated$.pipe(switchMap(() => this.#contactsService.contactsGetContacts()));
  }
}
