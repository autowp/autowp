import { Injectable } from '@angular/core';
import { Observable, ReplaySubject } from 'rxjs';
import { APIService } from './api.service';
import { APIUser } from './user';
import { catchError, switchMap, tap } from 'rxjs/operators';

@Injectable()
export class AuthService {
  private user$ = new ReplaySubject<APIUser>(1);

  constructor(private api: APIService) {}

  public setUser(value: APIUser) {
    this.user$.next(value);
  }

  public getUser(): Observable<APIUser> {
    return this.user$;
  }

  public login(
    email: string,
    password: string,
    remember: boolean
  ): Observable<APIUser> {
    return this.api
      .request('POST', 'login', {
        body: {
          login: email,
          password: password,
          remember: remember ? '1' : ''
        },
        headers: {
          'Content-Type': 'application/json'
        },
        observe: 'response'
      })
      .pipe(switchMap(() => this.loadMe()));
  }

  public signOut(): Observable<void> {
    return this.api
      .request('DELETE', 'login')
      .pipe(tap(() => this.setUser(null)));
  }

  public loadMe(): Observable<APIUser> {
    return this.api
      .request<APIUser>('GET', 'user/me')
      .pipe(tap(user => this.setUser(user)));
  }
}
