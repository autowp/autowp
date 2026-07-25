import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {UserFields, UsersRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {PageEnvService} from '@services/page-env.service';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {map} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-moder-users',
  imports: [RouterLink, UserComponent, NgbTooltip, PaginatorComponent, DatePipe, TimeAgoPipe],
  templateUrl: './users.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerUsersComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #usersClient = inject(UsersClient);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('page'))), {requireSync: true});

  protected readonly usersResource = rxResource({
    stream: () => {
      const pageStr = this.#page();
      return this.#usersClient.getUsers(
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
      );
    },
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 203,
    });
  }
}
