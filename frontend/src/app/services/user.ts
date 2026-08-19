import type {User, UserFields} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {GetUserRequest, UsersRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {defer, forkJoin, from, map, of, shareReplay, switchMap} from 'rxjs';

import {skipAuthMetadata} from './api.service';

// Ids per GetUsers call. Batches larger than this are split, so one page full of comments can't
// turn into a single request with an unbounded id list behind it.
const MAX_IDS_PER_REQUEST = 100;

export interface UserLookupOptions {
  /**
   * Send the signed-in user's token with the lookup.
   *
   * Off by default, which is what lets the SSR transfer cache hand the render's result to a
   * logged-in visitor instead of making the browser fetch every author again on hydration
   * (Angular refuses to cache a request carrying an Authorization header). The two answers differ
   * in exactly one way: an *admin* sees a deleted account's name and avatar, where everyone else
   * gets the bare stub - GetUsers takes no other branch on the caller unless fields like email or
   * lastIp are requested, which this service never does.
   *
   * Turn it on for moderation screens, where seeing deleted accounts is the point and the pages
   * are client-rendered anyway, so there is no transfer cache entry to lose.
   */
  authenticated?: boolean;
}

// Everything one lookup flavour needs: its own cache (the two flavours can resolve the same id
// differently) and its own batch in flight.
interface UserLookupLane {
  batch$: null | Observable<Map<string, User>>;
  batchIds: string[];
  batchIdSet: Set<string>;
  cache: Map<string, Observable<null | User>>;
}

function createLane(): UserLookupLane {
  return {batch$: null, batchIds: [], batchIdSet: new Set<string>(), cache: new Map<string, Observable<null | User>>()};
}

@Service()
export class UserService {
  readonly #usersClient = inject(UsersClient);

  // One entry per id ever asked for, holding the shared (shareReplay'd) result. On the server these
  // live for exactly one render - Angular builds a fresh injector per SSR request - so nothing
  // leaks between visitors.
  readonly #anonymous = createLane();
  readonly #authenticated = createLane();

  /**
   * Resolves one user, coalescing every id requested in the same microtask into one GetUsers call.
   *
   * A rendered page asks for a user per comment, per thumbnail owner, per forum row - one
   * GetUser call each before this batched, and during SSR all of them land on the backend at once
   * (each one also costing an ip_ban lookup in the interceptor and a connection out of the bounded
   * pool). Resolves to null for an id the backend doesn't return, rather than erroring: a deleted
   * or stale author reference shouldn't take down the row that mentions it.
   */
  public getUser$(id: string | undefined, options?: UserLookupOptions): Observable<null | User> {
    if (!id || id === '0') {
      return of(null);
    }

    const authenticated = options?.authenticated ?? false;
    const lane = authenticated ? this.#authenticated : this.#anonymous;

    const cached$ = lane.cache.get(id);
    if (cached$) {
      return cached$;
    }

    // defer, so the id joins a batch when someone actually subscribes - an unsubscribed
    // getUser$ would otherwise hold an id in a batch that never flushes.
    const user$ = defer(() => this.#enqueue(lane, id, authenticated)).pipe(
      map((users) => users.get(id) ?? null),
      shareReplay({bufferSize: 1, refCount: false}),
    );

    lane.cache.set(id, user$);

    return user$;
  }

  /**
   * Resolves many users at once, keyed by id. Ids the backend doesn't return are absent from the
   * map rather than an error - callers render "no user" for those (a comment thread with one
   * anonymous or deleted author still shows every other author).
   */
  public getUserMap$(ids: string[], options?: UserLookupOptions): Observable<Map<string, User>> {
    const unique = [...new Set(ids)];

    if (unique.length === 0) {
      return of(new Map<string, User>());
    }

    // Each getUser$ enqueues into the same batch, so this is still one request.
    return forkJoin(unique.map((id) => this.getUser$(id, options))).pipe(
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

  #enqueue(lane: UserLookupLane, id: string, authenticated: boolean): Observable<Map<string, User>> {
    if (!lane.batchIdSet.has(id)) {
      lane.batchIdSet.add(id);
      lane.batchIds.push(id);
    }

    lane.batch$ ??= this.#scheduleBatch(lane, authenticated);

    return lane.batch$;
  }

  #scheduleBatch(lane: UserLookupLane, authenticated: boolean): Observable<Map<string, User>> {
    // A microtask, deliberately not a timer: it coalesces everything a single change-detection
    // pass asks for, while still being tracked as pending work by SSR's whenStable() - a
    // setTimeout-based delay ahead of the first HTTP call is exactly what stops being tracked
    // under zoneless change detection (see the note on CommentsComponent.dataResource).
    return from(Promise.resolve()).pipe(
      switchMap(() => {
        const ids = lane.batchIds;

        // Close this batch before the request goes out: ids asked for from here on open the next.
        lane.batchIds = [];
        lane.batchIdSet = new Set<string>();
        lane.batch$ = null;

        return this.#fetch$(ids, authenticated);
      }),
      shareReplay({bufferSize: 1, refCount: false}),
    );
  }

  #fetch$(ids: string[], authenticated: boolean): Observable<Map<string, User>> {
    const chunks: string[][] = [];
    for (let offset = 0; offset < ids.length; offset += MAX_IDS_PER_REQUEST) {
      chunks.push(ids.slice(offset, offset + MAX_IDS_PER_REQUEST));
    }

    if (chunks.length === 0) {
      return of(new Map<string, User>());
    }

    return forkJoin(
      chunks.map((chunk) =>
        this.#usersClient.getUsers(
          new UsersRequest({id: chunk, limit: chunk.length}),
          authenticated ? undefined : skipAuthMetadata(),
        ),
      ),
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
