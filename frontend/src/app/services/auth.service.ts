import {inject, Injectable} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {APIMeRequest, APIUser} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {KEYCLOAK_EVENT_SIGNAL, KeycloakEventType} from 'keycloak-angular';
import Keycloak from 'keycloak-js';
import {from, Observable, of} from 'rxjs';
import {catchError, distinctUntilChanged, filter, map, shareReplay, switchMap} from 'rxjs/operators';

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

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  readonly #keycloak = inject(Keycloak);
  readonly #usersClient = inject(UsersClient);

  readonly #keycloakSignal = inject(KEYCLOAK_EVENT_SIGNAL);

  readonly #token$ = toObservable(this.#keycloakSignal).pipe(
    filter((event) =>
      [
        KeycloakEventType.AuthError,
        KeycloakEventType.AuthLogout,
        KeycloakEventType.AuthRefreshError,
        KeycloakEventType.AuthRefreshSuccess,
        KeycloakEventType.AuthSuccess,
        KeycloakEventType.Ready,
      ].includes(event.type),
    ),
    map(() => this.#keycloak.tokenParsed),
    distinctUntilChanged((a, b) => (a && b && a['jti'] === b['jti']) || (!a && !b)),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly authenticated$: Observable<boolean> = this.#token$.pipe(
    map((token) => !!token),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly user$: Observable<APIUser | null> = this.#token$.pipe(
    distinctUntilChanged((a, b) => {
      if (!a) {
        return !b;
      } else if (!b) {
        return false;
      }

      return (
        JSON.stringify({resource_access: a.resource_access, sub: a.sub}) ===
        JSON.stringify({resource_access: b.resource_access, sub: b.sub})
      );
    }),
    switchMap((token) => {
      if (!token) {
        return of(null);
      }

      return this.#usersClient.me(new APIMeRequest({})).pipe(
        catchError(() => {
          return of(null);
        }),
      );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public signOut$(): Observable<void> {
    return from(this.#keycloak.logout({redirectUri: window.location.href}));
  }

  public hasRole$(role: Role): Observable<boolean> {
    return this.#token$.pipe(
      map((token) => (token?.resource_access?.['autowp']?.roles || []).includes(role)),
      distinctUntilChanged(),
      shareReplay({bufferSize: 1, refCount: false}),
    );
  }
}
