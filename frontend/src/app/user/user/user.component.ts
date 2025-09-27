import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {APIUser} from '@grpc/spec.pb';
import {GoautowpAPIUser} from '@rest/model/goautowpAPIUser';

@Component({
  selector: 'app-user',
  imports: [RouterLink],
  templateUrl: './user.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserComponent {
  readonly user = input.required<APIUser | GoautowpAPIUser>();
}
