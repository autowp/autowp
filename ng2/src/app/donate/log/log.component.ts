import { Component, Injectable } from '@angular/core';
import { UserService, APIUser } from '../../services/user';
import { PageEnvService } from '../../services/page-env.service';

interface LogItem {
  sum: number;
  currency: string;
  date: string;
  user_id: number;
  user?: APIUser;
}

@Component({
  selector: 'app-donate-log',
  templateUrl: './log.component.html'
})
@Injectable()
export class DonateLogComponent {
  public items: LogItem[];

  constructor(
    private userService: UserService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/196/name',
          pageId: 196
        }),
      0
    );

    this.items = require('./data.json');

    for (const item of this.items) {
      if (item.user_id) {
        this.userService.getUser(item.user_id, {}).subscribe(user => {
          item.user = user;
        });
      }
    }
  }
}
