import type {OnInit} from '@angular/core';
import type {RouteParam} from '@utils/route-param';

import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {UserFields, UsersRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {errorMessage} from 'app/grpc';

import {Paginator} from '../../paginator/paginator/paginator';
import {UserLink} from '../../user/user/user-link';

@Component({
  selector: 'app-moder-users',
  imports: [RouterLink, UserLink, NgbTooltip, Paginator, DatePipe, TimeAgoPipe],
  templateUrl: './moder-users.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerUsers implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #usersClient = inject(UsersClient);

  readonly page = input<null | string, RouteParam>(null, {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? null,
  });

  protected readonly usersResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-users-page',
    params: () => this.page(),
    stream: ({params: pageStr}) =>
      this.#usersClient.getUsers(
        new UsersRequest({
          fields: new UserFields({
            email: true,
            lastOnline: true,
            login: true,
            photo: true,
            regDate: true,
          }),
          limit: 30,
          page: pageStr ? parseInt(pageStr) : undefined,
        }),
      ),
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_USERS,
    });
  }
}
