import { Component, Input, Injectable } from '@angular/core';
import { APIUser } from '../../services/user';

@Component({
  selector: 'app-user',
  templateUrl: './user.component.html'
})
@Injectable()
export class UserComponent {
  @Input() user: APIUser;
}
