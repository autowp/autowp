import type {User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {UsersRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {BehaviorSubject, map, switchMap} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-users-online',
  imports: [UserComponent, AsyncPipe],
  templateUrl: './online.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class UsersOnlineComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #usersClient = inject(UsersClient);

  readonly #reload$ = new BehaviorSubject<void>(void 0);
  protected readonly users$: Observable<User[]> = this.#reload$.pipe(
    switchMap(() => this.#usersClient.getUsers(new UsersRequest({isOnline: true}))),
    map((response) => response.items ?? []),
  );

  protected load() {
    this.#reload$.next();
  }
}
