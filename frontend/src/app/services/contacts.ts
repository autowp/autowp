import {inject, Service} from '@angular/core';
import {GetContactRequest} from '@grpc/spec.pb';
import {ContactsClient} from '@grpc/spec.pbsc';
import {catchError, map, Observable, of, throwError} from 'rxjs';

import {isNotFoundError} from '../grpc';

@Service()
export class AppContactsService {
  readonly #contactsClient = inject(ContactsClient);

  public isInContacts$(userId: string): Observable<boolean> {
    return this.#contactsClient.getContact(new GetContactRequest({userId})).pipe(
      map((response) => !!response.contactUserId),
      catchError((err: unknown) => {
        if (isNotFoundError(err)) {
          return of(false);
        }

        return throwError(() => err);
      }),
    );
  }
}
