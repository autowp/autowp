import type {User} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';

@Component({
  selector: 'app-user',
  imports: [RouterLink],
  templateUrl: './user-link.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserLink {
  readonly user = input.required<User>();
}
