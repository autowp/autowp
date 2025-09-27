import {inject, Injectable} from '@angular/core';
import {APIGetUserRequest, APIUser, APIUsersRequest, UserFields} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {forkJoin, Observable, of} from 'rxjs';
import {map, shareReplay, tap} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class UserService {
  readonly #usersClient = inject(UsersClient);

  #cache: Map<string, APIUser> = new Map<string, APIUser>();
  #promises = new Map<string, Observable<null>>();

  #cache2 = new Map<string, Observable<APIUser | null>>();

  private queryUsers$(ids: string[]): Observable<null> {
    const toRequest: string[] = [];
    const waitFor: Observable<null>[] = [];
    for (const id of ids) {
      const oldUser = this.#cache.get(id);
      if (oldUser !== undefined) {
        continue;
      }
      const oldPromise$ = this.#promises.get(id);
      if (oldPromise$ !== undefined) {
        waitFor.push(oldPromise$);
        continue;
      }
      toRequest.push(id);
    }

    if (toRequest.length > 0) {
      const promise$: Observable<null> = this.#usersClient
        .getUsers(
          new APIUsersRequest({
            id: toRequest,
            limit: '' + toRequest.length,
          }),
        )
        .pipe(
          tap((response) => {
            for (const item of response.items || []) {
              this.#cache.set(item.id, item);
            }
          }),
          map(() => null),
        );

      waitFor.push(promise$);

      for (const id of toRequest) {
        this.#promises.set(id, promise$);
      }
    }

    if (waitFor.length <= 0) {
      return of(null);
    }

    return forkJoin(waitFor).pipe(map(() => null));
  }

  public getUserMap$(ids: string[]): Observable<Map<string, APIUser>> {
    return this.queryUsers$(ids).pipe(
      map(() => {
        const result = new Map<string, APIUser>();
        for (const id of ids) {
          const user = this.#cache.get(id);
          if (user === undefined) {
            throw new Error('Failed to query user ' + id);
          }
          result.set(id, user);
        }
        return result;
      }),
    );
  }

  public getUser$(id: string | undefined): Observable<APIUser | null> {
    if (!id || id === '0') {
      return of(null);
    }

    const cached$ = this.#cache2.get(id);
    if (cached$) {
      return cached$;
    }

    const o$ = this.#usersClient.getUser(new APIGetUserRequest({userId: id})).pipe(
      map((user) => (user ? user : null)),
      shareReplay({bufferSize: 1, refCount: false}),
    );
    this.#cache2.set(id, o$);

    return o$;
  }

  public getByIdentity$(identity: string, fields: undefined | UserFields): Observable<APIUser | null> {
    const result = RegExp(/^user(\d+)$/).exec(identity);

    if (result) {
      return this.#usersClient.getUser(new APIGetUserRequest({fields, userId: result[1]}));
    }

    return this.#usersClient.getUser(new APIGetUserRequest({fields, identity}));
  }
}
