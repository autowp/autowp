import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { APIService } from './api.service';
import { APIUser } from './user';

@Injectable()
export class AuthService {
  loggedIn: boolean;
  user: APIUser | undefined = undefined;
  loggedIn$ = new BehaviorSubject<boolean>(this.loggedIn);
  private initPromise: Promise<void>;

  constructor(private api: APIService) {
    this.initPromise = new Promise<void>((resolve, reject) => {
      this.loadMe().then(
        () => {
          resolve();
        },
        error => {
          console.log('initPromise reject');
          console.log(error);
          resolve();
        }
      );
    });
  }

  setLoggedIn(value: boolean) {
    // Update login status subject
    this.loggedIn = value;
    this.loggedIn$.next(value);
  }

  login(email: string, password: string, remember: boolean): Promise<APIUser> {
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

  signOut(): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.api.request('DELETE', 'login').subscribe(
        response => {
          this.setLoggedIn(false);
          this.user = undefined;
          resolve();
        },
        error => {
          this.setLoggedIn(false);
          this.user = undefined;
          console.log(error);
          reject();
        }
      );
    });
  }

  get authenticated(): Promise<boolean> {
    return new Promise<boolean>((resolve, reject) => {
      this.initPromise.then(() => resolve(this.loggedIn), () => reject());
    });
  }

  loadMe(): Promise<APIUser> {
    return new Promise((resolve, reject) => {
      this.api.request<APIUser>('GET', 'user/me').subscribe(
        user => {
          this.setLoggedIn(true);
          this.user = user;
          resolve(user);
        },
        error => {
          this.user = undefined;
          console.log(error);
          reject('error');
        }
      );
    });
  }
}
