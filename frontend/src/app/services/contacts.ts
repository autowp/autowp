import {inject, Injectable} from '@angular/core';
import {GetContactRequest} from '@grpc/spec.pb';
import {ContactsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {Observable, of, throwError} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class AppContactsService {
  readonly #contactsClient = inject(ContactsClient);

  public isInContacts$(userId: string): Observable<boolean> {
    return this.#contactsClient.getContact(new GetContactRequest({userId})).pipe(
      map((response) => !!response.contactUserId),
      catchError((err: unknown) => {
        if (err instanceof GrpcStatusEvent && err.statusCode === 5) {
          return of(false);
        }

        return throwError(() => err);
      }),
    );
  }
}
