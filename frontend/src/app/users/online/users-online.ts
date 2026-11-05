import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {UsersRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map} from 'rxjs';

import {UserLink} from '../../user/user/user-link';
@Component({
  selector: 'app-users-online',
  imports: [ResourceStatus, UserLink],
  templateUrl: './users-online.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersOnline {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #usersClient = inject(UsersClient);

  protected readonly usersResource = rxResource({
    stream: () =>
      this.#usersClient.getUsers(new UsersRequest({isOnline: true})).pipe(map((response) => response.items ?? [])),
  });
  protected readonly users = computed(() => (this.usersResource.hasValue() ? this.usersResource.value() : undefined));

  protected load() {
    this.usersResource.reload();
  }
}
