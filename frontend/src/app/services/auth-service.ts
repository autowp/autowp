import type {Signal} from '@angular/core';
import type {User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {isPlatformServer} from '@angular/common';
import {computed, inject, Injector, linkedSignal, PLATFORM_ID, Service} from '@angular/core';
import {rxResource, toObservable} from '@angular/core/rxjs-interop';
import {MeRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {browserWindow} from '@utils/browser-window';
import {KEYCLOAK_EVENT_SIGNAL, KeycloakEventType} from 'keycloak-angular';
import Keycloak from 'keycloak-js';
import {catchError, distinctUntilChanged, filter, from, of, shareReplay} from 'rxjs';
export enum Role {
  ADMIN = 'admin',
  BRANDS_MODER = 'brands-moder',
  CARS_MODER = 'cars-moder',
  COMMENTS_MODER = 'comments-moder',
  FORUMS_MODER = 'forums-moder',
  MODER = 'moder',
  PICTURES_MODER = 'pictures-moder',
  USERS_MODER = 'users-moder',
}

interface AuthSession {
  readonly roles: readonly string[];
  readonly subject: string;
}

@Service()
export class AuthService {
  readonly #keycloak = inject(Keycloak);
  readonly #usersClient = inject(UsersClient);
  readonly #keycloakSignal = inject(KEYCLOAK_EVENT_SIGNAL);
  readonly #injector = inject(Injector);
  readonly #isServer = isPlatformServer(inject(PLATFORM_ID));
  readonly #window = browserWindow();

  // undefined: Keycloak has not decided yet; null: confirmed anonymous (including SSR).
  // Read the event synchronously so an already initialized browser never starts as anonymous.
  readonly #session = linkedSignal({
    source: this.#keycloakSignal,
    computation: (event, previous): AuthSession | null | undefined => {
      if (this.#isServer) return null;
      switch (event.type) {
        case KeycloakEventType.AuthError:
        case KeycloakEventType.AuthRefreshError:
        case KeycloakEventType.AuthRefreshSuccess:
        case KeycloakEventType.AuthSuccess:
        case KeycloakEventType.Ready:
          return this.readSession();
        case KeycloakEventType.AuthLogout:
          return null;
        case KeycloakEventType.KeycloakAngularInit:
        case KeycloakEventType.KeycloakAngularNotInitialized:
          return undefined;
        default:
          // TokenExpired/ActionUpdate do not turn a valid session into an anonymous one.
          return previous ? previous.value : this.readSession();
      }
    },
    // Refreshing the token without changing identity/roles must not refetch Me.
    equal: (a, b) => a === b || (!!a && !!b && a.subject === b.subject && a.roles.join('\0') === b.roles.join('\0')),
  });

  readonly authenticated = computed(() => {
    const session = this.#session();
    return session === undefined ? undefined : session !== null;
  });
  readonly ready = computed(() => this.authenticated() !== undefined);
  readonly roles = computed(() => {
    const session = this.#session();
    return session === undefined ? undefined : (session?.roles ?? []);
  });

  readonly #userResource = rxResource({
    params: () => this.#session(),
    stream: ({params}) =>
      params === null ? of(null) : this.#usersClient.me(new MeRequest({})).pipe(catchError(() => of(null))),
  });
  readonly user = computed(() => {
    const session = this.#session();
    if (session === undefined) return undefined;
    if (session === null) return null;
    return this.#userResource.hasValue() ? this.#userResource.value() : undefined;
  });

  // Observable adapters remain for guards, WebSocket lifecycles and event pipelines.
  // Pending initialization is deliberately never emitted as false/null.
  readonly authenticated$ = toObservable(this.authenticated).pipe(
    filter((value): value is boolean => value !== undefined),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: true}),
  );
  readonly user$ = toObservable(this.user).pipe(
    filter((value): value is null | User => value !== undefined),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: true}),
  );
  readonly #roleSignals = new Map<Role, Signal<boolean | undefined>>();
  readonly #roleObservables = new Map<Role, Observable<boolean>>();

  hasRole(role: Role): Signal<boolean | undefined> {
    let value = this.#roleSignals.get(role);
    if (!value) {
      value = computed(() => this.roles()?.includes(role));
      this.#roleSignals.set(role, value);
    }
    return value;
  }

  hasRole$(role: Role): Observable<boolean> {
    let value = this.#roleObservables.get(role);
    if (!value) {
      value = toObservable(this.hasRole(role), {injector: this.#injector}).pipe(
        filter((allowed): allowed is boolean => allowed !== undefined),
        distinctUntilChanged(),
        shareReplay({bufferSize: 1, refCount: true}),
      );
      this.#roleObservables.set(role, value);
    }
    return value;
  }

  signOut$(): Observable<void> {
    return from(this.#keycloak.logout({redirectUri: this.#window?.location.href}));
  }

  private readSession(): AuthSession | null | undefined {
    const token = this.#keycloak.tokenParsed;
    if (!this.#keycloak.authenticated || !token) return null;
    return {
      subject: token.sub ?? '',
      roles: [...(token.resource_access?.['autowp']?.roles ?? [])].sort((a, b) => a.localeCompare(b)),
    };
  }
}
