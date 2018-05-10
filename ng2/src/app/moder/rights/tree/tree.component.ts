import { Component, Injectable, Input } from '@angular/core';
import { APIACLRole } from '../../../services/acl.service';

@Component({
  selector: 'app-moder-rights-tree',
  templateUrl: './tree.component.html'
})
@Injectable()
export class ModerRightsTreeComponent {
  @Input() roles: APIACLRole[];
}
