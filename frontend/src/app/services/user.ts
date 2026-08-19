import type {User, UserFields} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {GetUserRequest, UsersRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {defer, forkJoin, from, map, of, shareReplay, switchMap} from 'rxjs';

// Ids per GetUsers call. Batches larger than this are split, so one page full of comments can't
// turn into a single request with an unbounded id list behind it.
const MAX_IDS_PER_REQUEST = 100;

@Service()
export class UserService {
  readonly #usersClient = inject(UsersClient);

  // One entry per id ever asked for, holding the shared (shareReplay'd) result. On the server this
  // instance lives for exactly one render - Angular builds a fresh injector per SSR request - so
  // nothing leaks between visitors.
  readonly #cache = new Map<string, Observable<null | User>>();

  // The batch currently being filled: every id asked for before the microtask below runs is
  // fetched by a single GetUsers call. `#batchIds` and `#batch$` are replaced (not mutated) the
  // moment that call starts, so ids arriving later open the next batch.
  #batchIds: string[] = [];
  #batchIdSet = new Set<string>();
  #batch$: null | Observable<Map<string, User>> = null;

  /**
   * Resolves one user, coalescing every id requested in the same microtask into one GetUsers call.
   *
   * A rendered page asks for a user per comment, per thumbnail owner, per forum row - one
   * GetUser call each before this batched, and during SSR all of them land on the backend at once
   * (each one also costing an ip_ban lookup in the interceptor and a connection out of the bounded
   * pool). Resolves to null for an id the backend doesn't return, rather than erroring: a deleted
   * or stale author reference shouldn't take down the row that mentions it.
   */
  public getUser$(id: string | undefined): Observable<null | User> {
    if (!id || id === '0') {
      return of(null);
    }

    const cached$ = this.#cache.get(id);
    if (cached$) {
      return cached$;
    }

    // defer, so the id joins a batch when someone actually subscribes - an unsubscribed
    // getUser$ would otherwise hold an id in a batch that never flushes.
    const user$ = defer(() => this.#enqueue(id)).pipe(
      map((users) => users.get(id) ?? null),
      shareReplay({bufferSize: 1, refCount: false}),
    );

    this.#cache.set(id, user$);

    return user$;
  }

  /**
   * Resolves many users at once, keyed by id. Ids the backend doesn't return are absent from the
   * map rather than an error - callers render "no user" for those (a comment thread with one
   * anonymous or deleted author still shows every other author).
   */
  public getUserMap$(ids: string[]): Observable<Map<string, User>> {
    const unique = [...new Set(ids)];

    if (unique.length === 0) {
      return of(new Map<string, User>());
    }

    // Each getUser$ enqueues into the same batch, so this is still one request.
    return forkJoin(unique.map((id) => this.getUser$(id))).pipe(
      map((users) => {
        const result = new Map<string, User>();

        users.forEach((user, index) => {
          const id = unique[index];
          if (user && id) {
            result.set(id, user);
          }
        });

        return result;
      }),
    );
  }

  public getByIdentity$(identity: string, fields: undefined | UserFields): Observable<null | User> {
    const result = RegExp(/^user(\d+)$/).exec(identity);

    if (result) {
      return this.#usersClient.getUser(new GetUserRequest({fields, userId: result[1]}));
    }

    return this.#usersClient.getUser(new GetUserRequest({fields, identity}));
  }

  #enqueue(id: string): Observable<Map<string, User>> {
    if (!this.#batchIdSet.has(id)) {
      this.#batchIdSet.add(id);
      this.#batchIds.push(id);
    }

    this.#batch$ ??= this.#scheduleBatch();

    return this.#batch$;
  }

  #scheduleBatch(): Observable<Map<string, User>> {
    // A microtask, deliberately not a timer: it coalesces everything a single change-detection
    // pass asks for, while still being tracked as pending work by SSR's whenStable() - a
    // setTimeout-based delay ahead of the first HTTP call is exactly what stops being tracked
    // under zoneless change detection (see the note on CommentsComponent.dataResource).
    return from(Promise.resolve()).pipe(
      switchMap(() => {
        const ids = this.#batchIds;

        // Close this batch before the request goes out: ids asked for from here on open the next.
        this.#batchIds = [];
        this.#batchIdSet = new Set<string>();
        this.#batch$ = null;

        return this.#fetch$(ids);
      }),
      shareReplay({bufferSize: 1, refCount: false}),
    );
  }

  #fetch$(ids: string[]): Observable<Map<string, User>> {
    const chunks: string[][] = [];
    for (let offset = 0; offset < ids.length; offset += MAX_IDS_PER_REQUEST) {
      chunks.push(ids.slice(offset, offset + MAX_IDS_PER_REQUEST));
    }

    if (chunks.length === 0) {
      return of(new Map<string, User>());
    }

    return forkJoin(
      chunks.map((chunk) => this.#usersClient.getUsers(new UsersRequest({id: chunk, limit: chunk.length}))),
    ).pipe(
      map((responses) => {
        const result = new Map<string, User>();

        for (const response of responses) {
          for (const user of response.items ?? []) {
            result.set(user.id, user);
          }
        }

        return result;
      }),
    );
  }
}
