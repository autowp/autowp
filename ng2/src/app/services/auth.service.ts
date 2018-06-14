import { Injectable } from '@angular/core';
import { Observable, ReplaySubject } from 'rxjs';
import { APIService } from './api.service';
import { APIUser } from './user';
import { share } from 'rxjs/operators';

@Injectable()
export class AuthService {
  private user$ = new ReplaySubject<APIUser>(1);
  private shared$: Observable<APIUser>;

  constructor(private api: APIService) {
    this.shared$ = this.user$.pipe(share());
  }

  public setUser(value: APIUser) {
    console.log('setUser', value);
    this.user$.next(value);
  }

  public getUser(): Observable<APIUser> {
    return this.shared$;
  }

  public login(
    email: string,
    password: string,
    remember: boolean
  ): Promise<APIUser> {
    return new Promise((resolve, reject) => {
      this.api
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
        .subscribe(
          response => {
            this.loadMe().then(
              user => {
                resolve(user);
              },
              message => {
                reject(message);
              }
            );
          },
          response => {
            reject(response);
          }
        );
    });
  }

  public signOut(): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.api.request('DELETE', 'login').subscribe(
        response => {
          this.setUser(null);
          resolve();
        },
        error => {
          this.setUser(null);
          console.log(error);
          reject();
        }
      );
    });
  }

  public loadMe(): Promise<APIUser> {
    return new Promise((resolve, reject) => {
      this.api.request<APIUser>('GET', 'user/me').subscribe(
        user => {
          this.setUser(user);
          resolve(user);
        },
        error => {
          this.setUser(null);
          console.log(error);
          reject('error');
        }
      );
    });
  }
}
